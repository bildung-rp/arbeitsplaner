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
 * Main code for local plugin aclmodules, planner for all learning tasks.
 *
 * @package   local_aclmodules
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

use local_aclmodules\local\aclmodules as local_aclmodules;

$courseid = required_param('courseid', PARAM_INT);
$msg = optional_param('msg', '', PARAM_TEXT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);
require_capability('local/aclmodules:edit', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aclmodules/planner/edit.php', array('courseid' => $course->id)));
$PAGE->set_title(get_string('planneredittitle', 'local_aclmodules'));
$PAGE->set_heading(get_string('plannereditheader', 'local_aclmodules'));

// We intentionally do not use standard moodle forms, as the layout is complex and then number of
// input form will be very high, so be aware of validating input data.
if ($form = data_submitted()) {

    if (isset($form->cancel)) {
        redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    }

    if (isset($form->save)) {

        // ...check sesskey.
        require_sesskey();

        $aclmodules = local_aclmodules::instance();

        // ...security checks are made in method!
        if ($aclmodules->save_acl_mod_access($form, $course, $context)) {
            $msg = 'aclsaved';
        }
    }
}

$aclmodules = local_aclmodules::instance();

// ... get all users for the course.
$participants = $aclmodules->get_all_gradable_users($context);

// ... get available levels for the course.
$levels = $aclmodules->get_levels($courseid);

// ...get all the course modules which have ACL control switched to on.
$modavailfields = $aclmodules->get_acl_mod_avail_fields($courseid);

// ...get all available mods per user.
$modavailuser = $aclmodules->get_mods_available_for_users($courseid, $participants);

// ...get user for each level group.
$leveltouserids = $aclmodules->get_level_users($courseid);

// ...get selected configoptions for each module instance.
$configoptions = $aclmodules->get_acl_mod_config($courseid);

$reportsdata = $aclmodules->get_course_state_report($course, $context);

if ($reportsdata['error'] == 1) {
    $moduserstatus = array();
    $sectionstates = array();
    $counts = array();
} else {
    $moduserstatus = $reportsdata['data'];
    $sectionstates = $reportsdata['sectionstates'];
    $counts = $reportsdata['counts'];
}

// ... get users preferences collapsed config columns.
user_preference_allow_ajax_update('aclcfgcolcollapsed_' . $courseid, PARAM_INT);
$cfgcolcollapsed = (!empty($USER->preference['aclcfgcolcollapsed_' . $courseid]));
$cfgcellstyle = ($cfgcolcollapsed) ? "display:none" : "";

// ... get users preferences hidden usercolumns.
user_preference_allow_ajax_update('aclcolvisible_' . $courseid, PARAM_INT);

$userslider = array();
$userslider['min'] = count(local_aclmodules::$configoptions) + count($levels) + 4;
$userslider['max'] = $userslider['min'] + count($participants) - 1;
$userslider['value'] = $userslider['min'];

if (!empty($USER->preference['aclcolvisible_' . $courseid])) {
    $userslider['value'] = $USER->preference['aclcolvisible_' . $courseid];
}

$userslider['value'] = min($userslider['value'], $userslider['max']);

// ...start page.
echo $OUTPUT->header();

// ... print message.
if (!empty($msg)) {
    echo $OUTPUT->notification(get_string($msg, 'local_aclmodules'), 'notifysuccess');
}

// ... output form.
$actionurl = new moodle_url('/local/aclmodules/planner/edit.php');

echo html_writer::start_tag('form', array('action' => $actionurl, 'method' => 'post', 'id' => 'plannerform', 'class' => 'mform'));

$buttons = html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'save', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary form-group'));
$buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'cancel', 'value' => get_string('cancel'), 'class' => 'btn  form-group'));

echo html_writer::tag('div', $buttons, ['class' => 'form-inline']);

echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => $USER->sesskey));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'courseid', 'value' => $course->id));

// ... switch acl control form course.
$aclactive = $aclmodules->get_acl_mod_config_value($course, 0, 'aclactive');
$class = ($aclactive == '1') ? 'aclmodon' : 'aclmodoff';
$aclcontrolcheckbox = html_writer::checkbox('aclactive', '1', $aclactive, '', array('id' => 'aclactive'));
$content = get_string('aclcontrol', 'local_aclmodules') . ": " . $aclcontrolcheckbox;
$content .= ($aclactive == '1') ? " (" . get_string('aclcontroloff', 'local_aclmodules') . ")" : '';
$o = html_writer::tag('div', $content, array('class' => $class));
$o .= html_writer::empty_tag('div', array('class' => 'clearfix'));
echo html_writer::tag('div', $o);

$renderer = $PAGE->get_renderer('local_aclmodules');

$icons = array();
$icons['add'] = $OUTPUT->pix_icon('add', get_string('add', 'local_aclmodules'), 'local_aclmodules');
$icons['less'] = $OUTPUT->pix_icon('less', get_string('less', 'local_aclmodules'), 'local_aclmodules');

// ... start building table.
$table = new html_table();
$table->id = 'acl-table';
$table->border = '1';
$table->attributes['class'] = 'generaltable nocollapse';

$rowindex = 0; // Count rows and numerate them.
$row2 = array();
$row2[] = "";

// ...insert cell to collapse config columns.
$class = 'togglehide';
$class .= ($cfgcellstyle) ? ' collapsed' : '';
$title = ($cfgcellstyle) ? get_string('showcfgcolumns', 'local_aclmodules') : get_string('hidecfgcolumns', 'local_aclmodules');
$row2[] = html_writer::tag('div', '', array('id' => 'cfgcolcollapse', 'class' => $class, 'title' => $title));

// ... printout configurations section.
foreach (local_aclmodules::$configoptions as $configoption) {
    $row2[] = $renderer->get_div_cell(get_string($configoption, 'local_aclmodules'), array('class' => 'head-config'), "cfgcell vertical", $cfgcellstyle);
}

// ... print out all levels columns.
$row2[] = $renderer->get_div_cell(get_string('all'), array('class' => 'head-level'), "cfgcell vertical", $cfgcellstyle);

foreach ($levels as $level) {
    $row2[] = $renderer->get_div_cell($level->name, array('class' => 'head-level'), "cfgcell vertical", $cfgcellstyle);
}

$cell = new html_table_cell();
$cell->style = "table-layout:fixed; width: 0px;";
$row2[] = $cell;

// ... print out all users columns.
$count = $userslider['min'];
foreach ($participants as $user) {
    $style = ($count < $userslider['value']) ? "display:none" : "";
    $row2[] = $renderer->get_div_cell(shorten_text(fullname($user)), array('class' => 'head-user'), "vertical", $style);
    $count++;
}
// ...print out empty columns to make last column resizeable.
$row2[] = "";

$table->data[] = $row2;
$rowindex++;

// Get info about the modules.
$modinfo = get_fast_modinfo($course);
// ...and sections.
$sectioninfo = $modinfo->get_section_info_all();

// Cet modulids per section.
$sectionmoduleids = array();

// Check hidden rows and setup slider.
user_preference_allow_ajax_update('aclrowvisible_' . $courseid, PARAM_INT);

// ... after max table count is known, do a limit to rowcount later.
$rowslider = array();
$rowslider['min'] = 1; // Don't hide row 0.
$rowslider['value'] = $rowslider['min'];

// ... set userpref, when section is given, collaps all sections except the selected.
$section = optional_param('section', 0, PARAM_INT);
if (!empty($section)) {

    foreach ($sectioninfo as $thissection) {

        $sectioncollapsed = !empty($USER->preference['aclsectioncollapsed-' . $thissection->id]);

        if (($section == $thissection->section)) {
            if ($sectioncollapsed) {
                set_user_preference('aclsectioncollapsed-' . $thissection->id, 0);
            }
        } else {
            if (!$sectioncollapsed) {
                set_user_preference('aclsectioncollapsed-' . $thissection->id, 1);
            }
        }
    }
} else {
    if (!empty($USER->preference['aclrowvisible_' . $courseid])) {
        $rowslider['value'] = $USER->preference['aclrowvisible_' . $courseid];
    }
}

foreach ($sectioninfo as $thissection) {

    // ... section is empty.
    if (empty($modinfo->sections[$thissection->section])) {
        continue;
    }

    user_preference_allow_ajax_update('aclsectioncollapsed-' . $thissection->id, PARAM_INT);
    $sectioncollapsed = !empty($USER->preference['aclsectioncollapsed-' . $thissection->id]);

    // ... not empty, get configurable modules from this section.
    $configurablemods = $aclmodules->get_configurable_modules($modinfo->sections[$thissection->section], $modinfo);

    if (count($configurablemods) > 0) {

        $row = new html_table_row();
        $row->id = "sectionrow_{$thissection->id}";
        $row->attributes['class'] = 'rc' . $rowindex;

        // ... first row, first cell.
        $cell = new html_table_cell();
        $sectionurl = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $thissection->section));

        $cell->text = html_writer::link($sectionurl, get_section_name($course, $thissection));
        $cell->attributes['class'] = "acl-table-sectionname";
        if ($sectioncollapsed) {
            $cell->attributes['class'] .= " collapsed";
        }
        $cell->id = "acl-table-section_" . $thissection->id;
        $row->cells[] = $cell;

        // ...insert cell to collapse config columns.
        $row->cells[] = "";

        // ...prepare the section form controls.
        foreach (local_aclmodules::$configoptions as $configoption) {

            $cell = new html_table_cell();

            $params = array('id' => 'sectionconfigoptions_' . $configoption . '_' . $thissection->id);
            $cell->text = html_writer::checkbox("sectionconfigoptions[{$configoption}][{$thissection->id}]", "1", '', '', $params);
            $cell->style = $cfgcellstyle;
            $cell->attributes['class'] = 'cfgcell';
            $row->cells[] = $cell;
        }

        // ... generate icons for levels add/remove.
        $add = html_writer::link('#', $icons['add'], array('id' => "sectionadd_{$thissection->id}_0", 'class' => 'addlevellink'));
        $less = html_writer::link('#', $icons['less'], array('id' => "sectionless_{$thissection->id}_0", 'class' => 'lesslevellink'));

        $cell = new html_table_cell();
        $cell->text = html_writer::tag('div', $add . "<br />" . $less, array('class' => 'acl-table-sectionleveledit'));
        $cell->style = $cfgcellstyle;
        $cell->attributes['class'] = 'cfgcell';
        $row->cells[] = $cell;

        foreach ($levels as $level) {

            $add = html_writer::link('#', $icons['add'], array('id' => "sectionadd_{$thissection->id}_{$level->id}", 'class' => 'addlevellink'));
            $less = html_writer::link('#', $icons['less'], array('id' => "sectionless_{$thissection->id}_{$level->id}", 'class' => 'lesslevellink'));

            $cell = new html_table_cell();
            $cell->text = html_writer::tag('div', $add . "<br />" . $less, array('class' => 'acl-table-sevtionleveledit'));
            $cell->style = $cfgcellstyle;
            $cell->attributes['class'] = 'cfgcell';
            $row->cells[] = $cell;
        }

        $row->cells[] = "";

        // ... add all users checkboxes.
        $count = $userslider['min'];
        foreach ($participants as $participant) {

            $style = ($count < $userslider['value']) ? "display:none" : "";

            $class = "sstatusdiv";
            if (isset($sectionstates[$thissection->id][$participant->id])) {
                $class .= " " . local_aclmodules::$moduserstates[$sectionstates[$thissection->id][$participant->id]]['class'];
            }
            $statusdiv = html_writer::tag('div', '', array("class" => $class, 'id' => "sstatusdiv_{$participant->id}_{$thissection->id}"));

            $params = array('id' => "sectionuseravail_{$participant->id}_{$thissection->id}");
            $value = !empty($counts[$thissection->id][$participant->id]['assigned']);
            $cellcontent = html_writer::checkbox("sectionuseravail[{$participant->id}][{$thissection->id}]", '1', $value, '', $params);
            $cellcontent .= $statusdiv;

            $row->cells[] = $renderer->get_cell($cellcontent, $style);
            $count++;
        }

        $row->cells[] = "";

        if ($rowindex < $rowslider['value']) {
            $row->style = 'display:none';
        }

        $table->data[] = $row;
        $rowindex++;

        $sectionmoduleids[$thissection->id] = array();

        foreach ($configurablemods as $mod) {

            $sectionmoduleids[$thissection->id][] = $mod->id;

            $row = new html_table_row();
            $row->attributes['class'] = 'rc' . $rowindex;

            if ($sectioncollapsed) {
                $row->attributes['class'] .= ' collapsed';
            }
            $row->id = "modulerow_{$thissection->id}_{$mod->id}";

            // ... first column turn on/off acl.
            $class = (isset($modavailfields[$mod->id])) ? 'aclmodon' : 'aclmodoff';

            // ... name of activity.
            $instancename = $mod->get_formatted_name();
            $activityclass = 'instancename';

            if (!$mod->visible) {
                $activityclass .= " dimmed_text";
            }

            $activitylink = html_writer::empty_tag('img', array('src' => $mod->get_icon_url(),
                        'class' => 'iconsmall activityicon', 'alt' => ' ', 'role' => 'presentation')) . "  " .
                    html_writer::tag('span', $instancename, array('class' => $activityclass));

            if ($mod->url) {
                $activitylink = html_writer::link($mod->url, $activitylink);
            }

            $row->cells[] = html_writer::tag('div', $activitylink, array('id' => 'aclmodactivestatus_' . $mod->id, 'class' => $class));

            // ...insert cell to collapse config columns.
            $row->cells[] = "";

            // ... add all the instance modules config data.
            foreach (local_aclmodules::$configoptions as $configoption) {

                if (!isset($configoptions[$mod->id][$configoption])) {
                    $configvalue = $aclmodules->get_acl_mod_config_default($mod->modname, $configoption);
                } else {
                    $configvalue = $configoptions[$mod->id][$configoption]->value;
                }

                $cell = new html_table_cell();

                $params = array('id' => 'configoptions_' . $configoption . '_' . $mod->id, "class" => $configoption);
                $cell->text = html_writer::checkbox("configoptions[{$configoption}][{$mod->id}]", "1", !empty($configvalue), '', $params);
                $cell->style = $cfgcellstyle;
                $cell->attributes['class'] = 'cfgcell';
                $row->cells[] = $cell;
            }

            // ... generate icons for levels add/remove.
            $add = html_writer::link('#', $icons['add'], array('id' => "add_{$mod->id}_0", 'class' => 'addlevellink'));
            $less = html_writer::link('#', $icons['less'], array('id' => "less_{$mod->id}_0", 'class' => 'lesslevellink'));

            $cell = new html_table_cell();
            $cell->text = html_writer::tag('div', $add . "<br />" . $less, array('class' => 'acl-table-leveledit'));
            $cell->style = $cfgcellstyle;
            $cell->attributes['class'] = 'cfgcell';
            $row->cells[] = $cell;

            foreach ($levels as $level) {
                $add = html_writer::link('#', $icons['add'], array('id' => "add_{$mod->id}_{$level->id}", 'class' => 'addlevellink'));
                $less = html_writer::link('#', $icons['less'], array('id' => "less_{$mod->id}_{$level->id}", 'class' => 'lesslevellink'));

                $cell = new html_table_cell();
                $cell->text = html_writer::tag('div', $add . "<br />" . $less, array('class' => 'acl-table-leveledit'));
                $cell->style = $cfgcellstyle;
                $cell->attributes['class'] = 'cfgcell';
                $row->cells[] = $cell;
            }

            $row->cells[] = "";

            // ... add all users checkboxes.
            $count = $userslider['min'];
            foreach ($participants as $participant) {

                $style = ($count < $userslider['value']) ? "display:none" : "";

                $class = "statusdiv";
                $desc = "";
                $attributes = array('id' => "moduseravail_{$participant->id}_{$mod->id}");

                if (isset($moduserstatus[$mod->id][$participant->id])) {

                    $class .= " " . local_aclmodules::$moduserstates[$moduserstatus[$mod->id][$participant->id]]['class'];
                    $desc = get_string(local_aclmodules::$moduserstates[$moduserstatus[$mod->id][$participant->id]]['desc'], 'local_aclmodules');

                    if ($moduserstatus[$mod->id][$participant->id] > 70) {
                        $attributes['disabled'] = "disabled";
                    }
                }
                $statusdiv = html_writer::tag('div', '', array(
                            'class' => $class,
                            'id' => "statusdiv_{$participant->id}_{$mod->id}",
                            'title' => $desc
                        ));



                $content = html_writer::checkbox("moduseravail[{$participant->id}][{$mod->id}]", '1', isset($modavailuser[$participant->id][$mod->id]), '', $attributes) .
                        "<br />" . $statusdiv;

                $row->cells[] = $renderer->get_cell($content, $style);
                $count++;
            }

            $row->cells[] = "";
            if ($rowindex < $rowslider['value']) {
                $row->style = 'display:none';
            }
            $table->data[] = $row;
            $rowindex++;
        }
    }
}

// ...ensure that value is not greater than count of table.
$rowslider['max'] = count($table->data) - 1;
$rowslider['value'] = min($rowslider['value'], $rowslider['max']);

$args = array(
    'modsavailactive' => array_keys($modavailfields),
    'leveltousers' => $leveltouserids,
    'sectioncmids' => $sectionmoduleids,
    'userslidervals' => $userslider,
    'rowslidervals' => $rowslider,
    'courseid' => $courseid
);

$PAGE->requires->yui_module('moodle-local_aclmodules-planner', 'M.local_aclmodules.planner', array($args), null, true);

$args = array();
$PAGE->requires->strings_for_js(array('stateedit', 'hidecfgcolumns', 'showcfgcolumns', 'nouserassignedtolevel', 'notsavedwarning', 'closedialog'), 'local_aclmodules');
$PAGE->requires->yui_module('moodle-local_aclmodules-stateeditdialog', 'M.local_aclmodules.stateeditdialog', array($args), null, true);
$PAGE->requires->yui_module('moodle-local_aclmodules-sectioncollapse', 'M.local_aclmodules.sectioncollapse', array($args), null, true);

// ...output table.
echo html_writer::tag('div', '', array('class' => 'vert_slider'));
echo html_writer::tag('div', html_writer::tag('div', '', array('class' => 'horiz_slider'), array('class' => 'yui3-skin-round-dark')));

echo html_writer::table($table);
echo html_writer::tag('div', $buttons, ['class' => 'form-inline']);
echo html_writer::end_tag('form');

echo $renderer->render_legend();

echo $OUTPUT->footer();