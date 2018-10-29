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
 *
 * @package   local_aclmodules
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aclmodules/lib.php');

/**
 * Restore plugin class that provides the necessary information
 * needed to restore one grid format course.
 */
class restore_local_aclmodules_plugin extends restore_local_plugin {

    protected $profilefieldid;

    protected function define_course_plugin_structure() {

        $paths = array();

        $elename = 'level';
        $elepath = $this->get_pathfor('/level');
        $paths[] = new restore_path_element($elename, $elepath);

        $userinfo = $this->get_setting_value('users');
        if ($userinfo) {
            $elename = 'userlevel';
            $elepath = $this->get_pathfor('/level/user');
            $paths[] = new restore_path_element($elename, $elepath);
        }

        return $paths;
    }

    public function process_level($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->courseid = $this->task->get_courseid();
        $data->timecreated = time();
        $newid = $DB->insert_record('local_acl_mod_level', $data);
        $this->set_mapping('level', $oldid, $newid);
    }

    public function process_userlevel($data) {
        global $DB;

        $data = (object) $data;
        $data->courseid = $this->task->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->levelid = $this->get_new_parentid('level');
        $data->timecreated = time();

        $DB->insert_record('local_acl_mod_userlevel', $data);
    }

    /* module availability is controlled by:
     * 1. entry in course_modules_avail_fields like (coursemoduleid, customfieldid, operator, value) values (3, 6, contains, #3#)
     * 2. entry in users profile field like ('#3#10#34#12#')
     */

    /* To restore the data completely:
     * 1. entry in the coursemodule must change to this course module (done after restoring the module).
     * 2. new module id must be added, when module was available in backup (local_acl_mod_userdata).
     */

    protected function define_module_plugin_structure() {

        $paths = array();

        $elename = 'config'; // This defines the postfix of 'process_*' below.
        $elepath = $this->get_pathfor('/config/data');
        $paths[] = new restore_path_element($elename, $elepath);

        $userinfo = $this->get_setting_value('users');
        if ($userinfo) {
            $elename = 'userstate'; // This defines the postfix of 'process_*' below.
            $elepath = $this->get_pathfor('/userstates/userstate');
            $paths[] = new restore_path_element($elename, $elepath);

            $elename = 'useravail'; // This defines the postfix of 'process_*' below.
            $elepath = $this->get_pathfor('/useravails/useravail');
            $paths[] = new restore_path_element($elename, $elepath);
        }

        return $paths; // And we return the interesting paths.
    }

    public function process_config($data) {
        global $DB;

        $data = (object) $data;
        $data->courseid = $this->task->get_courseid();
        $data->coursemoduleid = $this->task->get_moduleid();
        $data->timecreated = time();

        $DB->insert_record('local_acl_mod_config', $data);
    }

    public function process_userstate($data) {
        global $DB;

        $data = (object) $data;

        $data->courseid = $this->task->get_courseid();
        $data->cmid = $this->task->get_moduleid();
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = time();

        $DB->insert_record('local_acl_mod_userstate', $data);
    }

    public function process_useravail($data) {
        global $DB;

        $data = (object) $data;

        $data->courseid = $this->task->get_courseid();
        $data->coursemoduleid = $this->task->get_moduleid();
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = time();

        $DB->insert_record('local_acl_mod_useravail', $data);
    }

    /** get the profile field, which holds available modules for the user
     *  if no profile field exists plugin is not correctly installed.
     * 
     * @global database $DB
     * @return type
     */
    private function get_profile_field_id() {
        global $DB;

        if (isset($this->profilefieldid)) {
            return $this->profilefieldid;
        }

        $this->profilefieldid = $DB->get_record('user_info_field', array('shortname' => ACL_MOD_PROFILEFIELD_SHORTNAME));
        return $this->profilefieldid;
    }

}