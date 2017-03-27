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
 * User competency page class.
 *
 * @package    tool_lp
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lp\output;

use renderable;
use renderer_base;
use templatable;
use tool_lp\api;
use tool_lp\user_competency;
use tool_lp\external\user_competency_summary_in_course_exporter;

/**
 * User competency page class.
 *
 * @package    tool_lp
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_competency_summary_in_course implements renderable, templatable {

    /** @var userid */
    protected $userid;

    /** @var competencyid */
    protected $competencyid;

    /** @var courseid */
    protected $courseid;

    /**
     * Construct.
     *
     * @param int $userid
     * @param int $competencyid
     * @param int $courseid
     */
    public function __construct($userid, $competencyid, $courseid) {
        $this->userid = $userid;
        $this->competencyid = $competencyid;
        $this->courseid = $courseid;
    }

    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB;

        $usercompetency = api::get_user_competency_in_course($this->courseid, $this->userid, $this->competencyid);
        $competency = $usercompetency->get_competency();
        if (empty($usercompetency) || empty($competency)) {
            throw new invalid_parameter_exception('Invalid params. The competency does not belong to the course.');
        }

        $relatedcompetencies = api::list_related_competencies($competency->get_id());
        $user = $DB->get_record('user', array('id' => $this->userid));
        $evidence = api::list_evidence_in_course($this->userid, $this->courseid, $this->competencyid);
        $course = $DB->get_record('course', array('id' => $this->courseid));

        $params = array(
            'competency' => $competency,
            'usercompetency' => $usercompetency,
            'evidence' => $evidence,
            'user' => $user,
            'course' => $course,
            'relatedcompetencies' => $relatedcompetencies
        );
        $exporter = new user_competency_summary_in_course_exporter(null, $params);
        $data = $exporter->export($output);

        // Some adjustments specific to course.
        $data->usercompetencysummary->cangrade = user_competency::can_grade_user_in_course($this->userid, $this->courseid);

        return $data;
    }
}
