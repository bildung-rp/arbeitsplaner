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
 * Main code for local plugin aclmodules, view tasks.
 *
 * @package   local_aclmodules
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

use local_aclmodules\local\aclmodules as local_aclmodules;

$courseid = required_param('courseid', PARAM_INT);
$msg = optional_param('msg', '', PARAM_TEXT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);
require_capability('local/aclmodules:viewplanner', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aclmodules/planner/view.php', array('courseid' => $course->id)));
$PAGE->set_title(get_string('planneredittitle', 'local_aclmodules'));
$PAGE->set_heading(get_string('plannereditheader', 'local_aclmodules'));

$aclmodules = local_aclmodules::instance();

// ... get all users for the course.
$participant = $USER;

// ...get all the course modules which have ACL control switched to on.
$modavailfields = $aclmodules->get_acl_mod_avail_fields($courseid);

$reportsdata = $aclmodules->get_course_state_report($course, $context);

if ($reportsdata['error'] == 1) {
    $moduserstatus = array();
    $sectionstates = array();
    $counts = array();
} else {
    $moduserstatus = $reportsdata['data'];
    $sectionstates = $reportsdata['sectionstates'];
    $counts = $reportsdata['counts'];
}

// ...start output.
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('local_aclmodules');

// ... start building table.
$table = new html_table();
$table->id = 'acl-view-table';
$table->border = '1';

$table->head = array('',
    get_string('currentactivitystate', 'local_aclmodules'),
    get_string('modmessages', 'local_aclmodules'),
    get_string('rating', 'local_aclmodules'));

// Get info about the modules.
$modinfo = get_fast_modinfo($course);
// ...and sections.
$sectioninfo = $modinfo->get_section_info_all();

// Get modulids per section.
$sectionmoduleids = array();

$messagesinfo = array();
$messagesinfo = \local_aclmodules\local\modmessages::render_section_messagesinfo($course, $sectioninfo, $modinfo, $messagesinfo);

$availablesections = $aclmodules->get_sections_available_for_user($course->id, true);

foreach ($sectioninfo as $thissection) {

    // ... section is empty.
    if (empty($modinfo->sections[$thissection->section])) {
        continue;
    }

    if (!in_array($thissection->section, $availablesections)) {
        continue;
    }

    user_preference_allow_ajax_update('aclsectioncollapsed-' . $thissection->id, PARAM_INT);
    $sectioncollapsed = !empty($USER->preference['aclsectioncollapsed-' . $thissection->id]);

    // ... not empty, get configurable modules from this section.
    $configurablemods = $aclmodules->get_configurable_modules($modinfo->sections[$thissection->section], $modinfo);

    if (count($configurablemods) > 0) {

        $row = new html_table_row();
        $row->id = "sectionrow_{$thissection->id}";

        // ... first row, first cell.
        $cell = new html_table_cell();
        $cell->text = get_section_name($course, $thissection);
        $cell->attributes['class'] = "acl-table-sectionname";
        if ($sectioncollapsed) {
            $cell->attributes['class'] .= " collapsed";
        }
        $cell->id = "acl-table-section_" . $thissection->id;
        $row->cells[] = $cell;

        // Statusdiv for Section.
        $class = "sstatusdiv";
        if (isset($sectionstates[$thissection->id][$participant->id])) {
            $class .= " " . local_aclmodules::$moduserstates[$sectionstates[$thissection->id][$participant->id]]['class'];
        }
        $statusdiv = html_writer::tag('div', '', array("class" => $class, 'id' => "sstatusdiv_{$participant->id}_{$thissection->id}"));

        $row->cells[] = $renderer->get_cell($statusdiv);

        $row->cells[] = (!empty($messagesinfo[$thissection->section])) ? $messagesinfo[$thissection->section] : "";
        $row->cells[] = "";
        $table->data[] = $row;

        $sectionmoduleids[$thissection->id] = array();

        foreach ($configurablemods as $mod) {

            $sectionmoduleids[$thissection->id][] = $mod->id;

            if (!$mod->uservisible) {
                continue;
            }

            // ... add all users checkboxes.
            $row = new html_table_row();
            $row->id = "modulerow_{$thissection->id}_{$mod->id}";
            if ($sectioncollapsed) {
                $row->attributes['class'] .= " collapsed";
            }

            // ... first column turn on/off acl.
            $class = (isset($modavailfields[$mod->id])) ? 'aclmodon' : 'aclmodoff';

            // ... name of activity.
            $instancename = $mod->get_formatted_name();
            $activityclass = 'instancename';
            if (!$mod->visible) {
                $activityclass .= ' dimmed_text';
            }

            $activitylink = html_writer::empty_tag('img', array('src' => $mod->get_icon_url(),
                        'class' => 'iconsmall activityicon', 'alt' => ' ', 'role' => 'presentation')) . "  " .
                    html_writer::tag('span', $instancename, array('class' => $activityclass));

            if ($mod->url) {
                $activitylink = html_writer::link($mod->url, $activitylink);
            }

            $row->cells[] = html_writer::tag('div', $activitylink, array('id' => 'aclmodactivestatus_' . $mod->id, 'class' => $class));

            // Statusdiv for module.
            $class = "statusdiv";
            $desc = "";

            $attributes = array('id' => "moduseravail_{$participant->id}_{$mod->id}");

            if (isset($moduserstatus[$mod->id][$participant->id])) {

                $class .= " " . local_aclmodules::$moduserstates[$moduserstatus[$mod->id][$participant->id]]['class'];
                $desc = get_string(local_aclmodules::$moduserstates[$moduserstatus[$mod->id][$participant->id]]['desc'], 'local_aclmodules');

                if ($moduserstatus[$mod->id][$participant->id] > 70) {
                    $attributes['disabled'] = "disabled";
                }
            }

            $statusdiv = html_writer::tag('div', '', array(
                        'class' => $class,
                        'id' => "statusdiv_{$participant->id}_{$mod->id}",
                        'title' => $desc
                    ));

            $row->cells[] = $renderer->get_cell($statusdiv);
            $row->cells[] = \local_aclmodules\local\modmessages::render_messages($mod, $course);
            $row->cells[] = \local_aclmodules\local\modrating::render_rating($mod, $course);

            $table->data[] = $row;
        }
    }
}

$args = array();
$PAGE->requires->strings_for_js(array('stateedit', 'hidecfgcolumns', 'showcfgcolumns'), 'local_aclmodules');
$PAGE->requires->yui_module('moodle-local_aclmodules-sectioncollapse', 'M.local_aclmodules.sectioncollapse', array($args), null, true);

// ...output table.
echo html_writer::table($table);

echo $renderer->render_legend();

echo $OUTPUT->footer();