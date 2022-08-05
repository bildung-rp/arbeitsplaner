<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mod messages, provide messages related to a single module.
 *
 * @package   local_aclmodules
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aclmodules\local;

require_once($CFG->dirroot . '/local/aclmodules/lib.php');

/** this class retrieves all the module messages data from database and delegates display to the renderer,
 *  We use one instance (Singleton) per request to cache the data in this object.
 */
class modmessages {

    // Messages in this course grouped by module.
    protected $coursemodulemessages = array();
    // Numbers of unread and read Messages grouped by module 'unread' => modid => counts and 'unread' => modid => counts.
    protected $coursemodulemessagesinfo = array();
    // All the users, from whom messages would be viewable.
    protected $users = array();
    // All the users, which has already a conversation with this USER.
    protected $messageusers = array();

    protected $renderer;

    private function __construct() {
        // Make this contructor private, to avoid direct call.
    }

    /** create instance as a singleton, to use attributes as request cache and
     *  instantiate needed javascript.
     *
     * @global object $PAGE
     * @staticvar modmessages $modmessages
     * @param boolean $loadjavascript true, if javascript for AJAX loading should be loaded
     * @return modmessages instance of modmessages class.
     */
    public static function instance($loadjavascript = false) {
        global $PAGE;
        static $modmessages;

        if (isset($modmessages)) {
            return $modmessages;
        }

        if ($loadjavascript) {

            $args = array();
            $PAGE->requires->yui_module('moodle-local_aclmodules-modmessages', 'M.local_aclmodules.message', array($args), null, true);
        }

        $modmessages = new modmessages();
        return $modmessages;
    }

    /** get the renderer  */
    protected function get_renderer() {
        global $PAGE;

        if (!isset($this->renderer)) {
            $this->renderer = $PAGE->get_renderer('local_aclmodules');
        }
        return $this->renderer;
    }

    /** get the users, whose messages may be viewed by this user.
     *
     * @param record $course the course
     *
     * @global database $DB
     * @global record $USER
     * @param record $course
     * @param type $userid
     * @return array list of user records.
     */
    protected function get_viewable_users($course, $userid) {
        global $DB, $USER;

        // ... check cache for user with $userid.
        if (isset($this->users[$userid])) {
            return $this->users[$userid];
        }

        $context = \context_course::instance($course->id);

        // ... if user may not see all messages, restrict to teachers messages.
        if (has_capability('local/aclmodules:viewallmessages', $context, $userid)) {

            $this->users[$userid] = get_enrolled_users($context, '', 0, 'u.*', 'lastname');
        } else {

            // ... user may see messages of all teachers.
            $this->users[$userid] = get_enrolled_users($context, 'moodle/course:update', 0, 'u.*', 'lastname');
        }

        // ... add this user (normally $USER) to users information fo displaying.
        if (!isset($this->users[$userid][$userid])) {
            if ($userid == $USER->id) {
                $this->users[$userid][$userid] = $USER;
            } else {
                $this->users[$userid][$userid] = $DB->get_record('user', array('id' => $userid));
            }
        }

        return $this->users[$userid];
    }

    /**
     * Get all messages for the given modids and the given userids in one query
     * using two queries doesnt't speed up executing time.
     *
     * Note this is changed significantly for the new moodle 3.5
     *
     * @param array $modids
     * @param array $userids
     * @return array of message records from database
     */
    protected function get_modmessages($modids, $userids, $userid = null) {
        global $DB, $USER;

        if ($userid == null) {
            $userid = $USER->id;
        }

        if (count($modids) == 0) {
            return false;
        }

        list($inmodids, $modparams) = $DB->get_in_or_equal($modids);

        if (count($userids) == 0) {
            return false;
        }

        list($inuserids, $userparams) = $DB->get_in_or_equal($userids);

        // ... user params are used twice, take care about the order!
        $params = [
            \core_message\api::MESSAGE_ACTION_READ
        ];
        $params = array_merge($params, $userparams);
        $params[] = $userid;
        $params = array_merge($params, $userparams);
        $params[] = $userid;
        $params = array_merge($params, $modparams);

        $sql = "SELECT mr.*, mcm.userid as useridto, mua.timecreated as timeread,
                aclm.coursemoduleid as modid
                FROM {messages} mr
                JOIN {message_conversations} mc ON mc.id = mr.conversationid
                JOIN {message_conversation_members} mcm ON mcm.conversationid = mc.id AND mcm.userid <> mr.useridfrom
                JOIN {local_acl_mod_messages} aclm ON aclm.messageid = mr.id
                LEFT JOIN {message_user_actions} mua
                     ON (mua.messageid = mr.id AND mua.userid = mcm.userid AND mua.action = ?)
                WHERE ((mr.useridfrom {$inuserids} AND mcm.userid = ?)
                    OR (mcm.userid {$inuserids} AND mr.useridfrom = ?))
                    AND aclm.coursemoduleid {$inmodids}
                ORDER BY mr.timecreated ASC";

        $messages = $DB->get_records_sql($sql, $params);
        return $messages;
    }

    /**
     * Get the numbers of unread and read Messages grouped by module
     * 'unread' => modid => counts and 'unread' => modid => counts
     * this method is used to indicate new (i. e. unread) messages, so
     * no ordering is done to speed this up.
     *
     * @param record $course
     * @param int $userid
     * @return array, 'unread' => modid => counts and 'unread' => modid => counts
     */
    public function get_modmessages_info($course, $userid = null) {
        global $DB, $USER;

        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // ...use request cache if possible.
        if (isset($this->coursemodulemessagesinfo[$userid])) {
            return $this->coursemodulemessagesinfo[$userid];
        }

        // ... get viewable users.
        $this->users[$userid] = $this->get_viewable_users($course, $userid);

        if (count($this->users[$userid]) == 0) {
            return array();
        }

        // ... get the messages.
        $modinfo = get_fast_modinfo($course, $userid);
        $modids = array_keys($modinfo->get_cms());

        if (count($modids) == 0) {
            return false;
        }

        // ... prepare queries.
        list($inmodids, $modparams) = $DB->get_in_or_equal($modids);
        list($inuserids, $userparams) = $DB->get_in_or_equal(array_keys($this->users[$userid]));

        // ... user params are used twice, take care about the order!
        $params = [
            \core_message\api::MESSAGE_ACTION_READ
        ];
        $params = array_merge($params, $userparams);
        $params[] = $userid;
        $params = array_merge($params, $modparams);

        // ...count read messages.
        $sql1 = "SELECT aclm.coursemoduleid as modid, count(*) as count
                 FROM {messages} mr
                 JOIN {message_conversations} mc ON mc.id = mr.conversationid
                 JOIN {message_conversation_members} mcm ON mcm.conversationid = mc.id AND mcm.userid <> mr.useridfrom
                 JOIN {local_acl_mod_messages} aclm ON aclm.messageid = mr.id
                 JOIN {message_user_actions} mua
                     ON (mua.messageid = mr.id AND mua.userid = mcm.userid AND mua.action = ?)
                WHERE (mr.useridfrom {$inuserids} AND mcm.userid = ?)
                    AND (aclm.coursemoduleid {$inmodids})
                GROUP BY aclm.coursemoduleid";

        $countmessagesread = $DB->get_records_sql($sql1, $params);

        // ...count unread messages.
        $sql2 = "SELECT aclm.coursemoduleid as modid, count(*) as count
                 FROM {messages} mr
                 JOIN {message_conversations} mc ON mc.id = mr.conversationid
                 JOIN {message_conversation_members} mcm ON mcm.conversationid = mc.id AND mcm.userid <> mr.useridfrom
                 JOIN {local_acl_mod_messages} aclm ON aclm.messageid = mr.id
                 LEFT JOIN {message_user_actions} mua
                     ON (mua.messageid = mr.id AND mua.userid = mcm.userid AND mua.action = ?)
                WHERE (mr.useridfrom {$inuserids} AND mcm.userid = ?)
                    AND (aclm.coursemoduleid {$inmodids}) AND mua.id IS NULL
                GROUP BY aclm.coursemoduleid";

        $countmessagesunread = $DB->get_records_sql($sql2, $params);

        $this->coursemodulemessagesinfo[$userid] = array(
            'unread' => $countmessagesunread,
            'read' => $countmessagesread,
        );

        return $this->coursemodulemessagesinfo[$userid];
    }

    /**
     * Get all the module related messages for this USER and group them by module
     * even when it is called for one module all the messages of the course would be retrieved.
     *
     * @param object $course
     * @param int $userid
     * @return array
     */
    public function get_all_modmessages_for_user($course, $userid) {
        global $USER;

        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // ... use cache if possible.
        if (isset($this->coursemodulemessages[$userid])) {
            return array($this->coursemodulemessages[$userid], $this->messageusers[$userid], $this->users[$userid]);
        }

        // ... get viewable users.
        $this->users[$userid] = $this->get_viewable_users($course, $userid);

        if (count($this->users[$userid]) == 0) {
            return array();
        }

        // ... get the messages.
        $modinfo = get_fast_modinfo($course, $userid);
        $modids = array_keys($modinfo->get_cms());

        if (!$messages = $this->get_modmessages($modids, array_keys($this->users[$userid]), $userid)) {
            $messages = array();
        }

        // ... group messages by modid and userid to be viewed by this user.
        $groupedmessages = array();
        $messageusers = array();

        foreach ($messages as $message) {

            if (!isset($groupedmessages[$message->modid])) {
                $groupedmessages[$message->modid] = array();
            }

            if (!isset($messageusers[$message->modid])) {
                $messageusers[$message->modid] = array();
            }

            if ($message->useridfrom == $userid) {

                if (!isset($groupedmessages[$message->modid][$message->useridto])) {
                    $groupedmessages[$message->modid][$message->useridto] = array();
                }
                $groupedmessages[$message->modid][$message->useridto][] = $message;
                $messageusers[$message->modid][$message->useridto] = $this->users[$userid][$message->useridto];
            } else {

                if ($message->useridto == $userid) {

                    if (!isset($groupedmessages[$message->modid][$message->useridfrom])) {
                        $groupedmessages[$message->modid][$message->useridfrom] = array();
                    }
                    $groupedmessages[$message->modid][$message->useridfrom][] = $message;
                    $messageusers[$message->modid][$message->useridfrom] = $this->users[$userid][$message->useridfrom];
                }
            }
        }

        $this->coursemodulemessages[$userid] = $groupedmessages;
        $this->messageusers[$userid] = $messageusers;

        return array($this->coursemodulemessages[$userid], $this->messageusers[$userid], $this->users[$userid]);
    }

    /**
     * Send a message from one user to another. Will be delivered according to the message recipients messaging preferences
     *
     * @param object $userfrom the message sender
     * @param object $userto the message recipient
     * @param string $message the message
     * @return int|false the ID of the new message or false
     */
    public function message_post_message($mod, $userfrom, $userto, $message) {
        global $SITE;

        $eventdata = new \core\message\message();
        $eventdata->component = 'moodle';
        $eventdata->name = 'instantmessage';
        $eventdata->userfrom = $userfrom;
        $eventdata->userto = $userto;

        // ... using string manager directly so that strings in the message
        //  will be in the message recipients language rather than the senders language.
        $eventdata->subject = get_string_manager()->get_string('unreadnewmessage', 'message', fullname($userfrom), $userto->lang);

        $eventdata->fullmessage = $message;
        $eventdata->fullmessagehtml = $message;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->smallmessage = $message; // Store the message unfiltered. Clean up on output.

        $s = new \stdClass();
        $s->sitename = format_string($SITE->shortname, true, array('context' => \context_course::instance(SITEID)));

        $messageurl = new \moodle_url('/message/index.php', array('user' => $userto->id, 'id' => $userfrom->id));
        $s->url = $messageurl->out();

        $emailtagline = get_string_manager()->get_string('emailtagline', 'message', $s, $userto->lang);
        if (!empty($eventdata->fullmessage)) {
            $eventdata->fullmessage .= "\n\n-------------------------------------------------------------------\n" . $emailtagline;
        }
        if (!empty($eventdata->fullmessagehtml)) {
            $eventdata->fullmessagehtml .= "<br /><br />";
            $eventdata->fullmessagehtml .= "-------------------------------------------------------------------<br />";
            $eventdata->fullmessagehtml .= $emailtagline;
        }

        $eventdata->timecreated = time();

        $contexturl = new \moodle_url("/mod/{$mod->modname}/view.php", array('id' => $mod->id));
        $eventdata->contexturl = $contexturl->out();
        $eventdata->contexturlname = $mod->name;

        $eventdata->notification = 0;
        $eventdata->courseid = $mod->course;

        return message_send($eventdata);
    }

    /** setup submitted data and delegate saving of message to moodlecore
     *
     * @global database $DB
     * @param record $mod the module
     * @param int $useridfrom
     * @param int $useridto
     * @param string $message
     * @return return id of message inserted of false.
     */
    public function save_modmessage($mod, $useridfrom, $useridto, $message) {
        global $DB;

        // ...get the users data to store the message.
        $sql = "SELECT * FROM {user} WHERE id = ? OR id = ?";
        $users = $DB->get_records_sql($sql, array($useridfrom, $useridto));

        // ...setup the userdata.
        if (isset($users[$useridfrom])) {
            $userfrom = $users[$useridfrom];
        } else {
            $userfrom = $useridfrom;
        }

        if (isset($users[$useridto])) {
            $userto = $users[$useridto];
        } else {
            $userto = $useridto;
        }

        // ... send and save via moodle way and store the relation to module.
        if ($messageid = $this->message_post_message($mod, $userfrom, $userto, $message)) {

            $rec = (object) [
                    'coursemoduleid' => $mod->id,
                    'messageid' => $messageid
            ];

            $DB->insert_record('local_acl_mod_messages', $rec);
        }
        return $messageid;
    }

    /** mark the mod message as read
     *
     * @global database $DB
     * @param record $course
     * @param record $mod
     * @param int $messageid
     * @return boolean|string false if message is not valid, otherwise Information about message counts.
     */
    public function mark_message_as_read($course, $mod, $messageid) {
        global $DB, $USER;

        if (!$message = $DB->get_record('messages', array('id' => $messageid))) {
            return false;
        }

        $params = [
            'messageid' => $messageid,
            'coursemoduleid' => $mod->id
        ];
        if (!$modmessage = $DB->get_record('local_acl_mod_messages', $params)) {
            return false;
        }

        // ...check capability.
        if (($mod->id >= 0) and ( has_capability('local/aclmodules:viewmessages', \context_module::instance($mod->id)))) {
            // ... mark the message as read.
            \core_message\api::mark_message_as_read($USER->id, $message, time());
        }

        // ... return the new information about unread messages.
        $messagesinfo = $this->get_modmessages_info($course);
        $renderer = $this->get_renderer();

        return array($renderer->render_modmessages_header_info($mod, $messagesinfo),
            \html_writer::tag('span', get_string('modmessageread', 'local_aclmodules'), array('class' => 'modmessages-read'))
        );
    }

    /** prepare the data for displaying the messages for one modules and delegate the
     * rendering to the render.
     *
     * @global record $USER
     * @param object $mod
     * @param record $course
     * @return String the HTML-Code for the Module Messages.
     */
    public function get_rendered_modmessages($mod, $course, $userid = null) {
        global $USER;

        if ($userid == null) {
            $userid = $USER->id;
        }

        // ... capability check.
        $context = \context_course::instance($course->id);
        if (!has_capability('local/aclmodules:viewmessages', $context, $userid)) {
            return "";
        }

        // ...get all messages.
        list($messages, $messagesusers, $allusers) = $this->get_all_modmessages_for_user($course, $userid);

        $renderer = $this->get_renderer();
        $out = "";

        // ... get users with no conversation for recipient dropdown.
        $recipientsfordropdown = array();
        if (!empty($messages[$mod->id])) {
            $out = $renderer->render_modmessages($mod, $messages[$mod->id], $allusers);
            $recipientsfordropdown = array_diff_key($allusers, $messagesusers[$mod->id]);
        } else {
            $recipientsfordropdown = $allusers;
        }

        if (isset($recipientsfordropdown[$USER->id])) {
            unset($recipientsfordropdown[$USER->id]);
        }

        $out .= $renderer->render_modmessages_form($mod, $recipientsfordropdown);
        $out .= \html_writer::tag('span', '', array('id' => 'modmessages-status-' . $mod->id));
        return \html_writer::tag('div', $out, array('id' => 'modmessages-' . $mod->id, 'class' => 'modmessages-content'));
    }

    /** get the html-code for module related conversations betwees teacher and students
     *
     * @param record $mod, the coursemodule actually rendered.
     * @param record $course, the course
     * @return string, the HTML code (only for the header, conversations are loaded via AJAX.
     */
    public static function render_messages($mod, $course) {

        // ... check if module should is commentable.
        $aclmodules = aclmodules::instance();
        $commentable = $aclmodules->get_acl_mod_config_value($course, $mod, 'modulestocomment');

        if (!$commentable) {
            return "";
        }

        $modmessages = self::instance(true);

        $messagesinfo = $modmessages->get_modmessages_info($course);
        $renderer = $modmessages->get_renderer();

        return $renderer->render_modmessages_header($mod, $messagesinfo);
    }

    /** aggregate all the (read/unread) info about the messages for each section
     *
     * @global record $USER
     * @param record $course
     * @param array $sections
     * @param obejct $modinfo
     * @param int $userid
     * @return \stdClass the messageinfodata stored as object
     */
    protected function get_sections_messagesinfo($course, $sections, $modinfo,
                                                 $userid) {
        global $USER;

        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // ... create messageinfo.
        $modmessages = self::instance(true);
        $messageinfo = $modmessages->get_modmessages_info($course);

        $messagedata = array();

        foreach ($sections as $thissection) {

            if (empty($modinfo->sections[$thissection->section])) {
                continue;
            }

            $data = new \stdClass();
            $data->itemsread = 0;
            $data->itemsunread = 0;

            foreach ($modinfo->sections[$thissection->section] as $cmid) {

                $cm = $modinfo->get_cm($cmid);

                // ... don't count non visible coursemodules.
                if (!$cm->uservisible) {
                    continue;
                }

                if (!empty($messageinfo['read'][$cmid])) {
                    $data->itemsread += $messageinfo['read'][$cmid]->count;
                }

                if (!empty($messageinfo['unread'][$cmid])) {
                    $data->itemsunread += $messageinfo['unread'][$cmid]->count;
                }
            }
            $messagedata[$thissection->section] = $data;
        }
        return $messagedata;
    }

    /** get the HTML-Code for each section with additional informations from this plugin
     *
     * @param record $course
     * @param object $sections
     * @param array $mods
     * @param int $userid
     * @return array HTML-Code for each section.
     */
    public static function render_section_messagesinfo($course, $sections,
                                                       $modinfo,
                                                       $htmlpersection,
                                                       $userid = null) {

        $modmessages = self::instance(true);

        // ...render messages data.
        $messageinfo = $modmessages->get_sections_messagesinfo($course, $sections, $modinfo, $userid);

        $renderer = $modmessages->get_renderer();
        $renderer->render_section_messagesinfo($messageinfo, $sections, $modinfo, $htmlpersection);

        return $htmlpersection;
    }

}
