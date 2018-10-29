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
 * Mod messages, provide messages related to a single module.
 *
 * @package   local_aclmodules
 * @copyright 2013 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aclmodules\local;

require_once($CFG->dirroot . '/rating/lib.php');

/** this class is used as an adapter to moodles core rating functionality.
 *  it retrieves the ratings of a course only one time per request to speed up performance.
 * 
 * IMPORTANT: Note that this class is derived from the rating_manager
 */
class modrating extends \rating_manager {

    // The rating of a user grouped by module ids, userid => array(mods).
    protected $userratings = array();

    private function __construct() {
        // Make this contructor private, to avoid direct call.
    }

    /** create instance as a singleton, to user attributes as request cache. */
    public static function instance() {
        static $modrating;

        if (isset($modrating)) {
            return $modrating;
        }

        $modrating = new modrating();
        return $modrating;
    }

    /** get default options to retrieve all ratings by rting manager 
     * 
     * @param object $modinfo modifon-object of current course.
     * @return object the rating options object
     */
    protected function get_default_options($modinfo) {

        $course = $modinfo->get_course();

        $ratingoptions = new \stdClass;
        $ratingoptions->context = \context_course::instance($course->id);

        $ratingoptions->component = 'local_aclmodules';
        $ratingoptions->ratingarea = 'coursemodule';

        // ... to get ratings for all coursemodules provide ids here.
        $moditems = array();
        foreach ($modinfo->get_cms() as $cm) {
            $moditems[$cm->id] = (object) array('id' => $cm->id);
        }

        $ratingoptions->items = $moditems;

        $ratingoptions->aggregate = 1;

        // Use values < 0 to specify a non numeric global scale.
        $config = get_config('local_aclmodules');
        $ratingoptions->scaleid = $config->modratingscale;

        $ratingoptions->assesstimestart = 0;
        $ratingoptions->assesstimefinish = time() + 3600;

        return $ratingoptions;
    }

    /** get user */
    public function add_users_rating($course, $userid) {

        if (isset($this->userratings[$userid])) {
            return $this->userratings[$userid];
        }

        $modinfo = get_fast_modinfo($course);

        // ...prepare rating manager to retrieve ratings.
        $ratingoptions = $this->get_default_options($modinfo);
        $ratingoptions->userid = $userid;

        // ...retrieve ratings.
        $this->userratings[$userid] = $this->get_ratings($ratingoptions);
        return $this->userratings[$userid];
    }

    /** get users and and average ratings for one coursemodule
     * 
     * @global type $USER
     * @param type $mod
     * @param type $userid
     * @return boolean|float
     */
    public function get_users_cm_rating($mod, $userid = 0) {
        global $USER;

        $userid = (empty($userid)) ? $USER->id : $userid;

        $course = $mod->get_course();
        $userratings = $this->add_users_rating($course, $userid);

        if ($userratings[$mod->id]->rating) {

            return $userratings[$mod->id]->rating;
        } else {

            return false;
        }
    }

    /** get users rating for all course modules
     * 
     * @param record $course
     * @param int $userid
     * @return array
     */
    public function get_users_rating($course, $userid) {

        $coursemodules = $this->add_users_rating($course, $userid);

        $userratings = array();
        foreach ($coursemodules as $cm) {
            $userratings[$cm->id] = $cm->rating;
        }
        return $userratings;
    }

    public static function render_rating($mod, $course) {
        global $OUTPUT;

        // ... check if module should is rateable, local_aclmodules needed.
        $aclmodules = aclmodules::instance();
        $rateable = $aclmodules->get_acl_mod_config_value($course, $mod, 'modulestorate');

        if (!$rateable) {
            return '';
        }

        $modrating = self::instance();

        if ($rating = $modrating->get_users_cm_rating($mod)) {
            return \html_writer::tag('div', $OUTPUT->render($rating), array('class' => 'modrating'));
        }
        return '';
    }

}