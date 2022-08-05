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
/* this local plugin controls the visibility of course modules to individual users
 * by the folowing mechanism:
 *
 * prerequisites: enableavailability must be activated!
 *
 * 1- a hidden profile field local_acl_availablemodules is created to hold a '#'-separated list of ids of coursemodules, which
 *    are visible for the users.
 *    Example: User1 have the value #1#2# in this profilefield, so the user can see coursemodules 1 and 2
 *
 * 2- A teacher can control visiblilty by assigning modules to the user. By doing this an entry is made in
 *    modules with id 1 availability:
 *    user profilefield must contain #1#
 *
 * this local plugin uses database tables as follows:
 *
 *  - mdl_local_acl_mod_config:  acl config data for each course module instance
 *                               (i. e. wheter module is rateable, commentable ...)
 *  - mdl_local_acl_mod_level    : level used where users are assigned to (easy edit in the planner)
 *  - mdl_local_acl_mod_userlevel: relationship user to level.
 *  - mdl_local_acl_mod_useravail: holds the individual availability for each user
 *                                 (an entry (coursemoduleid, userid) means modul is available
 *                                 note that writing in this table is to be synchronized to users
 *                                 profilefield ('localaclavailablemodules')
 *  - mdl_local_acl_mod_userstate: additional state for a module after it is completed
 *                                 (means that the teacher has approved the completion)
 */

defined('MOODLE_INTERNAL') || die;

define('ACL_MOD_PROFILEFIELD_SHORTNAME', 'localaclavailablemodules');

/** extend the settings navigation
 *
 * @global record $COURSE
 * @param settings_navigation $navigation
 * @return boolean true if succeded
 */
function local_aclmodules_extend_settings_navigation(settings_navigation $navigation) {
    global $COURSE;

    if (!get_capability_info('local/aclmodules:edit')) {
        return false;
    }

    if (has_capability('local/aclmodules:viewplanner', context_course::instance($COURSE->id))) {

        $node = $navigation->get('courseadmin');

        if ($node) {

            if (has_capability('local/aclmodules:edit', context_course::instance($COURSE->id))) {

                $aclon = \local_aclmodules\local\aclmodules::instance()->get_acl_mod_config_value($COURSE, 0, 'aclon');
                $actionparams = array('courseid' => $COURSE->id, 'action' => 'switchacl', 'sesskey' => sesskey());

                if ($aclon) {
                    $editurl = new moodle_url('/local/aclmodules/planner/edit.php', array('courseid' => $COURSE->id));
                    $node->add(get_string('planner', 'local_aclmodules'), $editurl, navigation_node::TYPE_CUSTOM, null, 'local_aclmodules_edit');

                    $levelediturl = new moodle_url('/local/aclmodules/pages/leveledit.php', array('courseid' => $COURSE->id));
                    $node->add(get_string('levels', 'local_aclmodules'), $levelediturl, navigation_node::TYPE_CUSTOM, null, 'local_aclmodules_leveledit');

                    $actionparams['value'] = 0;
                    $courssettingsurl = new moodle_url('/local/aclmodules/action.php', $actionparams);
                    $node->add(get_string('setacloff', 'local_aclmodules'), $courssettingsurl, navigation_node::TYPE_CUSTOM, null, 'local_aclmodules_action');
                } else {

                    $actionparams['value'] = 1;
                    $courssettingsurl = new moodle_url('/local/aclmodules/action.php', $actionparams);
                    $node->add(get_string('setaclon', 'local_aclmodules'), $courssettingsurl, navigation_node::TYPE_CUSTOM, null, 'local_aclmodules_action');
                }
            } else {

                $viewurl = new moodle_url('/local/aclmodules/planner/view.php', array('courseid' => $COURSE->id));
                $node->add(get_string('planner', 'local_aclmodules'), $viewurl);
            }
        }
        return true;
    }
    return false;
}

/** send files (medal image) for this plugin */
function local_aclmodules_pluginfile($course, $cm, $context, $filearea, $args,
                                     $forcedownload, $args2) {

    $fs = get_file_storage();
    if (!$file = $fs->get_file($context->id, 'local_aclmodules', $filearea, 0, '/', $args[1]) or $file->is_directory()) {
        send_file_not_found();
    }
    ob_end_clean();
    send_stored_file($file);
}

/**
 * Eventhandling for the mod_created event, activate the
 * acl control by setting moduls availabilty field.
 *
 * @param record $eventdata, coming from the event.
 */
function local_aclmodules_course_module_created($event) {
    global $CFG, $DB;

    $eventdata = $event->get_data();

    if (!$course = $DB->get_record('course', array('id' => $eventdata['courseid']))) {
        return false;
    }

    $pluginactive = file_exists($CFG->dirroot . '/local/aclmodules/lib.php');
    $pluginactive = ($pluginactive and \local_aclmodules\local\aclmodules::is_active($course));

    if (!$pluginactive) {
        return false;
    }

    $aclmodules = \local_aclmodules\local\aclmodules::instance();
    $aclmodules->update_acl_active($eventdata['courseid']);
}

/**
 * Eventhandling for the mod_updated event, activate the
 * acl control by setting moduls availabilty field,
 * even if user has edit (i. e. deleted), when acl is on.
 */
function local_aclmodules_course_module_updated($event) {
    return local_aclmodules_course_module_created($event);
}

function local_aclmodules_course_restored($event) {
    $eventdata = $event->get_data();
    $aclmodules = \local_aclmodules\local\aclmodules::instance();
    $aclmodules->launch_after_restore($eventdata['objectid']);
}

function local_aclmodules_course_deleted($event) {
    $eventdata = $event->get_data();
    $aclmodules = \local_aclmodules\local\aclmodules::instance();
    $aclmodules->cleanup_course_deleted($eventdata['objectid']);
}

function local_aclmodules_completion_changed($event) {
    $aclmodules = \local_aclmodules\local\aclmodules::instance();
    $aclmodules->users_completion_changed($event);
}

/** clean up userrelated data, when user unenrolls a course.
 *
 * @param object $event
 */
function local_aclmodules_user_enrolment_deleted($event) {

    $eventdata = $event->get_data();

    $ue = $eventdata['other']['userenrolment'];

    if ($ue['lastenrol']) {
        $aclmodules = \local_aclmodules\local\aclmodules::instance();
        $aclmodules->cleanup_user_unenrolled($eventdata['relateduserid'], $eventdata['courseid']);
    }
}

/** check whether the required user info field exists and created id, if necessary. */
function local_aclmodules_install_or_upgrade() {
    global $DB;

    // ...check whether the appropriate user profile field exists to control availablity (see above).
    if (!$profilefieldexists = $DB->get_record('user_info_field', array('shortname' => ACL_MOD_PROFILEFIELD_SHORTNAME))) {

        // ... get max sortorder from existing fields.
        $sortorder = 1;
        if ($sortordermax = $DB->get_field_sql(
                "SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = '1'")) {
            $sortorder = $sortordermax + 1;
        }

        // ...prepare info field an insert it.
        $infofield = new stdClass();
        $infofield->shortname = ACL_MOD_PROFILEFIELD_SHORTNAME;
        $infofield->name = 'Available Modules';
        $infofield->datatype = 'text';
        $infofield->description = '';
        $infofield->descriptionformat = 1;
        $infofield->categoryid = 1;
        $infofield->sortorder = $sortorder;
        $infofield->required = 0;
        $infofield->locked = 1;
        $infofield->visible = 0;
        $infofield->forceunique = 0;
        $infofield->signup = 0;
        $infofield->defaultdata = '';
        $infofield->defaultdataformat = 0;
        $infofield->param1 = '30';
        $infofield->param2 = '100000';
        $infofield->param3 = '0';
        $infofield->param4 = '';
        $infofield->param5 = '';

        $DB->insert_record('user_info_field', $infofield);
    }
}

/** clean up all the information which is done by the plugin when uninstalled. */
function local_aclmodules_uninstall() {
    global $DB;

    if ($profilefieldexists = $DB->get_record('user_info_field', array('shortname' => ACL_MOD_PROFILEFIELD_SHORTNAME))) {

        // Delete users info data.
        $DB->delete_records('user_info_data', array('fieldid' => $profilefieldexists->id));

        // Delete all availdata from courses.
        $sql = "DELETE FROM {course_modules_avail_fields}
                WHERE customfieldid = ?
                AND operator = 'contains' AND value = CONCAT('#', coursemoduleid, '#')";

        $DB->execute($sql, array(ACL_MOD_PROFILEFIELD_SHORTNAME));

        // Delete profile field.
        $DB->delete_records('user_info_field', array('id' => $profilefieldexists->id));
    }
}

// Rating: core plugin rating is used for rating the
// coursemodules, section of needed callbacks started here
// ++++++++++++++++++++++   callbacks for ratings ++++++++++++++++++++++++++++++.

/** Return rating related permissions
 *
 * @param type $contextid
 * @param type $component
 * @param type $ratingarea
 * @return type
 */
function local_aclmodules_rating_permissions($contextid, $component, $ratingarea) {

    $context = context::instance_by_id($contextid, MUST_EXIST);

    // MUST override the permissions. Default are set to false by core plugin!
    $permissions = array();

    // ...can view the aggregate of ratings of their own items.
    $permissions['view'] = has_capability('local/aclmodules:rateview', $context);

    // ...can view the aggregate of ratings of other people's items.
    $permissions['viewany'] = has_capability('local/aclmodules:rateviewany', $context);

    // ...can view individual ratings.
    $permissions['viewall'] = has_capability('local/aclmodules:rateviewall', $context);

    // ...an submit ratings.
    $permissions['rate'] = has_capability('local/aclmodules:rate', $context);

    return $permissions;
}

/** Validates a submitted rating
 *
 * @param type $params
 * @return boolean
 */
function local_aclmodules_rating_validate($params) {
    return true;
}
