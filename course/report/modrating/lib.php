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
 * Main code for plugin coursereport_modrating
 *
 * @package   coursereport_modrating
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/aclmodules/lib.php');

/** extends the course administration menu to access modrating report for teachers
 *
 * @global record $PAGE
 * @param node $reportnav
 * @param record $course
 * @param record $coursecontext
 * @param return boolea true if node was added
 */
function modrating_report_extend_navigation($reportnav, $course, $coursecontext) {

    // ...if the plugin is not yet installed avoid Debugging-Error.
    if (!get_capability_info('coursereport/modrating:viewreport')) {
        return false;
    }

    $canseereviews = has_capability('coursereport/modrating:viewreport', $coursecontext);

    if ($canseereviews) {

        $aclon = \local_aclmodules\local\aclmodules::instance()->get_acl_mod_config_value($course, 0, 'aclon');

        if ($aclon) {
            $reporturl = new moodle_url('/course/report/modrating/view.php', array('courseid' => $course->id));
            $reportnav->add(get_string('modratingsreport', 'coursereport_modrating'),
                    $reporturl, navigation_node::TYPE_CUSTOM, null, null, new pix_icon('i/report', ''));
            return true;
        }
    }
    return false;
}