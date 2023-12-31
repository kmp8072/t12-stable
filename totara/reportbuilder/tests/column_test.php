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
 * @author Simon Coggins <simonc@catalyst.net.nz>
 * @package totara_reportbuilder
 *
 * Unit tests to check source column definitions
 *
 * vendor/bin/phpunit totara_reportbuilder_column_testcase
 *
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');

/**
 * @group totara_reportbuilder
 */
class totara_reportbuilder_column_testcase extends reportcache_advanced_testcase {
    // Warning: Massive amount of test data ahead.
    protected $user_info_field_data = array(
       'id' => 1, 'shortname' => 'datejoined', 'name' => 'Date Joined', 'datatype' => 'text', 'description' => '', 'categoryid' => 1,
       'sortorder' => 1, 'required' => 0, 'locked' => 0, 'visible' => 1, 'forceunique' => 0, 'signup' => 0, 'defaultdata' => '',
       'param1' => 30, 'param2' => 2048, 'param3' => 0, 'param4' => '', 'param5' => '',
    );

    protected $user_info_data_data = array(
         'id' => 1, 'userid' => 2, 'fieldid' => 1, 'data' => 'test',
    );

    protected $org_framework_data = array(
        array(
            'id' => 1, 'fullname' => 'Organisation Framework 1', 'shortname' => 'OFW1', 'idnumber' => 'ID1', 'description' => 'Description 1',
            'sortorder' => 1, 'visible' => 1, 'hidecustomfields' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
    );

    protected $org_data = array(
        'id' => 1, 'fullname' => 'Distric Office', 'shortname' => 'DO', 'description' => '', 'idnumber' => '', 'frameworkid' => 1, 'path' => '/1',
        'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '01', 'visible' => 1, 'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2,
    );

    protected $pos_framework_data = array(
        'id' => 1, 'fullname' => 'Postion Framework 1', 'shortname' => 'PFW1', 'idnumber' => 'ID1', 'description' => 'Description 1',
        'sortorder' => 1, 'visible' => 1, 'hidecustomfields' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
    );

    protected $pos_data = array(
        'id' => 1, 'fullname' => 'Data Analyst', 'shortname' => 'Data Analyst', 'idnumber' => '', 'description' => '', 'frameworkid' => 1,
        'path' => '/1', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '01', 'visible' => 1, 'timevalidfrom' => 0, 'timevalidto' => 0,
        'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2,
    );

    protected $f2f_session_data_data = array(
        'id' => 1, 'fieldid' => 1, 'facetofacesessionid' => 1, 'data' => 'Training Centre',
    );

    protected $course_completions_data = array(
        'id' => 1, 'userid' => 2, 'course' => 1, 'organisationid' => 1, 'positionid' => 1, 'deleted' => 0, 'timenotified' => 0,
        'timestarted' => 1140606000, 'timeenrolled' => 1140606000, 'timecompleted' => 1140606000, 'reaggregate' => 0,
        'rpl' => '', 'rplgrade' => 0, 'status' => 0,
    );

    protected $course_completion_criteria_data = array(
        'id' => 1, 'course' => 2, 'criteriatype' => 6, 'gradepass' => 2,
    );

    protected $course_completion_crit_compl_data = array(
        'id' => 1, 'userid' => 2, 'course' => 2, 'criteriaid' => 1, 'gradefinal' => 2, 'deleted' => 0,
    );

    protected $log_data = array(
        'id' => 1, 'time' => 1140606000, 'userid' => 2, 'ip' => '192.168.2.133', 'course' => 1,
        'module' => 'user', 'cmid' => 0, 'action' => 'update', 'url' => 'view.php', 'info' => 1,
    );

    protected $course_data = array(
        'id' => 2, 'fullname' => 'Test Course 1', 'shortname' => 'TC1', 'category' => 1, 'idnumber' => 'ID1', 'startdate' => 1140606000, 'icon' => '',
        'visible' => 1, 'audiencevisible' => 2, 'summary' => 'Course Summary', 'coursetype' => 0, 'lang' => 'en',
    );

    protected $feedback_data = array(
        'id' => 1, 'course' => 1, 'name' => 'Feedback', 'intro' => 'introduction', 'page_after_submit' => 'final_page',
    );

    protected $feedback_item_data = array(
        'id' => 1, 'feedback' => 1, 'template' => 0, 'name' => 'Question',
        'presentation' => 'A\r|B\r|C\r', 'type' => 'radio', 'hasvalue' => 1, 'position' => 1, 'required' => 0,
    );

    protected $feedback_completed_data = array(
        'id' => 1, 'feedback' => 1, 'userid' => 2, 'timemodified' => 1140606000,
    );

    protected $feedback_value_data = array(
        'id' => 1, 'course_id' => 0, 'item' => 1, 'completed' => 1, 'value' => 2,
    );

    protected $tag_coll_data = array(
        'id' => 2, 'sortorder' => 1
    );

    protected $tag_instance_data = array(
        'id' => 1, 'tagid' => 1, 'itemtype' => 'feedback', 'itemid' => 1,
    );

    protected $tag_data = array(
        'id' => 1, 'userid' => 2, 'name' => 'Tag', 'isstandard' => '1', 'tagcollid' => 2
    );

    protected $grade_items_data = array(
        array('id' => 1, 'courseid' => 2, 'itemtype' => 'course', 'gradepass' => 2, 'itemmodule' => 'assignment', 'iteminstance' => 1, 'scaleid' => 3),
        array('id' => 2, 'courseid' => 2, 'itemtype' => 'mod', 'gradepass' => 0, 'itemmodule' => 'assign', 'iteminstance' => 1, 'scaleid' => 3),
    );

    protected $grade_grades_data = array(
        'id' => 1, 'itemid' => 1, 'userid' => 2, 'finalgrade' => 2, 'rawgrademin' => 2, 'rawgrademax' => 2,
    );

    protected $framework_data = array(
        array(
            'id' => 1, 'fullname' => 'Framework 1', 'shortname' => 'FW1', 'idnumber' => 'ID1', 'description' => 'Description 1', 'sortorder' => 1,
            'visible' => 1, 'hidecustomfields' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 2, 'fullname' => 'Framework 2', 'shortname' => 'FW2', 'idnumber' => 'ID2', 'description' => 'Description 2', 'sortorder' => 2,
            'visible' => 1, 'hidecustomfields' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
    );

    protected $type_data = array(
        array(
            'id' => 1, 'fullname' => 'Hierarchy Type 1', 'shortname' => 'Type 1', 'description' => 'Description 1',
            'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 2, 'fullname' => 'Hierarchy Type 2', 'shortname' => 'Type 2', 'description' => 'Description 2',
            'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 3, 'fullname' => 'F2 Hierarchy Type 1', 'shortname' => 'F2 Type 1', 'description' => 'F2 Description 1',
            'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
    );

    protected $comp_data = array(
        array(
            'id' => 1, 'fullname' => 'Competency 1', 'shortname' =>  'Comp 1', 'description' => 'Competency Description 1', 'idnumber' => 'C1',
            'frameworkid' => 1, 'path' => '/1', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 2, 'fullname' => 'Competency 2', 'shortname' => 'Comp 2', 'description' => 'Competency Description 2', 'idnumber' => 'C2',
            'frameworkid' => 1,  'path' => '/1/2', 'depthlevel' => 2, 'parentid' => 1, 'sortthread' => '01.01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 3, 'fullname' => 'F2 Competency 1', 'shortname' => 'F2 Comp 1', 'description' => 'F2 Competency Description 1', 'idnumber' => 'F2 C1',
            'frameworkid' => 2, 'path' => '/3', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 4, 'fullname' => 'Competency 3', 'shortname' => 'Comp 3', 'description' => 'Competency Description 3', 'idnumber' => 'C3',
            'frameworkid' => 1, 'path' => '/1/4', 'depthlevel' => 2, 'parentid' => 1, 'sortthread' => '01.02', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 5, 'fullname' => 'Competency 4', 'shortname' => 'Comp 4', 'description' => 'Competency Description 4', 'idnumber' => 'C4',
            'frameworkid' => 1, 'path' => '/5', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '02', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
    );

    protected $type_field_data = array(
        'id' => 1, 'fullname' => 'Custom Field', 'shortname' => 'CF1', 'classid' => 2, 'datatype' => 'checkbox', 'description' => 'Custom Field Description 1',
        'sortorder' => 1, 'hidden' => 0, 'locked' => 0, 'required' => 0, 'forceunique' => 0, 'defaultdata' => 0,
        'param1' => null, 'param2' => null, 'param3' => null, 'param4' => null, 'param5' => null, 'typeid' => 1,
    );

    protected $type_data_data = array(
        'id' => 1, 'data' => 1, 'fieldid' => 1, 'competencyid' => 2, 'typeid' => 1,
    );

    protected $f2f_room_data = array(
        'id' => 1, 'name' => 'F2F room', 'capacity' => 10,
        'description' => 'Room description', 'custom' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591
    );

    protected $f2f_data = array(
        'id' => 1, 'course' => 1, 'name' => 'F2F name', 'shortname' => 'f2f', 'details' => 'details', 'approvaltype' => 0
    );

    protected $f2f_session_data = array(
        'id' => 1, 'facetoface' => 1, 'capacity' => 10, 'details' => 'details', 'duration' => 60,
        'normalcost' => 100, 'discountcost' => 90, 'usermodified' => 2
    );

    protected $f2f_session_dates_data = array(
        'id' => 1, 'sessionid' => 1, 'timestart' => 1140519599, 'timefinish' => 114051960, 'roomid' => 1
    );

    protected $f2f_signups_data = array(
        'id' => 1, 'sessionid' => 1, 'userid' => 2, 'discountcode' => '', 'mailedreminder' => 0, 'notificationtype' => 0, 'archived' => 0,
    );

    protected $f2f_signup_status_data = array(
        'id' => 1, 'signupid' => 1, 'statuscode' => 70, 'superceded' => 0, 'grade' => 2, 'note' => 'test note', 'createdby' => 1, 'timecreated' => 1205445539,
    );

    protected $f2f_session_roles_data = array(
        'id' => 1, 'sessionid' => 1, 'roleid' => 1, 'userid' => 2,
    );

    protected $f2f_asset_data = array('id' => 1, 'name' => 'Asset');

    protected $f2f_asset_dates = array('id' => 1, 'assetid' => 1, 'sessionsdateid' => 1);

    protected $f2f_interest_data = array(
        'id' => 1, 'facetoface' => 1, 'userid' => 2, 'timedeclared' => 114051960, 'reason' => 'A good reason.'
    );

    protected $scorm_data = array(
        'id' => 1, 'course' => 1, 'name' => 'Scorm', 'intro' => 'Hi there, this is a scorm.',
    );

    protected $scorm_scoes_data = array(
        'id' => 1, 'scorm' => 1, 'title' => 'SCO', 'launch' => 'launch',
    );

    protected $scorm_scoes_track_data = array(
        array(
            'id' => 1, 'userid' => 2, 'scormid' => 1, 'scoid' => 1, 'attempt' => 1, 'element' => 'cmi.core.lesson_status',
            'value' => 'done', 'timemodified' => 1205445539,
        ),
        array(
            'id' => 2, 'userid' => 2, 'scormid' => 1, 'scoid' => 1, 'attempt' => 1, 'element' => 'cmi.core.score.raw',
            'value' => '100', 'timemodified' => 1205445539,
        ),
        array(
            'id' => 3, 'userid' => 2, 'scormid' => 1, 'scoid' => 1, 'attempt' => 1, 'element' => 'cmi.core.score.min',
            'value' => '10', 'timemodified' => 1205445539,
        ),
        array(
            'id' => 4, 'userid' => 2, 'scormid' => 1, 'scoid' => 1, 'attempt' => 1, 'element' => 'cmi.core.score.max',
            'value' => '90', 'timemodified' => 1205445539,
        ),
    );

    protected $course_info_field_data = array(
        'id' => 1, 'fullname' => 'Field Name', 'shortname' => 'Field', 'datatype' => 'text', 'description' => 'Description',
        'sortorder' => 1, 'categoryid' => 1, 'hidden' => 0, 'locked' => 0, 'required' => 0, 'forceunique' => 0, 'defaultdata' => 'default',
        'param1' => 'text', 'param2' => 'text', 'param3' => 'text', 'param4' => 'text', 'param5' => 'text',
    );

    protected $course_info_data_data = array(
        'id' => 1, 'fieldid' => 1, 'courseid' => 1, 'data' => 'test',
    );

    protected $course_modules_data = array(
        'id' => 1, 'course' => 1, 'module' => 8, 'instance' => 1,
    );

    protected $block_totara_stats_data = array(
        'id' => 1, 'userid' => 2, 'timestamp' => 0, 'eventtype' => 1, 'data' => 1, 'data2' => 1,
    );

    protected $message_working_data = array(
        'id' => 1, 'unreadmessageid' => 1, 'processorid' => 1,
    );

    protected $message_data = array(
        'id' => 1, 'useridfrom' => 1, 'useridto' => 2, 'subject' => 'subject', 'fullmessage' => 'message', 'fullmessageformat' => 1,
        'fullmessagehtml' => 'message', 'smallmessage' => 'msg', 'notification' => 1, 'contexturl' => '', 'contexturlname' => '', 'timecreated' => 0,
    );

    protected $message_metadata_data = array(
        'id' => 1, 'messageid' => 1, 'msgtype' => 1, 'msgstatus' => 1, 'processorid' => 1, 'urgency' => 1,
        'roleid' => 1, 'onaccept' => '', 'onreject' => '', 'icon' => 'competency-regular',
    );

    protected $dp_plan_data = array(
        'id' => 1, 'templateid' => 1, 'userid' => 2, 'name' => 'DP', 'description' => '', 'startdate' => 0, 'enddate' => 0, 'status' => 1,
    );

    protected $dp_plan_competency_assign_data = array(
        'id' => 1, 'planid' => 1, 'competencyid' => 1, 'priority' => 1, 'duedate' => 1, 'approved' => 1, 'scalevalueid' => 1,
    );

    protected $dp_plan_course_assign_data = array(
        'id' => 1, 'planid' => 1, 'courseid' => 1, 'priority' => 1, 'duedate' => 1, 'approved' => 1, 'completionstatus' => 1, 'grade' => 2,
    );

    protected $dp_plan_objective_data = array(
        'id' => 1, 'planid' => 1, 'fullname' => 'Objective', 'shortname' => 'obj', 'description' => 'Objective description',
        'priority' => 10, 'duedate' => 1234567890, 'scalevalueid' => 1, 'approved' => 10,
    );

    protected $dp_plan_evidence_type_data = array(
        'id' => 1, 'name' => 'plan evidence type', 'description' => 'plan evidence description', 'timemodified' => 0, 'usermodified' => 2, 'sortorder' => 1,
    );

    protected $dp_plan_evidence_data = array(
        'name' => 'plan evidence', 'description' => 'plan evidence description', 'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2,
        'evidencetypeid' => 1, 'evidencelink' => 1, 'institution' => 'plan evidence institution', 'datecompleted' => 0, 'userid' => 2, 'readonly' => 0,
    );

    protected $dp_plan_evidence_relation_data = array(
        'id' => 1, 'evidenceid' => 1, 'planid' => 1, 'component' => 'competency', 'itemid' => 1,
    );

    protected $dp_plan_component_relation_data = array(
        'id' => 1, 'itemid1' => 1, 'component1' => 'competency', 'itemid2' => 1, 'component2' => 'course',
    );

    protected $cohort_data = array(
        'id' => 1, 'name' => 'cohort', 'contextid' => 0, 'descriptionformat' => 0, 'timecreated' => 0, 'timemodified' => 0, 'cohorttype' => 1,
    );

    protected $cohort_members_data = array(
        'id' => 1, 'cohortid' => 1, 'userid' => 1,
    );

    protected $prog_data = array(
        array(
            'id' => 1, 'certifid' => null, 'category' => 1, 'fullname' => 'program', 'shortname' => 'prog', 'idnumber' => '123',
            'icon' => 'default.png', 'summary' => 'summary', 'availablefrom' => 123456789, 'availableuntil' => 123456789, 'audiencevisible' => 2,
        ),
        array(
            'id' => 2, 'certifid' => 1, 'category' => 1, 'fullname' => 'Cf program fullname 101', 'shortname' => 'CP101', 'idnumber' => 'CP101',
            'summary' => 'CP101', 'availablefrom' => 123456789, 'availableuntil' => 123456789, 'icon' => 'people-and-communities', 'audiencevisible' => 2,
        ),
    );

    protected $context_data = array(
        array('instanceid' => 1, 'contextlevel' => CONTEXT_PROGRAM),
        array('instanceid' => 2, 'contextlevel' => CONTEXT_PROGRAM),
        array('instanceid' => 2, 'contextlevel' => CONTEXT_COURSE),
    );

    protected $prog_courseset_data = array(
        'id' => 1, 'programid' => 1, 'sortorder' => 1, 'competencyid' => 0, 'nextsetoperator' => 0, 'completiontype' => 1,
        'timeallowed' => 3024000, 'recurcreatetime' => 0, 'recurrencetime' => 0, 'contenttype' => 1, 'label' => 'courseset1',
    );

    protected $prog_courseset_course_data = array(
        'id' => 1, 'coursesetid' => 1, 'courseid' => 1,
    );

    protected $prog_extension_data = array(
        'id' => 1, 'programid' => 1, 'userid' => 2, 'status' => 0,
    );

    protected $prog_completion_data = array(
        array(
            'id' => 2, 'programid' => 1, 'userid' => 2, 'coursesetid' => 0, 'status' => 1, 'timedue' => 1205445539,
            'timecompleted' => 1205445539, 'timestarted' => 1205445539, 'positionid' => 1, 'organisationid' => 1,
        ),
        array(
            'id' => 3, 'programid' => 2, 'userid' => 2, 'coursesetid' => 0, 'status' => 1, 'timedue' => -1,
            'timecompleted' => 1378215781, 'timestarted' => 1378136334, 'positionid' => 1, 'organisationid' => 1,
        ),
    );

    protected $prog_completion_history_data = array(
        'id' => 2, 'programid' => 1, 'userid' => 2, 'coursesetid' => 0, 'status' => 1, 'timestarted' => 1205445539,
        'timedue' => 1205445539, 'timecompleted' => 1205445539, 'recurringcourseid' => 1, 'positionid' => 1, 'organisationid' => 1,
    );

    protected $prog_user_assignment_data = array(
        'id' => 1, 'programid' => 1, 'userid' => 2,
    );

    protected $pos_type_info_data_data = array(
        'id' => 1, 'fieldid' => 1, 'positionid' => 1, 'data' => 'test',
    );

    protected $org_type_info_data_data = array(
        'id' => 1, 'fieldid' => 1, 'organisationid' => 1, 'data' => 'test',
    );

    protected $pos_type_info_field_data = array(
        'id' => 1, 'fullname' => 'Field Name', 'shortname' => 'Field', 'datatype' => 'text', 'description' => 'Description',
        'sortorder' => 1, 'hidden' => 0, 'locked' => 0, 'required' => 0, 'forceunique' => 0, 'defaultdata' => 'default',
        'param1' => 'text', 'param2' => 'text', 'param3' => 'text', 'param4' => 'text', 'param5' => 'text',
    );

    protected $org_type_info_field_data = array(
        'id' => 1, 'fullname' => 'Field Name', 'shortname' => 'Field', 'datatype' => 'text', 'description' => 'Description',
        'sortorder' => 1, 'typeid' => 1, 'hidden' => 0, 'locked' => 0, 'required' => 0, 'forceunique' => 0, 'defaultdata' => 'default',
        'param1' => 'text', 'param2' => 'text', 'param3' => 'text', 'param4' => 'text', 'param5' => 'text',
    );

    protected $assignment_data = array(
        'id' => 1, 'course' => 2, 'name' => 'Assignment 001', 'description' => 'Assignment description 001', 'format' => 0, 'assignmenttype' => 'uploadsingle',
        'resubmit' => 0, 'preventlate' => 0, 'emailteachers' => 0, 'var1' => 0, 'var2' => 0, 'var3' => 0, 'var4' => 0, 'var5' => 0, 'maxbytes' => 1048576,
        'timedue' => 1332758400, 'timeavailable' => 1332153600, 'grade' => 2, 'timemodified' => 1332153673, 'intro' => 'introduction',
    );

    protected $assignment_submissions_data = array(
        'id' => 1, 'assignment' => 1, 'userid' => 2, 'timecreated' => 0, 'timemodified' => 1332166933, 'numfiles' => 1, 'data1' => '', 'data2' => '',
        'grade' => 2, 'submissioncomment' => 'well done', 'format' => 0, 'teacher' => 0, 'timemarked' => 0, 'mailed' => 1,
    );

    protected $assign_data = array(
        'id' => 1, 'course' => 2, 'name' => 'Assign 001', 'intro' => 'Assign description 001', 'introformat' => 1, 'alwaysshowdescription' => 0,
        'completionsubmit' => 1, 'sendnotifications' => 0, 'sendlatenotifications' => 0, 'allowsubmissionsfromdate' => 1332153600, 'duedate' => 1332758400,
        'maxattempts' => -1,
    );

    protected $assign_submission_data = array(
        'id' => 1, 'assignment' => 1, 'userid' => 2, 'timecreated' => 0, 'timemodified' => 1332166933, 'status' => 'submitted',
        'groupid' => 0, 'attemptnumber' => 0,
    );

    protected $assign_onlinetext_data = array(
        'id' => 1, 'assignment' => 1, 'submission' => 1, 'onlinetext' => '<p>qweqwe</p>', 'onlineformat' => 1,
    );

    protected $assign_grades_data = array(
        'id' => 1, 'assignment' => 1, 'userid' => 2, 'timecreated' => 0, 'timemodified' => 1332166933, 'grader' => 2,
        'grade' => 100.00000, 'attemptnumber' => 0,
    );

    protected $assign_feedback_data = array(
        'id' => 1, 'assignment' => 1, 'grade' => 1, 'commenttext' => 'qweqwe', 'commentformat' => 1,
    );

    protected $scale_data = array(
        array(
            'id' => 3, 'courseid' => 0, 'userid' => 2, 'name' => 'Scale 001', 'scale' => 'Bad,Average,Good', 'description' => '', 'timemodified' => 1332243112,
        ),
        array(
            'id' => 4, 'courseid' => 0, 'userid' => 2, 'name' => 'Scale 002', 'scale' => 'Awful,Satisfactory,Good,Excellent', 'description' => '', 'timemodified' => 1332243112,
        ),
    );

    protected $files_data = array(
        'contextid' => 1, 'itemid' => 1, 'filepath' => '/totara/', 'filename' => 'icon.gif', 'filesize' => 8,
        'filearea' => 'course', 'status' => 1, 'timecreated' => 0, 'timemodified' => 0, 'sortorder' => 1,
    );

    protected $sync_log_data = array(
        'id' => 1, 'runid' => 1, 'time' => 1, 'element' => 'user', 'logtype' => 'info', 'action' => 'user sync', 'info' => 'sync started',
    );

    protected $appraisal_user_assignment = array(
        'id' => 3, 'userid' => 2, 'appraisalid' => 1, 'activestageid' => 2, 'timecompleted' => null, 'status' => 1,
    );

    protected $goal = array(
        'id' => 1, 'frameworkid' => 1, 'fullname' => 'Goal 1', 'parentid' => 0, 'visible' => 1, 'proficiencyexpected' => 0,
         'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2,
    );

    protected $goal_framework = array(
        'id' => 1, 'fullname' => 'Goal Framework 1', 'sortorder' => 1, 'visible' => 1, 'hidecustomfields' => 0, 'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2,
    );

    protected $goal_record = array(
        'id' => 1, 'userid' => 2, 'scalevalueid' => 1, 'goalid' => 1, 'deleted' => 0,
    );

    protected $goal_item_history_data = array(
        'id' => 1, 'scope' => 2, 'itemid' => 1, 'scalevalueid' => 1, 'timemodified' => 1332153671, 'usermodified' => 2,
    );

    protected $goal_personal_data = array(
        'id' => 1, 'userid' => 2, 'name' => 'My Personal Goal', 'description' => '', 'targetdate' => 1332153671, 'scaleid' => 3, 'scalevalueid' => 1,
        'assigntype' => 1, 'timecreated' => 1332153671, 'usercreated' => 2, 'timemodified' => 1332153671, 'usermodified' => 2, 'deleted' => 0,
    );

    protected $visible_cohort_data = array(
        'id' => 1, 'cohortid' => 1, 'instanceid' => 1, 'instancetype' => 50, 'timemodified' => 1, 'timecreated' => 1, 'usermodified' => 2,
    );

    protected $totara_compl_import_course_data = array(
        'id' => 1, 'username' => 'username', 'courseshortname' => 'shortname', 'courseidnumber' => 'idnumber', 'completiondate' => '2013-08-27', 'grade' => 'grade',
        'timecreated' => 0, 'timeupdated' => 0, 'importuserid' => 1, 'importerror' => 1, 'importerrormsg' => 'nousername;usernamenotfound;nocourse;nomanualenrol;',
        'rownumber' => 1, 'importevidence' => 0,
        );

    protected $totara_compl_import_cert_data = array(
        'id' => 1, 'username' => 'username', 'certificationshortname' => 'shortname', 'certificationidnumber' => 'idnumber', 'completiondate' => '2013-08-27',
        'timecreated' => 0, 'timeupdated' => 0, 'importuserid' => 1, 'importerror' => 1, 'importerrormsg' => 'nousername;usernamenotfound;nocourse;nomanualenrol;',
        'rownumber' => 1, 'importevidence' => 0,
    );

    protected $certif_data = array(
        'id' => 1, 'learningcomptype' => 1, 'activeperiod' => '1 year', 'windowperiod' => '4 week', 'recertifydatetype' => 1, 'timemodified' => 1332153673,
    );

    protected $certif_completion_data = array(
        'id' => 1, 'certifid' => 1, 'userid' => 2, 'certifpath' => 1, 'status' => 1, 'renewalstatus' => 0,
        'timewindowopens' => 0, 'timeexpires' => 0, 'timecompleted' => 0, 'timemodified' => 1332153671,
    );

    protected $certif_completion_history_data = array(
        'id' => 1, 'certifid' => 1, 'userid' => 2, 'certifpath' => 1, 'status' => 1, 'renewalstatus' => 0,
        'timewindowopens' => 1332153673, 'timeexpires' => 1332113673, 'timecompleted' => null, 'timemodified' => 1332153671,
        'unassigned' => 0,
    );

    protected $course_completion_history_data = array(
        'id' => 1, 'courseid' => 1, 'userid' => 1, 'timecompleted' => 1332153671, 'grade' => 1,
    );

    protected $comp_record_history_data = array(
        'id' => 1, 'userid' => 1, 'competencyid' => 1, 'proficiency' => 1, 'timemodified' => 1332153671, 'usermodified' => 2,
    );

    protected $filler_data = array(
        'id' => 1, 'courseid' => 1, 'programid' => 1, 'competencyid' => 1, 'templateid' => 1, 'enabled' => 1,
        'sortorder' => 1, 'manualcomplete' => 1, 'component' => 'program', 'enrol' => 'cohort', 'customint1' => 1,
    );

    protected $dummy_data = array(
        'id' => 1, 'userid' => 2, 'frameworkid' => 1, 'competency' => 1, 'competencyid' => 1, 'competencycount' => 1, 'instanceid' => 1, 'iteminstance' => 1,
        'itemid' => 1, 'templateid' => 1, 'id1' => 1, 'id2' => 1, 'proficiency' => 1, 'timecreated' => 1, 'timemodified' => 1, 'usermodified' => 2,
        'organisationid' => 1, 'positionid' => 1, 'assessorid' => 1, 'assessorname' => 'Name', 'fullname' => 'fullname', 'visible' => 1, 'type' => 1,
    );

    protected $badges_issued = array(
        'id' => 1, 'dateexpire' => 1432153671, 'dateissued' => 1332153671, 'issuernotified' => 1332153671, 'uniquehash' => '1-2', 'userid' => 2,
        'idchar' => '', 'badgeimage' => '', 'issuername' => 'Issuer', 'issuercontact' => 'issuer@contac.com', 'name' => 'Badge1', 'type' => 1,
        'status' => 1,
    );

    protected $upgrade_log = array(
        'id' => 11111111, 'type' => 0, 'plugin' => 'totara_core', 'version' => 2014012345, 'targetversion' => 'targetversion',
        'info' => 'nothing', 'details' => null, 'backtrace' => null, 'userid' => 0, 'timemodified' => 10,
    );

    protected $logstore_standard_log_data = array('id' => 1, 'eventname' => '\core\event\user_loggedin', 'component' => 'core',
        'action' => 'loggedin', 'target' => 'user', 'objecttable' => 'user', 'objectid' => 2, 'crud' => 'r', 'edulevel' => 0,
        'contextid' => 1, 'contextlevel' => 10, 'contextinstanceid' => 0, 'userid' => 2, 'courseid' => 0, 'relateduserid' => 0,
        'anonymous' => 0, 'other' => 'a:1:{s:8:"username";s:5:"admin";}', 'timecreated' => 1416859984, 'origin' => 'web',
        'ip' => '127.0.0.1'
    );

    protected $repository_opensesame_bdls_data = array('id' => 1, 'name' => 'Some budle', 'timecreated' => 1416859984);

    protected $repository_opensesame_pkgs_data = array('id' => 1, 'zipfilename' => 'abc.zip', 'title' => 'abc',
        'expirationdate' => '',  'mobilecompatibility' => '', 'externalid' => 'xyz', 'description' => 'efg', 'duration' => '',
        'timecreated' => 1416859984, 'timemodified' => 1416859984);

    protected $repository_opensesame_bps_data = array('id' => 1, 'bundleid' => 1, 'packageid' => 1);

    protected $totara_connect_clients_data = array('id' => 1, 'status' => 1, 'clientidnumber' => 'xxx', 'clientsecret' => 'yyyy',
        'clientname' => 'some client', 'clienturl' => 'http://example.com/totara', 'clienttype' => 'totaralms', 'clientcomment' => '',
        'cohortid' => null, 'serversecret' => 'zzzz', 'addnewcohorts' => 0, 'addnewcourses' => 0, 'apiversion' => 1,
        'timecreated' => 1416859984, 'timemodified' => 1416859985);

    protected $tool_customlang_data = array('id' => 1, 'lang' => 'en', 'componentid' => 1, 'stringid' => 'totara', 'original' => 'totara',
        'master' => 'totara', 'local' => 'Totara', 'timecustomized' => 1416859984, 'timemodified' => 1416859984);

    protected $tool_customlang_components_data = array('id' => 1, 'name' => 'totara', 'version' => '1985031400');

    // NOTE: Do not add more data above - you can now avoid core changes by defining the
    // {@link phpunit_column_test_add_data()} method in your source instead.
    // See totara/reportbuilder/rb_sources/rb_source_reports.php for an example.


    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        global $DB;
        if ($DB->get_dbfamily() === 'mysql') {
            // MySQL default size is too small for some of our reports when all columns and filters are included.
            $sbs = $DB->get_field_sql("SELECT @@sort_buffer_size");
            $required = 2097152;
            if (strpos($DB->get_dbcollation(), 'utf8mb4') !== false) {
                $required = 6291456;
            }
            if ($sbs < $required) {
                $DB->execute("SET sort_buffer_size=$required");
            }
        }
    }

    protected function tearDown() {
        $this->user_info_field_data = null;
        $this->user_info_data_data = null;
        $this->org_framework_data = null;
        $this->org_data = null;
        $this->pos_framework_data = null;
        $this->pos_data = null;
        $this->f2f_session_data_data = null;
        $this->course_completions_data = null;
        $this->course_completion_criteria_data = null;
        $this->course_completion_crit_compl_data = null;
        $this->log_data = null;
        $this->course_data = null;
        $this->feedback_data = null;
        $this->feedback_item_data = null;
        $this->feedback_completed_data = null;
        $this->feedback_value_data = null;
        $this->tag_coll_data = null;
        $this->tag_instance_data = null;
        $this->tag_data = null;
        $this->grade_items_data = null;
        $this->grade_grades_data = null;
        $this->framework_data = null;
        $this->type_data = null;
        $this->comp_data = null;
        $this->type_field_data = null;
        $this->type_data_data = null;
        $this->f2f_room_data = null;
        $this->f2f_data = null;
        $this->f2f_session_data = null;
        $this->f2f_session_dates_data = null;
        $this->f2f_signups_data = null;
        $this->f2f_signup_status_data = null;
        $this->f2f_session_roles_data = null;
        $this->f2f_asset_data = null;
        $this->f2f_asset_dates = null;
        $this->f2f_interest_data = null;
        $this->scorm_data = null;
        $this->scorm_scoes_data = null;
        $this->scorm_scoes_track_data = null;
        $this->course_info_field_data = null;
        $this->course_info_data_data = null;
        $this->course_modules_data = null;
        $this->block_totara_stats_data = null;
        $this->message_working_data = null;
        $this->message_data = null;
        $this->message_metadata_data = null;
        $this->dp_plan_data = null;
        $this->dp_plan_competency_assign_data = null;
        $this->dp_plan_course_assign_data = null;
        $this->dp_plan_objective_data = null;
        $this->dp_plan_evidence_type_data = null;
        $this->dp_plan_evidence_data = null;
        $this->dp_plan_evidence_relation_data = null;
        $this->dp_plan_component_relation_data = null;
        $this->cohort_data = null;
        $this->cohort_members_data = null;
        $this->prog_data = null;
        $this->context_data = null;
        $this->prog_courseset_data = null;
        $this->prog_courseset_course_data = null;
        $this->prog_extension_data = null;
        $this->prog_completion_data = null;
        $this->prog_completion_history_data = null;
        $this->prog_user_assignment_data = null;
        $this->pos_type_info_data_data = null;
        $this->org_type_info_data_data = null;
        $this->pos_type_info_field_data = null;
        $this->org_type_info_field_data = null;
        $this->assignment_data = null;
        $this->assignment_submissions_data = null;
        $this->assign_data = null;
        $this->assign_submission_data = null;
        $this->assign_onlinetext_data = null;
        $this->assign_grades_data = null;
        $this->assign_feedback_data = null;
        $this->scale_data = null;
        $this->files_data = null;
        $this->sync_log_data = null;
        $this->appraisal_user_assignment = null;
        $this->goal = null;
        $this->goal_framework = null;
        $this->goal_record = null;
        $this->goal_item_history_data = null;
        $this->goal_personal_data = null;
        $this->visible_cohort_data = null;
        $this->totara_compl_import_course_data = null;
        $this->totara_compl_import_cert_data = null;
        $this->certif_data = null;
        $this->certif_completion_data = null;
        $this->certif_completion_history_data = null;
        $this->course_completion_history_data = null;
        $this->comp_record_history_data = null;
        $this->filler_data = null;
        $this->dummy_data = null;
        $this->badges_issued = null;
        $this->upgrade_log = null;
        $this->logstore_standard_log_data = null;
        $this->repository_opensesame_bdls_data = null;
        $this->repository_opensesame_pkgs_data = null;
        $this->repository_opensesame_bps_data = null;
        $this->totara_connect_clients_data = null;
        $this->tool_customlang_data = null;
        $this->tool_customlang_components_data = null;
        parent::tearDown();
    }

    protected function setUp() {
        global $DB;
        parent::setup();
        set_config('enablecompletion', 1);

        $DB->delete_records('upgrade_log', array());

        // Create a job assignment.
        \totara_job\job_assignment::create_default(2, array('organisationid' => 1, 'positionid' => 1));

        $this->loadDataSet($this->createArrayDataset(array(
            'user_info_field' => array($this->user_info_field_data),
            'user_info_data' => array($this->user_info_data_data),
            'org_framework' => $this->org_framework_data,
            'org_type' => $this->type_data,
            'org' => array($this->org_data),
            'pos_framework' => array($this->pos_framework_data),
            'pos_type' => $this->type_data,
            'pos' => array($this->pos_data),
            'facetoface_session_info_data' => array($this->f2f_session_data_data),
            'course_completion_crit_compl' => array($this->course_completion_crit_compl_data),
            'course_completion_criteria' => array($this->course_completion_criteria_data),
            'course_completions' => array($this->course_completions_data),
            'log' => array($this->log_data),
            'course' => array($this->course_data),
            'grade_items' => $this->grade_items_data,
            'grade_grades' => array($this->grade_grades_data),
            'comp_framework' => $this->framework_data,
            'comp_type' => $this->type_data,
            'comp' => $this->comp_data,
            'comp_type_info_field' => array($this->type_field_data),
            'comp_type_info_data' => array($this->type_data_data),
            'comp_record' => array($this->dummy_data),
            'comp_record_history' => array($this->comp_record_history_data),
            'comp_criteria' => array($this->dummy_data),
            'comp_criteria_record' => array($this->dummy_data),
            'comp_template' => array($this->dummy_data),
            'comp_template_assignment' => array($this->dummy_data),
            'pos_competencies' => array($this->dummy_data),
            'comp_relations' => array($this->dummy_data),
            'goal_framework' => array($this->goal_framework),
            'goal' => array($this->goal),
            'goal_personal' => array($this->goal_personal_data),
            'goal_record' => array($this->goal_record),
            'goal_item_history' => array($this->goal_item_history_data),
            'facetoface_room' => array($this->f2f_room_data),
            'facetoface' => array($this->f2f_data),
            'facetoface_sessions' => array($this->f2f_session_data),
            'facetoface_sessions_dates' => array($this->f2f_session_dates_data),
            'facetoface_signups' => array($this->f2f_signups_data),
            'facetoface_signups_status' => array($this->f2f_signup_status_data),
            'facetoface_session_roles' => array($this->f2f_session_roles_data),
            'facetoface_asset' => array($this->f2f_asset_data),
            'facetoface_asset_dates' => array($this->f2f_asset_dates),
            'facetoface_interest' => array($this->f2f_interest_data),
            'scorm_scoes' => array($this->scorm_scoes_data),
            'scorm_scoes_track' => $this->scorm_scoes_track_data,
            'feedback' => array($this->feedback_data),
            'feedback_item' => array($this->feedback_item_data),
            'feedback_completed' => array($this->feedback_completed_data),
            'feedback_value' => array($this->feedback_value_data),
            'tag_coll' => array($this->tag_coll_data),
            'tag' => array($this->tag_data),
            'tag_instance' => array($this->tag_instance_data),
            'course_info_field' => array($this->course_info_field_data),
            'course_info_data' => array($this->course_info_data_data),
            'course_modules' => array($this->course_modules_data),
            'block_totara_stats' => array($this->block_totara_stats_data),
            'message' => array($this->message_data),
            'message_working' => array($this->message_working_data),
            'message_metadata' => array($this->message_metadata_data),
            'dp_plan' => array($this->dp_plan_data),
            'dp_plan_competency_assign' => array($this->dp_plan_competency_assign_data),
            'dp_plan_course_assign' => array($this->dp_plan_course_assign_data),
            'dp_plan_objective' => array($this->dp_plan_objective_data),
            'dp_evidence_type' => array($this->dp_plan_evidence_type_data),
            'dp_plan_evidence' => array($this->dp_plan_evidence_data),
            'dp_plan_evidence_relation' => array($this->dp_plan_evidence_relation_data),
            'dp_plan_component_relation' => array($this->dp_plan_component_relation_data),
            'cohort' => array($this->cohort_data),
            'cohort_members' => array($this->cohort_members_data),
            'prog' => $this->prog_data,
            'prog_courseset' => array($this->prog_courseset_data),
            'prog_courseset_course' => array($this->prog_courseset_course_data),
            'prog_extension' => array($this->prog_extension_data),
            'prog_completion' => $this->prog_completion_data,
            'prog_completion_history' => array($this->prog_completion_history_data),
            'prog_user_assignment' => array($this->prog_user_assignment_data),
            'pos_type_info_field' => array($this->pos_type_info_field_data),
            'org_type_info_field' => array($this->org_type_info_field_data),
            'pos_type_info_data' => array($this->pos_type_info_data_data),
            'org_type_info_data' => array($this->org_type_info_data_data),
            'assignment' => array($this->assignment_data),
            'assignment_submissions' => array($this->assignment_submissions_data),
            'assign' => array($this->assign_data),
            'assign_submission' => array($this->assign_submission_data),
            'assignsubmission_onlinetext' => array($this->assign_onlinetext_data),
            'assign_grades' => array($this->assign_grades_data),
            'assignfeedback_comments' => array($this->assign_feedback_data),
            'scale' => $this->scale_data,
            'files' => array($this->files_data),
            'enrol' => array($this->filler_data),
            'prog_assignment' => array($this->filler_data),
            'totara_sync_log' => array($this->sync_log_data),
            'cohort_visibility' => array($this->visible_cohort_data),
            'appraisal_user_assignment' => array($this->appraisal_user_assignment),
            'totara_compl_import_course' => array($this->totara_compl_import_course_data),
            'totara_compl_import_cert' => array($this->totara_compl_import_cert_data),
            'certif' => array($this->certif_data),
            'certif_completion' => array($this->certif_completion_data),
            'certif_completion_history' => array($this->certif_completion_history_data),
            'course_completion_history' => array($this->course_completion_history_data),
            'badge_issued' => array($this->badges_issued),
            'context' => $this->context_data,
            'upgrade_log' => array($this->upgrade_log),
            'logstore_standard_log' => array($this->logstore_standard_log_data),
            'repository_opensesame_bdls' => array($this->repository_opensesame_bdls_data),
            'repository_opensesame_pkgs' => array($this->repository_opensesame_pkgs_data),
            'repository_opensesame_bps' => array($this->repository_opensesame_bps_data),
            'totara_connect_clients' => array($this->totara_connect_clients_data),
            'tool_customlang' => array($this->tool_customlang_data),
            'tool_customlang_components' => array($this->tool_customlang_components_data),
        )));
    }

    /**
     * Data provider for columns and filters test.
     *
     * Each source is tested separately, so that one failure won't prevent the other sources from being tested.
     */
    public function data_columns_and_filters() {
        $sources = array();

        // Loop through installed sources.
        $sourcelist = reportbuilder::get_source_list(true);
        foreach ($sourcelist as $sourcename => $title) {
            $sources[] = array($sourcename, $title);
        }

        return $sources;
    }

    /**
     * Check all reports columns and filters
     *
     * Note for MySQL/MariaDB: Report modification can result in queries hanging in a 'statistics' state in this test. If
     * you have this problem, the MySQL config setting "optimizer_search_depth" is likely the cause.
     *
     * @group slowtest
     * @dataProvider data_columns_and_filters
     */
    public function test_columns_and_filters($sourcename, $title) {
        global $SESSION, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // We need to be able to calculate the total count.
        set_config('allowtotalcount', 1, 'totara_reportbuilder');

        $i = 1;
        $reportname = 'Test Report';
        $filtername = 'filtering_testreport';

        $src = reportbuilder::get_source_object($sourcename, true); // Caching here is completely fine.
        $src->phpunit_column_test_add_data($this);

        // Create a report.
        $report = new stdClass();
        $report->fullname = 'Big test report';
        $report->shortname = 'bigtest';
        $report->source = $sourcename;
        $report->hidden = 0;
        $report->accessmode = 0;
        $report->contentmode = 0;
        $report->showtotalcount = 1;
        $bigreportid = $DB->insert_record('report_builder', $report);

        $sortorder = 1;
        foreach ($src->columnoptions as $column) {
            if (isset($column->grouping) and $column->grouping !== 'none') {
                debugging("Column option grouping was deprecated, use subqueries instead in {$sourcename} {$column->type}-{$column->value}", DEBUG_DEVELOPER);
            }
            // Create a report.
            $report = new stdClass();
            $report->fullname = $reportname;
            $report->shortname = 'test' . $i++;
            $report->source = $sourcename;
            $report->hidden = 0;
            $report->accessmode = 0;
            $report->contentmode = 0;
            $report->showtotalcount = 1;
            $reportid = $DB->insert_record('report_builder', $report);
            $col = new stdClass();
            $col->reportid = $reportid;
            $col->type = $column->type;
            $col->value = $column->value;
            $col->heading = $column->defaultheading;
            $col->sortorder = $sortorder++;
            $DB->insert_record('report_builder_columns', $col);

            // Create the reportbuilder object.
            $rb = reportbuilder::create($reportid);

            // We will be checking deprecated column's message for each column separately,
            // so there is no real need to re-check it again in the big report.
            // Therefore, we are skipping adding them to the big report with all the columns.
            if ($column->deprecated) {
                // Check there is a debugging message when a deprecated column is added to the report.
                $this->assertDebuggingCalled("Column {$col->type}-{$col->value} is a deprecated column in source " . get_class($src));
            } else {
                // Add column to the big report with everything.
                $col->reportid = $bigreportid;
                $DB->insert_record('report_builder_columns', $col);
            }

            $sql = $rb->build_query();

            $message = "\nReport title : {$title}\n";
            $message .= "Report sourcename : {$sourcename}\n";
            $message .= "Column option : Test {$column->type}_{$column->value} column\n";
            $message .= "SQL : {$sql[0]}\n";
            $message .= "SQL Params : " . var_export($sql[1], true) . "\n";

            // Get the column option object.
            $columnoption = reportbuilder::get_single_item($rb->columnoptions, $column->type, $column->value);

            // The answer here depends on if the column we are testing.
            $expectedcount = $src->phpunit_column_test_expected_count($columnoption);
            $this->assertEquals($expectedcount, $rb->get_full_count(), $message);

            // Remove the report again so reports report source gets expected count.
            $DB->delete_records('report_builder_columns', ['reportid' => $reportid]);
            $DB->delete_records('report_builder', ['id' => $reportid]);

            // Make sure the type string exists.
            $langstr = 'type_' . $column->type;
            if (!get_string_manager()->string_exists($langstr, 'rb_source_' . $sourcename)
                and !get_string_manager()->string_exists($langstr, 'totara_reportbuilder')
            ) {
                // Display in missing string format to make it obvious.
                $type = get_string($langstr, 'rb_source_' . $sourcename);
            }
        }

        $sortorder = 1;

        foreach ($src->filteroptions as $filter) {
            if (isset($filter->grouping) and $filter->grouping !== 'none') {
                debugging("Filter option grouping was deprecated, use subqueries instead in {$sourcename} {$column->type}-{$column->value}", DEBUG_DEVELOPER);
            }
            // Create a report.
            $report = new stdClass();
            $report->fullname = $reportname;
            $report->shortname = 'test' . $i++;
            $report->source = $sourcename;
            $report->hidden = 0;
            $report->accessmode = 0;
            $report->contentmode = 0;
            $reportid = $DB->insert_record('report_builder', $report);
            // If the filter is based on a column, include that column.
            if (empty($filter->field)) {
                // Add a single column.
                $col = new stdClass();
                $col->reportid = $reportid;
                $col->type = $filter->type;
                $col->value = $filter->value;
                $col->heading = 'Test' . $i++;
                $col->sortorder = 1;
                $colid = $DB->insert_record('report_builder_columns', $col);
            }
            // Add a single filter.
            $fil = new stdClass();
            $fil->reportid = $reportid;
            $fil->type = $filter->type;
            $fil->value = $filter->value;
            $fil->sortorder = $sortorder++;
            $filid = $DB->insert_record('report_builder_filters', $fil);

            // Add column to the big report with everything.
            $fil->reportid = $bigreportid;
            $DB->insert_record('report_builder_filters', $fil);

            // Set session to filter by this column.
            $fname = $filter->type . '-' . $filter->value;
            switch($filter->filtertype) {
                case 'date':
                    $search = array('before' => null, 'after' => 1);
                    break;
                case 'text':
                case 'number':
                case 'select':
                default:
                    $search = array('operator' => 1, 'value' => 2);
                    break;
            }
            $SESSION->{$filtername} = array();
            $SESSION->{$filtername}[$fname] = array($search);

            // Create the reportbuilder object.
            $rb = reportbuilder::create($reportid);

            // Just try to get the count, we cannot guess the actual number here.
            $rb->get_filtered_count();

            // Make sure the type string exists.
            $langstr = 'type_' . $filter->type;
            if (!get_string_manager()->string_exists($langstr, 'rb_source_' . $sourcename)
                and !get_string_manager()->string_exists($langstr, 'totara_reportbuilder')
            ) {
                // Display in missing string format to make it obvious.
                $type = get_string($langstr, 'rb_source_' . $sourcename);
            }
        }

        // Make sure that joins are not using reserved SQL keywords.
        $reserved = \sql_generator::getAllReservedWords();
        foreach ($src->joinlist as $join) {
            $message = "\nReport title : {$title}\n";
            $message .= "Report sourcename : {$sourcename}\n";
            $message .= "Join name {$join->name} is invalid, it cannot be any SQL reserved word!\n";
            $this->assertArrayNotHasKey($join->name, $reserved, $message);
        }

        // Test filters are compatible with caching.
        if ($src->cacheable) {
            foreach ($src->filteroptions as $filteroption) {
                if (isset($filteroption->filteroptions['cachingcompatible'])) {
                    // Developer says they know, no need to test!
                    continue;
                }
                if (empty($filteroption->field)) {
                    // The filter is using column info to get the field data.
                    continue;
                }
                if (reportbuilder::get_single_item($src->requiredcolumns, $filteroption->type, $filteroption->value)) {
                    $this->fail("Filter '{$filteroption->type}-{$filteroption->value}' in '{$sourcename}' has custom field and is colliding with required column, you need to add 'cachingcompatible' to filter options");
                }
                if (reportbuilder::get_single_item($src->columnoptions, $filteroption->type, $filteroption->value)) {
                    $this->fail("Filter '{$filteroption->type}-{$filteroption->value}' in '{$sourcename}' has custom field and is colliding with column option, you need to add 'cachingcompatible' to filter options");
                }
            }
        }

        // Test we can execute the query with all columns and filters.
        $rb = reportbuilder::create($bigreportid);
        list($sql, $params, $cacheschedule) = $rb->build_query(false, true, false);
        $rs = $DB->get_counted_recordset_sql($sql, $params);
        $rs->close();

        if (!$src->cacheable) {
            return;
        }

        if ($DB->get_dbvendor() === 'mysql') {
            $info = $DB->get_server_info();
            if (version_compare($info['version'], '5.7', '<')) {
                $this->markTestSkipped('MySQL versions lower than 5.7 have severe limits, skipping source caching test');
            }
        }
        if ($DB->get_dbvendor() === 'mariadb') {
            $info = $DB->get_server_info();
            if (version_compare($info['version'], '10.2', '<')) {
                $this->markTestSkipped('MariaDB versions lower than 10.2 have severe limits, skipping source caching test');
            }
        }

        if ($DB->get_dbfamily() === 'mysql') {
            // There are way too many columns for MySQL to handle, delete jobs related stuff.
            foreach (['user', 'manager', 'appraiser', 'tempmanager'] as $type) {
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'fullname'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'namelink'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'namelinkicon'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'email'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'emailunobscured'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'auth'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'institution'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'department'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'address'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'city'));
                $DB->delete_records('report_builder_columns', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'idnumber'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'fullname'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'namelink'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'namelinkicon'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'email'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'emailunobscured'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'auth'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'institution'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'department'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'address'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'city'));
                $DB->delete_records('report_builder_filters', array('reportid' => $bigreportid, 'type' => $type, 'value' => 'idnumber'));
            }
        }

        // Remove all filters that are not compatible with caching.
        foreach ($rb->filters as $filter) {
            /** @var rb_filter_type $filter */
            if ($filter->is_caching_compatible()) {
                continue;
            }
            $DB->delete_records('report_builder_filters', array('reportid' => $rb->_id, 'type' => $filter->type, 'value' => $filter->value));
        }

        // Now generate the cache table and run the query.
        $this->enable_caching($bigreportid);
        $rb = reportbuilder::create($bigreportid);
        if ($rb->cache) {
            list($sql, $params, $cacheschedule) = $rb->build_query(false, true, true);
            $rs = $DB->get_counted_recordset_sql($sql, $params);
            $rs->close();
        }

        reportbuilder_purge_cache($bigreportid, false);
    }

    public function test_embedded_reports() {
        $this->resetAfterTest();

        $embeddedobjects = reportbuilder_get_all_embedded_reports();
        foreach ($embeddedobjects as $embeddedobject) {
            $source = reportbuilder::get_source_object($embeddedobject->source, false, true, null);

            foreach ($embeddedobject->columns as $column) {
                foreach ($source->columnoptions as $option) {
                    /** @var rb_column_option $option */
                    if ($column['type'] === $option->type and $column['value'] === $option->value) {
                        continue 2;
                    }
                }
                $columnname = $column['type'] . '-' . $column['value'];
                $this->fail("Invalid column {$columnname} detected in embedded report {$embeddedobject->shortname}");
            }

            foreach ($embeddedobject->filters as $filter) {
                foreach ($source->filteroptions as $option) {
                    /** @var rb_filter_option $option */
                    if ($filter['type'] === $option->type and $filter['value'] === $option->value) {
                        continue 2;
                    }
                }
                $filtername = $filter['type'] . '-' . $filter['value'];
                $this->fail("Invalid filter {$filtername} detected in embedded report {$embeddedobject->shortname}");
            }
        }
    }
}
