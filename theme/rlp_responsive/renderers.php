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
 * Main code for renderer modification of grid format extension.
 *
 * @package   grid format extension.
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
include_once($CFG->dirroot . '/calendar/renderer.php');

class theme_rlp_responsive_core_calendar_renderer extends core_calendar_renderer {

    /**
     * Adds a pretent calendar block
     *
     * @param block_contents $bc
     * @param mixed $pos BLOCK_POS_RIGHT | BLOCK_POS_LEFT
     */
    public function add_pretend_calendar_block(block_contents $bc,
                                               $pos = BLOCK_POS_RIGHT) {
        global $PAGE;

        if ($PAGE->pagetype == 'calendar-event') {
            return false;
        } else {
            $this->page->blocks->add_fake_block($bc, $pos);
        }
    }

}

require_once ($CFG->dirroot . '/course/format/grid/renderer.php');

class theme_rlp_responsive_format_grid_renderer extends format_grid_renderer {

    public function print_multiple_section_page($course, $sections, $mods,
                                                $modnames, $modnamesused) {
        global $CFG, $COURSE;

        if ($COURSE->id == SITEID) {
            return parent::print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);
        }

        $pluginactive = file_exists($CFG->dirroot . '/local/aclmodules/lib.php');
        $pluginactive = ($pluginactive and \local_aclmodules\local\aclmodules::is_active($course));

        if ($pluginactive) {

            // ... get the current content.
            ob_start();
            parent::print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);
            $content = ob_get_contents();
            ob_end_clean();

            // ... modify the content.
            $aclmodules = \local_aclmodules\local\aclmodules::instance();
            echo $aclmodules->render_multiple_section_page($content, $course, $sections, $mods, $modnames, $modnamesused);

        } else {

            parent::print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused);
        }
    }

}