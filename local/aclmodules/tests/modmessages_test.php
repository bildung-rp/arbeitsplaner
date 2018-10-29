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
 * Tests for mod messages.
 *
 * @package   local_aclmodules
 * @copyright 2018 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/user/lib.php');

class local_aclmodules_modmessages_testcase extends advanced_testcase {

    /**
     * Create an entry in the old message table (lower than moolde 3.5).
     *
     * @param object $mod
     * @param array $record
     * @return object
     */
    private function create_message_old_format($mod, $record) {
        global $USER, $DB;
        static $count = 0;

        $count++;

        if (!isset($record['useridfrom'])) {
            $record['useridfrom'] = $USER->id;
        }

        if (!isset($record['useridto'])) {
            print_error('useridto must be set');
        }

        if (!isset($record['subject'])) {
            $record['subject'] = 'Subject '.$count;
        }

        if (!isset($record['fullmessage'])) {
            $record['fullmessage'] = 'Fullmessage '.$count;
        }

        if (!isset($record['fullmessagehtml'])) {
            $record['fullmessagehtml'] = 'Fullmessage '.$count;
        }

        if (!isset($record['fullmessageformat'])) {
            $record['fullmessageformat'] = FORMAT_PLAIN;
        }

        if (!isset($record['smallmessage'])) {
            $record['smallmessage'] = 'Smallmessage '.$count;
        }

        $record['notification'] = 0;
        $contexturl = new \moodle_url("/mod/{$mod->modname}/view.php", array('id' => $mod->id));
        $record['contexturl'] = $contexturl->out();
        $record['contexturlname'] = $mod->name;
        $record['timecreated'] = time();

        $id = $DB->insert_record('message', (object) $record);
        return $DB->get_record('message', ['id' => $id]);
    }

    /**
     * Test primary deployment with and without userdata of a template.
     */
    public function test_message() {
        global $DB, $USER;

        // Set up.
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $sink->clear();

        $course = $this->getDataGenerator()->create_course();

        // Users.
        $student1 = $this->getDataGenerator()->create_user(array('firstname' => 'student', 'lastname' => '1'));
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, 'student');
        $student2 = $this->getDataGenerator()->create_user(array('firstname' => 'student', 'lastname' => '2'));
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, 'student');

        $student3 = $this->getDataGenerator()->create_user(array('firstname' => 'student', 'lastname' => '3'));

        $assign = $this->getDataGenerator()->create_module('assign', array('course' => $course->id));
        $assignmodule = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);
        $forum1 = $this->getDataGenerator()->create_module('forum', array('course' => $course->id));
        $forummodule = get_coursemodule_from_instance('forum', $forum1->id, 0, false, MUST_EXIST);

        // Create old module messages.
        $m1 = $this->create_message_old_format($assignmodule, ['useridto' => $student1->id]);
        $m2 = $this->create_message_old_format($forummodule, ['useridto' => $student2->id]);

        $messages = $DB->get_records('message');
        $this->assertEquals(2, count($messages));

        $messages = $DB->get_records('messages');
        $this->assertEquals(0, count($messages));

        $task = new \core_message\task\migrate_message_data();
        $task->set_custom_data(['userid' => $USER->id]);
        $task->execute();

        $messages = $DB->get_records('message');
        $this->assertEquals(0, count($messages));

        $messages = $DB->get_records('messages');
        $this->assertEquals(2, count($messages));

        $modmessageshelper = local_aclmodules\local\modmessages::instance();
        list($messages, $messagesusers, $allusers) = $modmessageshelper->get_all_modmessages_for_user($course, $USER->id);

        $modmessagesassign = reset($messages[$assignmodule->id][$student1->id]);
        $this->assertEquals($student1->id, $modmessagesassign->useridto);

        $modmessagesforum = reset($messages[$forummodule->id][$student2->id]);
        $this->assertEquals($student2->id, $modmessagesforum->useridto);

    }

}
