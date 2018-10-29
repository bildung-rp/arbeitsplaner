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
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot . '/lib/formslib.php');

use local_aclmodules\local\aclmodules as local_aclmodules;

class stateedit_form extends moodleform {

    protected function definition() {

        $mform = $this->_form;

        $mod = $this->_customdata['cm'];
        $user = $this->_customdata['user'];

        $modulename = $mod->name;
        if ($url = $mod->url) {
            $modulename = html_writer::link($url, $modulename, array('target' => '_blank'));
        }
        
        $mform->addElement('static', 'activity', get_string('activity'), $modulename);
        $mform->addElement('static', 'user', get_string('user'), fullname($user));

        // ... actual state can be set by teacher or student.
        $currentactivitystate = $this->_customdata['currentactivitystate'];

        $description = local_aclmodules::$moduserstates[$currentactivitystate]['desc'];
        $class = "statusdiv " . local_aclmodules::$moduserstates[$currentactivitystate]['class'];

        $o = html_writer::tag('div', '', array("class" => $class, 'style' => 'float:left')).'&nbsp;';
        $o .= html_writer::tag('span', get_string($description, 'local_aclmodules'));
        $mform->addElement('static', 'currentactivitystate', get_string('currentactivitystate', 'local_aclmodules'), $o);

        // ... teacher can set only "assigned" and "passed" State.
        if ($currentactivitystate >= 70) {

            $choices = array("0" => get_string('select'));
            foreach (local_aclmodules::$completionstates as $key => $statestr) {
                $choices[$key] = get_string($statestr, 'local_aclmodules');
            }
            $mform->addElement('select', 'useractivitystate', get_string('completionstates', 'local_aclmodules'), $choices);
        }

        if (!empty($this->_customdata['modcomment'])) {
            $mform->addElement('textarea', 'useractivitymessage', get_string('stateactivitymessage', 'local_aclmodules'), array('cols' => 40, 'rows' => 5));
        } else {
            $mform->addElement('html', get_string('cannotbecommented', 'local_aclmodules'));
        }

        // Id of course, we are in.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', $this->_customdata['course']->id);

        // Id of module.
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', $this->_customdata['cm']->id);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->setDefault('userid', $user->id);

        $mform->addElement('html', html_writer::tag('div', "", array('id' => 'id_status')));

        $this->add_action_buttons(true, get_string('submit'));
    }

}