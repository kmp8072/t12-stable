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
 * Competencies to review renderable.
 *
 * @package    block_lp
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_lp\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use tool_lp\api;
use tool_lp\external\competency_exporter;
use tool_lp\external\user_competency_exporter;
use tool_lp\external\user_summary_exporter;
use moodle_url;

/**
 * Competencies to review renderable class.
 *
 * @package    block_lp
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competencies_to_review_page implements renderable, templatable {

    /** @var array Competencies to review. */
    protected $compstoreview;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->compstoreview = api::list_user_competencies_to_review(0, 1000);
    }

    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();

        $compstoreview = array();
        foreach ($this->compstoreview['competencies'] as $compdata) {
            $ucexporter = new user_competency_exporter($compdata->usercompetency,
                array('scale' => $compdata->competency->get_scale()));
            $compexporter = new competency_exporter($compdata->competency,
                array('context' => $compdata->competency->get_context()));
            $userexporter = new user_summary_exporter($compdata->user);
            $compstoreview[] = array(
                'usercompetency' => $ucexporter->export($output),
                'competency' => $compexporter->export($output),
                'user' => $userexporter->export($output),
            );
        }

        $data = array(
            'competencies' => $compstoreview,
            'cbebaseurl' => (new moodle_url('/admin/tool/lp'))->out(false),
            'pluginbaseurl' => (new moodle_url('/blocks/lp'))->out(false),
        );

        return $data;
    }

}
