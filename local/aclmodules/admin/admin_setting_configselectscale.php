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
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** This class provides a checkable matrix for the setting of the aclmodules plugin
 * 
 */
class admin_setting_configselectscale extends admin_setting_configselect {

    /**
     * Store new setting, if a global scale is used (value < 0), put a record in
     * grade_items table.
     *
     * @param mixed $data string or array, must not be NULL
     * @return string empty string if ok, string error message otherwise
     */
    public function write_setting($value) {
        global $DB;

        $params = array('courseid' => 1, 'itemname' => 'Modulrating', 'itemtype' => 'coursemodule');

        // ... a global scale should be used.
        if ($value < 0) {

            if ($exists = $DB->get_record('grade_items', $params)) {

                if ($value != -$exists->scaleid) {

                    $DB->set_field('grade_items', 'scaleid', -$value, array('id' => $exists->id));
                    $DB->set_field('grade_items', 'timemodified', time(), array('id' => $exists->id));
                }
            } else {
                $item = new stdClass();
                $item->courseid = 1;
                $item->itemname = 'Modulrating';
                $item->itemtype = 'coursemodule';
                $item->gradetype = 2;
                $item->scaleid = -$value;
                $item->timecreated = time();
                $item->timemodified = time();
                $DB->insert_record('grade_items', $item);
            }
        } else { // ... a rating based on points should be used.
            $DB->delete_records('grade_items', $params);
        }

        // ... set this setting to 1 to avoid save error.
        return $this->config_write($this->name, $value) ? '' : get_string('errorsetting', 'admin');
    }

}