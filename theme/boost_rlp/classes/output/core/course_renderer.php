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
 * Overridden renderer for unintrusive hacks.
 *
 * @package    theme_clean
 * @copyright  2017 Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_boost_rlp\output\core;

require_once($CFG->dirroot.'/course/renderer.php');

class course_renderer extends \core_course_renderer {

    /**
     * Overriden to modify the availability Information, when local_aclmodles
     * controls the availability of the activity.
     *
     * @param cm_info $mod
     * @param array $displayoptions
     * @return atring the availability informations
     */
    public function course_section_cm_availability(\cm_info $mod,
                                                   $displayoptions = array()) {
        global $COURSE, $CFG;

        $output = parent::course_section_cm_availability($mod, $displayoptions);

        if ($COURSE->id == SITEID) {
            return $output;
        }

        $pluginactive = file_exists($CFG->dirroot . '/local/aclmodules/lib.php');
        $pluginactive = ($pluginactive and \local_aclmodules\local\aclmodules::is_active($COURSE));

        if ($pluginactive) {

            $output = \local_aclmodules\local\aclmodules::render_moduleoptions($mod, $COURSE, $output);
        }

        return $output;
    }
	
}
