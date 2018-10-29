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

/** this is the main class doing all the reporting actions
 * 
 */
class report {

    private function __construct() {
        // Make this function private, to avoid direct call.
    }

    /** create instance as a singleton */
    public static function instance() {
        static $report;

        if (isset($report)) {
            return $report;
        }

        $report = new report();
        return $report;
    }

    /** get all the grades for all modules of a user in a course
     *
     * @global database $DB
     * @param int $courseid
     * @param int $userid
     * @return array list of grades grouped by modulename and instanceid (id of data in mdl_[modulename] table)
     */
    protected function get_users_module_grades($courseid, $userid) {
        global $DB;

        $sql = "SELECT g.*, gi.itemtype, gi.itemmodule, gi.iteminstance FROM {grade_items} gi
                JOIN {grade_grades} g ON g.itemid = gi.id
                WHERE g.userid = ? AND gi.courseid = ?";

        if (!$grades = $DB->get_records_sql($sql, array($userid, $courseid))) {
            return array();
        }

        $modulegrades = array();
        foreach ($grades as $grade) {
            if ($grade->itemtype == 'mod') {
                if (!isset($modulegrades[$grade->itemmodule])) {
                    $modulegrades[$grade->itemmodule] = array();
                }
                $modulegrades[$grade->itemmodule][$grade->iteminstance] = $grade;
            }
        }
        return $modulegrades;
    }

    /** get all the users completioninfo in a course, if a module has not activated
     *  completion tracking attibute tracking[$cmid] of returned data object is COMPLETION_TRACKING_NONE.
     *
     * @param type $course
     * @param type $modinfo
     * @param type $userid
     * @return object oject which contains two tracking and state list for the modules.
     */
    protected function get_users_completioninfo($course, $modinfo, $userid) {

        $completioninfo = new \completion_info($course);

        $mods = $modinfo->get_cms();

        $completiondata = new \stdClass();
        $completiondata->tracking = array();
        $completiondata->state = array();

        foreach ($mods as $cmid => $cm) {

            $completiondata->tracking[$cmid] = $completioninfo->is_enabled($cm);

            if ($completiondata->tracking[$cmid] != COMPLETION_TRACKING_NONE) {

                $completion = $completioninfo->get_data($cm, true, $userid, $modinfo);
                $completiondata->state[$cmid] = $completion->completionstate;
            }
        }

        return $completiondata;
    }

    /** get all the users modmessages grouped by the modules
     * 
     * @param record $course
     * @return array
     */
    protected function get_users_comments($course, $userid) {
        $messages = modmessages::instance();
        $messageinfodata = $messages->get_modmessages_info($course, $userid);
        return $messageinfodata;
    }

    /** get all the users ratings grouped by the modules
     *
     * @param object $course
     * @param int $userid
     * @return array
     */
    protected function get_users_ratings($course, $userid) {
        $ratings = modrating::instance();
        return $ratings->get_users_rating($course, $userid);
    }

    /** get then users reportsdata:
     * array('userid' => array('topicid' => array('cmid' => array('name', 'grade', 'completion', 'rating', 'comments'))));
     *
     * @param object $course
     * @param int $userid
     */
    public function get_users_reportsdata($course, $userid) {
        global $CFG;

        require_once($CFG->dirroot . '/lib/completionlib.php');

        // ...get fast modinfo for given user.
        $modinfo = get_fast_modinfo($course->id, $userid);

        $reportsdata = new \stdClass();

        // ... grades.
        $reportsdata->grades = $this->get_users_module_grades($course->id, $userid);

        // ...completioninfo.
        $reportsdata->completion = $this->get_users_completioninfo($course, $modinfo, $userid);

        // ...ratings.
        $reportsdata->ratings = $this->get_users_ratings($course, $userid);
        $reportsdata->rateable = array();

        // ...commentsinfo.
        $reportsdata->commentinfodata = $this->get_users_comments($course, $userid);
        $reportsdata->commentable = array();

        // ...sections and modules.
        $reportsdata->sections = array();

        // ... get all the rateable modules.
        $aclmodules = aclmodules::instance();

        foreach ($modinfo->get_section_info_all() as $thissection) {

            $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available && $thissection->showavailability
                    && !empty($thissection->availableinfo));

            if (!$showsection) {
                continue;
            }

            if (!empty($modinfo->sections[$thissection->section])) {

                $reportsdata->sections[$thissection->section] = new \stdClass();
                $reportsdata->sections[$thissection->section]->sectionname = get_section_name($course, $thissection);
                $reportsdata->sections[$thissection->section]->mods = array();

                // ... get visible mods.
                foreach ($modinfo->sections[$thissection->section] as $modnum) {

                    $mod = $modinfo->cms[$modnum];

                    $reportsdata->rateable[$modnum] = $aclmodules->get_acl_mod_config_value($course, $mod, 'modulestorate');
                    $reportsdata->commentable[$modnum] = $aclmodules->get_acl_mod_config_value($course, $mod, 'modulestocomment');

                    if ($mod->uservisible) {
                        $reportsdata->sections[$thissection->section]->mods[$modnum] = $mod;
                    }
                }
            }
        }

        return $reportsdata;
    }

    /** aggregate completioninfo for each section of the course
     * 
     * @global record $USER
     * @param record $course
     * @param array $sections
     * @param object $modinfo
     * @param int $userid
     * @return \\stdClass completiondata stored as a object
     */
    protected function get_sections_completioninfo($course, $sections, $modinfo,
                                                   $userid = null) {
        global $USER;

        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // ... create completioninfo.
        $completioninfo = new \completion_info($course, $userid);

        $completiondata = array();

        foreach ($sections as $thissection) {

            if (empty($modinfo->sections[$thissection->section])) {
                continue;
            }

            $data = new \stdClass();
            $data->completion = array();
            $data->itemscompleted = 0;
            $data->itemstracked = 0;

            foreach ($modinfo->sections[$thissection->section] as $cmid) {

                $cm = $modinfo->get_cm($cmid);

                // ... don't count non visible coursemodules.
                if (!$cm->uservisible) {
                    continue;
                }

                $comenabled = $completioninfo->is_enabled($cm);

                if ($comenabled != COMPLETION_TRACKING_NONE) {

                    // ... get completion data.
                    $completion = $completioninfo->get_data($cm, true, $userid, $modinfo);

                    $data->completion[$cmid] = $completion;
                    $data->itemscompleted += $completion->completionstate;
                    $data->itemstracked++;
                } else {

                    $data->completion[$cmid] = false;
                }
            }
            $completiondata[$thissection->section] = $data;
        }
        return $completiondata;
    }

    /** get sql statment for gradeable users in the course
     *
     * @global database $DB
     * @global type $CFG
     * @param type $coursecontext
     * @param type $order
     * @param type $groupid
     * @param type $extrajoin
     * @param type $extrawhere
     * @param type $extraparams
     * @param type $fields
     * @return type
     */
    protected function get_gradable_users_sql($coursecontext, $order = '',
                                              $groupid = 0, $extrajoin = '',
                                              $extrawhere = '',
                                              $extraparams = array(),
                                              $fields = "u.*", $limitstart = '',
                                              $limitcount = '') {

        global $DB, $CFG;

        list($gradebookrolessql, $params) =
                $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr');

        list($enrolledsql, $enrolledparams) = get_enrolled_sql($coursecontext);
        $params = array_merge($params, $enrolledparams);

        list($relatedcontexts, $ctxparams) = $DB->get_in_or_equal(
                $coursecontext->get_parent_context_ids(true), SQL_PARAMS_NAMED);

        $params = array_merge($params, $ctxparams);

        if ($groupid) {
            $groupsql = "INNER JOIN {groups_members} gm ON gm.userid = u.id";
            $groupwheresql = "AND gm.groupid = :groupid";
            $params['groupid'] = $groupid;
        } else {
            $groupsql = "";
            $groupwheresql = "";
        }
        $limit = '';
        if (!empty($limitcount)) {

            if (!empty($limitstart)) {
                $limit = " LIMIT {$limitstart}, {$limitcount}";
            } else {
                $limit = " LIMIT {$limitcount}";
            }
        }

        if (!empty($order)) {
            $orderby = "ORDER BY {$order}";
        } else {
            $orderby = '';
        }

        $userssql = "SELECT $fields
                        FROM {user} u
                        JOIN ($enrolledsql) je ON je.id = u.id
                             $groupsql
                        JOIN (
                                  SELECT DISTINCT ra.userid
                                    FROM {role_assignments} ra
                                   WHERE ra.roleid $gradebookrolessql
                                     AND ra.contextid $relatedcontexts
                             ) rainner ON rainner.userid = u.id
                        {$extrajoin}
                         WHERE u.deleted = 0
                             $groupwheresql
                             $extrawhere
                             $orderby {$limit}";

        $params = array_merge($params, $extraparams);
        return array($userssql, $params);
    }

    /** count all gradable users for paging in report
     * 
     * @global database $DB
     * @param type $coursecontext
     * @param type $groupid
     * @param type $extrajoin
     * @param type $extrawhere
     * @param type $extraparams
     * @return type
     */
    public function count_all_gradable_users($coursecontext, $groupid = 0,
                                             $extrajoin = '', $extrawhere = '',
                                             $extraparams = array()) {
        global $DB;

        list($countsql, $params) = $this->get_gradable_users_sql(
                $coursecontext, '', $groupid, $extrajoin, $extrawhere, $extraparams, 'count(*)'
        );

        return $DB->count_records_sql($countsql, $params);
    }

    /**  get all gradable users from the course
     *
     * @global database $DB
     * @param context $coursecontext
     * @param string $order
     * @param int $groupid
     * @return array list of user records which are gradable.
     */
    public function get_all_gradable_userdata($coursecontext,
                                              $order = 'lastname', $groupid = 0,
                                              $extrajoin = '', $extrawhere = '',
                                              $extraparams = array(),
                                              $limitstart = '',
                                              $limitcount = '', $fields = "u.*") {
        global $DB;

        list($userssql, $params) = $this->get_gradable_users_sql(
                $coursecontext, $order, $groupid, $extrajoin, $extrawhere, $extraparams, $fields, $limitstart, $limitcount
        );

        if (!$users = $DB->get_records_sql($userssql, $params)) {
            return array();
        }

        return $users;
    }

}