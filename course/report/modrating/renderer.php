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
 * renderer for coursereport_modrating
 *
 * @package   coursereport_modrating
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/lib/tablelib.php');

class coursereport_modrating_renderer extends plugin_renderer_base {

    public function render_stats($course, $statsdata) {
        global $OUTPUT;

        $o = '';
        
        if (!isset($statsdata->scales)) {
            return $OUTPUT->notification(get_string('nostatsdata', 'coursereport_modrating'));
        }

        if (count($statsdata->scales) > 1) {
            $o .= $OUTPUT->notification(get_string('multiplescalesused', 'coursereport_modrating'));
        }

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $context = context_course::instance($course->id);

        $infoicon = $OUTPUT->pix_icon('i/info', get_string('details', 'coursereport_modrating'));

        foreach ($statsdata->scales as $scaleid => $scale) {

            $table = new html_table();
            $table->head = array();
            $table->head[] = '';
            $table->head[] = '';
            $table->attributes = array('class' => 'generaltable modrating-stats-table');

            for ($i = 1; $i <= $scale->max; $i++) {

                $table->head[] = ($scale->isnumeric) ? $i : $scale->scaleitems[$i];
            }

            foreach ($sections as $section) {

                $srow = new html_table_row();
                $cell = new html_table_cell();
                $cell->colspan = $scale->max + 2;
                $cell->text = get_section_name($course, $section);
                $srow->cells[] = $cell;

                if (empty($modinfo->sections[$section->section])) {
                    continue;
                }

                $modids = $modinfo->sections[$section->section];

                $mrows = array();

                foreach ($modids as $modid) {

                    if (!empty($statsdata->counts[$modid])) {

                        $mod = $modinfo->get_cm($modid);
                         // ...values.
                        $row = new html_table_row();
                        $row->cells[] = $mod->get_formatted_name();

                        // Detailslink.
                        $params = array(
                            'contextid' => $context->id,
                            'component' => 'local_aclmodules',
                            'ratingarea' => 'coursemodule',
                            'itemid' => $modid,
                            'scaleid' => $scaleid
                        );
                        $detailsurl = new moodle_url('/rating/index.php', $params);

                        $row->cells[]  = html_writer::link($detailsurl, $infoicon, array('target' => '_blank'));

                        for ($i = 1; $i <= $scale->max; $i++) {

                            if (!empty($statsdata->counts[$modid][$scaleid][$i])) {
                                $row->cells[] = $statsdata->counts[$modid][$scaleid][$i];
                            } else {
                                $row->cells[] = 0;
                            }
                        }
                        $mrows[] = $row;
                    }
                }

                if (count($mrows) > 0) {
                    $table->data[] = $srow;

                    foreach ($mrows as $mrow) {
                        $table->data[] = $mrow;
                    }
                }
            }

            return html_writer::table($table);
        }
    }

}