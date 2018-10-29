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

//require_once($CFG->dirroot . '/theme/bootstrapbase/renderers.php');

/**
 * Clean core renderers.
 *
 * @package    theme_clean
 * @copyright  2015 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_rlp_clean_core_renderer extends theme_bootstrapbase_core_renderer {

    /**
     * Either returns the parent version of the header bar, or a version with the logo replacing the header.
     *
     * @since Moodle 2.9
     * @param array $headerinfo An array of header information, dependant on what type of header is being displayed. The following
     *                          array example is user specific.
     *                          heading => Override the page heading.
     *                          user => User object.
     *                          usercontext => user context.
     * @param int $headinglevel What level the 'h' tag will be.
     * @return string HTML for the header bar.
     */
    public function context_header($headerinfo = null, $headinglevel = 1) {

        if ($this->should_render_logo($headinglevel)) {
            return html_writer::tag('div', '', array('class' => 'logo'));
        }
        return parent::context_header($headerinfo, $headinglevel);
    }

    /**
     * Determines if we should render the logo.
     *
     * @param int $headinglevel What level the 'h' tag will be.
     * @return bool Should the logo be rendered.
     */
    protected function should_render_logo($headinglevel = 1) {
        global $PAGE;

        // Only render the logo if we're on the front page or login page
        // and the theme has a logo.
        $logo = $this->get_logo_url();
        if ($headinglevel == 1 && !empty($logo)) {
            if ($PAGE->pagelayout == 'frontpage' || $PAGE->pagelayout == 'login') {
                return true;
            }
        }

        return false;
    }
    
    public function course_content_header($onlyifnotcalledbefore = false) {
        global $CFG, $COURSE;

        $header = parent::course_content_header($onlyifnotcalledbefore);

        // ...we need this to make sure that notification page can be called, no need for tabs in mein course.
        if ($COURSE->id == SITEID) {
            return $header;
        }

        $pluginactive = file_exists($CFG->dirroot . '/local/aclmodules/lib.php');
        $pluginactive = ($pluginactive and \local_aclmodules\local\aclmodules::is_active($COURSE));

        if ($pluginactive) {
            $header .= \local_aclmodules\local\aclmodules::render_tabs();
        }
        return $header;
    }	    

    /**
     * Returns the navigation bar home reference.
     *
     * The small logo is only rendered on pages where the logo is not displayed.
     *
     * @param bool $returnlink Whether to wrap the icon and the site name in links or not
     * @return string The site name, the small logo or both depending on the theme settings.
     */
    public function navbar_home($returnlink = true) {
        global $CFG;

        $imageurl = $this->get_compact_logo_url(null, 35);
        if ($this->should_render_logo() || empty($imageurl)) {
            // If there is no small logo we always show the site name.
            return $this->get_home_ref($returnlink);
        }
        $image = html_writer::img($imageurl, get_string('sitelogo', 'theme_' . $this->page->theme->name),
            array('class' => 'small-logo'));

        if ($returnlink) {
            $logocontainer = html_writer::link(new moodle_url('/'), $image,
                array('class' => 'small-logo-container', 'title' => get_string('home')));
        } else {
            $logocontainer = html_writer::tag('span', $image, array('class' => 'small-logo-container'));
        }

        // Sitename setting defaults to true.
        if (!isset($this->page->theme->settings->sitename) || !empty($this->page->theme->settings->sitename)) {
            return $logocontainer . $this->get_home_ref($returnlink);
        }

        return $logocontainer;
    }

    /**
     * Returns a reference to the site home.
     *
     * It can be either a link or a span.
     *
     * @param bool $returnlink
     * @return string
     */
    protected function get_home_ref($returnlink = true) {
        global $CFG, $SITE;

        $sitename = format_string($SITE->shortname, true, array('context' => context_course::instance(SITEID)));

        if ($returnlink) {
            return html_writer::link(new moodle_url('/'), $sitename, array('class' => 'brand', 'title' => get_string('home')));
        }

        return html_writer::tag('span', $sitename, array('class' => 'brand'));
    }

    /**
     * Return the theme logo URL, else the site's logo URL, if any.
     *
     * Note that maximum sizes are not applied to the theme logo.
     *
     * @param int $maxwidth The maximum width, or null when the maximum width does not matter.
     * @param int $maxheight The maximum height, or null when the maximum height does not matter.
     * @return moodle_url|false
     */
    public function get_logo_url($maxwidth = null, $maxheight = 100) {
        global $CFG;

        if (!empty($this->page->theme->settings->logo)) {
            $url = $this->page->theme->setting_file_url('logo', 'logo');
            // Get a URL suitable for moodle_url.
            $relativebaseurl = preg_replace('|^https?://|i', '//', $CFG->wwwroot);
            $url = str_replace($relativebaseurl, '', $url);
            return new moodle_url($url);
        }
        return parent::get_logo_url($maxwidth, $maxheight);
    }

    /**
     * Return the theme's compact logo URL, else the site's compact logo URL, if any.
     *
     * Note that maximum sizes are not applied to the theme logo.
     *
     * @param int $maxwidth The maximum width, or null when the maximum width does not matter.
     * @param int $maxheight The maximum height, or null when the maximum height does not matter.
     * @return moodle_url|false
     */
    public function get_compact_logo_url($maxwidth = 100, $maxheight = 100) {
        global $CFG;

        if (!empty($this->page->theme->settings->smalllogo)) {
            $url = $this->page->theme->setting_file_url('smalllogo', 'smalllogo');
            // Get a URL suitable for moodle_url.
            $relativebaseurl = preg_replace('|^https?://|i', '//', $CFG->wwwroot);
            $url = str_replace($relativebaseurl, '', $url);
            return new moodle_url($url);
        }
        return parent::get_compact_logo_url($maxwidth, $maxheight);
    }

    /**
     * Overridden renderer to remove blocks depending on settings in local_authoringcapability plugin.
     *
     * Produces the content area for a block
     *
     * @param block_contents $bc
     * @return string
     */
    protected function block_content(block_contents $bc) {
        // SYNERGY-LEARNING: remove non available blocks from block_admin block.
        global $CFG;

        if ($bc->attributes['data-block'] == 'adminblock') {

            if (file_exists($CFG->dirroot . '/local/authoringcapability/classes/local/corechanges.php')) {
                $bc->content = \local_authoringcapability\local\corechanges::hide_block_ui_items($this->page, $bc);
            }

        }
        // SYNERGY-LEARNING: remove non available blocks from block_admin block.

        $output = html_writer::start_tag('div', array('class' => 'content'));
        if (!$bc->title && !$this->block_controls($bc->controls)) {
            $output .= html_writer::tag('div', '', array('class' => 'block_action notitle'));
        }

        $output .= $bc->content;
        $output .= $this->block_footer($bc);
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Overridden renderer to add body classes for hiding some mod form elements
     * in mod forms by local_authoringcapability plugin.
     *
     * It only uses to execute code before outputting header.
     * Added by SYNERGY-LEARNING: PLR-1.
     *
     * @return string
     */
    public function header() {
        global $CFG;

        $matches = array();
        if (preg_match('/mod-(.*)?-mod/', $this->page->pagetype, $matches)) {
            if (file_exists($CFG->dirroot . '/local/authoringcapability/classes/local/corechanges.php')) {
                \local_authoringcapability\local\corechanges::hide_mod_form_items($this->page);
            }
        }
        
        if (file_exists($CFG->dirroot . '/local/aclmodules/classes/local/aclmodules.php'))  { 
        	$set = \local_aclmodules\local\aclmodules::setActive(1); 
        }       

        return parent::header();
    }
}
