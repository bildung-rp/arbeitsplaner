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
 * Course Navigation main class
 *
 * @package    block_acl_coursenavigation
 * @copyright  Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_acl_coursenavigation extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_acl_coursenavigation');
    }

    public function applicable_formats() {
        return array('course' => true);
    }

    public function instance_allow_config() {
        return true;
    }

    public function get_content() {
        global $PAGE, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $format = \course_get_format($COURSE);
        $course = $format->get_course();

        if ($format->uses_sections()) {

            $viewtype = (isset($this->config->viewtype)) ? $this->config->viewtype : 'min';

            $renderer = $PAGE->get_renderer('block_acl_coursenavigation');
            $this->content->text = $renderer->render_content($course, $format, $viewtype);
        }
        return $this->content;
    }

}