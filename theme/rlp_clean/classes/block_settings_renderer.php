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
require_once($CFG->dirroot . '/blocks/settings/renderer.php');

class theme_rlp_clean_block_settings_renderer extends block_settings_renderer {

    /**
     * Remove nodes that should be hidden according to settings in local_authoringcapability
     * plugin.
     *
     * @param settings_navigation $navigation
     * @return string content of the settings tree.
     */
    public function settings_tree(settings_navigation $navigation) {
        global $CFG;
        // +++ SYNERGY LEARNING: filter settings navigation depending on level.
        if (file_exists($CFG->dirroot . '/local/authoringcapability/classes/local/corechanges.php')) {
            \local_authoringcapability\local\corechanges::hide_settings_navigation_items($navigation);
        }
        // +++ SYNERGY LEARNING: filter settings navigation depending on level.
        return parent::settings_tree($navigation);
    }

}
