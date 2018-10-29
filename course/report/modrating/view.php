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
 * Main code for local plugin coursereport_modrating
 *
 * @package   coursereport_modrating
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');

$courseid = required_param('courseid', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);
require_capability('coursereport/modrating:viewreport', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/report/modrating/view.php', array('courseid' => $course->id)));
$PAGE->set_title(get_string('modratingtitle', 'coursereport_modrating'));
$PAGE->set_heading(get_string('modratingheader', 'coursereport_modrating'));

$stats = new \coursereport_modrating\local\stats();
$statsdata = $stats->get_coursemodule_ratings($course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modratingheader', 'coursereport_modrating'), 2);

$renderer = $PAGE->get_renderer('coursereport_modrating');
echo $renderer->render_stats($course, $statsdata);

echo $OUTPUT->footer();