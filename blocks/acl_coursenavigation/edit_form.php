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
 * Course Navigation instance config form.
 *
 * @package    block_acl_coursenavigation
 * @copyright  Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_acl_coursenavigation_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $choices = array(
            'min' => get_string('viewmin', 'block_acl_coursenavigation'),
            'midi' => get_string('viewmidi', 'block_acl_coursenavigation'),
            'max' => get_string('viewmax', 'block_acl_coursenavigation'),
            );

        $mform->addElement('select', 'config_viewtype', get_string('viewtype', 'block_acl_coursenavigation'), $choices);
        $mform->setDefault('config_viewtype', 'min');

    }
}
