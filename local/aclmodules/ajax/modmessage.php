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
 * AJAX handler for module messages
 *
 * @package   local_aclmodules
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

$action = required_param('action', PARAM_ALPHA);
$modid = required_param('modid', PARAM_INT);

$cm = get_coursemodule_from_id(false, $modid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
require_course_login($course);

$return = new stdClass();

switch ($action) {

    case 'sendmessage' :

        $useridto = required_param('useridto', PARAM_INT);
        $useridfrom = required_param('useridfrom', PARAM_INT);
        $message = required_param('message', PARAM_TEXT);

        $modmessages = \local_aclmodules\local\modmessages::instance(true);

        if ($messageid = $modmessages->save_modmessage($cm, $useridfrom, $useridto, $message)) {

            $return->result = 'sendmessage';
            $return->modmessages = $modmessages->get_rendered_modmessages($cm, $course);

        } else {
            $return->result = 'error';
        }

        break;

    case 'getmessages' :

        $modmessages = \local_aclmodules\local\modmessages::instance(true);
        $return->result = 'getmessages';
        $return->modmessages = $modmessages->get_rendered_modmessages($cm, $course);

        break;

    case 'setreadmessage' :

        $messageid = required_param('messageid', PARAM_INT);

        $modmessages = \local_aclmodules\local\modmessages::instance(true);
        $return->result = 'setreadmessage';

        list($return->modmessagesheaderinfo, $return->modmessagesreadinfo) = $modmessages->mark_message_as_read($course, $cm, $messageid);
    break;
}

echo json_encode($return);
die;