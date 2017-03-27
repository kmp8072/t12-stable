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
 * Restore file.
 *
 * @package    tool_lp
 * @copyright  2015 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/moodle2/restore_tool_plugin.class.php');

/**
 * Restore class.
 *
 * @package    tool_lp
 * @copyright  2015 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_tool_lp_plugin extends restore_tool_plugin {

    /**
     * Return the paths.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = array(
            new restore_path_element('course_competency', $this->get_pathfor('/course_competencies/competency')),
            new restore_path_element('course_competency_settings', $this->get_pathfor('/course_competency_settings'))
        );
        return $paths;
    }

    /**
     * Return the paths.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        $paths = array(
            new restore_path_element('course_module_competency', $this->get_pathfor('/course_module_competencies/competency'))
        );
        return $paths;
    }

    /**
     * Process a course competency settings.
     *
     * @param  array $data The data.
     */
    public function process_course_competency_settings($data) {
        global $DB;

        $data = (object) $data;
        $courseid = $this->task->get_courseid();
        $exists = \tool_lp\course_competency_settings::get_record(array('courseid' => $courseid));

        // Now update or insert.
        if ($exists) {
            $settings = $exists;
            $settings->set_pushratingstouserplans($data->pushratingstouserplans);
            return $settings->update();
        } else {
            $data = (object) array('courseid' => $courseid, 'pushratingstouserplans' => $data->pushratingstouserplans);
            $settings = new \tool_lp\course_competency_settings(0, $data);
            return !empty($settings->create());
        }
    }

    /**
     * Process a course competency.
     *
     * @param  array $data The data.
     */
    public function process_course_competency($data) {
        $data = (object) $data;

        // Mapping the competency by ID numbers.
        $framework = \tool_lp\competency_framework::get_record(array('idnumber' => $data->frameworkidnumber));
        if (!$framework) {
            return;
        }
        $competency = \tool_lp\competency::get_record(array('idnumber' => $data->idnumber,
            'competencyframeworkid' => $framework->get_id()));
        if (!$competency) {
            return;
        }

        $params = array(
            'competencyid' => $competency->get_id(),
            'courseid' => $this->task->get_courseid()
        );
        $query = 'competencyid = :competencyid AND courseid = :courseid';
        $existing = \tool_lp\course_competency::record_exists_select($query, $params);

        if (!$existing) {
            // Sortorder is ignored by precaution, anyway we should walk through the records in the right order.
            $record = (object) $params;
            $record->ruleoutcome = $data->ruleoutcome;
            $coursecompetency = new \tool_lp\course_competency(0, $record);
            $coursecompetency->create();
        }

    }

    /**
     * Process a course module competency.
     *
     * @param  array $data The data.
     */
    public function process_course_module_competency($data) {
        $data = (object) $data;

        // Mapping the competency by ID numbers.
        $framework = \tool_lp\competency_framework::get_record(array('idnumber' => $data->frameworkidnumber));
        if (!$framework) {
            return;
        }
        $competency = \tool_lp\competency::get_record(array('idnumber' => $data->idnumber,
            'competencyframeworkid' => $framework->get_id()));
        if (!$competency) {
            return;
        }

        $params = array(
            'competencyid' => $competency->get_id(),
            'cmid' => $this->task->get_moduleid()
        );
        $query = 'competencyid = :competencyid AND cmid = :cmid';
        $existing = \tool_lp\course_module_competency::record_exists_select($query, $params);

        if (!$existing) {
            // Sortorder is ignored by precaution, anyway we should walk through the records in the right order.
            $record = (object) $params;
            $record->ruleoutcome = $data->ruleoutcome;
            $coursemodulecompetency = new \tool_lp\course_module_competency(0, $record);
            $coursemodulecompetency->create();
        }

    }

}
