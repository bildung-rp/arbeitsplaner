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
 * @package   local_aclmodules
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/** database structure of plugin:
 * 
 *  course      : local_acl_mod_level (id, courseid, name, description, timecreated);
 *  course      : local_acl_mod_userlevel(id, userid, levelid, timecreated)
 * 
 *  coursemodule: local_acl_mod_config(id, courseid, coursemoduleid, name, value, timecreated);
 *  coursemodule: local_acl_mod_userdata(id, courseid, userid, cmid, value, timecreated);
 * 
 *  further data used by the module:
 *  - profile data in users profile field localaclavailablemodules (automatically stored, when user is backuped).
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information to backup grid course format
 */
class backup_local_aclmodules_plugin extends backup_local_plugin {

    /**
     * Returns the format information to attach to module element
     */
    protected function define_module_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element();

        // ...wrapper for this plugins information for coursemodules.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        // ...local_acl_mod_config(id, courseid, coursemoduleid, name, value, timecreated).
        $configwrapper = new backup_nested_element('config');
        $pluginwrapper->add_child($configwrapper);

        $configitems = new backup_nested_element('data', array('id'), array('name', 'value'));
        $configwrapper->add_child($configitems);

        $configitems->set_source_table('local_acl_mod_config', array('coursemoduleid' => backup::VAR_MODID));
        // End of tag structure.

        $userinfo = $this->get_setting_value('users');

        // ...local_acl_mod_userdata(id, courseid, userid, cmid, value, timecreated).
        if ($userinfo) {

            $userwrapper = new backup_nested_element('userstates');
            $pluginwrapper->add_child($userwrapper);

            $user = new backup_nested_element('userstate', array(), array('userid', 'value'));
            $userwrapper->add_child($user);

            // ... backup activity states.
            $user->set_source_table('local_acl_mod_userstate', array(
                'courseid' => backup::VAR_COURSEID,
                'cmid' => backup::VAR_MODID)
            );

            // ... save userid, when modid is in profiledata.
            $availwrapper = new backup_nested_element('useravails');
            $pluginwrapper->add_child($availwrapper);

            $useravail = new backup_nested_element('useravail', array(), array('userid'));
            $availwrapper->add_child($useravail);

            // ... backup availability.
            $useravail->set_source_table('local_acl_mod_useravail', array(
                'courseid' => backup::VAR_COURSEID,
                'coursemoduleid' => backup::VAR_MODID)
            );
        }

        return $plugin;
    }

    /**
     * Returns the format information to attach to course element
     */
    protected function define_course_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element();

        $aclmodlevels = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($aclmodlevels);

        $level = new backup_nested_element('level', array(), array('id', 'name', 'description'));
        $aclmodlevels->add_child($level);

        // ...set source to populate the data.
        $level->set_source_table('local_acl_mod_level', array('courseid' => backup::VAR_PARENTID));

        $userinfo = $this->get_setting_value('users');

        if ($userinfo) {

            $user = new backup_nested_element('user', array(), array('userid'));
            $level->add_child($user);
            $user->set_source_table('local_acl_mod_userlevel', array('levelid' => backup::VAR_PARENTID));
        }

        return $plugin;
    }

}