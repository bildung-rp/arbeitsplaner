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
 * @copyright 2015 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/aclmodules/lib.php');

$action = required_param('action', PARAM_ALPHA);
$courseid = required_param('courseid', PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);

$context = context_course::instance($course->id, MUST_EXIST);
require_capability('local/aclmodules:edit', $context);

$PAGE->set_url(new moodle_url('/local/aclmodules/action.php'));
$PAGE->set_context($context);

if ($action == 'switchacl') {

    $value = required_param('value', PARAM_TEXT);
    $aclmodules = \local_aclmodules\local\aclmodules::instance();
    
    // Note that there are two different values controlling the plugin
    // aclon means plugin is switched on/off in the course administration.
    $aclmodules->save_acl_mod_config_value($courseid, 0, 'aclon', $value);
    // ... aclactive means acl control is active / inactive.
    // So you may have switched on plugin and (temporarily) have set acl to inactive.
    $aclmodules->save_acl_mod_config_value($courseid, 0, 'aclactive', $value);

    $data = (object) array ('courseid' => $courseid);
    $data->aclactive = $value;
    $aclmodules->save_acl_mod_avail_fields($data);
    
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));

} else {
    print_error('unkownaction');
}