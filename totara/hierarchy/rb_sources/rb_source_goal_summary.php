<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_goal_summary extends rb_base_source {
    public $shortname;

    /**
     * Stored during post_config so that it can be used later.
     *
     * @var int
     */
    private $goalframeworkid;


    public function __construct($groupid, rb_global_restriction_set $globalrestrictionset = null) {
        if ($groupid instanceof rb_global_restriction_set) {
            throw new coding_exception('Wrong parameter orders detected during report source instantiation.');
        }
        // Remember the active global restriction set.
        $this->globalrestrictionset = $globalrestrictionset;

        // Global Report Restrictions are applied in define_joinlist() and post_params() methods.

        $this->base = '{goal}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->embeddedparams = $this->define_embeddedparams();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_goal_summary');
        $this->shortname = 'goal_summary';
        $this->cacheable = false;
        $this->usedcomponents[] = 'totara_hierarchy';

        parent::__construct();
    }

    /**
     * Hide this source if feature disabled or hidden.
     * @return bool
     */
    public function is_ignored() {
        return !totara_feature_visible('goals');
    }

    /**
     * Global report restrictions are implemented in this source.
     * @return boolean
     */
    public function global_restrictions_supported() {
        return true;
    }

    protected function define_joinlist() {
        $global_restriction_join_gr = $this->get_global_report_restriction_join('gr', 'userid');

        $joinlist = array(
            new rb_join(
                'numberassigned',
                'LEFT',
                "(SELECT goalid, COUNT(gr.id) c
                        FROM {goal_record} gr
                        {$global_restriction_join_gr}
                       WHERE deleted = 0
                       GROUP BY goalid)",
                'numberassigned.goalid = base.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            // This join is required to keep the joining of goal custom fields happy.
            new rb_join(
                'goal',
                'INNER',
                '{goal}',
                'base.id = goal.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'goaltype',
                'LEFT',
                '{goal_type}',
                'goal.typeid = goaltype.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'goal'
            )
        );

        return $joinlist;
    }


    protected function define_columnoptions() {
        $columnoptions = array(
            new rb_column_option(
                'goal',
                'goalid',
                '',
                'base.id',
                array('selectable' => false)
            ),
            new rb_column_option(
                'goal',
                'name',
                get_string('goalnamecolumn', 'rb_source_goal_summary'),
                'base.fullname',
                array('defaultheading' => get_string('goalnameheading', 'rb_source_goal_summary'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text',
                      'displayfunc' => 'format_string')
            ),
            new rb_column_option(
                'goal',
                'namesummarylink',
                get_string('goalnamesummarylinkcolumn', 'rb_source_goal_summary'),
                'base.fullname',
                array('displayfunc' => 'goal_name_summary_link',
                      'extrafields' => array('goalid' => "base.id"),
                      'defaultheading' => get_string('goalnamesummarylinkheading', 'rb_source_goal_summary'))
            ),
            new rb_column_option(
                'goal',
                'numberofusersassigned',
                get_string('goalnumberofusersassignedcolumn', 'rb_source_goal_summary'),
                'COALESCE(numberassigned.c, 0)',
                array('joins' => 'numberassigned',
                      'defaultheading' => get_string('goalnumberofusersassignedheading', 'rb_source_goal_summary'),
                      'dbdatatype' => 'integer',
                      'displayfunc' => 'integer'
                )
            ),
            new rb_column_option(
                'goal',
                'scalevalues',
                get_string('goalscalevaluescolumn', 'rb_source_goal_summary'),
                'scalevalues_',
                array('columngenerator' => 'scalevalues',
                      'displayfunc' => 'integer',
                      'defaultheading' => get_string('goalscalevaluesheading', 'rb_source_goal_summary'))
            ),
            new rb_column_option(
                'goal_type',
                'name',
                get_string('goaltypename', 'rb_source_goal_summary'),
                'goaltype.fullname',
                array(
                    'joins' => 'goaltype',
                    'displayfunc' => 'format_string'
                )
            ),
            new rb_column_option(
                'goal_type',
                'typeid',
                get_string('goaltypename', 'rb_source_goal_summary'),
                'goaltype.id',
                array(
                    'joins' => 'goaltype',
                    'selectable' => false
                )
            )
        );

        return $columnoptions;
    }


    public function post_params(reportbuilder $report) {
        global $DB;

        $this->goalframeworkid = $report->get_param_value('goalframeworkid');

        $this->set_redirect(new moodle_url('/totara/hierarchy/rb_sources/goalsummaryselector.php',
                array('summaryreportid' => $report->_id)),
                get_string('selectgoalframework', 'totara_hierarchy'));

        // If the id was not specified then redirect to the selection page.
        if (!$this->goalframeworkid) {
            $this->needs_redirect();
            return;
        }

        $scaleassignment = $DB->get_record('goal_scale_assignments', array('frameworkid' => $this->goalframeworkid));

        $scalevalues = $DB->get_records('goal_scale_values', array('scaleid' => $scaleassignment->scaleid));

        foreach ($scalevalues as $scalevalue) {
            $alias = "gr{$scalevalue->id}";
            $global_restriction_join_grx = $this->get_global_report_restriction_join($alias, 'userid');
            $this->joinlist[] =
                new rb_join(
                    "goalrecord" . $scalevalue->id,
                    "LEFT",
                    "(SELECT goalid, COUNT({$alias}.id) c
                        FROM {goal_record} {$alias}
                        {$global_restriction_join_grx}
                       WHERE scalevalueid = {$scalevalue->id}
                         AND deleted = 0
                       GROUP BY goalid)",
                    "goalrecord" . $scalevalue->id . ".goalid = base.id"
                );
        }
    }


    public function rb_cols_generator_scalevalues($columnoption, $hidden) {
        global $DB;

        if (!$this->goalframeworkid) {
            return array();
        }

        $scaleassignment = $DB->get_record('goal_scale_assignments', array('frameworkid' => $this->goalframeworkid));

        $scalevalues = $DB->get_records('goal_scale_values', array('scaleid' => $scaleassignment->scaleid));

        $results = array();
        foreach ($scalevalues as $scalevalue) {
            $results[] =
                new rb_column(
                    "goalrecord" . $scalevalue->id,
                    "count",
                    $scalevalue->name,
                    "COALESCE(goalrecord" . $scalevalue->id . ".c, 0)",
                    array(
                        'joins' => array("goalrecord" . $scalevalue->id),
                        'displayfunc' => $columnoption->displayfunc,
                        'extrafields' => $columnoption->extrafields,
                        'required' => false,
                        'capability' => $columnoption->capability,
                        'noexport' => $columnoption->noexport,
                        'grouping' => $columnoption->grouping,
                        'nosort' => $columnoption->nosort,
                        'style' => $columnoption->style,
                        'class' => $columnoption->class,
                        'hidden' => $hidden
                    )
                );
        }

        return $results;
    }


    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'goal',
                'goalid',
                get_string('goalnamecolumn', 'rb_source_goal_summary'),
                'select',
                array('selectfunc' => 'goal')
            ),
            new rb_filter_option(
                'goal',
                'numberofusersassigned',
                get_string('goalnumberofusersassignedcolumn', 'rb_source_goal_summary'),
                'number'
            ),
            new rb_filter_option(
                'goal_type',
                'typeid',
                get_string('goaltypename', 'rb_source_goal_summary'),
                'select',
                array(
                    'selectfunc' => 'goal_type'
                )
            )
        );

        return $filteroptions;
    }


    /**
     * Filter goal.
     *
     * @return array
     */
    public function rb_filter_goal() {
        global $DB;

        $goals = array();

        if ($this->goalframeworkid) {
            $goallist = $DB->get_records('goal', array('frameworkid' => $this->goalframeworkid));
            foreach ($goallist as $goal) {
                $goals[$goal->id] = $goal->fullname;
            }
        }

        return $goals;
    }

    /**
     * Filter goal type
     *
     * @return array
     */
    public function rb_filter_goal_type() {
        global $DB;

        $goaltypes = array();

        $typelist = $DB->get_records('goal_type');
        foreach ($typelist as $type) {
            $goaltypes[$type->id] = $type->fullname;
        }

        return $goaltypes;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option('goalframeworkid', 'base.frameworkid')
        );

        return $paramoptions;
    }


    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'goal',
                'value' => 'name'
            ),
            array(
                'type' => 'goal',
                'value' => 'numberofusersassigned'
            ),
            array(
                'type' => 'goal',
                'value' => 'scalevalues'
            )
        );

        return $defaultcolumns;
    }


    protected function define_defaultfilters() {
        $defaultfilters = array();

        return $defaultfilters;
    }


    protected function define_embeddedparams() {
        $embeddedparams = array();

        return $embeddedparams;
    }

    /**
     * Link goal's name to summary report.
     *
     * @deprecated Since Totara 12.0
     * @param string $name
     * @param object Report row $row
     * @param bool $isexport optional false
     * @return string html link
     */
    public function rb_display_namesummarylink($name, $row, $isexport = false) {
        debugging('rb_source_goal_summary::rb_display_namesummarylink has been deprecated since Totara 12.0. Please use totara_hierarchy\rb\display\goal_name_summary_link::display', DEBUG_DEVELOPER);
        if ($isexport) {
            return $name;
        }
        if (empty($name)) {
            return '';
        }
        $url = new moodle_url('/totara/hierarchy/prefix/goal/statusreport.php',
                array('clearfilters' => 1, 'goalid' => $row->goalid));
        return html_writer::link($url, $name);
    }

}
