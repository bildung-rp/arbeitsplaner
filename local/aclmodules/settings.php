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
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_aclmodules',
                    get_string('pluginname', 'local_aclmodules'));
    $ADMIN->add('localplugins', $settings);

    require_once($CFG->dirroot . '/local/aclmodules/admin/admin_setting_configtablecheck.php');

    // ... module types, which should be configurable for availability control by this plugin.
    $rows = get_module_types_names();

    $columns = array('modulestocontrol' => new lang_string('modulestocontrol', 'local_aclmodules'));
    $descriptions = array();
    foreach (\local_aclmodules\local\aclmodules::$configoptions as $option) {
        $columns[$option] = new lang_string($option, 'local_aclmodules');
        $descriptions[] = html_writer::tag('b', $columns[$option]) . ': ' . new lang_string($option . '_desc', 'local_aclmodules');
    };
    $settings->add(new admin_setting_configtablecheck('local_aclmodules/config',
                    new lang_string('config', 'local_aclmodules'),
                    implode('<br />', $descriptions), array(), $rows, $columns));

    // Medal .
    $settings->add(new admin_setting_configstoredfile('local_aclmodules/medal',
                    new lang_string('medal', 'local_aclmodules'),
                    new lang_string('medal_desc', 'local_aclmodules'),
                    'medal', 0,
                    array('maxfiles' => 1, 'accepted_types' => array('image'))));
    // Count of visible messages.
    $settings->add(new admin_setting_configtext('local_aclmodules/countmessagedisplay',
                    new lang_string('countmessagedisplay', 'local_aclmodules'),
                    new lang_string('countmessagedisplaydesc', 'local_aclmodules'),
                    '3', PARAM_INT));

    // Scale for grading Modules.
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->dirroot . '/local/aclmodules/admin/admin_setting_configselectscale.php');

    if ($scales = grade_scale::fetch_all_global()) {

        $choices = array();
        for ($i = 3; $i <= 6; $i++) {
            $choices[$i] = get_string('pointscalemax', 'local_aclmodules', $i);
        }

        foreach ($scales as $scale) {
            $choices[-$scale->id] = $scale->name;
        }

        $settings->add(new admin_setting_configselectscale('local_aclmodules/modratingscale',
                        new lang_string('modratingscale', 'local_aclmodules'),
                        new lang_string('modratingscaledesc', 'local_aclmodules'),
                        '3', $choices));
    }
}