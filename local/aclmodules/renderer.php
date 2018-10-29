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
 * Main code for local plugin aclmodules
 *
 * @package   local_aclmodules
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_aclmodules_renderer extends plugin_renderer_base {

    public static $sectionactivitystates = array(
        70 => 'section-notstarted',
        140 => 'section-started',
        160 => 'section-completed',
        260 => 'section-passed'
    );

    public function render_section_activitystates($activitystates, $sections,
                                                  $modinfo, &$html, $medalurl) {
        global $USER;

        if (!isset($activitystates['sectionstates'])) {
            return $html;
        }

        $sectionstates = $activitystates['sectionstates'];

        foreach ($sections as $thissection) {

            $medalhtml = '';

            if (empty($modinfo->sections[$thissection->section])) {

                $class = 'section-empty';
                $text = get_string($class, 'local_aclmodules');
            } else {

                if (!isset($sectionstates[$thissection->id]) or !isset($sectionstates[$thissection->id][$USER->id])) {

                    $class = 'section-notracking';
                    $text = get_string($class, 'local_aclmodules');
                } else {

                    $class = self::$sectionactivitystates[$sectionstates[$thissection->id][$USER->id]];
                    $text = get_string($class, 'local_aclmodules');

                    if (($sectionstates[$thissection->id][$USER->id] == 260) and (!empty($medalurl))) {

                        $medalhtml = html_writer::empty_tag('img', array(
                                    'src' => $medalurl->out(),
                                    'alt' => $text
                                ));
                    }
                }
            }

            $html[$thissection->section] = html_writer::tag('div', $text, array('class' => $class));
            $html[$thissection->section] .= $medalhtml;
        }

        return $html;
    }

    public function get_cell($content, $style = "", $class = "") {
        $cell = new html_table_cell();
        $cell->attributes['class'] = $class;
        $cell->style = $style;
        $cell->text = $content;
        return $cell;
    }

    public function get_div_cell($content, $params, $class = "", $style = "") {

        return $this->get_cell(html_writer::tag('div', $content, $params), $style, $class);
    }

    /** renders the level edit form, we do intentionally do not use a standard moodleform
     *  because formelements are generated dynamically, when editing user adds new levels
     *  (i. e. columns to the table).
     *
     * A lot of javascript depends on this markup, so please be careful, when writing a new
     * rendering function.
     *
     * @param String $actionurl the url to post actions
     * @param record $course the current course
     * @param boolean $editingon true if editing is on
     * @param array $levels all levelrecords of this course
     * @param array $participants gradable users of this course
     * @param array $userlevels all userlevel records of this course
     * @return String HTML of leveledit form
     */
    public function render_leveledit_form($actionurl, $course, $editingon,
                                          $levels, $participants, $userlevels) {

        global $PAGE, $OUTPUT;

        // ... icons are used in javascript too.
        $icons = array();
        $icons['edit'] = $OUTPUT->pix_icon('editstring', 'edit', 'local_aclmodules');
        $icons['del'] = $OUTPUT->pix_icon('delete', 'delete', 'local_aclmodules');
        $icons['add'] = $OUTPUT->pix_icon('add', 'add', 'local_aclmodules');

        $o = html_writer::start_tag('form', array('id' => 'levelsform', 'action' => $actionurl, 'method' => 'post'));

        $o .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $o .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'courseid', 'value' => $course->id));

        // ... start building table.
        $table = new html_table();
        $table->id = 'acl-levels';

        $data = array("");
        $data[] = get_string('levelnotassigned', 'local_aclmodules');

        // ... row for levels.
        foreach ($levels as $level) {

            $levelform = html_writer::tag('span', $level->name);

            $params = array(
                'type' => 'hidden',
                'name' => "levelname[{$level->id}]",
                'value' => $level->name
            );

            $levelform .= html_writer::empty_tag('input', $params);
            if ($editingon) {
                $levelform .= html_writer::link('#', $icons['edit'], array('id' => "edit_{$level->id}", 'class' => 'editlevellink'));
                $levelform .= html_writer::link('#', $icons['del'], array('id' => "delete_{$level->id}", 'class' => 'deletelevellink'));
            }
            $levelform = html_writer::tag('div', $levelform, array('id' => "editlevel_{$level->id}", 'class' => 'editleveldiv'));
            $data[] = $levelform;
        }

        if ($editingon) {
            $newlevelform = html_writer::tag('span', get_string('addlevel', 'local_aclmodules'));
            $newlevelform = html_writer::link('#', $icons['add'] . $newlevelform, array('id' => "add_0", 'class' => 'addlevellink'));
            $newlevelform = html_writer::tag('div', $newlevelform, array('id' => "addlevel", 'class' => 'addleveldiv'));

            $data[] = $newlevelform;
        }
        $table->data[] = $data;

        foreach ($participants as $participant) {

            $data = array();

            $data[] = html_writer::tag('span', fullname($participant), array('id' => 'u_' . $participant->id));

            $params = array('type' => 'radio',
                'name' => "userlevel[{$participant->id}]",
                'value' => 'level_0');

            if (empty($userlevels[$participant->id])) {
                $params['checked'] = 'checked';
            }

            $data[] = html_writer::empty_tag('input', $params);

            foreach ($levels as $level) {

                $params = array('type' => 'radio',
                    'name' => "userlevel[{$participant->id}]",
                    'value' => 'level_' . $level->id,
                    'title' => $level->name);

                $checked = ((isset($userlevels[$participant->id]))
                        and ($userlevels[$participant->id]->levelid == $level->id));

                if ($checked) {
                    $params['checked'] = 'checked';
                }

                $data[] = html_writer::empty_tag('input', $params);
            }
            $table->data[] = $data;
        }

        // ...output table.
        $o .= html_writer::table($table);

        $buttons = html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'save', 'value' => get_string('savechanges')));
        $buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'cancel', 'value' => get_string('cancel')));
        $o .= html_writer::tag('p', $buttons);

        $o .= html_writer::end_tag('form');

        $args = array(
            'levelcount' => count($levels),
            'userids' => array_keys($participants),
            'icons' => $icons);

        $PAGE->requires->strings_for_js(array('edittitleinstructions'), 'local_aclmodules');
        $PAGE->requires->yui_module('moodle-local_aclmodules-levels', 'M.local_aclmodules.editlevels', array($args), null, true);

        return $o;
    }

    /** render explanations about the differnet states of a assignment
     *
     * @return String HTML of legend.
     */
    public function render_legend() {

        $legend = '';

        $legenditems = array_slice(\local_aclmodules\local\aclmodules::$moduserstates, 3);
        foreach ($legenditems as $state) {

            $class = 'planner-legend ' . $state['class'];
            $desc = get_string($state['desc'], 'local_aclmodules');
            $legend .= html_writer::tag('li', $desc, array('class' => $class));
        }
        $o = html_writer::tag('h4', get_string('legend', 'local_aclmodules'));
        $o .= html_writer::tag('ul', $legend, array('class' => 'planner-legend'));

        return $o;
    }

    /** render the tabs for the course to switch to several pages of plugin */
    public function render_tabs() {
        global $PAGE, $COURSE;

        $validpaths = array(
            '/course/view.php',
            '/local/aclmodules/pages/leveledit.php',
            '/local/aclmodules/planner/edit.php',
            '/local/aclmodules/planner/view.php'
        );

        // ...give report plugin a chance to hook into tabs.
        $plugintabs = array();
        $plugins = get_plugin_list_with_function('coursereport', 'get_course_tabs');

        foreach ($plugins as $function) {
            $function($plugintabs, $validpaths);
        }

        // ... calculate the highlighted tab.
        $display = false;
        $id = 1;
        foreach ($validpaths as $path) {
            if (strpos($PAGE->url->get_path(), $path) !== false) {
                $display = true;
                break;
            }
            $id++;
        }

        if (!$display) {
            return "";
        }

        $activitiesurl = new moodle_url('/course/view.php', array('id' => $COURSE->id));
        $tabs[] = new tabobject(1, $activitiesurl->out(), get_string('activities'));

        if (has_capability('local/aclmodules:edit', $PAGE->context)) {
            $levelurl = new moodle_url('/local/aclmodules/pages/leveledit.php', array("courseid" => $COURSE->id));
            $tabs[] = new tabobject(2, $levelurl->out(), get_string('levels', 'local_aclmodules'));
        }

        if (has_capability('local/aclmodules:edit', $PAGE->context)) {

            $params = array("courseid" => $COURSE->id);
            $section = optional_param('section', 0, PARAM_INT);
            if (!empty($section)) {
                $params['section'] = $section;
            }
            $plannerurl = new moodle_url('/local/aclmodules/planner/edit.php', $params);
            $tabs[] = new tabobject(3, $plannerurl->out(), get_string('planner', 'local_aclmodules'));
        } else {

            if (has_capability('local/aclmodules:viewplanner', $PAGE->context)) {
                $params = array("courseid" => $COURSE->id);
                $section = optional_param('section', 0, PARAM_INT);
                if (!empty($section)) {
                    $params['section'] = $section;
                }
                $plannerurl = new moodle_url('/local/aclmodules/planner/view.php', $params);
                $tabs[] = new tabobject(4, $plannerurl->out(), get_string('planner', 'local_aclmodules'));
            }
        }

        // ... all additional plugin tabs.
        foreach ($plugintabs as $count => $tabinfo) {
            $tabs[] = new tabobject(5 + $count, $tabinfo->url, $tabinfo->text);
        }

        return print_tabs(array($tabs), $id, null, null, true);
    }

    /** render the information about unread messages
     *
     * @param type $mod
     * @param array $messagesinfo list of message counts groupedby module id.
     * @return string HTML-Code
     */
    public function render_modmessages_header_info($mod, $messagesinfo) {

        // ...count messages, count new messages.
        $messagesread = (isset($messagesinfo['read'][$mod->id])) ? $messagesinfo['read'][$mod->id]->count : 0;
        $messagesunread = (isset($messagesinfo['unread'][$mod->id])) ? $messagesinfo['unread'][$mod->id]->count : 0;

        $newinfo = ($messagesunread > 0) ? '('.get_string('new', 'local_aclmodules')."! {$messagesunread})" : '';

        return html_writer::tag('span', $newinfo, array('class' => 'modmessage-header-info'));
    }

    /** renders the header of the module message section
     *
     * @param record $mod the current module
     * @param array $messagesinfo list of message counts groupedby module id.
     * @return string HTML-Code
     */
    public function render_modmessages_header($mod, $messagesinfo) {

        $modmessagestr = get_string('modmessages', 'local_aclmodules');

        $link = html_writer::link('#', $modmessagestr, array('class' => 'modmessages-img-collapsed'));
        $link .= html_writer::tag('span', '', array('class' => 'status'));

        $messageinfo = $this->render_modmessages_header_info($mod, $messagesinfo);

        return html_writer::tag('div', $link . $messageinfo, array('id' => 'modmessages-header-' . $mod->id));
    }

    /** render the form to start a new conversation, is no potetial recipients are given
     * return empty string.
     *
     * @global object $OUTPUT
     * @global record $USER
     * @param record $mod the current module
     * @param array $users the list of potential recipients
     * @return string the HTML-Code of the form.
     */
    public function render_modmessages_form($mod, $users) {
        global $OUTPUT, $USER;

        if (count($users) == 0) {
            return "";
        }

        $output = html_writer::tag('h4', get_string('startconversation', 'local_aclmodules'));

        $options = array();
        foreach ($users as $userid => $user) {
            $options[$userid] = fullname($user);
        }
        $recipient = html_writer::tag('span', " " . $OUTPUT->rarrow() . " ");

        if (count($options) > 1) {

            $recipient .= html_writer::select($options, 'useridto', '', array('' => 'choosedots'));
        } else {
            $username = reset($options);
            $recipient .= $username;
            $recipient .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'useridto', 'value' => key($options)));
        }

        $form = html_writer::tag('div', $OUTPUT->user_picture($USER, array('size' => 18)) . " " . fullname($USER) . $recipient, array('class' => 'modmessage-sender'));
        $form .= html_writer::tag('textarea', '', array('name' => "message", "cols" => "70"));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'mod', 'value' => $mod->id));
        $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'useridfrom', 'value' => $USER->id));
        $form .= html_writer::tag('button', get_string('sendmessage', 'message'), array('id' => 'btnsendnewmessage_' . $mod->id));

        $output .= html_writer::tag('form', $form, array('id' => 'newmessage-form-' . $mod->id));

        return $output;
    }

    public function render_mod_message($mod, $message, $users, $modmessagesunreadstr, $modmessagesreadstr) {
        global $USER;

        $userfrom = $users[$message->useridfrom];
        $userpicture = $this->output->user_picture($userfrom, array('size' => 18));
        $sender = html_writer::tag('div', $userpicture . " " . fullname($userfrom), array('class' => 'modmessage-sender'));
        $messagetext = html_writer::tag('div', $message->smallmessage, array('class' => 'modmessage-message'));

        $class = 'modmessage-mine';
        $readstatus = "";

        if ($userfrom->id != $USER->id) {

            $class = 'modmessage-others';

            if ($message->timeread == 0) {
                $link = html_writer::link('#', $modmessagesunreadstr);
                $readstatus = html_writer::tag('span', $link, array('class' => 'modmessages-unread',
                        'id' => 'modmessages-unread-' . $mod->id . '-' . $message->id));
            } else {
                $readstatus = html_writer::tag('span', $modmessagesreadstr, array('class' => 'modmessages-read'));
            }
            $readstatus = html_writer::tag('div', $readstatus);
        }

        return html_writer::tag('div', $sender . $messagetext . $readstatus, array('class' => $class));
    }

    /** the section of the module related conversations, please be carefull to modify, al lot
     * javascript replies on classes and ids given in the HMTL Code.
     *
     * @global object $OUTPUT
     * @global record $USER
     * @param record $mod the current module
     * @param array $messages all the messages for this module
     * @param array $users, information about all the users for given messages.
     * @return string the HTML-Code of the module messages.
     */
    public function render_modmessages($mod, $messages, $users) {
        global $OUTPUT, $USER;

        $config = get_config('local_aclmodules');

        $output = '';

        $modmessagesunreadstr = get_string('modmessageunread', 'local_aclmodules');
        $modmessagesreadstr = get_string('modmessageread', 'local_aclmodules');

        foreach ($messages as $userid => $usermessages) {

            $user = $users[$userid];

            $output .= html_writer::tag('h4', get_string('messageswithuser', 'local_aclmodules', fullname($user)));

            $count = count($usermessages);
            $minindextodisplay = max(0, $count - $config->countmessagedisplay);

            $usermessagestodisplay = array_slice($usermessages, $minindextodisplay);
            $oldmessagestohide = array_slice($usermessages, 0, $minindextodisplay);

            if (count($oldmessagestohide) > 0) {

                $hiddenmsg = "";
                foreach ($oldmessagestohide as $message) {
                    $hiddenmsg .= $this->render_mod_message($mod, $message, $users, $modmessagesunreadstr, $modmessagesreadstr);
                }

                // ...count messages, count new messages.
                $headerstr = get_string('modmessagesold', 'local_aclmodules', count($oldmessagestohide));
                $link = html_writer::link('#', $headerstr, array('class' => 'modmessages-img-collapsed'));
                $output .= html_writer::tag('div', $link, array('class' => 'modmessagesold-header'));
                $output .= html_writer::tag('div', $hiddenmsg, array('class' => 'modmessagesold-content', 'style' => 'display : none'));
            }

            foreach ($usermessagestodisplay as $message) {
                $output .= $this->render_mod_message($mod, $message, $users, $modmessagesunreadstr, $modmessagesreadstr);
            }

            $form = html_writer::tag('div', $OUTPUT->user_picture($USER, array('size' => 18)) . " " . fullname($USER), array('class' => 'modmessage-sender'));
            $form .= html_writer::tag('textarea', '', array("name" => "message", "cols" => "50", 'class' => 'modmessage-message'));
            $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'mod', 'value' => $mod->id));
            $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'useridfrom', 'value' => $USER->id));
            $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'useridto', 'value' => $userid));
            $form .= html_writer::tag('button', get_string('sendmessage', 'message'), array('id' => 'btnsendmessage_' . $mod->id));

            $output .= html_writer::tag('form', $form, array('id' => 'message-form-' . $mod->id));
        }

        return $output;
    }

    /** render the addition information about (read/unread) modmessages for the section
     *
     * @param array $messagedata data for each section
     * @param array $sections
     * @param object $modinfo
     * @param array $html list of HTML-Code for each section to append.
     * @return array the modified HTML-Code
     */
    public function render_section_messagesinfo($messagedata, $sections,
                                                $modinfo, &$html) {

        foreach ($sections as $thissection) {

            if (!empty($modinfo->sections[$thissection->section])) {

                $data = $messagedata[$thissection->section];
                $total = $data->itemsunread + $data->itemsread;
                $countstr = " " . $data->itemsunread . "/" . $total;

                if ($data->itemsunread > 0) {
                    $class = "section-unread";
                    $text = get_string('new') . $countstr;
                } else {
                    $class = "section-read";
                    $text = $countstr;
                }
            } else {

                $class = "";
                $text = "";
            }
            if (!isset($html[$thissection->section])) {
                $html[$thissection->section] = "";
            }
            $html[$thissection->section] .= html_writer::tag('div', $text, array('class' => $class));
        }

        return $html;
    }

}