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
 * Main code for local plugin coursereport_modreview
 *
 * @package   coursereport_modreview
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');

$courseid = required_param('courseid', PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$msg = optional_param('msg', '', PARAM_TEXT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);
require_capability('coursereport/modreview:viewreport', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/report/modreview/view.php', array('courseid' => $course->id)));
$PAGE->set_title(get_string('modreviewtitle', 'coursereport_modreview'));
$PAGE->set_heading(get_string('modreviewheader', 'coursereport_modreview'));

$modreview = \local_aclmodules\local\report::instance();

// ...start page.
echo $OUTPUT->header();

// ... print message.
if (!empty($msg)) {
    echo $OUTPUT->notification(get_string($msg, 'coursereport_modreview'), 'notifysuccess');
}

$baseurl = new moodle_url('/course/report/modreview/view.php', array('courseid' => $course->id, 'perpage' => $perpage));

// Define a table showing a list of participants.
$tablecolumns = array();
$tableheaders = array();
$tableheaders[] = null;

$tablecolumns[] = 'fullname';

$table = new flexible_table('user-index-participants-' . $course->id);
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($baseurl->out());
$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'modreview-participants');
$table->set_attribute('class', 'generaltable generalbox');
$table->set_control_variables(array(
    TABLE_VAR_IFIRST => 'sifirst',
    TABLE_VAR_ILAST => 'silast',
    TABLE_VAR_PAGE => 'spage'
));
$table->setup();

$renderer = $PAGE->get_renderer('coursereport_modreview');

// ... teachers may see whole report.
if (has_capability('coursereport/modreview:viewreportall', $context)) {

    list($condition, $params) = $table->get_sql_where();
    $condition = (!empty($condition)) ? " AND " . $condition : "";
    $currentgroup = groups_get_course_group($course, true);
    $matchcount = $modreview->count_all_gradable_users($context, $currentgroup, '', $condition, $params);

    $groupsmenu = groups_print_course_menu($course, $baseurl->out(), true);
    $controls = html_writer::tag('div', $groupsmenu, array('class' => 'modreview-groups'));
    $controls .= html_writer::tag('div', '', array('class' => 'clearer'));
    echo html_writer::tag('div', $controls);

    $table->initialbars(true);
    $table->pagesize($perpage, $matchcount);

    $participants = array();
    if ($matchcount > 0) {
        $participants = $modreview->get_all_gradable_userdata($context, 'lastname',
                $currentgroup, '', $condition, $params, $table->get_page_start(), $table->get_page_size());
    }

    foreach ($participants as $participant) {

        $data = array();

        $link = html_writer::link('#', fullname($participant), array('class' => 'modreview-img-collapsed'));
        $link .= html_writer::tag('span', '', array('class' => 'status'));

        $output = html_writer::tag('div', $link, array('id' => 'userreport-header-' . $participant->id));

        $data[] = $output;
        $table->add_data($data);
    }

    $table->print_html();
    $args = array();
    $args['courseid'] = $course->id;

    $PAGE->requires->yui_module('moodle-coursereport_modreview-reportuser',
            'M.coursereport_modreview.reportuser', array($args), null, true);

} else {

    $participant = $USER;
    // User is viewing his own report.
    $reportdate = $modreview->get_users_reportsdata($course, $participant->id);

    $data = array();
    $data[] = html_writer::tag('div', $renderer->render_users_modreport($reportdate), array('class' => 'content'));
    $table->add_data($data);

    echo html_writer::tag('h2', fullname($participant), array('class' => 'modreview-report-user'));
    $table->print_html();
}

echo $OUTPUT->footer();