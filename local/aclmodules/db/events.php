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
 * Events for local plugin aclmodules
 *
 * @package   local_aclmodules
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyeft/gpl.html GNU GPL v3 or later
 */
$observers = array(
    array(
        'eventname' => '\core\event\course_module_created',
        'callback' => 'local_aclmodules_course_module_created',
        'includefile' => '/local/aclmodules/lib.php',
        'internal' => true
    ),
    array(
        'eventname' => '\core\event\course_module_updated',
        'callback' => 'local_aclmodules_course_module_updated',
        'includefile' => '/local/aclmodules/lib.php',
        'internal' => true
    ),
    array(
        'eventname' => '\core\event\course_restored',
        'callback' => 'local_aclmodules_course_restored',
        'includefile' => '/local/aclmodules/lib.php',
        'internal' => true
    ),
    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => 'local_aclmodules_user_enrolment_deleted',
        'includefile' => '/local/aclmodules/lib.php',
        'internal' => true
    ),
    array(
        'eventname' => '\core\event\course_deleted',
        'callback' => 'local_aclmodules_course_deleted',
        'includefile' => '/local/aclmodules/lib.php',
        'internal' => true
    ),
    array(
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => 'local_aclmodules_completion_changed',
        'includefile' => 'local/aclmodules/lib.php',
        'internal' => true,
    )
);