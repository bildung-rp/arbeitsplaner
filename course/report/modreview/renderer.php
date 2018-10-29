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
 * Main code for local plugin coursereport_modreview
 *
 * @package   coursereport_modreview
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

class coursereport_modreview_renderer extends plugin_renderer_base {

    /** render the modname for the reports view 
     * 
     * @param  object $mod the coursemodule record
     * @return string the html for displaying the name
     */
    protected function render_modname($mod) {
        return $mod->get_formatted_name();
    }

    /** render the grade for the reports view 
     * 
     * @param object $mod the module which is gradable
     * @param object $reportdata aggregated data for the user
     * @return string the html for the grade.
     */
    protected function render_grade($mod, $reportdata) {

        if (isset($reportdata->grades[$mod->modname][$mod->instance]->rawgrade)) {

            return $reportdata->grades[$mod->modname][$mod->instance]->rawgrade;
        } else {
            return " -- ";
        }
    }

    /**  render the completion info for the users module
     * 
     * @global object $OUTPUT
     * @param object $mod the module which completion tracking is (hopefully) on.
     * @param object $reportdata aggregated data for the user
     * @return string the html for the completion info.
     */
    protected function render_completion($mod, $reportdata) {
        global $OUTPUT;

        if ($reportdata->completion->tracking[$mod->id] == COMPLETION_TRACKING_NONE) {

            return get_string('completiontrackinginactive', 'coursereport_modreview');
        } else {

            $completionicon = "";

            // ... get correct completion icon.
            if ($reportdata->completion->tracking[$mod->id] == COMPLETION_TRACKING_MANUAL) {

                switch ($reportdata->completion->state[$mod->id]) {
                    case COMPLETION_INCOMPLETE:
                        $completionicon = 'manual-n';
                        break;
                    case COMPLETION_COMPLETE:
                        $completionicon = 'manual-y';
                        break;
                }
            } else { // Automatic.
                switch ($reportdata->completion->state[$mod->id]) {
                    case COMPLETION_INCOMPLETE:
                        $completionicon = 'auto-n';
                        break;
                    case COMPLETION_COMPLETE:
                        $completionicon = 'auto-y';
                        break;
                    case COMPLETION_COMPLETE_PASS:
                        $completionicon = 'auto-pass';
                        break;
                    case COMPLETION_COMPLETE_FAIL:
                        $completionicon = 'auto-fail';
                        break;
                }
            }

            $output = '';

            if ($completionicon) {

                $formattedname = $mod->get_formatted_name();

                $imgalt = get_string('completion-alt-' . $completionicon, 'completion', $formattedname);
                // In auto mode, or when editing, the icon is just an image.
                $completionpixicon = new pix_icon('i/completion-' . $completionicon, $imgalt, '',
                                array('title' => $imgalt));
                $output .= html_writer::tag('span', $OUTPUT->render($completionpixicon), array('class' => 'autocompletion'));
                $output .= " " . html_writer::tag('span', $imgalt);
            }
            return $output;
        }
    }

    /** render the ratings (modrating) for one module
     * 
     * @param object $mod
     * @param object $reportdata the data for the given module.
     * @return string HTML
     */
    protected function render_rating($mod, $reportdata) {
        global $OUTPUT;

        if ($reportdata->rateable[$mod->id] == 0) {
            return get_string('notrateable', 'coursereport_modreview');
        }

        if (isset($reportdata->ratings[$mod->id]->rating)) {

            $value = round($reportdata->ratings[$mod->id]->rating, 0);

            if ($value >= 0) {

                $stars = '';

                $star = $OUTPUT->render(new pix_icon('star', '', 'coursereport_modreview'));

                for ($i = 0; $i < $value; $i++) {
                    $stars .= $star;
                }
                return html_writer::tag('span', $stars) . " ({$value})";
            } else {

                return get_string('notyetrated', 'coursereport_modreview');
            }
        } else {

            return get_string('notyetrated', 'coursereport_modreview');
        }
    }

    /** render the comments (modmessages) for one module
     * 
     * @param object $mod
     * @param object $reportdata the data for the given module.
     * @return string HTML
     */
    protected function render_comments($mod, $reportdata) {

        if ($reportdata->commentable[$mod->id] == 0) {
            return get_string('notcommentable', 'coursereport_modreview');
        }

        $unreadcount = (!empty($reportdata->commentinfodata['unread'][$mod->id])) ?
                $reportdata->commentinfodata['unread'][$mod->id]->count : 0;

        $readcount = (!empty($reportdata->commentinfodata['read'][$mod->id])) ?
                $reportdata->commentinfodata['read'][$mod->id]->count : 0;

        $total = $unreadcount + $readcount;

        $countstr = " " . $unreadcount . "/" . $total;

        if ($unreadcount > 0) {
            $class = "section-unread";
            $text = get_string('new') . $countstr;
        } else {
            $class = "section-read";
            $text = $countstr;
        }

        return html_writer::tag('div', $text, array('class' => $class));
    }

    /** renders one section for the given reportsdata 
     * 
     * @param int $sectionid the id of the section
     * @param object $reportdata aggregated data for the user
     * @return string the html for the section info.
     */
    protected function render_section_info_table($sectionid, $reportdata) {

        $sectiondata = $reportdata->sections[$sectionid];

        if (count($sectiondata->mods) == 0) {
            return '';
        }

        $output = html_writer::tag('h5', $sectiondata->sectionname);

        $table = new html_table();
        $table->head = array(
            get_string('activities', 'coursereport_modreview'),
            get_string('grade', 'coursereport_modreview'),
            get_string('completionstatus', 'coursereport_modreview'),
            get_string('rating', 'coursereport_modreview'),
            get_string('comments', 'coursereport_modreview')
        );

        foreach ($sectiondata->mods as $mod) {

            $modname = $this->render_modname($mod);
            $grade = $this->render_grade($mod, $reportdata);
            $completion = $this->render_completion($mod, $reportdata);
            $rating = $this->render_rating($mod, $reportdata);
            $comments = $this->render_comments($mod, $reportdata);

            $table->data[] = array($modname, $grade, $completion, $rating, $comments);
        }

        $output .= html_writer::table($table);

        return $output;
    }

    /** render the reportsdate for one user
     * 
     * @param object $reportdata
     * @return string the html for the user report.
     */
    public function render_users_modreport($reportdata) {

        $output = '';

        foreach ($reportdata->sections as $sectionid => $unused) {
            $output .= $this->render_section_info_table($sectionid, $reportdata);
        }

        return $output;
    }

    /* render the completioninfo for each section depending on completioninfo of the modules in the section
     *
     * @param array $completiondata completion data calculated by modreview.
     * @param array $sections list of sections
     * @param object $modinfo
     * @param array $html list of HTML-Code for each section to append.
     * @return array the modified HTML-Code

     public function render_section_completioninfo($completiondata, $sections, $modinfo, &$html) {

      foreach ($sections as $thissection) {

      if (!empty($modinfo->sections[$thissection->section])) {


      $data = $completiondata[$thissection->section];

      $class = "";

      // ... there are items to track in this section.
      if ($data->itemstracked > 0) {

      if ($data->itemstracked == $data->itemscompleted) {

      $class = "section-completed";
      } else {

      if ($data->itemscompleted > 0) {

      $class = "section-started";
      } else {

      $class = "section-notstarted";
      }
      }
      } else {
      $class = "section-notracking";
      }

      $text = get_string($class, "coursereport_modreview");
      } else {

      $class = "section-empty";
      $text = get_string($class, "coursereport_modreview");
      }

      $html[$thissection->section] = html_writer::tag('div', $text, array('class' => $class));
      }

      return $html;
      } */
}