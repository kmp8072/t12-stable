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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package mod_facetoface
 */
global $CFG;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/facetoface/rb_sources/rb_facetoface_base_source.php');

class rb_source_facetoface_sessions extends rb_facetoface_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $sourcetitle, $requiredcolumns;

    public function __construct($groupid, rb_global_restriction_set $globalrestrictionset = null) {
        if ($groupid instanceof rb_global_restriction_set) {
            throw new coding_exception('Wrong parameter orders detected during report source instantiation.');
        }
        // Remember the active global restriction set.
        $this->globalrestrictionset = $globalrestrictionset;

        // Apply global user restrictions.
        $this->add_global_report_restriction_join('base', 'userid');

        $this->base = '{facetoface_signups}';
        $this->usedcomponents[] = 'mod_facetoface';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_facetoface_sessions');
        $this->add_customfields();
        parent::__construct();
    }

    /**
     * Global report restrictions are implemented in this source.
     * @return boolean
     */
    public function global_restrictions_supported() {
        return true;
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {
        global $CFG;
        require_once($CFG->dirroot .'/mod/facetoface/lib.php');

        // joinlist for this source
        $joinlist = array(
            new rb_join(
                'sessions',
                'LEFT',
                '{facetoface_sessions}',
                'sessions.id = base.sessionid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'facetoface',
                'LEFT',
                '{facetoface}',
                'facetoface.id = sessions.facetoface',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'sessions'
            ),
            new rb_join(
                'sessiondate',
                'INNER',
                '{facetoface_sessions_dates}',
                '(sessiondate.sessionid = base.sessionid)',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                'sessions'
            ),
            new rb_join(
                'status',
                'LEFT',
                '{facetoface_signups_status}',
                '(status.signupid = base.id AND status.superceded = 0)',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'attendees',
                'LEFT',
                // subquery as table
                "(SELECT su.sessionid, count(ss.id) AS number
                    FROM {facetoface_signups} su
                    JOIN {facetoface_signups_status} ss
                        ON su.id = ss.signupid
                    WHERE ss.superceded=0 AND ss.statuscode >= 50
                    GROUP BY su.sessionid)",
                'attendees.sessionid = base.sessionid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'cancellationstatus',
                'LEFT',
                '{facetoface_signups_status}',
                '(cancellationstatus.signupid = base.id AND
                    cancellationstatus.superceded = 0 AND
                    cancellationstatus.statuscode = '.MDL_F2F_STATUS_USER_CANCELLED.')',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'room',
                'LEFT',
                '{facetoface_room}',
                'sessiondate.roomid = room.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'sessiondate'
            ),
            new rb_join(
                'bookedby',
                'LEFT',
                '{user}',
                'bookedby.id = base.bookedby',
                REPORT_BUILDER_RELATION_MANY_TO_ONE
            ),
            new rb_join(
                'creator',
                'LEFT',
                '{user}',
                'status.createdby = creator.id',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                'status'
            ),
            new rb_join(
                'pos',
                'LEFT',
                '{pos}',
                'pos.id = base.positionid',
                REPORT_BUILDER_RELATION_MANY_TO_ONE
            ),
            new rb_join(
                'pos_assignment',
                'LEFT',
                '{pos_assignment}',
                'pos_assignment.id = base.positionassignmentid',
                REPORT_BUILDER_RELATION_MANY_TO_ONE
            ),
            new rb_join(
                'approver',
                'LEFT',
                // Subquery as table - statuscode 50 = approved.
                "(SELECT status.signupid, status.createdby as approverid, status.timecreated as approvaltime
                    FROM {facetoface_signups_status} status
                   WHERE status.statuscode = 50
                 )",
                'base.id = approver.signupid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'assetdate',
                'LEFT',
                '{facetoface_asset_dates}',
                'assetdate.sessionsdateid = base.id',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                'sessiondate'
            ),
            new rb_join(
                'asset',
                'LEFT',
                '{facetoface_asset}',
                'assetdate.assetid = asset.id',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                'assetdate'
            )
        );


        // include some standard joins
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'facetoface', 'course', 'INNER');
        $this->add_context_table_to_joinlist($joinlist, 'course', 'id', CONTEXT_COURSE, 'INNER');
        // requires the course join
        $this->add_course_category_table_to_joinlist($joinlist,
            'course', 'category');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // requires the position_assignment join
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_tag_tables_to_joinlist('course', $joinlist, 'facetoface', 'course');

        $this->add_facetoface_session_roles_to_joinlist($joinlist);

        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_cohort_course_tables_to_joinlist($joinlist, 'facetoface', 'course');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB, $CFG;
        $intimezone = '';
        if (!empty($CFG->facetoface_displaysessiontimezones)) {
            $intimezone = '_in_timezone';
        }

        $usernamefieldscreator = totara_get_all_user_name_fields_join('creator');
        $usernamefieldsbooked  = totara_get_all_user_name_fields_join('bookedby');
        $columnoptions = array(
            new rb_column_option(
                'session',                  // Type.
                'capacity',                 // Value.
                get_string('sesscapacity', 'rb_source_facetoface_sessions'),    // Name.
                'sessions.capacity',        // Field.
                array('joins' => 'sessions', 'dbdatatype' => 'integer')         // Options array.
            ),
            new rb_column_option(
                'session',
                'numattendees',
                get_string('numattendees', 'rb_source_facetoface_sessions'),
                'attendees.number',
                array('joins' => 'attendees', 'dbdatatype' => 'integer')
            ),
            new rb_column_option(
                'session',
                'details',
                get_string('sessdetails', 'rb_source_facetoface_sessions'),
                'sessions.details',
                array(
                    'joins' => 'sessions',
                    'displayfunc' => 'tinymce_textarea',
                    'extrafields' => array(
                        'filearea' => '\'session\'',
                        'component' => '\'mod_facetoface\'',
                        'fileid' => 'sessions.id',
                        'context' => '\'context_module\'',
                        'recordid' => 'sessions.facetoface'
                    ),
                    'dbdatatype' => 'text',
                    'outputformat' => 'text'
                )
            ),
            new rb_column_option(
                'session',
                'duration',
                get_string('sessduration', 'rb_source_facetoface_sessions'),
                'CASE WHEN sessiondate.timestart > 0
                    THEN
                        (sessiondate.timefinish-sessiondate.timestart)/' . MINSECS . '
                    ELSE
                        sessions.duration/' . MINSECS . '
                    END',
                array(
                    'joins' => array('sessiondate','sessions'),
                    'dbdatatype' => 'decimal',
                    'displayfunc' => 'hours_minutes',
                )
            ),
            new rb_column_option(
                'session',
                'signupperiod',
                get_string('signupperiod', 'rb_source_facetoface_sessions'),
                'sessions.registrationtimestart',
                array(
                    'joins' => array('sessions','sessiondate'),
                    'dbdatatype' => 'timestamp',
                    'displayfunc' => 'nice_two_datetime_in_timezone',
                    'extrafields' => array('finishdate' => 'sessions.registrationtimefinish', 'timezone' => 'sessiondate.sessiontimezone'),
                    'outputformat' => 'text'
                )
            ),
            new rb_column_option(
                'session',
                'signupstartdate',
                get_string('signupstartdate', 'rb_source_facetoface_sessions'),
                'sessions.registrationtimestart',
                array(
                    'joins' => array('sessions','sessiondate'),
                    'dbdatatype' => 'timestamp',
                    'displayfunc' => 'nice_datetime_in_timezone',
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'outputformat' => 'text'
                )            ),
            new rb_column_option(
                'session',
                'signupenddate',
                get_string('signupenddate', 'rb_source_facetoface_sessions'),
                'sessions.registrationtimefinish',
                array(
                    'joins' => array('sessions','sessiondate'),
                    'dbdatatype' => 'timestamp',
                    'displayfunc' => 'nice_datetime_in_timezone',
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'outputformat' => 'text'
)            ),
            new rb_column_option(
                'status',
                'statuscode',
                get_string('status', 'rb_source_facetoface_sessions'),
                'status.statuscode',
                array(
                    'joins' => 'status',
                    'displayfunc' => 'f2f_status',
                )
            ),
             new rb_column_option(
                'session',
                'discountcode',
                get_string('discountcode', 'rb_source_facetoface_sessions'),
                'base.discountcode',
                array('dbdatatype' => 'text',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'session',
                'normalcost',
                get_string('normalcost', 'rb_source_facetoface_sessions'),
                'sessions.normalcost',
                array(
                    'joins' => 'sessions',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
            ),
            new rb_column_option(
                'session',
                'discountcost',
                get_string('discountcost', 'rb_source_facetoface_sessions'),
                'sessions.discountcost',
                array(
                    'joins' => 'sessions',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
            ),
            new rb_column_option(
                'facetoface',
                'name',
                get_string('ftfname', 'rb_source_facetoface_sessions'),
                'facetoface.name',
                array('joins' => 'facetoface',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'facetoface',
                'namelink',
                get_string('ftfnamelink', 'rb_source_facetoface_sessions'),
                "facetoface.name",
                array(
                    'joins' => array('facetoface','sessions'),
                    'displayfunc' => 'link_f2f',
                    'defaultheading' => get_string('ftfname', 'rb_source_facetoface_sessions'),
                    'extrafields' => array('activity_id' => 'sessions.facetoface'),
                )
            ),
            new rb_column_option(
                'status',
                'createdby',
                get_string('createdby', 'rb_source_facetoface_sessions'),
                $DB->sql_concat_join("' '", $usernamefieldscreator),
                array(
                    'joins' => 'creator',
                    'displayfunc' => 'link_user',
                    'extrafields' => array_merge(array('id' => 'creator.id'), $usernamefieldscreator),
                )
            ),
            new rb_column_option(
                'date',
                'sessiondate',
                get_string('sessdate', 'rb_source_facetoface_sessions'),
                'sessiondate.timestart',
                array(
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'joins' =>'sessiondate',
                    'displayfunc' => 'nice_date' . $intimezone,
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'date',
                'sessiondate_link',
                get_string('sessdatelink', 'rb_source_facetoface_sessions'),
                'sessiondate.timestart',
                array(
                    'joins' => 'sessiondate',
                    'displayfunc' => 'link_f2f_session',
                    'defaultheading' => get_string('sessdate', 'rb_source_facetoface_sessions'),
                    'extrafields' => array('session_id' => 'base.sessionid', 'timezone' => 'sessiondate.sessiontimezone'),
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'date',
                'datefinish',
                get_string('sessdatefinish', 'rb_source_facetoface_sessions'),
                'sessiondate.timefinish',
                array(
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'joins' => 'sessiondate',
                    'displayfunc' => 'nice_date' . $intimezone,
                    'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'date',
                'timestart',
                get_string('sessstart', 'rb_source_facetoface_sessions'),
                'sessiondate.timestart',
                array(
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'joins' => 'sessiondate',
                    'displayfunc' => 'nice_time' . $intimezone,
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'date',
                'timefinish',
                get_string('sessfinish', 'rb_source_facetoface_sessions'),
                'sessiondate.timefinish',
                array(
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'joins' => 'sessiondate',
                    'displayfunc' => 'nice_time' . $intimezone,
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'session',
                'cancellationdate',
                get_string('cancellationdate', 'rb_source_facetoface_sessions'),
                'cancellationstatus.timecreated',
                array('joins' => 'cancellationstatus', 'displayfunc' => 'nice_datetime', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'session',
                'bookedby',
                get_string('bookedby', 'rb_source_facetoface_sessions'),
                $DB->sql_concat_join("' '", $usernamefieldsbooked),
                array(
                    'joins' => 'bookedby',
                    'displayfunc' => 'link_user',
                    'extrafields' => array_merge(array('id' => 'bookedby.id'), $usernamefieldsbooked),
                )
            ),
            new rb_column_option(
                'session',
                'positionname',
                get_string('selectedposition', 'mod_facetoface'),
                'pos.fullname',
                array('joins' => 'pos',
                    'dbdatatype' => 'text',
                    'outputformat' => 'text')
            ),
            new rb_column_option(
                'session',
                'positionnameedit',
                get_string('selectedpositionedit', 'rb_source_facetoface_sessions'),
                'pos_assignment.fullname',
                array(
                    'columngenerator' => 'position_edit')
                ),
            new rb_column_option(
                'session',
                'positionassignmentname',
                get_string('selectedpositionassignment', 'mod_facetoface'),
                'pos_assignment.fullname',
                array('joins' => 'pos_assignment',
                'dbdatatype' => 'text',
                'outputformat' => 'text')
            ),
            new rb_column_option(
                'session',
                'positiontype',
                get_string('selectedpositiontype', 'mod_facetoface'),
                'base.positiontype',
                array('dbdatatype' => 'text',
                      'outputformat' => 'text',
                      'displayfunc' => 'position_type')
            ),
            new rb_column_option(
                'status',
                'timecreated',
                get_string('timeofsignup', 'rb_source_facetoface_sessions'),
                'status.timecreated',
                array(
                    'displayfunc' => 'nice_datetime',
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'approver',
                'approvername',
                get_string('approvername', 'mod_facetoface'),
                'approver.approverid',
                array('joins' => 'approver',
                      'displayfunc' => 'approvername')
            ),
            new rb_column_option(
                'approver',
                'approveremail',
                get_string('approveremail', 'mod_facetoface'),
                'approver.approverid',
                array('joins' => 'approver',
                      'displayfunc' => 'approveremail')
            ),
            new rb_column_option(
                'approver',
                'approvaltime',
                get_string('approvertime', 'mod_facetoface'),
                'approver.approvaltime',
                array('joins' => 'approver',
                      'displayfunc' => 'nice_datetime')
            ),
            new rb_column_option(
                'session',
                'cancelledstatus',
                get_string('cancelledstatus', 'mod_facetoface'),
                'sessions.cancelledstatus',
                array(
                    'displayfunc' => 'show_cancelled_status',
                    'joins' => 'sessions',
                    'dbdatatype' => 'integer'
                )
            ),
        );

        // include some standard columns
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_tag_fields_to_columns('course', $columnoptions);

        $this->add_facetoface_session_roles_to_columns($columnoptions);
        $this->add_assets_fields_to_columns($columnoptions);
        $this->add_rooms_fields_to_columns($columnoptions);

        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_cohort_course_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'facetoface',
                'name',
                get_string('ftfname', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'status',
                'statuscode',
                get_string('status', 'rb_source_facetoface_sessions'),
                'multicheck',
                array(
                    'selectfunc' => 'session_status_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'date',
                'sessiondate',
                get_string('sessdate', 'rb_source_facetoface_sessions'),
                'date'
            ),
            new rb_filter_option(
                'date',
                'timestart',
                get_string('sessstart', 'rb_source_facetoface_sessions'),
                'date',
                array('includetime' => true)
            ),
            new rb_filter_option(
                'date',
                'timefinish',
                get_string('sessfinish', 'rb_source_facetoface_sessions'),
                'date',
                array('includetime' => true)
            ),
            new rb_filter_option(
                'session',
                'capacity',
                get_string('sesscapacity', 'rb_source_facetoface_sessions'),
                'number'
            ),
            new rb_filter_option(
                'session',
                'details',
                get_string('sessdetails', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'discountcode',
                get_string('discountcode', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'duration',
                get_string('sessduration', 'rb_source_facetoface_sessions'),
                'number'
            ),
            new rb_filter_option(
                'session',
                'normalcost',
                get_string('normalcost', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'discountcost',
                get_string('discountcost', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'bookedby',
                get_string('bookedby', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'reserved',
                get_string('reserved', 'rb_source_facetoface_sessions'),
                'select',
                array(
                     'selectchoices' => array(
                         '0' => get_string('reserved', 'rb_source_facetoface_sessions'),
                     )
                ),
                'base.userid'
            ),
            new rb_filter_option(
                'session',
                'signupstartdate',
                get_string('signupstartdate', 'rb_source_facetoface_summary'),
                'date'
            ),
            new rb_filter_option(
                'session',
                'signupenddate',
                get_string('signupenddate', 'rb_source_facetoface_summary'),
                'date'
            ),
            new rb_filter_option(
                'room',
                'name',
                get_string('roomname', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'room',
                'capacity',
                get_string('roomcapacity', 'rb_source_facetoface_sessions'),
                'number'
            ),
            new rb_filter_option(
                'room',
                'description',
                get_string('roomdescription', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'status',
                'createdby',
                get_string('createdby', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'positionname',
                get_string('selectedpositionname', 'mod_facetoface'),
                'select',
                array(
                    'selectfunc' => 'positions_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                ),
                'pos.id',
                'pos'
            ),
            new rb_filter_option(
                'session',
                'positionassignment',
                get_string('selectedpositionassignment', 'mod_facetoface'),
                'text',
                array(),
                'pos_assignment.fullname',
                'pos_assignment'
            ),
            new rb_filter_option(
                'session',
                'positiontype',
                get_string('selectedpositiontype', 'mod_facetoface'),
                'select',
                array(
                    'selectfunc' => 'position_types_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                ),
                'base.positiontype'
            ),
            new rb_filter_option(
                'session',
                'cancelledstatus',
                get_string('cancelledstatus', 'facetoface'),
                'select',
                array(
                    'selectfunc' => 'cancel_status',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
        );

        // include some standard filters
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_tag_fields_to_filters('course', $filteroptions);

        // add session role fields to filters
        $this->add_facetoface_session_role_fields_to_filters($filteroptions);

        $this->add_cohort_user_fields_to_filters($filteroptions);
        $this->add_cohort_course_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    public function rb_filter_position_types_list() {
        global $CFG, $POSITION_TYPES;

        include_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

        return $POSITION_TYPES;
    }

    public function rb_filter_cancel_status() {
        $selectchoices = array(
            '1' => get_string('cancelled', 'rb_source_facetoface_sessions')
        );

        return $selectchoices;
    }

    protected function define_contentoptions() {
        $contentoptions = array(
            new rb_content_option(
                'current_pos',
                get_string('currentpos', 'totara_reportbuilder'),
                'position.path',
                'position'
            ),
            new rb_content_option(
                'current_org',
                get_string('currentorg', 'totara_reportbuilder'),
                'organisation.path',
                'organisation'
            ),
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_facetoface_sessions'),
                array(
                    'userid' => 'base.userid',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                'position_assignment'
            ),
            new rb_content_option(
                'date',
                get_string('thedate', 'rb_source_facetoface_sessions'),
                'sessiondate.timestart',
                'sessiondate'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'userid',         // parameter name
                'base.userid'     // field
            ),
            new rb_param_option(
                'courseid',
                'course.id',
                'course'
            ),
            new rb_param_option(
                'status',
                'status.statuscode',
                'status'
            ),
            new rb_param_option(
                'sessionid',
                'base.sessionid'
            ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'course',
                'value' => 'courselink',
            ),
            array(
                'type' => 'date',
                'value' => 'sessiondate',
            ),
        );

        return $defaultcolumns;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array();

        $requiredcolumns[] = new rb_column(
            'course',
            'coursevisible',
            '',
            "course.visible",
            array(
                'joins' => 'course',
                'required' => 'true',
                'hidden' => 'true'
            )
        );

        $requiredcolumns[] = new rb_column(
            'course',
            'courseaudiencevisible',
            '',
            "course.audiencevisible",
            array(
                'joins' => 'course',
                'required' => 'true',
                'hidden' => 'true')
        );

        $requiredcolumns[] = new rb_column(
            'ctx',
            'id',
            '',
            "ctx.id",
            array(
                'joins' => 'ctx',
                'required' => 'true',
                'hidden' => 'true'
            )
        );

        return $requiredcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
            ),
            array(
                'type' => 'course',
                'value' => 'fullname',
                'advanced' => 1,
            ),
            array(
                'type' => 'status',
                'value' => 'statuscode',
                'advanced' => 1,
            ),
            array(
                'type' => 'date',
                'value' => 'sessiondate',
                'advanced' => 1,
            ),
        );

        return $defaultfilters;
    }

    protected function add_customfields() {
        $this->columnoptions[] = new rb_column_option(
            'facetoface_signup',
            'allsignupcustomfields',
            get_string('allsignupcustomfields', 'rb_source_facetoface_sessions'),
            'allsignupcustomfields',
            array(
                'columngenerator' => 'allcustomfields'
            )
        );
        $this->add_custom_fields_for('facetoface_session', 'sessions', 'facetofacesessionid', $this->joinlist, $this->columnoptions, $this->filteroptions);
        $this->add_custom_fields_for('facetoface_signup', 'status', 'facetofacesignupid', $this->joinlist, $this->columnoptions, $this->filteroptions);
        $this->add_custom_fields_for('facetoface_cancellation', 'cancellationstatus', 'facetofacecancellationid', $this->joinlist, $this->columnoptions, $this->filteroptions);
        $this->add_custom_fields_for('facetoface_sessioncancel', 'sessions', 'facetofacecancellationid', $this->joinlist, $this->columnoptions, $this->filteroptions);

        $this->add_custom_fields_for(
            'facetoface_room',
            'room',
            'facetofaceroomid',
            $this->joinlist,
            $this->columnoptions,
            $this->filteroptions,
            true
        );

        $this->add_custom_fields_for(
            'facetoface_asset',
            'asset',
            'facetofaceassetid',
            $this->joinlist,
            $this->columnoptions,
            $this->filteroptions,
            true
        );
    }

    //
    //
    // Methods for adding commonly used data to source definitions
    //
    //

    //
    //
    // Face-to-face specific display functions
    //
    //
    /**
     * Position name column with edit icon
     */
    public function rb_display_position_edit($position, $row, $isexport = false) {
        global $OUTPUT;

        if ($isexport) {
            return $position;
        }

        if (!$cm = get_coursemodule_from_instance('facetoface', $row->facetofaceid, $row->courseid)) {
            print_error('error:incorrectcoursemodule', 'facetoface');
        }
        $context = context_module::instance($cm->id);
        $canchangesignedupjobposition = has_capability('mod/facetoface:changesignedupjobposition', $context);

        $label = position::position_label($row);

        $url = new moodle_url('/mod/facetoface/attendee_position.php', array('s' => $row->sessionid, 'id' => $row->userid));
        $pix = new pix_icon('t/edit', get_string('edit', 'facetoface'));
        $icon = $OUTPUT->action_icon($url, $pix, null, array('class' => 'action-icon attendee-edit-position pull-right'));
        $positionhtml = html_writer::span($label, 'position'.$row->userid, array('id' => 'position'.$row->userid));

        if ($canchangesignedupjobposition) {
            return $icon . $positionhtml;
        }
        return $positionhtml;
    }

    /**
     * Position name column that will be displayed only when position select settings are enabled
     *
     * @param rb_column_option $columnoption Column settings configured by user
     * @param bool $hidden should this column always be hidden
     * @return array
     */
    public function rb_cols_generator_position_edit(rb_column_option $columnoption, $hidden) {
        $result = array();

        $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
        if ($selectpositiononsignupglobal) {
            $result[] = new rb_column(
                'session',
                'positionnameedit',
                format_text($columnoption->name),
                'pos_assignment.fullname',
                array(
                    'joins' => array('pos_assignment','pos'),
                    'dbdatatype' => 'text',
                    'outputformat' => 'text',
                    'displayfunc' => 'position_edit',
                    'hidden' => $hidden,
                    'extrafields' => array(
                        'positionassignmentname' => 'pos_assignment.fullname',
                        'positiontype' => 'pos_assignment.type',
                        'positionname' => 'pos.fullname',
                        'userid' => 'base.userid',
                        'courseid' => 'facetoface.course',
                        'sessionid' => 'sessions.id',
                        'facetofaceid' => 'facetoface.id')
                    )
            );
        }
        return $result;
    }

    public function rb_display_position_type($position, $row) {
        global $CFG, $POSITION_TYPES;

        include_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

        return get_string('type'.$POSITION_TYPES[$position], 'totara_hierarchy');
    }

    // convert a f2f activity name into a link to that activity
    function rb_display_link_f2f($name, $row) {
        global $OUTPUT;
        $activityid = $row->activity_id;
        return $OUTPUT->action_link(new moodle_url('/mod/facetoface/view.php', array('f' => $activityid)), $name);
    }

    // Override user display function to show 'Reserved' for reserved spaces.
    function rb_display_link_user($user, $row, $isexport = false) {
        if (!empty($row->id)) {
            return parent::rb_display_link_user($user, $row, $isexport);
        }
        return get_string('reserved', 'rb_source_facetoface_sessions');
    }

    // Override user display function to show 'Reserved' for reserved spaces.
    function rb_display_link_user_icon($user, $row, $isexport = false) {
        if (!empty($row->id)) {
            return parent::rb_display_link_user_icon($user, $row, $isexport);
        }
        return get_string('reserved', 'rb_source_facetoface_sessions');
    }

    /**
     * Display the email address of the approver
     *
     * @param int $approverid
     * @param object $row
     * @return string
     */
    function rb_display_approveremail($approverid, $row) {
        if (empty($approverid)) {
            return '';
        } else {
            $approver = core_user::get_user($approverid);
            return $approver->email;
        }
    }

    /**
     * Display the full name of the approver
     *
     * @param int $approverid
     * @param object $row
     * @return string
     */
    function rb_display_approvername($approverid, $row) {
        if (empty($approverid)) {
            return '';
        } else {
            $approver = core_user::get_user($approverid);
            return fullname($approver);
        }
    }

    // Override user display function to show 'Reserved' for reserved spaces.
    function rb_display_user($user, $row, $isexport = false) {
        if (!empty($user)) {
            return parent::rb_display_user($user, $row, $isexport);
        }
        return get_string('reserved', 'rb_source_facetoface_sessions');
    }


    //
    //
    // Source specific filter display methods
    //
    //

    function rb_filter_session_status_list() {
        global $CFG,$MDL_F2F_STATUS;

        include_once($CFG->dirroot.'/mod/facetoface/lib.php');

        $output = array();
        if (is_array($MDL_F2F_STATUS)) {
            foreach ($MDL_F2F_STATUS as $code => $statusitem) {
                $output[$code] = get_string('status_'.$statusitem,'facetoface');
            }
        }
        // show most completed option first in pulldown
        return array_reverse($output, true);

    }

    function rb_filter_coursedelivery_list() {
        $coursedelivery = array();
        $coursedelivery['Internal'] = 'Internal';
        $coursedelivery['External'] = 'External';
        return $coursedelivery;
    }

    /**
     * Reformat a timestamp and timezone into a date, showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row (which should include a timezone field)
     *
     * @return string Date in a nice format
     */
    function rb_display_show_cancelled_status($status) {
        if ($status == 1) {
            return get_string('cancelled', 'rb_source_facetoface_sessions');
        }
        return "";
    }

    public function post_config(reportbuilder $report) {
        $userid = $report->reportfor;
        if (isset($report->embedobj->embeddedparams['userid'])) {
            $userid = $report->embedobj->embeddedparams['userid'];
        }
        $fieldalias = 'course';
        $fieldbaseid = $report->get_field('course', 'id', 'course.id');
        $fieldvisible = $report->get_field('course', 'coursevisible', 'course.visible');
        $fieldaudvis = $report->get_field('course', 'courseaudiencevisible', 'course.audiencevisible');
        $report->set_post_config_restrictions(totara_visibility_where($userid,
            $fieldbaseid, $fieldvisible, $fieldaudvis, $fieldalias, 'course', $report->is_cached()));
    }

} // end of rb_source_facetoface_sessions class

