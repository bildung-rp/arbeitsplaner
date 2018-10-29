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
 * Ajax handler for state edit page.
 *
 * @package   local_aclmodules
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/aclmodules/pages/stateedit_form.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$action = optional_param('action', 'display', PARAM_ALPHA);

$cm = get_coursemodule_from_id('', $cmid, 0, true, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course);

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);

// ...course context is retrieved by coursemodule and ensured to exist.
require_capability('local/aclmodules:edit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aclmodules/pages/stateedit_ajax.php', array('id' => $cmid)));

$user = $DB->get_record('user', array('id' => $userid));

switch ($action) {

    case 'display' :

        $aclmodules = \local_aclmodules\local\aclmodules::instance();

        // ...check, wheter module is commentable.
        $modcomment = $aclmodules->get_acl_mod_config_value($course, $cm, 'modulestocomment');

        $reportsdata = $aclmodules->get_user_state_report($course, $cm, $user);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($cm->id);

        $stateediteditform = new stateedit_form(null,
                        array(
                            'course' => $course,
                            'cm' => $cm,
                            'user' => $user,
                            'currentactivitystate' => $reportsdata['state'],
                            'modcomment' => $modcomment
                ));
        // ...get the data.
        $stateediteditform->display();
        die;
        break;

    case 'update' :

        require_sesskey();

        $aclmodules = \local_aclmodules\local\aclmodules::instance();
        $data = new stdClass();
        $data->userid = $userid;
        $data->cmid = $cmid;
        $data->courseid = $course->id;
        $data->useractivitystate = optional_param('useractivitystate', 0, PARAM_INT);
        $data->useractivitymessage = optional_param('useractivitymessage', '', PARAM_TEXT);

        if ($result = $aclmodules->save_users_userstate($data, $user, $cm)) {
            $result['reportsdata'] = $aclmodules->get_user_state_report($course, $cm, $user);
            echo json_encode($result);
        }
        die;
        break;

    default:
        print_error('unknown action');
}

