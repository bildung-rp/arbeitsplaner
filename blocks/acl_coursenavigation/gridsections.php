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
 * Course Navigation main class
 *
 * @package    block_acl_coursenavigation
 * @copyright  Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/aclmodules/lib.php');

$courseid = required_param('courseid', PARAM_INT);
 $section     = optional_param('section', 1, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);

$course = course_get_format($course)->get_course();

$PAGE->set_pagelayout('popup');
$PAGE->set_pagetype('course-view-' . $course->format);

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

$PAGE->add_body_class('hidden-page');

$PAGE->set_url(new moodle_url('/blocks/acl_coursevigation/ajax.php', array('courseid' => $courseid)));

echo $OUTPUT->header();

$marker = -1;

echo html_writer::start_tag('div', array('class' => 'course-content', 'style' => 'display:none'));

// ...make sure that section 0 exists (this function will create one if it is missing).
course_create_sections_if_missing($course, 0);

// ...get information about course modules and existing module types
// format.php in course formats may rely on presence of these variables.
$modinfo = get_fast_modinfo($course);
$modnames = get_module_types_names();
$modnamesplural = get_module_types_names(true);
$modnamesused = $modinfo->get_used_module_names();
$mods = $modinfo->get_cms();
$sections = $modinfo->get_section_info_all();

// CAUTION, hacky fundamental variable defintion to follow!
// Note that because of the way course formats are constructed though
// inclusion we pass parameters around this way..

// Include the actual course format.
require($CFG->dirroot . '/course/format/' . $course->format . '/format.php');
// Content wrapper end.

echo html_writer::end_tag('div');

$args = array();
$args['courseid'] = $courseid;
$args['section'] = $section;
$PAGE->requires->yui_module('moodle-block_acl_coursenavigation-showsection',
        'M.block_acl_coursenavigation.section', array($args), null, true);

echo $OUTPUT->footer();