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
function xmldb_local_aclmodules_upgrade($oldversion) {
    global $CFG, $DB;

    // ... check if required user info field exists.
    require_once($CFG->dirroot . "/local/aclmodules/lib.php");
    local_aclmodules_install_or_upgrade();

    $dbman = $DB->get_manager();

    if ($oldversion < 2014061802) {

        // Define table local_acl_mod_userstate to be created.
        $table = new xmldb_table('local_acl_mod_userstate');

        // Adding fields to table local_acl_mod_userstate.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '11', null, null, null, null);

        // Adding keys to table local_acl_mod_userstate.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table local_acl_mod_userstate.
        $table->add_index('idx_courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));
        $table->add_index('idx_crsid_usrid', XMLDB_INDEX_NOTUNIQUE, array('courseid', 'userid'));

        // Conditionally launch create table for local_acl_mod_userstate.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Aclmodules savepoint reached.
        upgrade_plugin_savepoint(true, 2014061802, 'local', 'aclmodules');
    }

    if ($oldversion < 2014061807) {

        // Define table local_acl_mod_useravail to be created.
        $table = new xmldb_table('local_acl_mod_useravail');

        // Adding fields to table local_acl_mod_useravail.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coursemoduleid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_acl_mod_useravail.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table local_acl_mod_useravail.
        $table->add_index('idx_crsid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));
        $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $table->add_index('idx_crsid_usrid', XMLDB_INDEX_NOTUNIQUE, array('courseid', 'userid'));

        // Conditionally launch create table for local_acl_mod_useravail.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Now transform all the old userdata in profile field to the new table;
        // get the profile field data.

        $sql = "SELECT infodata.userid, infodata.data FROM {user_info_data} infodata
                JOIN {user_info_field} infofield ON infofield.id = infodata.fieldid
                WHERE infofield.shortname = ?";

        $params = array('localaclavailablemodules');

        if ($infodata = $DB->get_records_sql($sql, $params)) {

            foreach ($infodata as $userid => $infodate) {

                if (!empty($infodate->data)) {

                    $modids = explode('#', $infodate->data);

                    foreach ($modids as $modid) {

                        if (!empty($modid)) {

                            if ($coursemodule = $DB->get_record('course_modules', array('id' => $modid))) {

                                $useravail = new stdClass();
                                $useravail->courseid = $coursemodule->course;
                                $useravail->coursemoduleid = $coursemodule->id;
                                $useravail->userid = $userid;
                                $useravail->timecreated = time();
                                $DB->insert_record('local_acl_mod_useravail', $useravail);
                            }
                        }
                    }
                }
            }
        }
        // Aclmodules savepoint reached.
        upgrade_plugin_savepoint(true, 2014061807, 'local', 'aclmodules');
    }

    if ($oldversion < 2018081700) {

        // Define table local_acl_mods_messages to be created.
        $table = new xmldb_table('local_acl_mod_messages');

        // Adding fields to table local_acl_mods_messages.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('coursemoduleid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('messageid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_acl_mods_messages.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table local_acl_mods_messages.
        $table->add_index('idx_modid', XMLDB_INDEX_NOTUNIQUE, array('coursemoduleid'));
        $table->add_index('idx_messageid', XMLDB_INDEX_NOTUNIQUE, array('messageid'));
        $table->add_index('idx_modid_messageid', XMLDB_INDEX_NOTUNIQUE, array('coursemoduleid', 'messageid'));

        // Conditionally launch create table for local_acl_mods_messages.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Aclmodules savepoint reached.
        upgrade_plugin_savepoint(true, 2018081700, 'local', 'aclmodules');
    }


    return true;
}