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

namespace coursereport_modrating\local;

class stats {


    private $scales = array();

    /**
     * Generates a scale object that can be returned
     *
     * @global moodle_database $DB moodle database object
     * @param int $scaleid scale-type identifier
     * @return stdClass scale for ratings
     */
    protected function generate_rating_scale_object($scaleid) {
        global $DB;

        if (!array_key_exists('s'.$scaleid, $this->scales)) {
            $scale = new \stdClass;
            $scale->id = $scaleid;
            $scale->name = null;
            $scale->courseid = null;
            $scale->scaleitems = array();
            $scale->isnumeric = true;
            $scale->max = $scaleid;

            if ($scaleid < 0) {
                // It is a proper scale (not numeric).
                $scalerecord = $DB->get_record('scale', array('id' => abs($scaleid)));
                if ($scalerecord) {
                    // We need to generate an array with string keys starting at 1.
                    $scalearray = explode(',', $scalerecord->scale);
                    $c = count($scalearray);
                    for ($i = 0; $i < $c; $i++) {
                        // ...treat index as a string to allow sorting without changing the value.
                        $scale->scaleitems[(string)($i + 1)] = $scalearray[$i];
                    }
                    krsort($scale->scaleitems); // ...have the highest grade scale item appear first.
                    $scale->isnumeric = false;
                    $scale->name = $scalerecord->name;
                    $scale->courseid = $scalerecord->courseid;
                    $scale->max = count($scale->scaleitems);
                }
            } else {
                // ...generate an array of values for numeric scales.
                for ($i = 0; $i <= (int)$scaleid; $i++) {
                    $scale->scaleitems[(string)$i] = $i;
                }
            }

            $this->scales['s'.$scaleid] = $scale;
        }

        return $this->scales['s'.$scaleid];
    }

    /** get all the courserating counts grouped by course module and grouped by scaleid and rating */
    public function get_coursemodule_ratings($course) {
        global $DB;

        // Get the scale for the course.
        $sql = "SELECT r.* FROM {course_modules} cm
               JOIN {rating} r ON r.itemid = cm.id
               WHERE cm.course = ? AND r.ratingarea = ? AND r.component = ?";

        $params = array($course->id, 'coursemodule', 'local_aclmodules');

        if (!$data = $DB->get_records_sql($sql, $params)) {
            return array();
        }

        // Group Results.
        $counts = array();
        $scaleids = array();
        foreach ($data as $date) {

            if (!isset($counts[$date->itemid])) {
                $counts[$date->itemid] = array();
            }

            if (!isset($counts[$date->itemid][$date->scaleid])) {
                $counts[$date->itemid][$date->scaleid][$date->rating] = 0;
                $scaleids[$date->scaleid] = $date->scaleid;
            }

            $counts[$date->itemid][$date->scaleid][$date->rating]++;
        }

        $scales = array();
        foreach ($scaleids as $scaleid) {
            $scales[$scaleid] = $this->generate_rating_scale_object($scaleid);
        }

        $result = new \stdClass();
        $result->counts = $counts;
        $result->scales = $scales;

        return $result;
    }
}