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
 * Editing Levels (= groups of Users, which have the same learning objectives).
 *
 * @package   local_aclmodules
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aclmodules\local;

require_once($CFG->dirroot . '/local/aclmodules/lib.php');

class aclmodules {

    // ... notification settings for the new notification conditions.
    public static $configoptions = array(
        'modulestorate',
        'modulestocomment',
        'modulesautoapprove'
    );
    // ...a lot of states for coursemodule workflow here,
    // main states are: 70 = assigned, 160 = finished and 260 = passed.
    public static $moduserstates = array(
        0 => array("desc" => "ms-notvisible-notassigned", "class" => "state_0", "color" => "transparent"),
        20 => array("desc" => "ms-notvisible-assigned", "class" => "state_20", "color" => "transparent"),
        10 => array("desc" => "ms-visible-notassigned", "class" => "state_10", "color" => "transparent"),
        30 => array("desc" => "ms-visible-nocompletion-assigned", "class" => "state_30", "color" => "#cccccc"),
        70 => array("desc" => "ms-visible-completion-assigned", "class" => "state_70", "color" => "#ffff00"),
        140 => array("desc" => "ss-visible-completion-inprogress", "class" => "state_140", "color" => "999900"),
        160 => array("desc" => "ms-visible-completion-finished", "class" => "state_160", "color" => "ff0000"),
        260 => array("desc" => "ms-visible-completion-passed", "class" => "state_260", "color" => "#00ff00"),
    );
    // ...we need seperate states for switching states in stateedit dialog.
    public static $completionstates = array(
        70 => 'state-assigned',
        260 => 'state-passed'
    );
    // ... cache the participants of the course.
    private $participants;
    // ... cache the profilefield, which holds the ids of accessible modules.
    private $profilefield;
    // ... holds module-types, which are able to control.
    private $moduletypestocontrol;
    private $modavailfields = array();
    private $config;
    private $cmsconfig;
    private $groupeduserstates;
    private $coursecompletioninfos;

    /**
     * prepare config data for later use
     */
    private function __construct() {
        // Make this function private, to avoid direct call.
        $config = get_config('local_aclmodules');

        $this->config = [];
        $this->moduletypestocontrol = [];

        if (!isset($config->modulestocontrol)) {
            return;
        }

        $this->moduletypestocontrol = explode(",", $config->modulestocontrol);

        $this->config = array();
        foreach ($config as $name => $option) {
            $this->config[$name] = array_flip(explode(",", $option));
        }
    }

    /** create instance as a singleton */
    public static function instance() {
        static $aclmodules;

        if (isset($aclmodules)) {
            return $aclmodules;
        }

        $aclmodules = new aclmodules();
        return $aclmodules;
    }

    /** check whether the plugin is active in given course.
     *
     * @param record $course
     * @return boolean
     */
    public static function is_active($course) {

        $aclmodules = self::instance();
        $aclon = $aclmodules->get_acl_mod_config_value($course, 0, 'aclon');

        return ($aclon == '1');
    }
    
    /* Added by Patrick Liersch (PL) - Static Funktion for setting Status aclmodul activated by Theme or not - 0 = off / 1 = on (default 0) */
    public static function setActive($val) {
      global $active;
      
      $active = $val;
      return $active;
    }
    /* Added by Patrick Liersch (PL) - Static Funktion for getting Status aclmodul activated by Theme or not - 0 = off / 1 = on (default 0) */
    public static function getActive() {
      global $active;
    
      return $active;
    }    

    /** get users coursemodule states for each user to check, whether a coursemodule is passed.
     *
     * @global database $DB
     * @param int $courseid
     * @return array() of userstates grouped by userid from local_acl_mod_userstate.
     */
    protected function get_course_userstates($courseid) {
        global $DB;

        if (isset($this->groupeduserstates)) {
            return $this->groupeduserstates;
        }

        if (!$userstates = $DB->get_records('local_acl_mod_userstate', array('courseid' => $courseid))) {
            $userstates = array();
        }

        $groupeduserstates = array();
        foreach ($userstates as $date) {

            if (!isset($groupeduserstates[$date->userid])) {
                $groupeduserstates[$date->userid] = array();
            }

            $groupeduserstates[$date->userid][$date->cmid] = $date;
        }

        $this->groupeduserstates = $groupeduserstates;

        return $this->groupeduserstates;
    }

    /** updates the users session data, when users completion state is changed.
     * This is necessary for all active sessions.
     *
     * @global database $DB
     * @param type $session
     * @param type $data
     */
    private function update_users_completion_cache($session, $data, $modulescompletion) {

        // Get the session_id of the current user and (temporary) close the session.
        $oldsessionid = session_id();
        session_write_close();

        // Start the the session of the user to change availability data.
        session_id($session->sid);
        session_start();

        // Write the data and close session (and hopefully to not collide with parallel session).
        $sessioncache = $_SESSION['SESSION'];

        if (isset($sessioncache->completioncache[$data->courseid])
                and isset($sessioncache->completioncache[$data->courseid][$data->cmid])) {

            $sessioncache->completioncache[$data->courseid][$data->cmid] = $modulescompletion;
        }

        session_write_close();

        // Restore the session of current user.
        session_id($oldsessionid);
        session_start();
    }

    /** deprecated:: updates the users session data, when users completion state is changed.
     * This is necessary for all active sessions.
     *
     * @global database $DB
     * @param type $session
     * @param type $data
     */
    private function update_users_completion_cache_db($session, $data) {
        global $DB;

        debugging('call to deprecated function update_users_completion_cache');

        $sessdata = base64_decode($session->sessdata);

        $matches = array();
        if (preg_match('/(SESSION\|)(.*)(USER\|)/', $sessdata, $matches)) {

            if (isset($matches[2])) {

                $sessioncachestr = $matches[2];

                $sessioncache = unserialize($sessioncachestr);

                if (isset($sessioncache->completioncache[$data->courseid])
                        and isset($sessioncache->completioncache[$data->courseid][$data->cmid])) {

                    $sessioncache->completioncache[$data->courseid][$data->cmid]->completionstate = 0;

                    // ...this is to reset mod chat correctly and may get deprecated.
                    if (isset($sessioncache->completioncache[$data->courseid][$data->cmid]->viewed)) {
                        $sessioncache->completioncache[$data->courseid][$data->cmid]->viewed = 0;
                    }
                }
                $sessioncachestr = serialize($sessioncache);

                $sessdata = str_replace($matches[2], $sessioncachestr, $sessdata);
                $session->sessdata = base64_encode($sessdata);

                $DB->update_record('sessions', $session);
            }
        }
    }

    /** save users activity state, optional send a message to the user.
     *  only the "passed" state is save in the database, other states are based on
     *  completion tracking!
     *
     *
     * @param type $data
     */
    public function save_users_userstate($data, $userto, $cm) {
        global $DB, $USER;

        $message = array();
        $newstate = false;

        // ... check existing values.
        $params = array(
            'courseid' => $data->courseid,
            'cmid' => $data->cmid,
            'userid' => $data->userid,
            'value' => 'passed'
        );

        // ... set only, when completion-tracking is on.
        if (!empty($data->useractivitystate) and ($data->useractivitystate >= 70)) {

            if ($exists = $DB->get_record('local_acl_mod_userstate', $params)) {

                if ($data->useractivitystate != 260) {
                    $DB->delete_records('local_acl_mod_userstate', array('id' => $exists->id));
                }
            } else {

                if ($data->useractivitystate == 260) {
                    $userdate = new \stdClass();
                    $userdate->courseid = $data->courseid;
                    $userdate->cmid = $data->cmid;
                    $userdate->userid = $data->userid;
                    $userdate->value = 'passed';
                    $userdate->timecreated = time();
                    $DB->insert_record('local_acl_mod_userstate', $userdate);
                }
            }

            // ... if users completion is not accepted now deleted the completion record, if there is any.
            if ($data->useractivitystate == 70) {

                // ...delete users completion.
                $params = array('coursemoduleid' => $data->cmid, 'userid' => $data->userid);
                if ($modulescompletion = $DB->get_record('course_modules_completion', $params)) {

                    $modulescompletion->viewed = 0;
                    $modulescompletion->completionstate = 0;
                    $modulescompletion->timemodified = time();
                    $DB->update_record('course_modules_completion', $modulescompletion);

                    // ... update users session completion cache.
                    $activesessions = $this->get_active_sessions_for_users(array($data->userid));

                    if (isset($activesessions[$data->userid])) {
                        $this->update_users_completion_cache($activesessions[$data->userid], $data, $modulescompletion);
                    }
                }
            }

            $message[] = get_string('activitystatesaved', 'local_aclmodules');
            $newstate = $data->useractivitystate;
        }

        // ...optionally send a module-message.
        if (!empty($data->useractivitymessage)) {

            $modmessage = modmessages::instance();
            $modmessage->message_post_message($cm, $USER, $userto, $data->useractivitymessage);
            $message[] = get_string('modmessagessent', 'local_aclmodules');
        }

        return array('error' => '0', 'message' => implode('<br />', $message));
    }

    /** get the global config for a module type (i. e. modname)
     *
     * @param string $modname
     * @param string $configoption
     * @return int 1 (checked) or 0 (not checked)
     */
    public function get_acl_mod_config_default($modname, $configoption) {

        if (!isset($this->config[$configoption])) {
            return 0;
        }

        if (isset($this->config[$configoption][$modname])) {
            return 1;
        } else {
            return 0;
        }
    }

    /** get all the coursemodules instance config data
     *
     * @global database $DB
     * @param int $courseid, the courseid
     * @return array, the config data.
     */
    public function get_acl_mod_config($courseid) {
        global $DB;

        if (isset($this->cmsconfig)) {
            return $this->cmsconfig;
        }

        if (!$configdata = $DB->get_records('local_acl_mod_config', array('courseid' => $courseid))) {
            $this->cmsconfig = array();
            return $this->cmsconfig;
        }

        // Group by coursemoduleid.
        $cmsconfig = array();

        foreach ($configdata as $data) {

            if (!isset($cmsconfig[$data->coursemoduleid])) {
                $cmsconfig[$data->coursemoduleid] = array();
            }
            $cmsconfig[$data->coursemoduleid][$data->name] = $data;
        }
        $this->cmsconfig = $cmsconfig;
        return $this->cmsconfig;
    }

    /** get the acl config data (i. e. rateable, commetable) for one module instance
     *
     * Note we retrieve the data for all modules in the course an cache it.
     *
     * @param recored $course
     * @param type $mod
     * @param type $configoption
     * @return type
     */
    public function get_acl_mod_config_value($course, $mod, $configoption) {

        $configoptions = $this->get_acl_mod_config($course->id);

        // ... all coursesettings are stored with mod->id == 0!
        if (!is_object($mod) and ($mod == 0)) {
            $mod = (object) array('id' => 0, 'modname' => 'coursesetting');
        }

        if (!isset($configoptions[$mod->id][$configoption])) {
            $configvalue = $this->get_acl_mod_config_default($mod->modname, $configoption);
        } else {
            $configvalue = $configoptions[$mod->id][$configoption]->value;
        }
        return $configvalue;
    }

    public function save_acl_mod_config_value($courseid, $cmid, $name, $value) {
        global $DB;

        $params = array(
            'courseid' => $courseid,
            'coursemoduleid' => $cmid,
            'name' => $name
        );

        if ($exists = $DB->get_record('local_acl_mod_config', $params)) {

            if ($exists->value != $value) {
                $DB->set_field('local_acl_mod_config', 'value', $value, array('id' => $exists->id));
            }
            return true;
        }

        $newconfig = new \stdClass();
        $newconfig->courseid = $courseid;
        $newconfig->coursemoduleid = $cmid;
        $newconfig->name = $name;
        $newconfig->value = $value;
        $newconfig->timecreated = time();

        $DB->insert_record('local_acl_mod_config', $newconfig);

        // ...clear cmsconfig cache.
        unset($this->cmsconfig);

        return true;
    }

    /** save the acl config data for each course module instance in local_acl_mod_config
     * (i. e. whether module is rateable, commentable ...)
     *  Note that global config of this module hold default data for instances.
     *
     * @global database $DB
     * @param record $form the submitted form data.
     * @return boolean
     */
    private function save_acl_mod_config($form) {
        global $DB;

        // ... save the course related options (cmid == 0).
        $aclactive = (isset($form->aclactive)) ? '1' : '0';
        $this->save_acl_mod_config_value($form->courseid, 0, 'aclactive', $aclactive);

        // ... now save the coursemodule-related options.
        // Get existing data cmids => array('configoption' => databaserecord).
        $existingconfig = $this->get_acl_mod_config($form->courseid);

        $modinfo = get_fast_modinfo($form->courseid);
        $mods = $modinfo->get_cms();

        foreach ($mods as $mod) {

            foreach (self::$configoptions as $configoption) {

                // ... must we update?
                if (isset($existingconfig[$mod->id][$configoption])) {

                    // ... from database.
                    $record = $existingconfig[$mod->id][$configoption];

                    // ...is there submitted data?
                    $submittedvalue = (isset($form->configoptions[$configoption][$mod->id])) ? "1" : "0";

                    if ($record->value != $submittedvalue) {
                        $record->value = $submittedvalue;
                        $DB->update_record('local_acl_mod_config', $record);
                    }
                } else { // ...have to insert!
                    $newconfigoption = new \stdClass();
                    $newconfigoption->courseid = $form->courseid;
                    $newconfigoption->coursemoduleid = $mod->id;
                    $newconfigoption->name = $configoption;

                    $submittedvalue = (isset($form->configoptions[$configoption][$mod->id])) ? '1' : '0';

                    $newconfigoption->value = $submittedvalue;
                    $newconfigoption->timecreated = time();

                    $DB->insert_record('local_acl_mod_config', $newconfigoption);
                }
            }

            // ... are there records to delete?
            $intersect = array_intersect(array_keys($existingconfig), array_keys($mods));
            // ...do not delete course config (array(0)).
            $deleteids = array_diff(array_keys($existingconfig), $intersect, array(0));

            if (count($deleteids) > 0) {
                list($incmids, $params) = $DB->get_in_or_equal($deleteids);
                $DB->delete_records_select('local_acl_mod_config', "coursemoduleid {$incmids}", $params);
            }
        }

        unset($this->cmsconfig);
        return true;
    }

    /** This is a very tricky (and ugly) way to push data in the current session of another user
     *
     * @param type $session
     * @param type $acldata
     */
    private function update_users_session_data($session, $acldata) {

        // Get the session_id of the current user and (temporary) close the session.
        $oldsessionid = session_id();
        session_write_close();

        // Start the the session of the user to change availability data.
        session_id($session->sid);
        session_start();
        // Write the data and close session (and hopefully to not collide with parallel session).
        $_SESSION['USER']->profile[ACL_MOD_PROFILEFIELD_SHORTNAME] = $acldata;
        session_write_close();

        // Restore the session of current user.
        session_id($oldsessionid);
        session_start();
    }

    /** reload users profile data without logout/login when indicated via user_preference
     *  not used in the moment, while customer don't want to hav a hack in require_login.
     *
     * @global \local_aclmodules\local\record $CFG
     * @global \local_aclmodules\local\record $USER
     */
    public static function reload_users_acldata() {
        global $CFG, $USER;

        if (get_user_preferences('reloadcustomfields', false)) {

            require_once($CFG->dirroot . '/user/profile/lib.php');
            profile_load_custom_fields($USER);

            set_user_preference('reloadcustomfields', false);
        }
    }

    /** deprecated way to update session data for active sessions when db session handling is used.
     *  not used in the moment.
     *
     * @global database $DB
     * @param type $session
     * @param type $acldata
     */
    private function update_users_session_data_db($session, $acldata) {
        global $DB;

        $data = base64_decode($session->sessdata);

        $parts = explode("USER|", $data);

        $user = unserialize($parts[1]);
        $user->profile[ACL_MOD_PROFILEFIELD_SHORTNAME] = $acldata;
        $parts[1] = serialize($user);

        $data = $parts[0] . "USER|" . $parts[1];

        $session->sessdata = base64_encode($data);
        $DB->update_record('sessions', $session);
    }

    /** get active sessions from database to correct user info field
     *
     * @global database $DB
     * @global record $CFG
     * @param array $participants
     * @return array list of active sessions indexed by userid.
     */
    private function get_active_sessions_for_users($userids) {
        global $DB, $CFG;

        list($inuserids, $params) = $DB->get_in_or_equal($userids);

        $timeoutlimit = time() - $CFG->sessiontimeout;
        $params[] = $timeoutlimit;

        $sql = "SELECT s.userid, s.* FROM {sessions} s
                WHERE userid {$inuserids} AND timemodified > ? AND
                timemodified = (SELECT MAX(timemodified) FROM {sessions} s2 WHERE s2.userid = s.userid)";

        if (!$activesessions = $DB->get_records_sql($sql, $params)) {
            return array();
        }
        return $activesessions;
    }

    /** synchronize mods availability with local_acl_mod_useravail, this is done by
     *  putting the value #moduleid# in user additional profile field
     *  (i. e. called ACL_MOD_PROFILEFIELD_SHORTNAME)
     *  to restrict access to module.
     *
     * @global database $DB
     * @param type $courseid
     * @param type $users
     */
    private function synchronize_users_info_data($users) {
        global $DB;

        // Get active sessions for updating user info data during session.
        $activesessions = $this->get_active_sessions_for_users(array_keys($users));

        // ...get available modules for changed users.
        $availablemods = $this->get_mods_available_for_users(0, $users);

        // ...get existing user info data, to decide whether to update or insert.
        $existinginfodata = $this->get_avail_infodata_for_users($users);

        foreach ($users as $userid => $user) {

            $newavaildata = (isset($availablemods[$user->id])) ? $availablemods[$user->id] : array();
            $availdata = '#' . implode('#', $newavaildata) . '#';

            $newrecord = new \stdClass();
            $newrecord->userid = $user->id;
            $newrecord->fieldid = $this->get_profile_field()->id;
            $newrecord->data = $availdata;
            $newrecord->dataformat = 0;

            if (isset($existinginfodata[$userid])) {
                $newrecord->id = $existinginfodata[$userid]->id;
                $DB->update_record('user_info_data', $newrecord);
            } else {
                $DB->insert_record('user_info_data', $newrecord);
            }

            if (isset($activesessions[$userid])) {

                // When another user is loggedin the
                // same time as teacher changes visibility we have to push new values
                // of the profilefield into $USER object of the other user. This is done by:
                //
                // 1) indicating the need of reloading the custom profile field by setting a user_preference
                // 2) checking this user preference every time a course page is loaded (must hack into require_login)
                //    and load customfields the user reloadcustomfields is true.
                // set_user_preference('reloadcustomfields', true, $userid);
                // Not used in the moment.

                $this->update_users_session_data($activesessions[$userid], $availdata);
            }
        }
    }

    /** save the availability for the given users
     *
     * @global database $DB
     * @param record $form the submitted form data.
     */
    private function save_mods_available_for_users($form, $course, $context) {
        global $DB;

        if (!isset($form->moduseravail)) {
            $form->moduseravail = array();
        }

        // ... ensure that activity with state above 70 remain assigned.
        $reportsdata = $this->get_course_state_report($course, $context);

        if ($reportsdata['error'] == 0) {

            $moduserstates = $reportsdata['data'];

            //  ...array($modid => array($userid)).
            foreach ($moduserstates as $modid => $userstates) {

                foreach ($userstates as $userid => $state) {

                    $modavailable = (isset($form->moduseravail[$userid]) and isset($form->moduseravail[$userid][$modid]));

                    // ... available data not in form submit for higher states.
                    if (!$modavailable and ($state > 70)) {

                        // ...add moduseravail to avoid deletion.
                        if (!isset($form->moduseravail[$userid])) {
                            $form->moduseravail[$userid] = array();
                        }
                        $form->moduseravail[$userid][$modid] = 1;
                    }
                }
            }
        }

        $context = \context_course::instance($form->courseid);
        $participants = $this->get_all_gradable_users($context);

        // Get all user avail data for the course. // array userid => array(modids).
        $existinguseravaildata = $this->get_mods_available_for_users($form->courseid, $participants);
        $newuseravaildata = $form->moduseravail;

        $coursemodules = $DB->get_records('course_modules', array('course' => $form->courseid));

        $changedusers = array();

        foreach ($participants as $user) {

            foreach ($coursemodules as $mod) {

                if (!empty($newuseravaildata[$user->id]) && (!empty($newuseravaildata[$user->id][$mod->id]))) {

                    // ...availability was submitted.
                    if (empty($existinguseravaildata[$user->id]) || empty($existinguseravaildata[$user->id][$mod->id])) {
                        // ...insert and remember user.
                        $changedusers[$user->id] = $user;

                        $newrecord = new \stdClass();
                        $newrecord->courseid = $form->courseid;
                        $newrecord->userid = $user->id;
                        $newrecord->coursemoduleid = $mod->id;
                        $newrecord->timecreated = time();
                        $DB->insert_record('local_acl_mod_useravail', $newrecord);
                    }
                } else {

                    if (!empty($existinguseravaildata[$user->id]) && !empty($existinguseravaildata[$user->id][$mod->id])) {

                        // ...delete and remember user.
                        $changedusers[$user->id] = $user;

                        $conditions = array();
                        $conditions['userid'] = $user->id;
                        $conditions['courseid'] = $form->courseid;
                        $conditions['coursemoduleid'] = $mod->id;

                        $DB->delete_records('local_acl_mod_useravail', $conditions);
                    }
                }
            }
        }

        if (count($changedusers) > 0) {
            $this->synchronize_users_info_data($changedusers);
        }
    }

    /** get the value of users profile field (ACL_MOD_PROFILEFIELD_SHORTNAME),
     *  which holds the data for acl restriciton
     *
     * @global database $DB
     * @param array $participants
     * @return array the value per participant.
     */
    private function get_avail_infodata_for_users($participants) {
        global $DB;

        if (count($participants) == 0) {
            return array();
        }

        list($inuserids, $params) = $DB->get_in_or_equal(array_keys($participants));
        $params[] = ACL_MOD_PROFILEFIELD_SHORTNAME;

        $sql = "SELECT infodata.userid, infodata.* FROM {user_info_data} infodata
                JOIN {user_info_field} infofield ON infofield.id = infodata.fieldid
                WHERE userid {$inuserids} AND infofield.shortname = ?";

        if (!$acluserdata = $DB->get_records_sql($sql, $params)) {
            return array();
        }
        return $acluserdata;
    }

    /** get all availability info for the participants of a course
     *
     * @global database $DB
     * @param int $courseid, if 0 don't filter per course.
     * @param array $participants
     * @return array
     */
    private function get_acl_mod_useravail($courseid, $participants) {
        global $DB;

        if (count($participants) == 0) {
            return array();
        }

        list($inuserids, $params) = $DB->get_in_or_equal(array_keys($participants));

        $wherecourseid = "";
        if ($courseid > 0) {
            $params[] = $courseid;
            $wherecourseid = " AND courseid = ? ";
        }

        $sql = "SELECT * FROM {local_acl_mod_useravail} useravail
                WHERE userid {$inuserids}{$wherecourseid}";

        if (!$useravailmods = $DB->get_records_sql($sql, $params)) {
            return array();
        }

        return $useravailmods;
    }

    /** get a list of all available modules for each participant
     *
     * @param int $courseid, id of the course, set to 0 means all of the users mods.
     * @param array $participants list of participants of course
     * @return array list of moduleids for each user.
     */
    public function get_mods_available_for_users($courseid, $participants) {

        $acluseravail = $this->get_acl_mod_useravail($courseid, $participants);

        // ... explode and group data per user.
        $modsavailableperuser = array();
        foreach ($acluseravail as $useravail) {
            if (!isset($modsavailableperuser[$useravail->userid])) {
                $modsavailableperuser[$useravail->userid] = array();
            }
            $modsavailableperuser[$useravail->userid][$useravail->coursemoduleid] = $useravail->coursemoduleid;
        }
        return $modsavailableperuser;
    }

    /** get sectionnumbers (not ids!) of all sections, which are available for the user,
     *  i. e. which contains at least one visible (and optional aclcontrolled) coursemodule.
     *
     * @global record $USER
     * @param int $courseid
     * @param boolean $requireaclcontrolled, if true require at least one visible aclcontrolled course module.
     * @return array of sectionums, which contains at least one visible coursemodule.
     */
    public function get_sections_available_for_user($courseid,
                                                    $requireaclcontrolled = false) {
        global $USER;

        $modinfo = get_fast_modinfo($courseid);
        $sectioninfo = $modinfo->get_section_info_all();

        if ($requireaclcontrolled) {

            $aclcontrolledmods = $this->get_mods_available_for_users($courseid, array($USER->id => $USER));
            if (!isset($aclcontrolledmods[$USER->id])) {
                return array();
            }
            $aclcontrolledmodids = $aclcontrolledmods[$USER->id];
        }

        $availablesections = array();

        foreach ($sectioninfo as $section) {

            if (empty($modinfo->sections[$section->section])) {
                continue;
            }

            $modnumbers = $modinfo->sections[$section->section];
            if ($requireaclcontrolled) {
                $modnumbers = array_intersect($modnumbers, $aclcontrolledmodids);
            }

            // ... section is empty and should not be available (i. e. visible).
            if (empty($modnumbers)) {
                continue;
            }

            foreach ($modinfo->sections[$section->section] as $modnumber) {

                $mod = $modinfo->cms[$modnumber];

                if ($mod->uservisible) {
                    $availablesections[$section->section] = $section->section;
                    break;
                }
            }
        }
        return $availablesections;
    }

    /** get the profile field, which holds available modules for the user
     *  if no profile field exists plugin is not correctly installed.
     *
     * @global database $DB
     * @return type
     */
    private function get_profile_field() {
        global $DB;

        if (isset($this->profilefield)) {
            return $this->profilefield;
        }

        if (!$profilefield = $DB->get_record('user_info_field', array('shortname' => ACL_MOD_PROFILEFIELD_SHORTNAME))) {
            print_error('no user profile fields found, plugin is not correctly configurated!');
        }

        $this->profilefield = $profilefield;
        return $profilefield;
    }

    /** get all the entries in course_modules_avail_fields, if there is an entry
     * for the user info field with shortname ACL_MOD_PROFILEFIELD_SHORTNAME the
     * availability of the coursemodule will be controlled by the user info data in
     * this field (i. e. user will see this field when info data contains #coursemoduleid#
     *
     * @global database $DB
     * @param int $courseid
     * @return array,
     */
    public function get_acl_mod_avail_fields($courseid) {
        global $DB;

        if (isset($this->modavailfields[$courseid])) {
            return $this->modavailfields[$courseid];
        }

        $modinfo = get_fast_modinfo($courseid);
        $cms = $modinfo->get_cms();

        $modavailfields = array();

        foreach ($cms as $cm) {

            if (!empty($cm->availability)) {

                $ci = new \core_availability\info_module($cm);
                $tree = $ci->get_availability_tree();
                $structure = $tree->save();

                foreach ($structure->c as $c) {

                    // This module acl is created in the first level of condition tree, so test only first level.
                    if (isset($c->type) && ($c->type == 'profile') &&
                            ($c->op == 'contains') &&
                            ($c->cf == ACL_MOD_PROFILEFIELD_SHORTNAME) &&
                            $c->v[0] = '#') {
                        $modavailfields[$cm->id] = $c->v;
                    }
                }
            }
        }

        $this->modavailfields[$courseid] = $modavailfields;
        return $modavailfields;
    }

    /** delete the availability condition for this plugin
     *
     * @param type $cm
     */
    private function delete_mods_availability($cm) {
        global $DB;

        if (empty($cm->availability)) {

            return false;
        }

        $modified = false;

        $ci = new \core_availability\info_module($cm);
        $tree = $ci->get_availability_tree();
        $structure = $tree->save();

        // Example of avail structure:
        // stdClass Object (
        // [op] => &
        // [showc] => Array ( [0] => 1 [1] => 1 )
        // [c] => Array (
        // [0] => stdClass Object ( [type] => profile [op] => contains [cf] => localaclavailablemodules [v] => #3#).

        foreach ($structure->c as $key => $condition) {
            if (isset($condition->type) and ($condition->type == 'profile')
                    and ($condition->op == 'contains') and ($condition->v == '#' . $cm->id . '#')) {
                unset($structure->c[$key]);
                unset($structure->showc[$key]);
                $modified = true;
            }
        }

        if ($modified) {

            $structure->c = array_values($structure->c);
            $structure->showc = array_values($structure->showc);

            $DB->set_field('course_modules', 'availability', json_encode($structure), array('id' => $cm->id));
        }
    }

    /** delete the availability condition for this plugin
     *
     * @param type $cm
     */
    private function insert_mods_availability($cm) {
        global $DB;

        if (!empty($cm->availability)) {

            $ci = new \core_availability\info_module($cm);
            $tree = $ci->get_availability_tree();
            $structure = $tree->save();
        } else {

            $structure = new \stdClass();
            $structure->op = '&';
            $structure->showc = array();
            $structure->c = array();
        }

        $newcondition = new \stdClass();
        $newcondition->type = 'profile';
        $newcondition->cf = ACL_MOD_PROFILEFIELD_SHORTNAME;
        $newcondition->op = 'contains';
        $newcondition->v = '#' . $cm->id . '#';

        $structure->c[] = $newcondition;
        $structure->showc[] = false;

        $DB->set_field('course_modules', 'availability', json_encode($structure), array('id' => $cm->id));
    }

    /** save the data in $data to the course_modules_avail_fields info to enable
     *  Visibility control.
     *
     * if  aclcontrol is on this is done for all coursemodules in the course, if aclcontrol is
     * off all the visiblility restriction will be deleted.
     *
     * @param type $data at least an object with attribute courseid.
     */
    public function save_acl_mod_avail_fields($data) {

        $rebuildcacheneeded = false;

        // ... if acl control is set to on, get all coursemodules configurated for acl control.
        $coursemoduleids = array();

        if (!empty($data->aclactive)) {

            $modinfo = get_fast_modinfo($data->courseid);
            $configurablemods = $this->get_configurable_modules(array_keys($modinfo->get_cms()), $modinfo);
            $coursemoduleids = array_keys($configurablemods);
        }

        // ... now write information to course modules.
        $existinginfo = $this->get_acl_mod_avail_fields($data->courseid);

        $existinginfoids = array_keys($existinginfo);
        $intersectids = array_intersect($existinginfoids, $coursemoduleids);

        $cmidstodelete = array_diff($existinginfoids, $intersectids);
        $cmidstoinsert = array_diff($coursemoduleids, $intersectids);

        // ... delete entries.
        if (count($cmidstodelete) > 0) {

            $modinfo = get_fast_modinfo($data->courseid);

            // Get ids of course_modules_avail_fields_table <> coursemoduleid!
            foreach ($cmidstodelete as $cmid) {
                $this->delete_mods_availability($modinfo->get_cm($cmid));
            }
            $rebuildcacheneeded = true;
        }

        if (count($cmidstoinsert)) {

            $modinfo = get_fast_modinfo($data->courseid);

            // Get ids of course_modules_avail_fields_table <> coursemoduleid!
            foreach ($cmidstoinsert as $cmid) {

                $this->insert_mods_availability($modinfo->get_cm($cmid));
            }
            $rebuildcacheneeded = true;
        }

        if ($rebuildcacheneeded) {
            // ... reset cache for this request.
            unset($this->modavailfields[$data->courseid]);
            rebuild_course_cache($data->courseid);
        }
    }

    /** update the available field info for all modules with acl control active,
     *  this is done when course module are created, updated or deleted.
     *
     * @param type $courseid
     */
    public function update_acl_active($courseid) {

        $data = new \stdClass();
        $data->courseid = $courseid;
        $configoptions = $this->get_acl_mod_config($courseid);

        if (isset($configoptions[0]['aclactive'])) {
            $data->aclactive = 1;
        }

        rebuild_course_cache($courseid);
        $this->save_acl_mod_avail_fields($data);
    }

    /** save all the acl data (config data inclusive!) from the planner form
     *
     * @param record $form the submitted data form planner/edit.php
     * @return boolean
     */
    public function save_acl_mod_access($form, $course, $context) {

        // ...save data into availablility fields of course modules.
        $this->save_acl_mod_avail_fields($form);
        // ...save data into the additional profile field for the user.
        $this->save_mods_available_for_users($form, $course, $context);
        // ...save config data for each module and acl control on/off.
        $this->save_acl_mod_config($form);

        return true;
    }

    /** get all Modules which has a controllables modul type (i. e. choice, chat, form etc.
     *
     * @param array $cmids, coursemodule ids to search
     * @param object $modinfo mod info from course
     * @return array list of modules which may be acl controlled.
     */
    public function get_configurable_modules($cmids, $modinfo) {

        $configurablemods = array();

        foreach ($cmids as $cmid) {

            $mod = $modinfo->cms[$cmid];
            if (in_array($mod->modname, $this->moduletypestocontrol)) {
                $configurablemods[$cmid] = $mod;
            }
        }
        return $configurablemods;
    }

    /** get levels from database for a particular course.
     *
     * @global database $DB
     * @param int $courseid, id of the course
     * @return array of level records.
     */
    public function get_levels($courseid) {
        global $DB;

        if (!$levels = $DB->get_records('local_acl_mod_level', array('courseid' => $courseid))) {
            return array();
        }

        return $levels;
    }

    /** save the data, which is submitted from the level form in pages/leveledit.php
     *  (i. e. save new levels and new user to level relationships)
     *
     * @global database $DB
     * @param object $form, the submitted data
     * @return boolean true if succeded
     */
    public function save_levels($form) {
        global $DB;

        // ...do some security checks for formdata (sesskey is already checked).
        $form->courseid = (int) $form->courseid;
        $context = \context_course::instance($form->courseid, MUST_EXIST);
        require_capability('local/aclmodules:edit', $context);

        $form->levelname = clean_param_array($form->levelname, PARAM_TEXT);

        if (!empty($form->newlevel)) {
            $form->newlevel = clean_param_array($form->newlevel, PARAM_TEXT);
        }

        // ... get existing levels.
        if ($existinglevels = $DB->get_records('local_acl_mod_level', array('courseid' => $form->courseid))) {

            $existinglevelids = array_keys($existinglevels);
        } else {

            $existinglevelids = array();
        }

        // ... calculate ids.
        if (!isset($form->levelname)) {
            $form->levelname = array();
        }
        $levelidstoupdate = array_intersect(array_keys($form->levelname), $existinglevelids);
        $levelidstodelete = array_diff($existinglevelids, $levelidstoupdate);

        // ... update existing levels.
        foreach ($levelidstoupdate as $levelid) {

            $level = $existinglevels[$levelid];
            $level->name = $form->levelname[$levelid];
            $DB->update_record('local_acl_mod_level', $level);
        }

        // ... delete removed levels.
        if (count($levelidstodelete) > 0) {

            list($inlevelid, $params) = $DB->get_in_or_equal($levelidstodelete);

            // ... delete levels.
            $DB->delete_records_select('local_acl_mod_level', "id {$inlevelid}", $params);

            // ... delete users levels.
            $DB->delete_records_select('local_acl_mod_userlevel', "levelid {$inlevelid}", $params);
        }

        // ... insert new levels.
        $newlevelids = array();
        if (!empty($form->newlevel)) {
            foreach ($form->newlevel as $key => $levelname) {
                $newlevel = new \stdClass();
                $newlevel->courseid = $form->courseid;
                $newlevel->name = $levelname;
                $newlevel->timecreated = time();
                $newlevelids[$key] = $DB->insert_record('local_acl_mod_level', $newlevel);
            }
        }

        // ... set new userlevels (userid => level).
        if (!empty($form->userlevel)) {

            // ... get exisisting user levels.
            $userleveluserids = $this->get_users_levels($form->courseid);
            $existingleveluserids = array_keys($userleveluserids);
            $leveluseridstoupdate = array_intersect(array_keys($form->userlevel), $existingleveluserids);

            foreach ($form->userlevel as $userid => $value) {

                $info = explode('_', $value);

                if ($info[0] == 'level') { // Existing level.
                    $levelid = $info[1];
                }

                if ($info[0] == 'newlevel') {
                    // New level, use newlevelids to map correct id.
                    if (!isset($newlevelids[$info[1]])) {
                        debugging("incorrect levelid for new level! {Infoid: {$info[1]}");
                        continue;
                    }
                    $levelid = $newlevelids[$info[1]];
                }

                if (in_array($userid, $leveluseridstoupdate)) {

                    $userlevel = $userleveluserids[$userid];
                    $userlevel->levelid = $levelid;
                    $DB->update_record('local_acl_mod_userlevel', $userlevel);
                } else {

                    $userlevel = new \stdClass();
                    $userlevel->userid = $userid;
                    $userlevel->levelid = $levelid;
                    $userlevel->timecreated = time();
                    $DB->insert_record('local_acl_mod_userlevel', $userlevel);
                }
            }
        }
        return true;
    }

    /** get all levels information from database indexed by userid
     *
     * @global database $DB
     * @param int $courseid
     * @return array
     */
    public function get_users_levels($courseid) {
        global $DB;

        $sql = "SELECT ul.userid, ul.id, ul.levelid, ul.timecreated
                FROM {local_acl_mod_userlevel} ul
                JOIN {local_acl_mod_level} l ON ul.levelid = l.id
                WHERE l.courseid = ?";

        if (!$userlevels = $DB->get_records_sql($sql, array($courseid))) {
            return array();
        }
        return $userlevels;
    }

    /** get all the levels for a course and group users per level
     *
     * @param int $courseid
     * @return array list of users per level
     */
    public function get_level_users($courseid) {

        if (!$userlevels = $this->get_users_levels($courseid)) {
            return array();
        }

        // ... group users by level. levelid => array of userids.
        $usersperlevel = array();
        foreach ($userlevels as $userlevel) {
            if (!isset($usersperlevel[$userlevel->levelid])) {
                $usersperlevel[$userlevel->levelid] = array();
            }
            $usersperlevel[$userlevel->levelid][$userlevel->userid] = $userlevel->userid;
        }
        return $usersperlevel;
    }

    /** get sql statment for gradeable users in the course
     *
     * @global database $DB
     * @global type $CFG
     * @param type $coursecontext
     * @param type $order
     * @param type $groupid
     * @param type $extrajoin
     * @param type $extrawhere
     * @param type $extraparams
     * @param type $fields
     * @return type
     */
    protected function get_gradable_users_sql($coursecontext,
                                              $order = 'lastname', $groupid = 0,
                                              $extrajoin = '', $extrawhere = '',
                                              $extraparams = array(),
                                              $fields = "u.*") {

        global $DB, $CFG;

        list($gradebookrolessql, $params) =
                $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr');

        list($enrolledsql, $enrolledparams) = get_enrolled_sql($coursecontext);
        $params = array_merge($params, $enrolledparams);

        list($relatedcontexts, $ctxparams) = $DB->get_in_or_equal(
                $coursecontext->get_parent_context_ids(true), SQL_PARAMS_NAMED);

        $params = array_merge($params, $ctxparams);

        if ($groupid) {
            $groupsql = "INNER JOIN {groups_members} gm ON gm.userid = u.id";
            $groupwheresql = "AND gm.groupid = :groupid";
            $params['groupid'] = $groupid;
        } else {
            $groupsql = "";
            $groupwheresql = "";
        }

        $userssql = "SELECT $fields
                        FROM {user} u
                        JOIN ($enrolledsql) je ON je.id = u.id
                             $groupsql
                        JOIN (
                                  SELECT DISTINCT ra.userid
                                    FROM {role_assignments} ra
                                   WHERE ra.roleid $gradebookrolessql
                                     AND ra.contextid $relatedcontexts
                             ) rainner ON rainner.userid = u.id
                        {$extrajoin}
                         WHERE u.deleted = 0
                             $groupwheresql
                             $extrawhere
                    ORDER BY $order";

        $params = array_merge($params, $extraparams);
        return array($userssql, $params);
    }

    /**  get all gradable users from the course
     *
     * @global database $DB
     * @param context $coursecontext
     * @param string $order
     * @param int $groupid
     * @return array list of user records which are gradable.
     */
    public function get_all_gradable_users($coursecontext, $order = 'lastname',
                                           $groupid = 0) {
        global $DB;

        if (isset($this->participants)) {
            return $this->participants;
        }

        list($userssql, $params) = $this->get_gradable_users_sql($coursecontext, $order, $groupid);
        $this->participants = $DB->get_records_sql($userssql, $params);

        return $this->participants;
    }

    /** get completioninfo for the course and for all users to avoid many database calls
     * by using completion_info->get_data().
     *
     * @global database $DB
     * @param type $courseid
     * @return type
     */
    private function get_users_completion_info($courseid) {
        global $DB;

        if (isset($this->coursecompletioninfos)) {
            return $this->coursecompletioninfos;
        }

        $sql = "SELECT cmc.* FROM {course_modules} cm
                INNER JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                WHERE cm.course = ?";

        if (!$coursecompletions = $DB->get_records_sql($sql, array($courseid))) {
            return array();
        }

        // ... group results by cmid and userid.
        $groupedresults = array();
        foreach ($coursecompletions as $completion) {
            if (!isset($groupedresults[$completion->coursemoduleid])) {
                $groupedresults[$completion->coursemoduleid] = array();
            }

            $groupedresults[$completion->coursemoduleid][$completion->userid] = $completion;
        }

        $this->coursecompletioninfos = $groupedresults;
        return $this->coursecompletioninfos;
    }

    /** get completion info for the whole course by one database call (for further use)
     *  and extract users completion info
     *
     * @param type $courseid
     * @param type $cmid
     * @param type $userid
     * @return \StdClass
     */
    protected function get_user_completion_info($courseid, $cmid, $userid) {

        $completioninfo = $this->get_users_completion_info($courseid);

        if (isset($completioninfo[$cmid]) and isset($completioninfo[$cmid][$userid])) {
            return $completioninfo[$cmid][$userid];
        }

        // Row not present counts as 'not complete'.
        $data = new \stdClass();
        $data->id = 0;
        $data->coursemoduleid = $cmid;
        $data->userid = $userid;
        $data->completionstate = 0;
        $data->viewed = 0;
        $data->timemodified = 0;
        return $data;
    }

    /** if users completion changed, we have to set the state to passed, if module
     *  has automatic approving set and completion criterias are met.
     *
     * @global \database $DB
     * @param int $courseid
     * @param int $userid
     * @param int $modid
     * @return boolean true if state was changed automatically.
     */
    public function users_completion_changed($event) {
        global $DB;

        $eventdata = $event->get_data();

        if (!$course = $DB->get_record('course', array('id' => $eventdata['courseid']))) {
            return false;
        }

        if (!$user = $DB->get_record('user', array('id' => $eventdata['relateduserid']))) {
            return false;
        }

        $cmid = $eventdata['contextinstanceid'];

        // If a module is "only viewed" completion is not comletely calculated, when triggering this method
        // so we have to check if "viewed flag" is set and call completion->update_state($cm, COMPLETION_COMPLETE, $userid);
        // to calculate completion.
        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($cmid);

        $modulescompletion = $event->get_record_snapshot('course_modules_completion', $eventdata['objectid']);

        if (($modulescompletion->completionstate == 0) and ($modulescompletion->viewed == 1)) {
            $completion = new \completion_info($course);
            $completion->update_state($cm, COMPLETION_COMPLETE, $user->id);
        }

        // Clear request cache.
        unset($this->groupeduserstates);
        unset($this->coursecompletioninfos);

        // ... and calculate new value.
        $reportdata = $this->get_users_statesreport($course, $modinfo, array($user->id => $user));
        $state = $reportdata['data'][$cmid][$user->id];

        // If state == 160 (finished) and automatic approval is on, set state to 260 (passed).
        if ($state == 160) {

            // ...check whether automatic approval is on.
            $automaticapproval = $this->get_acl_mod_config_value($course, $cm, 'modulesautoapprove');

            if ($automaticapproval == 1) {

                $data = new \stdClass();
                $data->useractivitystate = 260;
                $data->courseid = $course->id;
                $data->cmid = $cm->id;
                $data->userid = $user->id;
                $this->save_users_userstate($data, $user, $cm);
            }
            return true;
        }
        return false;
    }

    /** get all of the reports data, which is needed to display the planner.
     *
     * @param record $course
     * @param record $context
     * @return array
     */
    public function get_course_state_report($course, $context) {

        if (!$participants = $this->get_all_gradable_users($context)) {
            return array('error' => '1', 'data' => array());
        }

        $modinfo = get_fast_modinfo($course);

        return $this->get_users_statesreport($course, $modinfo, $participants);
    }

    /** aggregate all states, which are needed to display the planner.
     *
     * @global record $CFG
     * @param record $course
     * @param obejct $modinfo
     * @param array $participants
     * @return array
     */
    public function get_users_statesreport($course, $modinfo, $participants) {
        global $CFG;

        require_once($CFG->dirroot . '/lib/completionlib.php');

        $sectioninfo = $modinfo->get_section_info_all();

        // ... get assigned modids for each user. userid => array(modid).
        $modavailuser = $this->get_mods_available_for_users($course->id, $participants);

        // ... get data to retrieve passed modules for the users.
        $groupeduserstates = $this->get_course_userstates($course->id);

        $data = array(); // ...array(modid => array(userid => state)).
        $counts = array(); // ...array(sectionid => array(userid => array(statevalue =count))).

        foreach ($sectioninfo as $thissection) {

            $counts[$thissection->id] = array();

            // ... section is empty.
            if (empty($modinfo->sections[$thissection->section])) {
                continue;
            }

            // ... first check the acl-configurable modules.
            $configurablemods = $this->get_configurable_modules($modinfo->sections[$thissection->section], $modinfo);

            if (count($configurablemods) == 0) {
                continue;
            }

            // ...initialize array.
            foreach ($configurablemods as $modid => $mod) {
                $data[$modid] = array();
            }

            foreach ($participants as $userid => $unused) {

                // ... total holds number of mods with state 70, 160 or 260.
                $counts[$thissection->id][$userid] = array('total' => 0, 'assigned' => 0, '70' => 0, '160' => 0, '260' => 0);

                foreach ($configurablemods as $modid => $mod) {

                    $assigned = (isset($modavailuser[$userid][$mod->id]));

                    if ($assigned) {
                        $counts[$thissection->id][$userid]['assigned']++;
                    }

                    if (!$mod->visible) {
                        $data[$modid][$userid] = ($assigned) ? 20 : 0;
                        continue;
                    }

                    // ... for visible mods check completiontracking.
                    if (!$mod->completion) {
                        $data[$modid][$userid] = ($assigned) ? 30 : 10;
                        continue;
                    }

                    if (!$assigned) {
                        $data[$modid][$userid] = 10;
                        continue;
                    }

                    // ... check whether the user has passed this mod already.
                    if (isset($groupeduserstates[$userid]) and
                            isset($groupeduserstates[$userid][$modid]) and
                            ($groupeduserstates[$userid][$modid]->value == 'passed')) {
                        $data[$modid][$userid] = 260;

                        $counts[$thissection->id][$userid]['total']++;
                        $counts[$thissection->id][$userid][$data[$modid][$userid]]++;
                        continue;
                    }

                    // ... get the completionstate of the user, dont use completion_info->get_data to avoid many DB Queries.
                    $completiondata = $this->get_user_completion_info($course->id, $mod->id, $userid);

                    $data[$modid][$userid] = ($completiondata->completionstate == COMPLETION_INCOMPLETE) ? 70 : 160;
                    $counts[$thissection->id][$userid]['total']++;
                    $counts[$thissection->id][$userid][$data[$modid][$userid]]++;
                }
            }
        }

        // ... calculate the sectionstates.
        $sectionstates = array();
        foreach ($sectioninfo as $thissection) {

            $sectionstates[$thissection->id] = array();

            foreach ($participants as $userid => $unused) {

                if (!isset($counts[$thissection->id][$userid])) {
                    continue;
                }

                if ($counts[$thissection->id][$userid]['total'] > 0) {

                    $total = $counts[$thissection->id][$userid]['total'];
                    $assigned = $counts[$thissection->id][$userid]["70"] / $total;
                    $finished = $counts[$thissection->id][$userid]["160"] / $total;
                    $passed = $counts[$thissection->id][$userid]["260"] / $total;

                    if ($assigned == 1) {
                        $sectionstates[$thissection->id][$userid] = 70;
                        continue;
                    }
                    if ($finished == 1) {
                        $sectionstates[$thissection->id][$userid] = 160;
                        continue;
                    }
                    if ($passed == 1) {
                        $sectionstates[$thissection->id][$userid] = 260;
                        continue;
                    }
                    $sectionstates[$thissection->id][$userid] = 140;
                }
            }
        }

        return array("error" => '0', 'data' => $data, 'sectionstates' => $sectionstates, 'counts' => $counts);
    }

    /** get the state for a single coursemodule and one user
     *
     * @param record $course
     * @param object $mod
     * @param record $user
     * @return array (to be JSON encoded);
     */
    public function get_user_state_report($course, $mod, $user) {

        // ... get reports data for one user.
        $participants = array($user->id => $user);

        $modinfo = get_fast_modinfo($course);
        $usersreportdata = $this->get_users_statesreport($course, $modinfo, $participants);

        $state = 0;
        if (isset($usersreportdata['data'][$mod->id][$user->id])) {
            $state = $usersreportdata['data'][$mod->id][$user->id];
        }

        // ...only return the reports data regarding the module.
        $cminfo = $modinfo->get_cm($mod->id);
        $sectionid = $cminfo->section;

        $sectionstate = 0;
        if (isset($usersreportdata['sectionstates'][$sectionid][$user->id])) {
            $sectionstate = $usersreportdata['sectionstates'][$sectionid][$user->id];
        }

        $counts = array("total" => 0, "assigned" => 0, "70" => 0, "160" => 0, "260" => 0);
        if (isset($usersreportdata['counts'][$sectionid][$user->id])) {
            $counts = $usersreportdata['counts'][$sectionid][$user->id];
        }

        return array("error" => '0', 'state' => $state, 'section' => $sectionid, 'sectionstate' => $sectionstate);
    }

    /** after restroing the course, we have to map the new moduleids. */
    private function map_acl_mod_avail_fields($courseid) {
        global $DB;

        $modinfo = get_fast_modinfo($courseid);
        $cms = $modinfo->get_cms();

        foreach ($cms as $cm) {

            if (!empty($cm->availability)) {

                $ci = new \core_availability\info_module($cm);
                $tree = $ci->get_availability_tree();
                $structure = $tree->save();

                foreach ($structure->c as &$c) {

                    // ...this module acl is created in the first level of condition tree, so test only first level.
                    if (isset($c->type) && ($c->type == 'profile') &&
                            ($c->op == 'contains') &&
                            ($c->cf == ACL_MOD_PROFILEFIELD_SHORTNAME) &&
                            $c->v[0] = '#') {

                        $c->v = '#' . $cm->id . '#';
                    }
                }

                $DB->set_field('course_modules', 'availability', json_encode($structure), array('id' => $cm->id));
            }
        }
        rebuild_course_cache($courseid);
    }

    /** launch some actions after restore:
     * - switch aclcontrol to on.
     * - synchronize profilefields for participants of the course, when restored with userdata.
     *
     * @global database $DB
     * @param type $courseid
     */
    public function launch_after_restore($courseid) {
        global $DB;

        $coursecontext = \context_course::instance($courseid);

        $participants = $this->get_all_gradable_users($coursecontext);

        if (count($participants) > 0) {
            $this->synchronize_users_info_data($participants);
        }

        // ... check if module have enabled availability.
        $modwithacl = $this->get_acl_mod_avail_fields($courseid);

        if (count($modwithacl) > 0) {

            $existingconfig = $this->get_acl_mod_config($courseid);

            if (!isset($existingconfig[0]['aclactive'])) {

                // ...switch aclactive to on.
                $aclconfig = new \stdClass();
                $aclconfig->courseid = $courseid;
                $aclconfig->coursemoduleid = 0;
                $aclconfig->name = 'aclactive';
                $aclconfig->value = 1;
                $aclconfig->timecreated = time();
                $DB->insert_record('local_acl_mod_config', $aclconfig);
            } else {

                $aclconfig = $existingconfig[0]['aclactive'];
                $aclconfig->value = 1;
                $DB->update_record('local_acl_mod_config', $aclconfig);
            }

            // ...fix all wrong ids for modules.
            $this->map_acl_mod_avail_fields($courseid);
        }
    }

    /** delete all the useravail data for this user and synchronize his
     *  profile data.
     *
     * @param type $userid
     * @param type $courseid
     */
    public function cleanup_user_unenrolled($userid, $courseid) {
        global $DB;

        // ...cleanup useravails.
        $deleteparams = array('courseid' => $courseid, 'userid' => $userid);
        $DB->delete_records('local_acl_mod_useravail', $deleteparams);

        if ($user = $DB->get_record('user', array('id' => $userid))) {

            $this->synchronize_users_info_data(array($userid => $user));
        }

        // ... clean up users states.
        $deleteparams = array('courseid' => $courseid, 'userid' => $userid);
        $DB->delete_records('local_acl_mod_userstate', $deleteparams);

        // ... clean up users levels.
        if ($levels = $DB->get_records('local_acl_mod_level', array('courseid' => $courseid))) {

            list($inlevels, $param) = $DB->get_in_or_equal(array_keys($levels));
            $param[] = $userid;

            $DB->delete_records_select('local_acl_mod_userlevel', "levelid {$inlevels} AND userid = ?", $param);
        }
    }

    /** delete all the course related data from this plugin
     *  user related data is deleted during enrol (see cleanup_user_unenrolled)
     *
     * @global database $DB
     * @param int $courseid
     */
    public function cleanup_course_deleted($courseid) {
        global $DB;

        // ... clean up levels.
        $DB->delete_records('local_acl_mod_level', array('courseid' => $courseid));

        // ... clean up config.
        $DB->delete_records('local_acl_mod_config', array('courseid' => $courseid));
    }

    public static function render_tabs() {
        global $PAGE, $COURSE;

        if ($COURSE->id == SITEID) {
            return '';
        }

        $renderer = $PAGE->get_renderer('local_aclmodules');
        return $renderer->render_tabs();
    }

    /** get and return the HTML-code for this user for all sections of the course.
     *
     * @param record $course
     * @param object $sections
     * @param object $modinfo
     * @param array $htmlpersection, the html code for each section
     * @return array the html code for each section with added information relating acl and completion.
     */
    public static function render_section_statesinfo($course, $sections,
                                                     $modinfo, $htmlpersection) {
        global $PAGE, $USER;

        $aclmodules = self::instance();

        // ...activitystates.
        $activitystates = $aclmodules->get_users_statesreport($course, $modinfo, array($USER->id => $USER));

        // ...get medal image.
        $fs = get_file_storage();
        $syscontext = \context_system::instance();

        $medalurl = '';
        if ($files = $fs->get_area_files($syscontext->id, 'local_aclmodules', 'medal', 0, 'filename', false)) {
            $file = reset($files);
            $medalurl = \moodle_url::make_pluginfile_url($syscontext->id, 'local_aclmodules', 'medal', 0, '/', $file->get_filename(), false);
        }

        $renderer = $PAGE->get_renderer("local_aclmodules");
        $renderer->render_section_activitystates($activitystates, $sections, $modinfo, $htmlpersection, $medalurl);

        return $htmlpersection;
    }

    public static function render_moduleoptions($mod, $course, $output) {

        // ... no additional features, when mod is not available.
        if (!$mod->uservisible) {
            return $output;
        }

        $notavailable = get_string('list_root_and', 'availability');
        if (preg_match('/' . $notavailable . '/i', $output)) {
            $output = \html_writer::tag('div', get_string('aclmodactive', 'local_aclmodules'), array('class' => 'availabilityinfo'));
        }

        $rating = \local_aclmodules\local\modrating::render_rating($mod, $course);

        $messages = \local_aclmodules\local\modmessages::render_messages($mod, $course);

        $additionalinfo = $rating . $messages;

        if (!empty($additionalinfo)) {
            $additionalinfo .= \html_writer::empty_tag('div', array('class' => 'clearfix'));
            $output .= \html_writer::tag('div', $additionalinfo, array('class' => 'modreview-header'));
        }
        return $output;
    }

    protected $addinfo;
    protected $visiblesection = array();

    protected function callback_sectioninfo($matches) {

        $sectionnum = $matches[2];

        if (!isset($this->visiblesection[$sectionnum])) {
            return str_replace('role="region"', 'role="region" style="display:none"', $matches[0]);
        }

        if (!empty($this->addinfo[$sectionnum])) {
            return str_replace($matches[1], $matches[1] . $this->addinfo[$sectionnum], $matches[0]);
        }

        return $matches[0];
    }

    /** plugin modifies the content (HTML String) of multiple section page for all
     *  courses which are in grid format.
     *
     * @param String $content HTML content of the multiple section page
     * @param record $course the course
     * @return String the modified content.
     */
    public function render_multiple_section_page($content, $course) {

        $matches = array();

        if (preg_match('/<ul class="gridicons.*?">(.*?)<\/ul>/', $content, $matches)) {

            $modinfo = get_fast_modinfo($course->id);
            $sections = $modinfo->get_section_info_all();

            $htmlpersection = array();
            $htmlpersection = self::render_section_statesinfo($course, $sections, $modinfo, $htmlpersection);
            $htmlpersection = \local_aclmodules\local\modmessages::render_section_messagesinfo($course, $sections, $modinfo, $htmlpersection);

            $this->addinfo = $htmlpersection;
            $this->visiblesection = self::instance()->get_sections_available_for_user($course->id, false);

            $pattern = '/<li.*?role=.*?>.*?(<a.*?id="gridsection-(.*?)".*?<\/a>*.?)<\/li>/';
            $replace = preg_replace_callback($pattern, array($this, 'callback_sectioninfo'), $matches[1]);
            $content = str_replace($matches[1], $replace, $content);

        }
        return $content;
    }


    /**
     * Migrate the assignment of mod messages.
     *
     * @staticvar array $modnames
     * @param int $newmessageid
     * @param object $oldmessage
     * @return void
     */
    public static function migrate_message($newmessageid, $oldmessage) {
        global $DB;
        static $modnames;

        if (!isset($modnames)) {
            $modnames = $DB->get_fieldset_select('modules', 'name', '');
        }

        $matches = [];
        if (!preg_match('/(.*)?\/mod\/(.*)?\/view.php\?id=([0-9]*)$/', $oldmessage->contexturl, $matches)) {
            return;
        }

        $urlmodname = $matches[2];
        if (!in_array($urlmodname, $modnames)) {
            return;
        }

        $modid = $matches[3];
        $sql = "SELECT cm.id, m.name as modname
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                WHERE cm.id = ? ";

        if (!$module = $DB->get_record_sql($sql, [$modid])) {
            return;
        }

        if ($module->modname != $urlmodname) {
            return;
        }

        $rec = [
            'coursemoduleid' => $module->id,
            'messageid' => $newmessageid
        ];

        if ($exists = $DB->get_record('local_acl_mod_messages', $rec)) {
            return;
        }

        $DB->insert_record('local_acl_mod_messages', (object) $rec);
    }

}