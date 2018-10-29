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
 * Editing Levels (= groups of Users, which have the same learning objectives.
 *
 * @package   local_aclmodules
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/aclmodules/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$editing = optional_param('editing', -1, PARAM_INT);
$msg = optional_param('msg', '', PARAM_TEXT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);
require_capability('local/aclmodules:edit', $context);

$PAGE->set_context($context);

$pageurl = new moodle_url('/local/aclmodules/pages/leveledit.php', array('courseid' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('levelsedittitle', 'local_aclmodules'));
$PAGE->set_heading(get_string('levelseditheader', 'local_aclmodules'));

// ... we do intentionally do not use a standard moodleform
// because formelements are generated dynamically, when editing user adds new levels
// (i. e. columns to the table).
if ($form = data_submitted()) {

    if (isset($form->cancel)) {
        redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    }

    if (isset($form->save)) {

        require_sesskey();

        $aclmodules = \local_aclmodules\local\aclmodules::instance();

        // ...security checks are made in method!
        if ($aclmodules->save_levels($form)) {
            $msg = 'levelssaved';
        }
    }
}

// ... user is editing?
$PAGE->set_other_editing_capability('local/aclmodules:edit');

$editingon = false;

if ($PAGE->user_allowed_editing()) {

    if ($editing != -1) {

        $USER->editing = $editing;
    }
    $editingon = $PAGE->user_is_editing();
}

// ... getting data.
$aclmodules = \local_aclmodules\local\aclmodules::instance();
$levels = $aclmodules->get_levels($courseid);
$participants = $aclmodules->get_all_gradable_users($context);
$userlevels = $aclmodules->get_users_levels($courseid);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('levelseditheader', 'local_aclmodules'));

if ($PAGE->user_allowed_editing()) {

    $buttonstr = ($editingon) ? get_string('turneditingoff') : get_string('turneditingon');

    $pageurl->param('editing', !$editingon);
    $button = $OUTPUT->single_button($pageurl, $buttonstr);

    echo html_writer::tag('div', $button, array('class' => 'navbutton'));
}

if (!empty($msg)) {
    echo $OUTPUT->notification(get_string($msg, 'local_aclmodules'), 'notifysuccess');
}

// ...output form.
$renderer = $PAGE->get_renderer('local_aclmodules');
$actionurl = new moodle_url('/local/aclmodules/pages/leveledit.php');
echo $renderer->render_leveledit_form($actionurl, $course, $editingon, $levels, $participants, $userlevels);

echo $OUTPUT->footer();