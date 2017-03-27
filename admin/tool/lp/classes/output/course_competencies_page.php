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
 * Class containing data for course competencies page
 *
 * @package    tool_lp
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lp\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use context_system;
use context_course;
use tool_lp\api;

/**
 * Class containing data for course competencies page
 *
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_competencies_page implements renderable, templatable {

    /** @var stdClass $course Course record for this page. */
    var $course = null;

    /**
     * Construct this renderable.
     * @param stdClass $course The course record for this page.
     */
    public function __construct($courseid) {
        $context = context_course::instance($courseid);
        $this->courseid = $courseid;
        $this->competencies = api::list_competencies_in_course($courseid);
        $this->canmanagecompetencyframeworks = has_capability('tool/lp:competencymanage', context_system::instance());
        $this->canmanagecoursecompetencies = has_capability('tool/lp:coursecompetencymanage', $context);
        $this->manageurl = new moodle_url('/admin/tool/lp/competencyframeworks.php');
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->courseid = $this->courseid;
        $data->competencies = array();
        foreach ($this->competencies as $competency) {
            $record = $competency->to_record();
            array_push($data->competencies, $record);
        }
        $data->canmanagecompetencyframeworks = $this->canmanagecompetencyframeworks;
        $data->canmanagecoursecompetencies = $this->canmanagecoursecompetencies;
        $data->manageurl = $this->manageurl->out(true);

        return $data;
    }
}
