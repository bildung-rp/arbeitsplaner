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
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(__FILE__) . '../../../../config.php');
require_once($CFG->dirroot . '/local/aclmodules/pages/stateedit_form.php');
require_once($CFG->dirroot . '/local/aclmodules/lib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$cm = get_coursemodule_from_id('', $cmid, 0, true, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_pagelayout('popup');

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);
require_capability('local/aclmodules:edit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aclmodules/pages/stateedit.php', array('id' => $cmid)));
$PAGE->set_title(get_string('stateedit', 'local_aclmodules'));
$PAGE->set_heading(get_string('stateeditheading', 'local_aclmodules'));

$aclmodules = \local_aclmodules\local\aclmodules::instance();

// ...check, wheter module is commentable.
$modcomment = $aclmodules->get_acl_mod_config_value($course, $cm, 'modulestocomment');

$user = $DB->get_record('user', array('id' => $userid));

$reportsdata = $aclmodules->get_user_state_report($course, $cm, $user);

$modinfo = get_fast_modinfo($course->id);
$cm = $modinfo->get_cm($cm->id);

$formparams = array(
    'course' => $course,
    'cm' => $cm,
    'user' => $user,
    'currentactivitystate' => $reportsdata['state'],
    'modcomment' => $modcomment
);

$stateediteditform = new stateedit_form(null, $formparams);

if ($stateediteditform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', array("id" => $course->id)));
}

if ($data = $stateediteditform->get_data()) {

    if ($result = $aclmodules->save_users_userstate($data, $user, $cm)) {

        $redirect = new moodle_url('/course/view.php' , array('id' => $course->id));
        redirect($redirect, $result['message']);
    } else {
        $msg = get_string('errorsavestateedit', 'local_aclmodules');
    }
}

echo $OUTPUT->header();

if (!empty($msg)) {
    echo $OUTPUT->notification(get_string($msg, 'local_aclmodules'));
}

$stateediteditform->display();

echo $OUTPUT->footer();