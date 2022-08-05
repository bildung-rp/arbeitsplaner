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
 * Course Navigation renderer
 *
 * @package    block_acl_coursenavigation
 * @copyright  Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_acl_coursenavigation_renderer extends plugin_renderer_base {

    /** render the topics list for all formats that uses sections
     *
     * @param record $course
     * @return string
     */
    protected function render_topics($course) {

        $o = '';

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();

        $visiblesections = \local_aclmodules\local\aclmodules::instance()->get_sections_available_for_user($course->id, false);

        foreach ($sections as $section => $thissection) {

            if ($section > $course->numsections) {
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available &&
                    !empty($thissection->availableinfo));

            $showsection = ($showsection and (isset($visiblesections[$section])));

            if (!$showsection) {
                continue;
            }
            $url = new moodle_url('/course/view.php', array('id' => $course->id, 'section' => $section));
            $o .= html_writer::tag('li', html_writer::link($url, get_section_name($course, $section)));
        }

        if (!empty($o)) {
            $o = html_writer::tag('ul', $o, array('class' => 'block-nav-sections'));
        }
        return $o;
    }

    /** render the grids, when course format is grid format (parts taken from grid format).
     *
     * @global object $OUTPUT
     * @param record $course
     * @param format_base $gridformat
     * @return string
     */
    protected function render_grids($course, $gridformat) {
        global $OUTPUT, $PAGE, $COURSE;

        $context = context_course::instance($course->id);

        $modinfo = get_fast_modinfo($course);

		if(method_exists($gridformat,'get_summary_visibility')) {
			$summarystatus = $gridformat->get_summary_visibility($course->id);
			$showtopic0attop = ($summarystatus->showsummary == 1);

			$o = '';

			// Get the section images for the course.
			$sectionimages = $gridformat->get_images($course->id);

			// CONTRIB-4099:...
			$gridimagepath = $gridformat->get_image_path();

			$visiblesections = \local_aclmodules\local\aclmodules::instance()->get_sections_available_for_user($course->id, false);

			for ($section = ($showtopic0attop) ? 1 : 0; $section <= $course->numsections; $section++) {

				$thissection = $modinfo->get_section_info($section);
				$showsection = $thissection->uservisible ||
						($thissection->visible && !$thissection->available &&
						!empty($thissection->availableinfo));

				$showsection = ($showsection and (isset($visiblesections[$section])));

				if ($showsection) {

					$sectionname = $gridformat->get_section_name($thissection);

					// Ensure the record exists.
					if (($sectionimages === false) || (!array_key_exists($thissection->id, $sectionimages))) {
						// ...get_image has 'repair' functionality for when there are issues with the data.
						$sectionimage = $gridformat->get_image($course->id, $thissection->id);
					} else {
						$sectionimage = $sectionimages[$thissection->id];
					}

					// If the image is set then check that displayedimageindex is greater than 0 otherwise create the displayed image.
					// This is a catch-all for existing courses.
					if (isset($sectionimage->image) && ($sectionimage->displayedimageindex < 1)) {
						// Set up the displayed image:...
						$sectionimage->newimage = $sectionimage->image;
						$sectionimage = $gridformat->setup_displayed_image($sectionimage, $context->id, $gridformat->get_settings(), null);
					}

					$showimg = false;
					if (is_object($sectionimage) && ($sectionimage->displayedimageindex > 0)) {
						$imgurl = moodle_url::make_pluginfile_url(
										$context->id, 'course', 'section', $thissection->id,
								$gridimagepath, $sectionimage->displayedimageindex . '_' . $sectionimage->image
						);
						$showimg = true;
					} else if ($section == 0) {
						$imgurl = $OUTPUT->image_url('info', 'format_grid');
						$showimg = true;
					}

					$title = '';
					if ($showimg) {
						$img = html_writer::empty_tag('img', array(
									'src' => $imgurl,
									'alt' => $sectionname,
									'role' => 'img',
									'aria-label' => $sectionname));
						$title = html_writer::tag('div', $img, array('class' => 'image_holder'));
					}

					$title .= html_writer::tag('p', $sectionname, array('class' => 'icon_content'));

					$url = course_get_url($course, $thissection->section);
					if ($url) {
						$title = html_writer::link($url, $title, array(
									'id' => 'gridsection-' . $thissection->section,
									'role' => 'link',
									'class' => 'gridicon_link',
									'aria-label' => $sectionname));
					}

					$liattributes = array(
						'role' => 'region',
						'aria-label' => $sectionname
					);
					$o .= html_writer::tag('li', $title, $liattributes);
				}

				// ... load additional javascript only when not on course view page.

				if (($PAGE->pagetype != 'course-view-grid') and ($course->coursedisplay == 0)) {
					$args = array();
					$args['courseid'] = $COURSE->id;
					$PAGE->requires->yui_module('moodle-block_acl_coursenavigation-module',
							'M.block_acl_coursenavigation.init', array($args));
				}
			}		
			return html_writer::tag('ul', $o, array('class' => 'gridicons'));
		}
    }

    /** render the content of the course navigation block
     *
     * @param record $course
     * @param format_base $format
     * @param string $viewtype
     * @return string
     */
    public function render_content($course, $format, $viewtype) {

        $course->numsections = course_get_format($course)->get_last_section_number();

        if ($viewtype == 'min') {

            $url = new moodle_url('/course/view.php', array('id' => $course->id));
            return html_writer::link($url, $course->fullname);
        }

        if ($viewtype == 'midi') {

            return $this->render_topics($course);
        }

        if ($viewtype == 'max') {

            $o = '';

            $url = new moodle_url('/course/view.php', array('id' => $course->id));
            $link = html_writer::link($url, $course->fullname);

            $o .= html_writer::tag('div', $link, array('class' => 'course-link'));

            if ($format->get_format() == 'grid') {

                $o .= $this->render_grids($course, $format);
            } else {
                $o .= $this->render_topics($course);
            }
            $o .= html_writer::tag('div', '', array('class' => 'clearfix'));

            return $o;
        }
    }

}