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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/totara/certification/lib.php');
require_once($CFG->dirroot . '/totara/coursecatalog/lib.php');

define('COMPLETIONTYPE_ALL', 1);
define('COMPLETIONTYPE_ANY', 2);
define('COMPLETIONTYPE_SOME', 3);
define('COMPLETIONTYPE_OPTIONAL', 4);

define('NEXTSETOPERATOR_THEN', 1);
define('NEXTSETOPERATOR_OR', 2);
define('NEXTSETOPERATOR_AND', 3);

abstract class course_set {
    public $id, $programid, $contenttype, $sortorder, $label;
    public $competencyid, $nextsetoperator, $completiontype;
    public $timeallowed, $timeallowednum, $timeallowedperiod;
    public $recurrencetime, $recurcreatetime;
    public $isfirstset, $islastset;
    public $uniqueid;
    public $certifpath;

    public function __construct($programid, $setob=null, $uniqueid=null) {
        if (is_object($setob)) {
            $this->id = $setob->id;
            $this->programid = $setob->programid;
            $this->sortorder = $setob->sortorder;
            $this->contenttype = $setob->contenttype;
            $this->label = $setob->label;
            $this->competencyid = $setob->competencyid;
            $this->nextsetoperator = $setob->nextsetoperator;
            $this->completiontype = $setob->completiontype;
            $this->timeallowed = $setob->timeallowed;
            $this->recurrencetime = $setob->recurrencetime;
            $this->recurcreatetime = $setob->recurcreatetime;
            $this->certifpath = $setob->certifpath;
        } else {
            $this->id = 0;
            $this->programid = $programid;
            $this->sortorder = 0;
            $this->contenttype = 0;
            $this->label = '';
            $this->competencyid = 0;
            $this->nextsetoperator = 0;
            $this->completiontype = 0;
            $this->timeallowed = 0;
            $this->recurrencetime = 0;
            $this->recurcreatetime = 0;
            $this->certifpath = 0;
        }

        $timeallowed = program_utilities::duration_explode($this->timeallowed);
        $this->timeallowednum = $timeallowed->num;
        $this->timeallowedperiod = $timeallowed->period;

        if ($uniqueid) {
            $this->uniqueid = $uniqueid;
        } else {
            $this->uniqueid = rand();
        }
    }

    public function init_form_data($formnameprefix, $formdata) {
        $defaultlabel = $this->get_default_label();

        $this->id = $formdata->{$formnameprefix.'id'};
        $this->programid = $formdata->id;
        $this->contenttype = $formdata->{$formnameprefix.'contenttype'};
        $this->sortorder = $formdata->{$formnameprefix.'sortorder'};
        $this->label = isset($formdata->{$formnameprefix.'label'})
                           && ! empty($formdata->{$formnameprefix.'label'}) ? $formdata->{$formnameprefix.'label'} : $defaultlabel;
        $this->nextsetoperator = isset($formdata->{$formnameprefix.'nextsetoperator'}) ? $formdata->{$formnameprefix.'nextsetoperator'} : 0;
        $this->timeallowednum = $formdata->{$formnameprefix.'timeallowednum'};
        $this->timeallowedperiod = $formdata->{$formnameprefix.'timeallowedperiod'};
        $this->timeallowed = program_utilities::duration_implode($this->timeallowednum, $this->timeallowedperiod);
    }

    protected function get_completion_type_string() {

        switch ($this->completiontype) {
        case COMPLETIONTYPE_ANY:
        case COMPLETIONTYPE_SOME:
        case COMPLETIONTYPE_OPTIONAL:
            $completiontypestr = get_string('or', 'totara_program');
            break;
        case COMPLETIONTYPE_ALL:
            $completiontypestr = get_string('and', 'totara_program');
            break;
        default:
            return false;
            break;
        }
        return $completiontypestr;
    }

    /**
     * Get html definition list of completion settings details (any, some, or all courses, min. number)
     * This does not include list of courses itself.
     *
     * @return string
     */
    protected function get_completion_explanation_html() {
        $out = '';
        // Explain some or all courses must be completed.
        $typestr = get_string('completeallcourses', 'totara_program');
        if ($this->completiontype != COMPLETIONTYPE_ALL) {
            if ($this->completiontype == COMPLETIONTYPE_ANY) {
                // Only one course must be completed.
                $typestr = get_string('completeanycourse', 'totara_program');
            } else {
                $a = new stdClass();
                $a->mincourses = $this->mincourses;
                $typestr = get_string('completemincourses', 'totara_program', $a);
            }
        }

        if ($this->timeallowed > 0) {
            $numperiod = program_utilities::get_duration_num_and_period($this->timeallowed);
            $typestr .= html_writer::tag('p', get_string('allowtimeforset' . $numperiod->periodkey, 'totara_program', $numperiod->num));
        }

        $out .= html_writer::div($typestr);
        return $out;
    }

    public function get_set_prefix() {
        return $this->uniqueid;
    }

    public function set_certifpath($c) {
        $this->certifpath=$c;
    }


    public function get_default_label() {
        return get_string('untitledset', 'totara_program');
    }

    public function is_recurring() {
        return false;
    }

    public function check_course_action($action, $formdata) {
        return false;
    }

    public function save_set() {
        global $DB;
        // Make sure the course set is saved with a sensible label instead of the default
        if ($this->label == $this->get_default_label()) {
            $this->label = get_string('legend:courseset', 'totara_program', $this->sortorder);
        }

        if (empty($this->islastset)) {
            $nextsetoperator = $this->nextsetoperator;
        } else {
            // We should have an empty nextsetoperator if this is the last courseset.
            $nextsetoperator = 0;
        }

        $todb = new stdClass();
        $todb->programid = $this->programid;
        $todb->sortorder = $this->sortorder;
        $todb->competencyid = $this->competencyid;
        $todb->nextsetoperator = $nextsetoperator;
        $todb->completiontype = $this->completiontype;
        $todb->timeallowed = $this->timeallowed;
        $todb->recurrencetime = $this->recurrencetime;
        $todb->recurcreatetime = $this->recurcreatetime;
        $todb->contenttype = $this->contenttype;
        $todb->label = $this->label;
        $todb->certifpath = $this->certifpath;

        if ($this->id > 0) { // if this set already exists in the database
            $todb->id = $this->id;
            return $DB->update_record('prog_courseset', $todb);
        } else {
            if ($id = $DB->insert_record('prog_courseset', $todb)) {
                $this->id = $id;
                return true;
            }
            return false;
        }
    }

    /**
     * Returns true or false depending on whether or not this course set
     * contains the specified course
     *
     * @param int $courseid
     * @return bool
     */
    abstract public function contains_course($courseid);

    /**
     * Checks whether or not the specified user has completed all the criteria
     * necessary to complete this course set and adds a record to the database
     * if so or returns false if not
     *
     * @param int $userid
     * @return int|bool
     */
    abstract public function check_courseset_complete($userid);

    /**
     * Updates the completion record in the database for the specified user
     *
     * @param int $userid
     * @param array $completionsettings Contains the field values for the record
     * @return bool|int
     */
    public function update_courseset_complete($userid, $completionsettings) {
        global $DB;
        $eventtrigger = false;

        // if the course set is being marked as complete we need to trigger an
        // event to any listening modules
        if (array_key_exists('status', $completionsettings)) {
            if ($completionsettings['status'] == STATUS_COURSESET_COMPLETE) {

                // flag that we need to trigger the courseset_completed event
                $eventtrigger = true;
            }
        }

        if ($completion = $DB->get_record('prog_completion',
                        array('coursesetid' => $this->id, 'programid' => $this->programid, 'userid' => $userid))) {

            // Do not update record if we have not received any data
            // (generally because we just want to make sure a record exists)
            if (empty($completionsettings)) {
                return true;
            }

            foreach ($completionsettings as $key => $val) {
                $completion->$key = $val;
            }

            if (empty($completion->timestarted)) {
                $completion->timestarted = !empty($completion->timecompleted) ? $completion->timecompleted : time();

                $certid = $DB->get_field('prog', 'certifid', array('id' => $this->programid));
                if (empty($certid)) {
                    $progcomp = prog_load_completion($this->programid, $userid, true);
                } else {
                    list($certcomp, $progcomp) = certif_load_completion($this->programid, $userid, true);
                }

                if (empty($progcomp->timestarted)) {
                    $progcomp->timestarted = $completion->timestarted;
                    if (empty($certid)) {
                        prog_write_completion($progcomp, 'Marked program as started');
                    } else {
                        certif_write_completion($certcomp, $progcomp, 'Marked program as started');
                    }
                }
            }

            if ($update_success = $DB->update_record('prog_completion', $completion)) {
                if ($eventtrigger) {
                    // trigger an event to notify any listeners that this course
                    // set has been completed
                    $event = \totara_program\event\program_courseset_completed::create(
                        array(
                            'objectid' => $this->programid,
                            'context' => context_program::instance($this->programid),
                            'userid' => $userid,
                            'other' => array(
                                'coursesetid' => $this->id,
                                'certifid' => 0,
                            )
                        )
                    );
                    $event->trigger();
                }
            }

            return $update_success;

        } else {

            $now = time();

            $completion = new stdClass();
            $completion->programid = $this->programid;
            $completion->userid = $userid;
            $completion->coursesetid = $this->id;
            $completion->status = STATUS_COURSESET_INCOMPLETE;
            $completion->timecreated = $now;
            $completion->timedue = 0;

            foreach ($completionsettings as $key => $val) {
                $completion->$key = $val;
            }

            if (empty($completion->timestarted)) {
                $completion->timestarted = !empty($completion->timecompleted) ? $completion->timecompleted : $now;
            }

            if ($insert_success = $DB->insert_record('prog_completion', $completion)) {
                if ($eventtrigger) {
                    // trigger an event to notify any listeners that this course
                    // set has been completed
                    $event = \totara_program\event\program_courseset_completed::create(
                        array(
                            'objectid' => $this->programid,
                            'context' => context_program::instance($this->programid),
                            'userid' => $userid,
                            'other' => array(
                                'coursesetid' => $this->id,
                                'certifid' => 0,
                            )
                        )
                    );
                    $event->trigger();
                }
            }

            return $insert_success;
        }
    }

    /**
     * Returns true or false depending on whether or not the specified user has
     * completed this course set
     *
     * @param int $userid
     * @return bool
     */
    public function is_courseset_complete($userid) {
        global $DB;
        if (!$userid) {
            return false;
        }
        $completion_status = $DB->get_field('prog_completion', 'status',
                        array('coursesetid' => $this->id, 'programid' => $this->programid, 'userid' => $userid));
        if ($completion_status === false) {
            return false;
        }

        return ($completion_status == STATUS_COURSESET_COMPLETE);
    }

    /**
     * Returns true if ths courseset is optional.
     * @return bool
     */
    public function is_considered_optional() {
        return false;
    }

    /**
     * Returns the HTML suitable for displaying a course set to a learner.
     *
     * @param int $userid
     * @param array $previous_sets
     * @param array $next_sets
     * @param bool $accessible Indicates whether or not the courses in the set are accessible to the user
     * @param bool $viewinganothersprogram Indicates if you are viewing another persons program
     *
     * @return string
     */
    abstract public function display($userid=null,$previous_sets=array(),$next_sets=array(),$accessible=true, $viewinganothersprogram=false);

    /**
     * Returns the HTML suitable for display the nextsetoperator in a friendly/informative manner
     *
     * @return string
     */
    public function display_nextsetoperator() {
        $out = '';
        if (!isset($this->islastset) || $this->islastset === false) {
            $out .= html_writer::start_tag('div', array('class' => 'nextsetoperator'));
            switch ($this->nextsetoperator) {
                case NEXTSETOPERATOR_THEN:
                    $out .= html_writer::tag('div', get_string('then', 'totara_program'), array('class' => 'operator-then'));
                    break;
                case NEXTSETOPERATOR_OR:
                    $out .= html_writer::tag('div', get_string('or', 'totara_program'), array('class' => 'operator-or'));
                    break;
                case NEXTSETOPERATOR_AND:
                    $out .= html_writer::tag('div', get_string('and', 'totara_program'), array('class' => 'operator-and'));
                    break;
            }
            $out .= html_writer::end_tag('div');
        }

        return $out;
    }

    /**
     * Returns an HTML string suitable for displaying as the label for a course
     * set in the program overview form
     *
     * @return string
     */
    public function display_form_label() {
        return $this->label;
    }

    /**
     * Returns an HTML string suitable for displaying as the element body
     * for a course set in the program overview form
     *
     * @return string
     */
    abstract public function display_form_element();

    /**
     * This method must be overrideen by sub-classes
     *
     * @param bool $return
     * @return string
     */
    abstract public function print_set_minimal();

    /**
     * Defines the form elements for a course set
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     */
    abstract public function get_courseset_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true);

    public function get_nextsetoperator_select_form_template(&$mform, &$template_values, $formdataobject, $prefix, $updateform=true) {
        $templatehtml = '';
        $hidden = false;

        if ($updateform) {
            if (isset($this->islastset) && $this->islastset) {
                $hidden = true;
                $mform->addElement('hidden', $prefix.'nextsetoperator', 0);
            } else {
                $options = array(
                    NEXTSETOPERATOR_THEN => get_string('then', 'totara_program'),
                    NEXTSETOPERATOR_OR => get_string('or', 'totara_program'),
                    NEXTSETOPERATOR_AND => get_string('and', 'totara_program')
                );
                $mform->addElement('select', $prefix.'nextsetoperator', get_string('label:nextsetoperator', 'totara_program'), $options);
                $mform->setDefault($prefix.'nextsetoperator', $this->nextsetoperator);
            }

            $mform->setType($prefix.'nextsetoperator', PARAM_INT);
            $template_values['%'.$prefix.'nextsetoperator%'] = array('name'=>$prefix.'nextsetoperator', 'value'=>null);
        }

        if ($hidden) {
            $operatorclass = '';
        } else {
            if ($this->nextsetoperator == NEXTSETOPERATOR_THEN) {
                $operatorclass = 'nextsetoperator-then';
            } else if ($this->nextsetoperator == NEXTSETOPERATOR_OR) {
                $operatorclass = 'nextsetoperator-or';
            } else {
                $operatorclass = 'nextsetoperator-and';
            }
        }

        $templatehtml .= html_writer::tag('div', '%' . $prefix . 'nextsetoperator%', array('class' => $operatorclass)) . "\n";
        $formdataobject->{$prefix.'nextsetoperator'} = $this->nextsetoperator;

        return $templatehtml;

    }

    /**
     * Returns text such as 'all courses from Course set 1'
     */
    abstract public function get_course_text($courseset);

    abstract public function get_courses();

    abstract public function delete_course($courseid);

    abstract public function build_progressinfo();

    public function set_progressinfo_course_scores($progressinfo, $userid) {
        $sets = $progressinfo->search_criteria($this->get_progressinfo_key());
        if (empty($sets)) {
            return;
        }

        foreach ($sets as $setinfo) {
            foreach ($this->get_courses() as $course) {
                // For now all criteria have the same weight - 1
                $weight = 1;

                // Get user's progress in completing this course
                $params = array(
                    'userid' => $userid,
                    'course' => $course->id,
                );
                $ccompletion = new completion_completion($params);
                // Need score between 0 and 1 where 1 == complete
                $percentagecomplete = $ccompletion->get_percentagecomplete();
                if ($percentagecomplete === false) {
                    $score = 0;
                } else {
                    $score = $percentagecomplete / 100.0;
                }

                // Each course can only appear once in a courseset
                $courseinfo = $setinfo->get_criteria($this->get_progressinfo_course_key($course));
                if ($courseinfo !== false) {
                    $courseinfo->set_weight($weight);
                    $courseinfo->set_score($score);

                    // We store the course timestarted and timecompleted in courses' customdata to
                    // allow us to use it when saving courseset completion status
                    $customdata = array ('timestarted' => $ccompletion->timestarted,
                                         'timecompleted' => $ccompletion->timecompleted);

                    if ($this->completiontype == COMPLETIONTYPE_SOME) {
                        if ($sumfield = customfield_get_field_instance($course, $this->coursesumfield, 'course', 'course')) {
                            $sumfieldval = $sumfield->display_data();
                            if ($sumfieldval === (string)(int)$sumfieldval) {
                                $customdata['coursepoints'] = (int)$sumfieldval;
                            } else {
                                $customdata['coursepoints'] = 0;
                            }
                        }
                    }

                    $courseinfo->set_customdata($customdata);
                }
            }
        }
    }

    public function get_progressinfo_key() {
        return 'courseset_'.$this->id.'_'.$this->label;
    }

    public function get_progressinfo_course_key($course) {
        return 'course_'.$course->id.'_'.$course->fullname;
    }

}

class multi_course_set extends course_set {

    public $courses, $courses_deleted_ids;
    public $mincourses, $coursesumfield, $coursesumfieldtotal;

    public function __construct($programid, $setob=null, $uniqueid=null) {
        global $DB;
        parent::__construct($programid, $setob, $uniqueid);

        $this->contenttype = CONTENTTYPE_MULTICOURSE;
        $this->courses = array();
        $this->courses_deleted_ids = array();

        $this->mincourses = 0;
        $this->coursesumfield = 0;
        $this->coursesumfieldtotal = 0;

        if (is_object($setob)) {
            $courseset_courses = $DB->get_records('prog_courseset_course', array('coursesetid' => $this->id), 'id asc');
            foreach ($courseset_courses as $courseset_course) {
                $course = $DB->get_record('course', array('id' => $courseset_course->courseid));
                if (!$course) {
                    // if the course has been deleted before being removed from the program we remove it from the course set
                    $DB->delete_records('prog_courseset_course', array('id' => $courseset_course->id));
                } else {
                    $this->courses[] = $course;
                }
            }

            $this->mincourses = $setob->mincourses;
            $this->coursesumfield = $setob->coursesumfield;
            $this->coursesumfieldtotal = $setob->coursesumfieldtotal;
        }
    }

    public function init_form_data($formnameprefix, $formdata) {
        global $DB;
        parent::init_form_data($formnameprefix, $formdata);

        $this->completiontype = $formdata->{$formnameprefix.'completiontype'};

        $this->mincourses = isset($formdata->{$formnameprefix.'mincourses'})
                           && ! empty($formdata->{$formnameprefix.'mincourses'}) ? $formdata->{$formnameprefix.'mincourses'} : '';

        $this->coursesumfield = isset($formdata->{$formnameprefix.'coursesumfield'})
                           && ! empty($formdata->{$formnameprefix.'coursesumfield'}) ? $formdata->{$formnameprefix.'coursesumfield'} : '';

        $this->coursesumfieldtotal = isset($formdata->{$formnameprefix.'coursesumfieldtotal'})
                           && ! empty($formdata->{$formnameprefix.'coursesumfieldtotal'}) ? $formdata->{$formnameprefix.'coursesumfieldtotal'} : '';

        if (isset($formdata->{$formnameprefix.'courses'})) {
            $courseids = explode(',', $formdata->{$formnameprefix.'courses'});
            foreach ($courseids as $courseid) {
                if ($courseid && $course = $DB->get_record('course', array('id' => $courseid))) {
                    $this->courses[] = $course;
                }
            }
        }

        $this->courses_deleted_ids = $this->get_deleted_courses($formdata);

    }

    /**
     * Retrieves the ids of any deleted courses for this course set from the
     * submitted data and returns an array containing the course id numbers
     * or an empty array
     *
     * @param <type> $formdata
     * @return <type>
     */
    public function get_deleted_courses($formdata) {

        $prefix = $this->get_set_prefix();

        if (!isset($formdata->{$prefix.'deleted_courses'}) || empty($formdata->{$prefix.'deleted_courses'})) {
            return array();
        }
        return explode(',', $formdata->{$prefix.'deleted_courses'});
    }

    public function check_course_action($action, $formdata) {

        $prefix = $this->get_set_prefix();

        foreach ($this->courses as $course) {
            if (isset($formdata->{$prefix.$action.'_'.$course->id})) {
                return $course->id;
            }
        }
        return false;
    }

    public function save_set() {
        global $DB;
        // Make sure the course set is saved with a sensible label instead of the default
        if ($this->label == $this->get_default_label()) {
            $this->label = get_string('legend:courseset', 'totara_program', $this->sortorder);
        }

        if (empty($this->islastset)) {
            $nextsetoperator = $this->nextsetoperator;
        } else {
            // We should have an empty nextsetoperator if this is the last courseset.
            $nextsetoperator = 0;
        }

        $todb = new stdClass();
        $todb->programid = $this->programid;
        $todb->sortorder = $this->sortorder;
        $todb->competencyid = $this->competencyid;
        $todb->nextsetoperator = $nextsetoperator;
        $todb->completiontype = $this->completiontype;
        $todb->timeallowed = $this->timeallowed;
        $todb->recurrencetime = $this->recurrencetime;
        $todb->recurcreatetime = $this->recurcreatetime;
        $todb->contenttype = $this->contenttype;
        $todb->label = $this->label;
        $todb->certifpath = $this->certifpath;

        $todb->mincourses = $this->mincourses;
        $todb->coursesumfield = $this->coursesumfield;
        $todb->coursesumfieldtotal = $this->coursesumfieldtotal;

        if ($this->id == 0) { // if this set doesn't already exist in the database
            $id = $DB->insert_record('prog_courseset', $todb);

            $this->id = $id;
        } else {
            $todb->id = $this->id;
            $DB->update_record('prog_courseset', $todb);
        }

        return $this->save_courses();
    }

    public function save_courses() {
        global $DB;
        if (!$this->id) {
            return false;
        }

        // first get program enrolment plugin class
        $program_plugin = enrol_get_plugin('totara_program');
        // then delete any courses from the database that have been marked for deletion
        foreach ($this->courses_deleted_ids as $courseid) {
            if ($courseset_course = $DB->get_record('prog_courseset_course',
                            array('coursesetid' => $this->id, 'courseid' => $courseid))) {
                $DB->delete_records('prog_courseset_course', array('coursesetid' => $this->id, 'courseid' => $courseid));
            }
        }

        //if the course no longer exists in any programs, remove the program enrolment plugin
        $courses_still_associated = prog_get_courses_associated_with_programs($this->courses_deleted_ids);
        $courses_to_remove_plugin_from = array_diff($this->courses_deleted_ids, array_keys($courses_still_associated));
        foreach ($courses_to_remove_plugin_from as $courseid) {
            $instance = $program_plugin->get_instance_for_course($courseid);
            if ($instance) {
                $program_plugin->delete_instance($instance);
            }
        }


        // then add any new courses
        foreach ($this->courses as $course) {
            if (!$ob = $DB->get_record('prog_courseset_course', array('coursesetid' => $this->id, 'courseid' => $course->id))) {
                //check if program enrolment plugin is already enabled on this course
                $instance = $program_plugin->get_instance_for_course($course->id);
                if (!$instance) {
                    //add it
                    $program_plugin->add_instance($course);
                }
                $ob = new stdClass();
                $ob->coursesetid = $this->id;
                $ob->courseid = $course->id;
                $DB->insert_record('prog_courseset_course', $ob);
            }
        }
        return true;
    }

    public function add_course($formdata) {
        global $DB;
        $courseid_elementname = $this->get_set_prefix().'courseid';

        if (isset($formdata->$courseid_elementname)) {
            $courseid = $formdata->$courseid_elementname;
            foreach ($this->courses as $course) {
                if ($courseid == $course->id) {
                    return true;
                }
            }
            $course = $DB->get_record('course', array('id' => $courseid));
            $this->courses[] = $course;
            return true;
        }
        return false;
    }

    public function delete_course($courseid) {
        global $DB;
        $new_courses = array();
        $coursefound = false;

        foreach ($this->courses as $course) {
            if ($course->id != $courseid) {
                $new_courses[] = $course;
            } else {
                if ($courseset_course = $DB->get_record('prog_courseset_course',
                                array('coursesetid' => $this->id, 'courseid' => $course->id))) {
                    $this->courses_deleted_ids[] = $course->id;
                }
                $coursefound = true;
            }
        }

        if ($coursefound) {
            $this->courses = $new_courses;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns true or false depending on whether or not this course set
     * contains the specified course
     *
     * @param int $courseid
     * @return bool
     */
    public function contains_course($courseid) {

        $courses = $this->courses;

        foreach ($courses as $course) {
            if ($course->id == $courseid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether or not the specified user has completed all the criteria
     * necessary to complete this course set and adds a record to the database
     * if so or returns false if not
     *
     * @param int $userid
     * @return int|bool
     */
    public function check_courseset_complete($userid) {
        $courses = $this->courses;
        $completiontype = $this->completiontype;

        // Check if this is an 'optional' or 'some 0' course set where the user doesn't have to complete any courses.
        if ($completiontype == COMPLETIONTYPE_OPTIONAL ||
            $completiontype == COMPLETIONTYPE_SOME && $this->mincourses == 0 && $this->coursesumfieldtotal == 0) {
            $completionsettings = array(
                'status'        => STATUS_COURSESET_COMPLETE,
                'timecompleted' => time()
            );
            return $this->update_courseset_complete($userid, $completionsettings);
        }

        // Check that the course set contains at least one course.
        if (!count($courses)) {
            return false;
        }

        $incomplete = false;
        $timestarted = 0;
        $completedcourses = 0;
        $coursefieldvalsum = 0;
        foreach ($courses as $course) {
            // create a new completion object for this course
            $completion_info = new completion_info($course);

            $params = array('userid' => $userid, 'course' => $course->id);
            $completion_completion = new completion_completion($params);

            // check if the course is complete
            if ($completion_completion->is_complete()) {
                if ($completiontype == COMPLETIONTYPE_ANY) {
                    $completionsettings = array(
                        'status'        => STATUS_COURSESET_COMPLETE,
                        'timestarted'   => $completion_completion->timestarted,
                        'timecompleted' => $completion_completion->timecompleted
                    );
                    return $this->update_courseset_complete($userid, $completionsettings);
                } else if ($completiontype == COMPLETIONTYPE_SOME) {
                    $completedcourses++;
                    if ($completedcourses >= $this->mincourses) {
                        $coursessatisfied = true;
                    }

                    if ($sumfield = customfield_get_field_instance($course, $this->coursesumfield, 'course', 'course')) {
                        $sumfieldval = $sumfield->display_data();
                        if ($sumfieldval === (string)(int)$sumfieldval) {
                            $coursefieldvalsum += (int)$sumfieldval;
                        }
                    }
                    if ($coursefieldvalsum >= $this->coursesumfieldtotal) {
                        $fieldsumsatisfied = true;
                    }

                    if (!empty($coursessatisfied) && !empty($fieldsumsatisfied)) {
                        return $this->complete_courseset_latest_completion($userid, $courses);
                    }
                }

                if (!empty($completion_completion->timestarted) && (empty($timestarted) || $completion_completion->timestarted < $timestarted)) {
                    $timestarted = $completion_completion->timestarted;
                }
            } else {
                $incomplete = true;
                if (!empty($completion_completion->timestarted) && (empty($timestarted) || $completion_completion->timestarted < $timestarted)) {
                    $timestarted = $completion_completion->timestarted;
                }
            }

            // Now we have the earliest time started, mark the courseset as started.
            if (!empty($timestarted)) {
                $this->mark_started($userid, $timestarted);
            }

            // If there is an incomplete course in a complete all courseset return false.
            if ($completiontype == COMPLETIONTYPE_ALL && $incomplete) {
                return false;
            }
        }

        // If processing reaches here and all courses in this set must be completed then
        // the course set is complete.
        if ($completiontype == COMPLETIONTYPE_ALL) {
            return $this->complete_courseset_latest_completion($userid, $courses);
        }

        return false;
    }

    /**
     * Set the timestarted field in the user's program completion record.
     *
     * If the user doesn't currently have a program completion record then it will be created. Certifications
     * may be restored from history.
     *
     * @param int $userid
     * @param int $timestarted
     */
    private function mark_started($userid, $timestarted) {
        global $DB;

        // Check what to do with the course set completion record.
        $csetcomp = prog_load_courseset_completion($this->id, $userid, false);

        if (!empty($csetcomp)) {
            // Course set completion record exists, so we might update it.
            if (empty($csetcomp->timestarted)) {
                $csetcomp->timestarted = $timestarted;

                prog_write_courseset_completion($csetcomp, "Courseset completion marked as started");
            }
        } else {
            // Course set completion record doesn't exist, so we create it and set time started.
            $data = array('timestarted' => $timestarted);
            prog_create_courseset_completion($this->id, $userid, $data, "Courseset completion created and marked as started");
        }

        $sql = "SELECT id FROM {prog} WHERE id = :programid AND certifid IS NOT NULL";
        $iscertif = $DB->record_exists_sql($sql, array('programid' => $this->programid));

        // Check what to do with the program completion record.
        if ($iscertif) {
            list($certcomp, $progcomp) = certif_load_completion($this->programid, $userid, false);
        } else {
            $progcomp = prog_load_completion($this->programid, $userid, false);
        }

        if (!empty($progcomp)) {
            // Program completion record exists, so we might update it.
            if (empty($progcomp->timestarted)) {
                $progcomp->timestarted = $timestarted;

                $message = "Program completion marked as started for courseset {$this->id}";
                if (!empty($certcomp)) {
                    certif_write_completion($certcomp, $progcomp, $message);
                } else {
                    prog_write_completion($progcomp, $message);
                }
            }
        } else {
            // Program completion record doesn't exist, so we create it and set time started.
            if ($iscertif) {
                // Certif_create_completion may restore a previous assignment.
                certif_create_completion($this->programid, $userid, "Completion created in mark_started");
                list($certcomp, $progcomp) = certif_load_completion($this->programid, $userid);
                // Double-check in case we restored a previous completion that already had a timestarted.
                if (empty($progcomp->timestarted)) {
                    $progcomp->timestarted = $timestarted;
                    certif_write_completion($certcomp, $progcomp, "Program completion marked as started due to courseset {$this->id}");
                }
            } else {
                $data = array('timestarted' => $timestarted);
                prog_create_completion($this->programid, $userid, $data, "Program completion created and marked as started due to courseset {$this->id}");
            }
        }
    }

    /**
     * Complete the course set, using the latest course completion in the set.
     *
     * @param int $userid
     * @param array $courses to use for deternminig the latest completion
     *
     * @return boolean
     */
    private function complete_courseset_latest_completion($userid, $courses) {
        global $DB;

        // Get the last course completed so we can use that timestamp for the courseset.
        $courseids = array();
        foreach ($courses as $course) {
            $courseids[] = $course->id;
        }

        list($incourse, $params) = $DB->get_in_or_equal($courseids);
        $sql = "SELECT MAX(timecompleted) AS timecompleted
            FROM {course_completions}
            WHERE course $incourse
            AND userid = ?";
        $params[] = $userid;
        $completion = $DB->get_record_sql($sql, $params);

        $completionsettings = array(
            'status'        => STATUS_COURSESET_COMPLETE,
            'timecompleted' => $completion->timecompleted
        );

        return $this->update_courseset_complete($userid, $completionsettings);
    }


    /**
     * Display a normal course set.
     *
     * @param null $userid If specified then it indicates the user is assigned to the program.
     * @param array $previous_sets
     * @param array $next_sets
     * @param bool $accessible True if assigned user has reached this course set.
     * @param bool $viewinganothersprogram
     * @return string
     */
    public function display($userid=null, $previous_sets=array(), $next_sets=array(), $accessible=true, $viewinganothersprogram=false) {
        global $USER, $OUTPUT, $DB, $CFG;

        if ($userid) {
            $usercontext = context_user::instance($userid);
        }

        $out = '';
        $out .= html_writer::start_tag('div', array('class' => 'surround display-program'));
        $out .= $OUTPUT->heading(format_string($this->label), 3);

        switch ($this->completiontype) {
            case COMPLETIONTYPE_ALL:
                $out .= html_writer::tag('p', html_writer::tag('strong', get_string('completeallcourses', 'totara_program')));
                break;
            case COMPLETIONTYPE_ANY:
                $out .= html_writer::tag('p', html_writer::tag('strong', get_string('completeanycourse', 'totara_program')));
                break;
            case COMPLETIONTYPE_SOME:
                $str = new stdClass();
                $str->mincourses = $this->mincourses;
                $str->sumfield = '';
                if ($coursecustomfield = $DB->get_record('course_info_field', array('id' => $this->coursesumfield))) {
                    $str->sumfield = format_string($coursecustomfield->fullname);
                }
                $str->sumfieldtotal = $this->coursesumfieldtotal;
                if (!empty($str->mincourses) && !empty($str->sumfield) && !empty($str->sumfieldtotal)) {
                    $completestr = get_string('completemincoursesminsum', 'totara_program', $str);
                } else if (!empty($this->mincourses)) {
                    $completestr = get_string('completemincourses', 'totara_program', $str);
                } else {
                    $completestr = get_string('completeminsumfield', 'totara_program', $str);
                }


                $out .= html_writer::tag('p', html_writer::tag('strong', $completestr));
                if (!empty($str->sumfield)) {
                    // Add information about the criteria.
                    $criteriacompletionset = get_string('criteriacompletioncourseset', 'totara_program', $str->sumfield);
                    $out .= html_writer::tag('p', html_writer::tag('strong', $criteriacompletionset));
                }

                break;
            case COMPLETIONTYPE_OPTIONAL:
                $out .= html_writer::tag('p', html_writer::tag('strong', get_string('completeoptionalcourses', 'totara_program')));
                break;
        }

        $numperiod = program_utilities::get_duration_num_and_period($this->timeallowed);

        if ($this->timeallowed > 0) {
            $out .= html_writer::tag('p', get_string('allowtimeforset' . $numperiod->periodkey, 'totara_program', $numperiod->num));
        }

        $customfieldcriteria = $this->completiontype == COMPLETIONTYPE_SOME &&
            $coursecustomfield && $coursecustomfield->hidden != 1;
        if (count($this->courses) > 0) {
            $table = new html_table();
            $table->head = array(get_string('coursename', 'totara_program'));

            if ($customfieldcriteria) {
                $table->head[] = $str->sumfield;
                $table->colclasses[] = 'criteriacourseset';
            }
            $table->head[] = get_string('actions');
            $table->colclasses = array('coursename', 'launchcourse');
            $table->attributes['class'] = 'fullwidth generaltable';
            if ($userid) {
                $table->head[] = get_string('status', 'totara_program');
                $table->colclasses[] = 'status';
                $completeheading = false;
            }

            // Get label for launch/view course button - applies to all courses.
            $launchviewlabel = get_string('launchcourse', 'totara_program');
            if (!empty($this->certifpath)) {
                $certificationid = $DB->get_field('prog', 'certifid', array('id' => $this->programid));
                $certifpath_user = get_certification_path_user($certificationid, $userid);
                if ($certifpath_user == CERTIFPATH_RECERT && !certif_iswindowopen($certificationid, $userid)) {
                    $launchviewlabel = get_string('viewcourse', 'totara_program');
                }
            }

            foreach ($this->courses as $course) {
                $cells = array();
                $coursecontext = context_course::instance($course->id);
                $coursename = format_string($course->fullname);

                if (empty($course->icon)) {
                    $course->icon = 'default';
                }

                $coursedetails = html_writer::empty_tag('img', array('src' => totara_get_icon($course->id, TOTARA_ICON_TYPE_COURSE),
                    'class' => 'course_icon', 'alt' => ''));

                $showcourseset = false;
                if ($userid) {
                    $showcourseset = (is_enrolled($coursecontext, $userid) || totara_course_is_viewable($course->id, $userid))
                                     && $accessible;
                }

                // Site admin can access any course.
                if (is_siteadmin($USER->id)) {
                    // Get visibility class name.
                    $dimmed = totara_get_style_visibility($course);
                    $coursedetails .= html_writer::link(new moodle_url('/course/view.php',
                        array('id' => $course->id)), $coursename, array('class' => $dimmed));
                    $launch = html_writer::tag('div', $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $course->id)),
                                    get_string('launchcourse', 'totara_program'), null), array('class' => 'prog-course-launch'));
                } else {
                    // User must be enrolled or course can be viewed (checks audience visibility),
                    // And course must be accessible.
                    if ($showcourseset) {
                        $coursedetails .= html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $coursename);
                        $launch = html_writer::tag('div', $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $course->id)),
                                         get_string('launchcourse', 'totara_program'), null), array('class' => 'prog-course-launch'));
                    } else if ($userid && $accessible && !empty($CFG->audiencevisibility) && $course->audiencevisible != COHORT_VISIBLE_NOUSERS) {
                        // If the program has been assigned but the user is not yet enrolled in the course,
                        // a course with audience visibility set to "Enrolled users" would not allow the user to become enrolled.
                        // Instead, when accessing the course, we redirect to the program requirements page, which will then directly enrol them into the course.
                        // This isn't needed for normal visibility because if the course is hidden then it will be inaccessible anyway.
                        $params = array('id' => $this->programid, 'cid' => $course->id, 'userid' => $userid, 'sesskey' => $USER->sesskey);
                        $requrl = new moodle_url('/totara/program/required.php', $params);
                        $coursedetails .= html_writer::link($requrl, $coursename);
                        $button = $OUTPUT->single_button($requrl, get_string('launchcourse', 'totara_program'), null);
                        $launch = html_writer::tag('div', $button, array('class' => 'prog-course-launch'));
                    } else {
                        $coursedetails .= $coursename;
                        $launch = html_writer::tag('div', $OUTPUT->single_button(null, get_string('notavailable', 'totara_program'), null,
                                        array('tooltip' => null, 'disabled' => true)), array('class' => 'prog-course-launch'));
                    }
                }
                $cells[] = new html_table_cell($coursedetails);
                if ($customfieldcriteria) {
                    $customfieldsdata = customfield_get_data($course, 'course', 'course');
                    $criteria = $coursecustomfield->defaultdata;
                    if (array_key_exists($coursecustomfield->fullname, $customfieldsdata)) {
                        $criteria = $customfieldsdata[$coursecustomfield->fullname];
                    }
                    $cells[] = new html_table_cell($criteria);
                }
                $cells[] = new html_table_cell($launch);

                if ($userid) {
                    if (!$status = $DB->get_field('course_completions', 'status', array('userid' => $userid, 'course' => $course->id))) {
                        $status = null;
                    }
                    $cells[] = new html_table_cell(totara_display_course_progress_bar($userid, $course->id, $status));
                    $markstaff = (\totara_job\job_assignment::is_managing($USER->id, $userid) && has_capability('totara/program:markstaffcoursecomplete', $usercontext));
                    $markuser = has_capability('totara/core:markusercoursecomplete', $usercontext);
                    $markcourse = has_capability('totara/program:markcoursecomplete', $coursecontext);
                    if ($showcourseset && ($markstaff || $markuser || $markcourse)) {
                        $completion = new completion_info($course);

                        if ($completion->is_course_complete($userid)) {
                            $ticked = 'y';
                            $action = 'delete';
                        } else {
                            $ticked = 'n';
                            $action = 'add';
                        }

                        $urlparams = array(
                            'userid' => $userid,
                            'courseid' => $course->id,
                            'progid' => $this->programid,
                            'sesskey' => sesskey()
                        );

                        $url = new moodle_url('/totara/program/content/completecourse.php', $urlparams);
                        $pix = new pix_icon('i/completion-manual-' . $ticked, get_string('completion-alt-manual-' . $ticked,
                                'completion', format_string($course->fullname)), '', array('class' => 'iconsmall'));
                        $link = $OUTPUT->action_icon($url, $pix, null, array('class' => $action . 'completion'));

                        $cells[] = new html_table_cell($link);
                        if (!$completeheading) {
                            $table->head[] = get_string('markcompletheading', 'totara_program');
                            $table->colclasses[] = 'markcomplete';
                            $completeheading = true;
                        }
                    }
                }
                $row = new html_table_row($cells);
                $table->data[] = $row;
            }
            $out .= html_writer::table($table, true);
        } else {
            $out .= html_writer::tag('p', get_string('nocourses', 'totara_program'));
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Returns an HTML string suitable for displaying as the element body
     * for a course set in the program overview form
     *
     * @return string
     */
    public function display_form_element() {

        $completiontypestr = $this->get_completion_type_string();
        $courses = $this->courses;

        $out = '';
        $out .= html_writer::start_tag('div', array('class' => 'courseset'));
        $out .= html_writer::start_tag('div', array('class' => 'courses'));

        if (count($courses)) {
            $coursestr = '';
            foreach ($courses as $course) {
                $coursestr .= format_string($course->fullname).' '.$completiontypestr.' ';
            }
            $coursestr = trim($coursestr);
            $coursestr = rtrim($coursestr, $completiontypestr);
            $out .= $coursestr;
        } else {
            $out .= get_string('nocourses', 'totara_program');
        }

        $out .= html_writer::end_tag('div');
        $out .= $this->get_completion_explanation_html();

        if (!isset($this->islastset) || $this->islastset === false) {
            if ($this->nextsetoperator != 0) {
                $out .= html_writer::start_tag('div', array('class' => 'nextsetoperator'));
                if ($this->nextsetoperator == NEXTSETOPERATOR_THEN) {
                    $out .= get_string('then', 'totara_program');
                } else if ($this->nextsetoperator == NEXTSETOPERATOR_OR) {
                    $out .= get_string('or', 'totara_program');
                } else {
                    $out .= get_string('and', 'totara_program');
                }
                $out .= html_writer::end_tag('div');
            }
        }
        $out .= html_writer::end_tag('div');

        return $out;
    }

    public function print_set_minimal() {

        $prefix = $this->get_set_prefix();

        $out = '';
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."id", 'value' => $this->id));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."label", 'value' => ''));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."sortorder", 'value' => $this->sortorder));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."contenttype", 'value' => $this->contenttype));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."nextsetoperator", 'value' => ''));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."completiontype", 'value' => COMPLETIONTYPE_ALL));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."mincourses", 'value' => 0));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."coursesumfield", 'value' => 0));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."coursesumfieldtotal", 'value' => 0));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."timeallowedperiod", 'value' => TIME_SELECTOR_DAYS));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."timeallowednum", 'value' => '1'));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."certifpath", 'value' => CERTIFPATH_CERT));

        if (isset($this->courses) && is_array($this->courses) && count($this->courses)>0) {
            $courseidsarray = array();
            foreach ($this->courses as $course) {
                $courseidsarray[] = $course->id;
            }
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."courses", 'value' => implode(',', $courseidsarray)));
        } else {
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."courses", 'value' => ''));
        }

        return $out;
    }

    public function print_courses() {
        global $OUTPUT;

        $prefix = $this->get_set_prefix();

        $out = '';
        $out .= html_writer::start_tag('div', array('class' => 'fitem'));
        $out .= html_writer::tag('div', get_string('courses', 'totara_program'). ':', array('class' => 'fitemtitle'));
        if (isset($this->courses) && is_array($this->courses) && count($this->courses)>0) {

            if (!$completiontypestr = $this->get_completion_type_string()) {
                print_error('unknowncompletiontype', 'totara_program', '', $this->sortorder);
            }

            $firstcourse = true;
            $list = '';
            foreach ($this->courses as $course) {
                if ($firstcourse) {
                    $content = html_writer::tag('span', '&nbsp;', array('class' => 'operator'));
                    $firstcourse = false;
                } else {
                    $content = html_writer::tag('span', $completiontypestr, array('class' => 'operator'));
                }
                $content .= html_writer::start_tag('div', array('class' => 'totara-item-group delete_item'));
                $content .= html_writer::start_tag('a',
                                array('class' => 'totara-item-group-icon coursedeletelink', 'href' => 'javascript:;',
                                      'data-coursesetid' => $this->id, 'data-coursesetprefix' => $prefix,
                                      'data-coursetodelete_id' => $course->id)
                            );
                $content .= $OUTPUT->pix_icon('t/delete', get_string('delete'));
                $content .= html_writer::end_tag('a');
                $content .= format_string($course->fullname);
                $content .= html_writer::end_tag('div');
                $list .= html_writer::tag('li', $content);
            }
            $ulattrs = array('id' => $prefix.'courselist', 'class' => 'course_list');
            $out .= html_writer::tag('div', html_writer::tag('ul', $list, $ulattrs), array('class' => 'felement'));

            $courseidsarray = array();
            foreach ($this->courses as $course) {
                $courseidsarray[] = $course->id;
            }
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix.'courses', 'value' => implode(',', $courseidsarray)));
        } else {
            $out .= html_writer::tag('div', get_string('nocourses', 'totara_program'), array('class' => 'felement'));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix.'courses', 'value' => ''));
        }
        $out .= html_writer::end_tag('div'); // End fitem.
        return $out;
    }

    public function print_deleted_courses() {

        $prefix = $this->get_set_prefix();

        $out = '';
        $deletedcourseidsarray = array();

        if ($this->courses_deleted_ids) {
            foreach ($this->courses_deleted_ids as $deleted_course_id) {
                $deletedcourseidsarray[] = $deleted_course_id;
            }
        }

        $deletedcoursesstr = implode(',', $deletedcourseidsarray);
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."deleted_courses", 'value' => $deletedcoursesstr));
        return $out;
    }

    /**
     * Defines the form elements for a course set
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     * @return <type>
     */
    public function get_courseset_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT, $DB;
        $prefix = $this->get_set_prefix();

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'surround course_set edit-program'));

        $helpbutton = $OUTPUT->help_icon('multicourseset', 'totara_program');
        $legend = ((isset($this->label) && ! empty($this->label)) ? $this->label : get_string('untitledset', 'totara_program',
                         $this->sortorder)) . ' ' . $helpbutton;
        $templatehtml .= html_writer::tag('legend', $legend);


        // Add set buttons
        $templatehtml .= html_writer::start_tag('div', array('class' => 'setbuttons'));

        // Add the move up button for this set
        if ($updateform) {
            $attributes = array();
            $attributes['class'] = 'btn-cancel moveup fieldsetbutton';
            if (isset($this->isfirstset)) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] .= ' disabled';
            }
            $mform->addElement('submit', $prefix.'moveup', get_string('moveup', 'totara_program'), $attributes);
            $template_values['%'.$prefix.'moveup%'] = array('name' => $prefix.'moveup', 'value' => null);
            $templatehtml .= '%'.$prefix.'moveup%'."\n";

            // Add the move down button for this set
            $attributes = array();
            $attributes['class'] = 'btn-cancel movedown fieldsetbutton';
            if (isset($this->islastset)) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] .= ' disabled';
            }
            $mform->addElement('submit', $prefix.'movedown', get_string('movedown', 'totara_program'), $attributes);
            $template_values['%'.$prefix.'movedown%'] = array('name' => $prefix.'movedown', 'value' => null);
            $templatehtml .= '%'.$prefix.'movedown%'."\n";

            // Add the delete button for this set
            $mform->addElement('submit', $prefix.'delete', get_string('delete', 'totara_program'),
                             array('class' => "btn-cancel delete fieldsetbutton setdeletebutton"));
            $template_values['%'.$prefix.'delete%'] = array('name' => $prefix.'delete', 'value' => null);
            $templatehtml .= '%'.$prefix.'delete%'."\n";
        }

        $templatehtml .= html_writer::end_tag('div');


        // Add the course set id
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'id', $this->id);
            $mform->setType($prefix.'id', PARAM_INT);
            $mform->setConstant($prefix.'id', $this->id);
            $template_values['%'.$prefix.'id%'] = array('name' => $prefix.'id', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'id%'."\n";
        $formdataobject->{$prefix.'id'} = $this->id;

        // Add the course set sort order
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'sortorder', $this->sortorder);
            $mform->setType($prefix.'sortorder', PARAM_INT);
            $mform->setConstant($prefix.'sortorder', $this->sortorder);
            $template_values['%'.$prefix.'sortorder%'] = array('name' => $prefix.'sortorder', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'sortorder%'."\n";
        $formdataobject->{$prefix.'sortorder'} = $this->sortorder;

        // Add the course set content type
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'contenttype', $this->contenttype);
            $mform->setType($prefix.'contenttype', PARAM_INT);
            $mform->setConstant($prefix.'contenttype', $this->contenttype);
            $template_values['%'.$prefix.'contenttype%'] = array('name' => $prefix.'contenttype', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'contenttype%'."\n";
        $formdataobject->{$prefix.'contenttype'} = $this->contenttype;

        // Add the list of deleted courses
        $templatehtml .= html_writer::start_tag('div', array('id' => $prefix.'deletedcourseslist'));
        $templatehtml .= $this->get_deleted_courses_form_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= html_writer::end_tag('div');

        // Add the course set label
        if ($updateform) {
            $mform->addElement('text', $prefix.'label', $this->label, array('size' => '40', 'maxlength' => '255', 'id' => $prefix.'label'));
            $mform->setType($prefix.'label', PARAM_TEXT);
            $template_values['%'.$prefix.'label%'] = array('name' => $prefix.'label', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('setlabel', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:setname', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'label'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'label%', array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'label'} = $this->label;

        // Add the completion type drop down field
        if ($updateform) {
            $completiontypeoptions = array(
                COMPLETIONTYPE_ANY => get_string('onecourse', 'totara_program'),
                COMPLETIONTYPE_ALL => get_string('allcourses', 'totara_program'),
                COMPLETIONTYPE_SOME => get_string('somecourses', 'totara_program'),
                COMPLETIONTYPE_OPTIONAL => get_string('completionoptional', 'totara_program'),
            );
            $onchange = 'return M.totara_programcontent.changeCompletionTypeString(this, '.$prefix.');';
            $mform->addElement('select', $prefix.'completiontype', get_string('label:learnermustcomplete', 'totara_program'),
                             $completiontypeoptions, array('class' => 'completiontype', 'onchange' => $onchange, 'id' => $prefix.'completiontype'));
            $mform->setType($prefix.'completiontype', PARAM_INT);
            $mform->setDefault($prefix.'completiontype', COMPLETIONTYPE_ALL);
            $template_values['%'.$prefix.'completiontype%'] = array('name' => $prefix.'completiontype', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('completiontype', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:learnermustcomplete', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'completiontype'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'completiontype%', array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'completiontype'} = $this->completiontype;

        // Min courses.
        if ($updateform) {
            $mform->addElement('text', $prefix.'mincourses', $this->mincourses, array('size' => '10', 'maxlength' => '255', 'id' => $prefix.'mincourses'));
            $mform->setType($prefix.'mincourses', PARAM_INT);
            $template_values['%'.$prefix.'mincourses%'] = array('name' => $prefix.'mincourses', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('mincourses', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('mincourses', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'mincourses'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'mincourses%', array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'mincourses'} = $this->mincourses;

        // Course custom field to be used for calculating the minimum sum
        if ($updateform) {
            $customfields  = $DB->get_records('course_info_field', array(), 'fullname');
            $canviewhidden = is_siteadmin();
            foreach ($customfields as $i => $field) {
                if ($field->hidden && !$canviewhidden) {
                    $customfields[$i] = get_string('hiddenfield', 'totara_program');
                } else {
                    $customfields[$i] = format_string($field->fullname);
                }
            }
            $customfields = array(0 => get_string('none')) + $customfields;
            $mform->addElement('select', $prefix.'coursesumfield', get_string('label:coursescorefield', 'totara_program'),
                             $customfields, array('id' => $prefix.'coursesumfield'));
            $mform->setType($prefix.'coursesumfield', PARAM_INT);
            $template_values['%'.$prefix.'coursesumfield%'] = array('name' => $prefix.'coursesumfield', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('coursescorefield', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:coursescorefield', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'coursesumfield'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'coursesumfield%', array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'coursesumfield'} = $this->coursesumfield;

        // Course field sum min val.
        if ($updateform) {
            $mform->addElement('text', $prefix.'coursesumfieldtotal', $this->coursesumfieldtotal, array('size' => '10', 'maxlength' => '255', 'id' => $prefix.'coursesumfieldtotal'));
            $mform->setType($prefix.'coursesumfieldtotal', PARAM_INT);
            $template_values['%'.$prefix.'coursesumfieldtotal%'] = array('name' => $prefix.'coursesumfieldtotal', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('minimumscore', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:minimumscore', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'coursesumfieldtotal'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'coursesumfieldtotal%', array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'coursesumfieldtotal'} = $this->coursesumfieldtotal;

        // Add the time allowance selection group
        if ($updateform) {
            $element = $mform->addElement('text', $prefix.'timeallowednum', $this->timeallowednum, array('size' => 4, 'maxlength' => 3, 'id' => $prefix.'timeallowance'));
            $mform->setType($prefix.'timeallowednum', PARAM_INT);
            $mform->addRule($prefix.'timeallowednum', get_string('required'), 'required', null, 'server');

            $timeallowanceoptions = program_utilities::get_standard_time_allowance_options(true);
            $select = $mform->addElement('select', $prefix.'timeallowedperiod', '', $timeallowanceoptions, array('id' => $prefix.'timeperiod'));
            $mform->setType($prefix.'timeallowedperiod', PARAM_INT);
            $templatehtml .= html_writer::tag('label', get_string('timeperiod', 'totara_program'), array('for' => $prefix.'timeperiod', 'class' => 'accesshide'));

            $template_values['%'.$prefix.'timeallowednum%'] = array('name'=>$prefix.'timeallowednum', 'value'=>null);
            $template_values['%'.$prefix.'timeallowedperiod%'] = array('name'=>$prefix.'timeallowedperiod', 'value'=>null);
        }
        $helpbutton = $OUTPUT->help_icon('minimumtimerequired', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:minimumtimerequired', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'timeallowance'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'timeallowednum% %'.$prefix.'timeallowedperiod%',
            array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'timeallowednum'} = $this->timeallowednum;
        $formdataobject->{$prefix.'timeallowedperiod'} = $this->timeallowedperiod;

        // Add the list of courses for this set
        $templatehtml .= html_writer::start_tag('div', array('id' => $prefix.'courselist', 'class' => 'courselist'));
        $templatehtml .= $this->get_courses_form_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= html_writer::end_tag('div');

        // Add the 'Add course' drop down list
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::tag('div', '', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'courseadder felement'));

        if ($updateform) {
            $mform->addElement('button', $prefix . 'addcourse', get_string('addcourses', 'totara_program'),
                               array('data-program-courseset-prefix' => $prefix));
            $template_values['%' . $prefix . 'addcourse%'] = array('name' => $prefix . 'addcourse', 'value' => null);
            $templatehtml .= '%' . $prefix . 'addcourse%' . "\n";
        }

        $templatehtml .= html_writer::end_tag('div'); // End felement.
        $templatehtml .= html_writer::end_tag('div'); // End fitem.

        $templatehtml .= html_writer::end_tag('fieldset');

        $templatehtml .= $this->get_nextsetoperator_select_form_template($mform, $template_values, $formdataobject, $prefix, $updateform);

        return $templatehtml;

    }

    /**
     * Defines the form elemens for the courses in a course set
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     * @return <type>
     */
    public function get_courses_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;

        $prefix = $this->get_set_prefix();

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::tag('div', get_string('courses', 'totara_program'). ':', array('class' => 'fitemtitle'));
        if (isset($this->courses) && is_array($this->courses) && count($this->courses)>0) {

            if (!$completiontypestr = $this->get_completion_type_string()) {
                print_error('unknowncompletiontype', 'totara_program', '', $this->sortorder);
            }

            $firstcourse = true;
            $list = '';
            foreach ($this->courses as $course) {
                if ($firstcourse) {
                    $content = html_writer::tag('span', '&nbsp;', array('class' => 'operator'));
                    $firstcourse = false;
                } else {
                    $content = html_writer::tag('span', $completiontypestr, array('class' => 'operator'));
                }
                $content .= html_writer::start_tag('div', array('class' => 'totara-item-group delete_item'));
                $content .= html_writer::start_tag('a',
                                array('class' => 'totara-item-group-icon coursedeletelink', 'href' => 'javascript:;',
                                      'data-coursesetid' => $this->id, 'data-coursesetprefix' => $prefix,
                                      'data-coursetodelete_id' => $course->id)
                            );
                $content .= $OUTPUT->pix_icon('t/delete', get_string('delete'));
                $content .= html_writer::end_tag('a');
                $content .= format_string($course->fullname);
                $content .= html_writer::end_tag('div');
                $list .= html_writer::tag('li', $content);
            }
            $ulattrs = array('id' => $prefix.'displaycourselist', 'class' => 'course_list');
            $templatehtml .= html_writer::tag('div', html_writer::tag('ul', $list, $ulattrs), array('class' => 'felement'));

            $courseidsarray = array();
            foreach ($this->courses as $course) {
                $courseidsarray[] = $course->id;
            }
            $coursesstr = implode(',', $courseidsarray);
            if ($updateform) {
                $mform->addElement('hidden', $prefix.'courses', $coursesstr);
                $mform->setType($prefix.'courses', PARAM_SEQUENCE);
                $mform->setConstant($prefix.'courses', $coursesstr);
                $template_values['%'.$prefix.'courses%'] = array('name'=>$prefix.'courses', 'value'=>null);
            }
            $templatehtml .= '%'.$prefix.'courses%'."\n";
            $formdataobject->{$prefix.'courses'} = $coursesstr;

        } else {
            if ($updateform) {
                $mform->addElement('hidden', $prefix.'courses');
                $mform->setType($prefix.'courses', PARAM_SEQUENCE);
                $mform->setConstant($prefix.'courses', '');
                $template_values['%'.$prefix.'courses%'] = array('name'=>$prefix.'courses', 'value'=>null);
            }
            $templatehtml .= '%'.$prefix.'courses%'."\n";
            $formdataobject->{$prefix.'courses'} = '';

            $templatehtml .= html_writer::tag('div', get_string('nocourses', 'totara_program'), array('class' => 'felement'));
        }

        $templatehtml .= html_writer::end_tag('div'); // End fitem.

        return $templatehtml;

    }

    /**
     * Defines the form elements for the deleted courses
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     * @return <type>
     */
    public function get_deleted_courses_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {

        $prefix = $this->get_set_prefix();

        $templatehtml = '';
        $deletedcourseidsarray = array();

        if ($this->courses_deleted_ids) {
            foreach ($this->courses_deleted_ids as $deleted_course_id) {
                $deletedcourseidsarray[] = $deleted_course_id;
            }
        }

        $deletedcoursesstr = implode(',', $deletedcourseidsarray);
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'deleted_courses', $deletedcoursesstr);
            $mform->setType($prefix.'deleted_courses', PARAM_SEQUENCE);
            $mform->setConstant($prefix.'deleted_courses', $deletedcoursesstr);
            $template_values['%'.$prefix.'deleted_courses%'] = array('name'=>$prefix.'deleted_courses', 'value'=>null);
        }
        $templatehtml .= '%'.$prefix.'deleted_courses%'."\n";
        $formdataobject->{$prefix.'deleted_courses'} = $deletedcoursesstr;


        return $templatehtml;
    }

    public function get_course_text($courseset) {
        if ($courseset->completiontype == COMPLETIONTYPE_ALL) {
            return get_string('allcoursesfrom', 'totara_program') . ' "' . format_string($courseset->label) . '"';
        }
        else if ($courseset->completiontype == COMPLETIONTYPE_SOME) {
            return get_string('somecoursesfrom', 'totara_program') . ' "' . format_string($courseset->label) . '"';
        }
        else if ($courseset->completiontype == COMPLETIONTYPE_OPTIONAL) {
            return get_string('nocoursesfrom', 'totara_program') . ' "' . format_string($courseset->label) . '"';
        }
        else {
            return get_string('onecoursesfrom', 'totara_program') . ' "' . format_string($courseset->label) . '"';
        }
    }

    public function get_courses() {
        return $this->courses;
    }

    /**
     * Returns true if ths courseset is optional.
     * @return bool
     */
    public function is_considered_optional() {
        if ($this->completiontype == COMPLETIONTYPE_OPTIONAL) {
            // Clearly so.
            return true;
        }
        if ($this->completiontype == COMPLETIONTYPE_SOME && $this->mincourses == 0) {
            // Some courses are required, but that is set to 0, so its optional.
            return true;
        }
        return parent::is_considered_optional();
    }

    /**
     * Build progressinfo hierarchy for this courseset
     *
     * @return \totara_core\progressinfo\progressinfo
     */
    public function build_progressinfo() {
        $agg_class = '';
        $customdata = null;

        switch ($this->completiontype) {
            case COMPLETIONTYPE_ANY;
                $agg_method = \totara_core\progressinfo\progressinfo::AGGREGATE_ANY;
                break;

            case COMPLETIONTYPE_SOME;
                $agg_method = \totara_core\progressinfo\progressinfo::AGGREGATE_ALL;
                $agg_class = '\totara_program\progress\progressinfo_aggregate_some';
                $customdata = array('requiredcourses' => $this->mincourses,
                                    'requiredpoints' => $this->coursesumfieldtotal,
                                    'totalcourses' => 0,
                                    'totalpoints' => 0);
                break;

            case COMPLETIONTYPE_OPTIONAL;
                $agg_method = \totara_core\progressinfo\progressinfo::AGGREGATE_NONE;
                break;

            default:
                $agg_method = \totara_core\progressinfo\progressinfo::AGGREGATE_ALL;
        }

        $progressinfo = \totara_core\progressinfo\progressinfo::from_data($agg_method, 0, 0, $customdata, $agg_class);

        // Add courses in the courseset
        foreach ($this->get_courses() as $course) {
            $progressinfo->add_criteria(parent::get_progressinfo_course_key($course));
        }

        return $progressinfo;
    }
}


class competency_course_set extends course_set {

    public function __construct($programid, $setob=null, $uniqueid=null) {
        parent::__construct($programid, $setob, $uniqueid);
        $this->contenttype = CONTENTTYPE_COMPETENCY;

        if (is_object($setob)) {
            // completiontype can change if the competency changes so we have to check it every time
            if (!$this->completiontype = $this->get_completion_type()) {
                $this->completiontype = COMPLETIONTYPE_ALL;
            }
        }
    }

    public function save_set() {

        $courses = $this->get_competency_courses();
        $program_plugin = enrol_get_plugin('totara_program');
        foreach ($courses as $course) {
            //check if program enrolment plugin is already enabled on this course
            $instance = $program_plugin->get_instance_for_course($course->id);
            if (!$instance) {
                //add it
                $program_plugin->add_instance($course);
            }
        }
        return parent::save_set();
    }

    public function init_form_data($formnameprefix, $formdata) {
        parent::init_form_data($formnameprefix, $formdata);

        $this->competencyid = $formdata->{$formnameprefix.'competencyid'};

        if (!$this->completiontype = $this->get_completion_type()) {
            $this->completiontype = COMPLETIONTYPE_ALL;
        }
    }

    public function get_completion_type() {
        global $DB;
        if (!$competency = $DB->get_record('comp', array('id' => $this->competencyid))) {
            return false;
        } else {
            return ($competency->aggregationmethod == COMPLETIONTYPE_ALL ? COMPLETIONTYPE_ALL : COMPLETIONTYPE_ANY);
        }
    }

    public function add_competency($formdata) {
        global $DB;
        $competencyid_elementname = $this->get_set_prefix().'competencyid';

        if (isset($formdata->$competencyid_elementname)) {
            $competencyid = $formdata->$competencyid_elementname;
            if ($competency = $DB->get_record('comp', array('id' => $competencyid))) {
                $this->competencyid = $competency->id;
                $this->completiontype = $this->get_completion_type();
                // completiontype can change if the competency changes so we have to check it every time
                return true;
            }
        }
        return false;
    }

    public function get_competency_courses() {
        global $DB;

        $sql = "SELECT c.*
            FROM {course} AS c
            JOIN {comp_criteria} AS cc ON c.id = cc.iteminstance
           WHERE cc.competencyid = ?
             AND cc.itemtype = ?";

        return $DB->get_records_sql($sql, array($this->competencyid, 'coursecompletion'));
    }

    /**
     * Returns true or false depending on whether or not this course set
     * contains the specified course
     *
     * @param int $courseid
     * @return bool
     */
    public function contains_course($courseid) {

        $courses = $this->get_competency_courses();

        if ($courses) {
            foreach ($courses as $course) {
                if ($course->id == $courseid) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks whether or not the specified user has completed all the criteria
     * necessary to complete this course set and adds a record to the database
     * if so or returns false if not
     *
     * @param int $userid
     * @return int|bool
     */
    public function check_courseset_complete($userid) {

        $courses = $this->get_competency_courses();
        $completiontype = $this->get_completion_type();

        // check that the course set contains at least one course
        if (!$courses || !count($courses)) {
            return false;
        }

        foreach ($courses as $course) {

            $set_completed = false;

            // create a new completion object for this course
            $completion_info = new completion_info($course);

            // check if the course is complete
            if ($completion_info->is_course_complete($userid)) {
                if ($completiontype == COMPLETIONTYPE_ANY) {
                    $completionsettings = array(
                        'status'        => STATUS_COURSESET_COMPLETE,
                        'timecompleted' => time()
                    );
                    return $this->update_courseset_complete($userid, $completionsettings);
                }
            } else {
                // if all courses must be completed for this ourse set to be complete
                if ($completiontype == COMPLETIONTYPE_ALL) {
                    return false;
                }
            }
        }

        // if processing reaches here and all courses in this set must be comleted then the course set is complete
        if ($completiontype == COMPLETIONTYPE_ALL) {
            $completionsettings = array(
                'status'        => STATUS_COURSESET_COMPLETE,
                'timecompleted' => time()
            );
            return $this->update_courseset_complete($userid, $completionsettings);
        }

        return false;
    }

    /**
     * Display a competency course set.
     *
     * @param null $userid If specified then it indicates the user is assigned to the program.
     * @param array $previous_sets
     * @param array $next_sets
     * @param bool $accessible True if assigned user has reached this course set.
     * @param bool $viewinganothersprogram
     * @return string
     */
    public function display($userid=null,$previous_sets=array(),$next_sets=array(),$accessible=true, $viewinganothersprogram=false) {
        global $OUTPUT, $DB, $CFG, $USER;

        $out = '';
        $out .= html_writer::start_tag('div', array('class' => 'surround display-program'));
        $out .= $OUTPUT->heading(format_string($this->label), 3);

        switch ($this->completiontype) {
            case COMPLETIONTYPE_ALL:
                $out .= html_writer::tag('p', html_writer::tag('strong', get_string('completeallcourses', 'totara_program')));
                break;
            case COMPLETIONTYPE_ANY:
                $out .= html_writer::tag('p', html_writer::tag('strong', get_string('completeanycourse', 'totara_program')));
                break;
        }

        $numperiod = program_utilities::get_duration_num_and_period($this->timeallowed);

        if ($this->timeallowed > 0) {
            $out .= html_writer::tag('p', get_string('allowtimeforset' . $numperiod->periodkey, 'totara_program', $numperiod->num));
        }

        $courses = $this->get_competency_courses();

        if ($courses && count($courses) > 0) {
            $table = new html_table();
            $table->head = array(get_string('coursename', 'totara_program'), '');
            $table->colclasses = array('coursename', 'launchcourse');
            $table->attributes['class'] = 'fullwidth generaltable';
            if ($userid) {
                $table->head[] = get_string('status', 'totara_program');
                $table->colclasses[] = 'status';
            }
            foreach ($courses as $course) {
                if (empty($course->icon)) {
                    $course->icon = 'default';
                }

                $cells = array();
                $coursedetails = html_writer::empty_tag('img', array('src' => totara_get_icon($course->id, TOTARA_ICON_TYPE_COURSE),
                    'class' => 'course_icon', 'alt' => ''));
                $coursedetails .= $accessible ? html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)),
                                 format_string($course->fullname)) : format_string($course->fullname);
                $cells[] = new html_table_cell($coursedetails);
                if (is_siteadmin($USER->id) || $userid && $accessible && totara_course_is_viewable($course->id, $userid)) {
                    $launch = html_writer::tag('div', $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $course->id)),
                                     get_string('launchcourse', 'totara_program'), null), array('class' => 'prog-course-launch'));
                } else if ($userid && $accessible && !empty($CFG->audiencevisibility) && $course->audiencevisible != COHORT_VISIBLE_NOUSERS) {
                    // If the program has been assigned but the user is not yet enrolled in the course,
                    // a course with audience visibility set to "Enrolled users" would not allow the user to become enrolled.
                    // Instead, when "Launch" is clicked, we redirect to the program requirements page, which will then directly enrol them into the course.
                    // This isn't needed for normal visibility because if the course is hidden then it will be inaccessible anyway.
                    $params = array('id' => $this->programid, 'cid' => $course->id, 'userid' => $userid, 'sesskey' => $USER->sesskey);
                    $requrl = new moodle_url('/totara/program/required.php', $params);
                    $button = $OUTPUT->single_button($requrl, get_string('launchcourse', 'totara_program'), null);
                    $launch = html_writer::tag('div', $button, array('class' => 'prog-course-launch'));
                } else {
                    $launch = html_writer::tag('div', $OUTPUT->single_button(null, get_string('notavailable', 'totara_program'), null,
                                     array('tooltip' => null, 'disabled' => true)), array('class' => 'prog-course-launch'));
                }
                $cells[] = new html_table_cell($launch);
                if ($userid) {
                    if (!$status = $DB->get_field('course_completions', 'status', array('userid' => $userid, 'course' => $course->id))) {
                        $status = COMPLETION_STATUS_NOTYETSTARTED;
                    }
                    $cells[] = new html_table_cell(totara_display_course_progress_bar($userid, $course->id, $status));
                }
                $row = new html_table_row($cells);
                $table->data[] = $row;
            }
            $out .= html_writer::table($table);
        } else {
            $out .= html_writer::tag('p', get_string('nocourses', 'totara_program'));
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Returns an HTML string suitable for displaying as the element body
     * for a course set in the program overview form
     *
     * @return string
     */
    public function display_form_element() {

        $completiontypestr = $this->get_completion_type_string();
        $courses = $this->get_competency_courses();

        $out = '';
        $out .= html_writer::start_tag('div', array('class' => 'courseset'));
        $out .= html_writer::start_tag('div', array('class' => 'courses'));

        if ($courses && count($courses) > 0) {
            $coursestr = '';
            foreach ($courses as $course) {
                $coursestr .= format_string($course->fullname).' '.$completiontypestr.' ';
            }
            $coursestr = trim($coursestr);
            $coursestr = rtrim($coursestr, $completiontypestr);
            $out .= $coursestr;
        } else {
            $out .= get_string('nocourses', 'totara_program');
        }

        $out .= html_writer::end_tag('div');
        $out .= $this->get_completion_explanation_html();

        if ($this->nextsetoperator != 0) {
            $out .= html_writer::start_tag('div', array('class' => 'nextsetoperator'));
            if ($this->nextsetoperator == NEXTSETOPERATOR_THEN) {
                $out .= get_string('then', 'totara_program');
            } else if ($this->nextsetoperator == NEXTSETOPERATOR_OR) {
                $out .= get_string('or', 'totara_program');
            } else {
                $out .= get_string('and', 'totara_program');
            }
            $out .= html_writer::end_tag('div');
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
     * Prints only the inputs required for this course set as hidden inputs.
     * This is used when a new set is created by javascript in the form so that
     * the new set values will be submitted when the form is submitted.
     *
     * @param <type> $return
     * @return <type>
     */
    public function print_set_minimal() {

        $prefix = $this->get_set_prefix();

        $out = '';
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."id", 'value' => $this->id));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."label", 'value' => ''));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."sortorder", 'value' => $this->sortorder));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."contenttype", 'value' => $this->contenttype));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."nextsetoperator", 'value' => ''));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."timeallowedperiod", 'value' => TIME_SELECTOR_DAYS));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."timeallowednum", 'value' => '1'));

        if ($this->competencyid > 0) {
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."competencyid", 'value' => $this->competencyid));
        } else {
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."competencyid", 'value' => '0'));
        }

        return $out;
    }

    /**
     * Defines the form elements for this course set and builds the template
     * in which the form will be rendered.
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     * @return <type>
     */
    public function get_courseset_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT, $DB;
        $prefix = $this->get_set_prefix();

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'course_set surround edit-program'));

        $helpbutton = $OUTPUT->help_icon('competencycourseset', 'totara_program');
        $legend = ((isset($this->label) && ! empty($this->label)) ? $this->label : get_string('legend:courseset', 'totara_program',
                        $this->sortorder)) . ' ' . $helpbutton;
        $templatehtml .= html_writer::tag('legend', $legend);
        $templatehtml .= html_writer::start_tag('div', array('class' => 'setbuttons'));

        // Add the move up button for this set
        if ($updateform) {
            $attributes = array();
            $attributes['class'] = 'btn-cancel moveup fieldsetbutton';
            if (isset($this->isfirstset)) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] .= ' disabled';
            }
            $mform->addElement('submit', $prefix.'moveup', get_string('moveup', 'totara_program'), $attributes);
            $template_values['%'.$prefix.'moveup%'] = array('name' => $prefix.'moveup', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'moveup%'."\n";

        // Add the move down button for this set
        if ($updateform) {
            $attributes = array();
            $attributes['class'] = 'btn-cancel movedown fieldsetbutton';
            if (isset($this->islastset)) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] .= ' disabled';
            }
            $mform->addElement('submit', $prefix.'movedown', get_string('movedown', 'totara_program'), $attributes);
            $template_values['%'.$prefix.'movedown%'] = array('name' => $prefix.'movedown', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'movedown%'."\n";

        // Add the delete button for this set
        if ($updateform) {
            $mform->addElement('submit', $prefix.'delete', get_string('delete', 'totara_program'),
                             array('class' => "btn-cancel delete fieldsetbutton setdeletebutton"));
            $template_values['%'.$prefix.'delete%'] = array('name' => $prefix.'delete', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'delete%'."\n";
        $templatehtml .= html_writer::end_tag('div');

        // Add the course set id
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'id', $this->id);
            $mform->setType($prefix.'id', PARAM_INT);
            $mform->setConstant($prefix.'id', $this->id);
            $template_values['%'.$prefix.'id%'] = array('name' => $prefix.'id', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'id%'."\n";
        $formdataobject->{$prefix.'id'} = $this->id;

        // Add the course set sort order
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'sortorder', $this->sortorder);
            $mform->setType($prefix.'sortorder', PARAM_INT);
            $mform->setConstant($prefix.'sortorder', $this->sortorder);
            $template_values['%'.$prefix.'sortorder%'] = array('name' => $prefix.'sortorder', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'sortorder%'."\n";
        $formdataobject->{$prefix.'sortorder'} = $this->sortorder;

        // Add the course set content type
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'contenttype', $this->contenttype);
            $mform->setType($prefix.'contenttype', PARAM_INT);
            $mform->setConstant($prefix.'contenttype', $this->contenttype);
            $template_values['%'.$prefix.'contenttype%'] = array('name' => $prefix.'contenttype', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'contenttype%'."\n";
        $formdataobject->{$prefix.'contenttype'} = $this->contenttype;

        // Add the course set label
        if ($updateform) {
            $mform->addElement('text', $prefix.'label', $this->label, array('size' => '40', 'maxlength' => '255'));
            $mform->setType($prefix.'label', PARAM_TEXT);
            $template_values['%'.$prefix.'label%'] = array('name' => $prefix.'label', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('setlabel', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:setname', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'label'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'label%', array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'label'} = $this->label;

        if ($this->competencyid > 0) {
            if ($competency = $DB->get_record('comp', array('id' => $this->competencyid))) {
                $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
                $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
                $templatehtml .= html_writer::tag('label', get_string('label:competencyname', 'totara_program'));
                $templatehtml .= html_writer::end_tag('div');
                $templatehtml .= html_writer::tag('div', format_string($competency->fullname), array('class' => 'felement'));
                $templatehtml .= html_writer::end_tag('div');
            }
        }

        // Add the time allowance selection group
        if ($updateform) {

            $mform->addElement('text', $prefix.'timeallowednum', $this->timeallowednum, array('size' => 4, 'maxlength' => 3));
            $mform->setType($prefix.'timeallowednum', PARAM_INT);
            $mform->addRule($prefix.'timeallowednum', get_string('required'), 'required', null, 'server');

            $timeallowanceoptions = program_utilities::get_standard_time_allowance_options(true);
            $mform->addElement('select', $prefix.'timeallowedperiod', '', $timeallowanceoptions);
            $mform->setType($prefix.'timeallowedperiod', PARAM_INT);

            $template_values['%'.$prefix.'timeallowednum%'] = array('name' => $prefix.'timeallowednum', 'value' => null);
            $template_values['%'.$prefix.'timeallowedperiod%'] = array('name' => $prefix.'timeallowedperiod', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('minimumtimerequired', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:minimumtimerequired', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'timeallowance'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%' . $prefix . 'timeallowednum% %' . $prefix . 'timeallowedperiod%',
            array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'timeallowednum'} = $this->timeallowednum;
        $formdataobject->{$prefix.'timeallowedperiod'} = $this->timeallowedperiod;

        $templatehtml .= html_writer::start_tag('div', array('id' => $prefix.'courselist', 'class' => 'courselist'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::tag('div', get_string('courses', 'totara_program'). ':', array('class' => 'fitemtitle'));

        if ($this->competencyid > 0) {

            if (!$completiontypestr = $this->get_completion_type_string()) {
                print_error('unknowncompletiontype', 'totara_program', '', $this->sortorder);
            }

            if ($courses = $this->get_competency_courses()) {
                $firstcourse = true;
                $list = '';
                foreach ($courses as $course) {
                    if ($firstcourse) {
                        $content = html_writer::tag('span', '&nbsp;', array('class' => 'operator'));
                        $firstcourse = false;
                    } else {
                        $content = html_writer::tag('span', $completiontypestr, array('class' => 'operator'));
                    }
                    $content .= html_writer::start_tag('div', array('class' => 'totara-item-group delete_item'));
                    $content .= html_writer::start_tag('a',
                                    array('class' => 'totara-item-group-icon coursedeletelink', 'href' => 'javascript:;',
                                          'data-coursesetid' => $this->id, 'data-coursesetprefix' => $prefix,
                                          'data-coursetodelete_id' => $course->id)
                                );
                    $content .= $OUTPUT->pix_icon('t/delete', get_string('delete'));
                    $content .= html_writer::end_tag('a');
                    $content .= format_string($course->fullname);
                    $content .= html_writer::end_tag('div');
                    $list .= html_writer::tag('li', $content);
                }
                $ulattrs = array('id' => $prefix.'courselist', 'class' => 'course_list');
                $templatehtml .= html_writer::tag('div', html_writer::tag('ul', $list, $ulattrs), array('class' => 'felement'));
            } else {
                $templatehtml .= html_writer::tag('div', get_string('nocourses', 'totara_program'), array('class' => 'felement'));
            }

            // Add the competency id element to the form
            if ($updateform) {
                $mform->addElement('hidden', $prefix.'competencyid', $this->competencyid);
                $mform->setType($prefix.'competencyid', PARAM_INT);
                $template_values['%'.$prefix.'competencyid%'] = array('name'=>$prefix.'competencyid', 'value'=>null);
            }
            $templatehtml .= '%'.$prefix.'competencyid%'."\n";
            $formdataobject->{$prefix.'competencyid'} = $this->competencyid;

        } else { // if no competency has been added to this set yet

            $course_competencies = $DB->get_records_menu('comp', null, 'fullname ASC', 'id,fullname');
            if (count($course_competencies) > 0) {
                if ($updateform) {
                    $mform->addElement('select',  $prefix.'competencyid', '', $course_competencies);
                    $mform->addElement('submit', $prefix.'addcompetency', get_string('addcompetency', 'totara_program'));
                    $template_values['%'.$prefix.'competencyid%'] = array('name'=>$prefix.'competencyid', 'value'=>null);
                    $template_values['%'.$prefix.'addcompetency%'] = array('name'=>$prefix.'addcompetency', 'value'=>null);
                }
                $templatehtml .= '%'.$prefix.'competencyid%'."\n";
                $templatehtml .= '%'.$prefix.'addcompetency%'."\n";
            } else {
                // Add the competency id element to the form
                if ($updateform) {
                    $mform->addElement('hidden', $prefix.'competencyid', 0);
                    $mform->setType($prefix.'competencyid', PARAM_INT);
                    $template_values['%'.$prefix.'competencyid%'] = array('name'=>$prefix.'competencyid', 'value'=>null);
                }
                $templatehtml .= '%'.$prefix.'competencyid%'."\n";
                $templatehtml .= html_writer::tag('p', get_string('nocompetenciestoadd', 'totara_program'));
                $formdataobject->{$prefix.'competencyid'} = 0;
            }
        }

        $templatehtml .= html_writer::end_tag('div'); // End fitem.
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::end_tag('fieldset');
        $templatehtml .= $this->get_nextsetoperator_select_form_template($mform, $template_values, $formdataobject, $prefix, $updateform);

        return $templatehtml;

    }


    public function get_course_text($courseset) {
        if ($courseset->completiontype == COMPLETIONTYPE_ALL) {
            return get_string('allcoursesfrom', 'totara_program') . ' "' . format_string($courseset->label) . '"';
        } else {
            return get_string('onecoursesfrom', 'totara_program') . ' "' . format_string($courseset->label) . '"';
        }
    }

    public function get_courses() {
        return $this->get_competency_courses();
    }

    public function delete_course($courseid) {
        // This is dictated by what courses are connected to competencies so deleting is not possible via
        // the course set.
        // This might have been called without knowing what type of courseset this is. So let the script
        //  carry on, but in case anything needs to know whether it was deleted, return false.
        return false;
    }

    public function build_progressinfo() {
        $agg_class = '';
        $customdata = null;

        switch ($this->completiontype) {
            case COMPLETIONTYPE_ANY;
                $agg_method = \totara_core\progressinfo\progressinfo::AGGREGATE_ANY;
                break;

            default:
                $agg_method = \totara_core\progressinfo\progressinfo::AGGREGATE_ALL;
        }

        $progressinfo = \totara_core\progressinfo\progressinfo::from_data($agg_method, 0, 0, $customdata, $agg_class);

        // Add courses in the courseset
        $courses = $this->get_competency_courses();
        if ($courses) {
            foreach ($courses as $course) {
                $progressinfo->add_criteria(parent::get_progressinfo_course_key($course));
            }
        }

        return $progressinfo;
    }
}

class recurring_course_set extends course_set {

    public $recurrencetime, $recurrencetimenum, $recurrencetimeperiod;
    public $recurcreatetime, $recurcreatetimenum, $recurcreatetimeperiod;
    public $course;

    public function __construct($programid, $setob=null, $uniqueid=null) {
        global $DB;
        parent::__construct($programid, $setob, $uniqueid);

        $this->contenttype = CONTENTTYPE_RECURRING;

        if (is_object($setob)) {
            if ($courseset_course = $DB->get_record('prog_courseset_course', array('coursesetid' => $this->id))) {
                $course = $DB->get_record('course', array('id' => $courseset_course->courseid));
                if (!$course) {
                    $DB->delete_records('prog_courseset_course', array('id' => $courseset_course->id));
                    $this->course = array();
                } else {
                    $this->course = $course;
                }
            }
            $recurrencetime = program_utilities::duration_explode($this->recurrencetime);
            $this->recurrencetimenum = $recurrencetime->num;
            $this->recurrencetimeperiod = $recurrencetime->period;
            $recurcreatetime = program_utilities::duration_explode($this->recurcreatetime);
            $this->recurcreatetimenum = $recurcreatetime->num;
            $this->recurcreatetimeperiod = $recurcreatetime->period;
        } else {
            $this->recurrencetimenum = 0;
            $this->recurrencetimeperiod = 0;
            $this->recurcreatetimenum = 0;
            $this->recurcreatetimeperiod = 0;
        }

    }

    public function init_form_data($formnameprefix, $data) {
        global $DB;
        parent::init_form_data($formnameprefix, $data);

        $this->recurrencetimenum = $data->{$formnameprefix.'recurrencetimenum'};
        $this->recurrencetimeperiod = $data->{$formnameprefix.'recurrencetimeperiod'};
        $this->recurrencetime = program_utilities::duration_implode($this->recurrencetimenum, $this->recurrencetimeperiod);

        $this->recurcreatetimenum = $data->{$formnameprefix.'recurcreatetimenum'};
        $this->recurcreatetimeperiod = $data->{$formnameprefix.'recurcreatetimeperiod'};
        $this->recurcreatetime = program_utilities::duration_implode($this->recurcreatetimenum, $this->recurcreatetimeperiod);

        $courseid = $data->{$formnameprefix.'courseid'};
        if ($course = $DB->get_record('course', array('id' => $courseid))) {
            $this->course = $course;
        }

    }

    public function is_recurring() {
        return true;
    }

    public function save_set() {
        if (parent::save_set()) {
            return $this->save_course();
        } else {
            return false;
        }
    }

    public function save_course() {
        global $DB;
        if (!$this->id) {
            return false;
        }

        if (!is_object($this->course)) {
            // Check for an old record that might need to be removed.
            $oldrecord = $DB->get_record('prog_courseset_course', array('coursesetid' => $this->id));
            if ($oldrecord) {
                $removed_id = $oldrecord->courseid;
                $DB->delete_records('prog_courseset_course', array('coursesetid' => $this->id));
            } else {
                // There was no record and nothing to save.
                return false;
            }
        } else if ($ob = $DB->get_record('prog_courseset_course', array('coursesetid' => $this->id))) {
            if ($this->course->id == $ob->courseid) {
                // nothing to do
                return true;
            } else {
                $removed_id = $ob->courseid;
                $added_id = $this->course->id;
                $ob->courseid = $this->course->id;
                $DB->update_record('prog_courseset_course', $ob);
            }
        } else {
            $removed_id = false;
            $added_id = $this->course->id;
            $ob = new stdClass();
            $ob->coursesetid = $this->id;
            $ob->courseid = $this->course->id;
            $DB->insert_record('prog_courseset_course', $ob);
        }

        $program_plugin = enrol_get_plugin('totara_program');

        // if the course no longer exists in any programs, remove the program enrolment plugin
        if ($removed_id) {
            $courses_still_associated = prog_get_courses_associated_with_programs(array($removed_id));
            // don't consider the one we've just added
            if (isset($added_id)) {
                unset($courses_still_associated[$added_id]);
            }
            if (empty($courses_still_associated)) {
                $instance = $program_plugin->get_instance_for_course($removed_id);
                if ($instance) {
                    $program_plugin->delete_instance($instance);
                }
            }
        }

        if (isset($added_id)) {
            // if the new course doesn't yet have the enrollment plugin, add it
            $instance = $program_plugin->get_instance_for_course($added_id);
            if (!$instance) {
                $course = $DB->get_record('course', array('id' => $added_id));
                $program_plugin->add_instance($course);
            }
        }

        return true;
    }

    /**
     * Returns true or false depending on whether or not this course set
     * contains the specified course
     *
     * @param int $courseid
     * @return bool
     */
    public function contains_course($courseid) {

        if ($this->course->id == $courseid) {
            return true;
        }

        return false;
    }

    /**
     * Checks whether or not the specified user has completed all the criteria
     * necessary to complete this course set and adds a record to the database
     * if so or returns false if not
     *
     * @param int $userid
     * @return int|bool
     */
    public function check_courseset_complete($userid) {

        $course = $this->course;

        // create a new completion object for this course
        $completion_info = new completion_info($course);

        // check if the course is complete
        if ($completion_info->is_course_complete($userid)) {
            $completionsettings = array(
                'status'        => STATUS_COURSESET_COMPLETE,
                'timecompleted' => time()
            );
            return $this->update_courseset_complete($userid, $completionsettings);
        }

        return false;
    }

    /**
     * Display a recurring course set.
     *
     * @param null $userid If specified then it indicates the user is assigned to the program.
     * @param array $previous_sets
     * @param array $next_sets
     * @param bool $accessible True if assigned user has reached this course set.
     * @param bool $viewinganothersprogram
     * @return string
     */
    public function display($userid=null,$previous_sets=array(),$next_sets=array(),$accessible=true, $viewinganothersprogram=false) {
        global $CFG, $OUTPUT, $DB, $USER;

        $out = '';
        $out .= html_writer::start_tag('div', array('class' => 'surround display-program'));
        $out .= $OUTPUT->heading(format_string($this->label), 3);

        $numperiod = program_utilities::get_duration_num_and_period($this->timeallowed);

        if ($this->timeallowed > 0) {
            $out .= html_writer::tag('p', get_string('allowtimeforset' . $numperiod->periodkey, 'totara_program', $numperiod->num));
        }

        if (is_object($this->course)) {
            $table = new html_table();
            $table->head = array(get_string('coursename', 'totara_program'), '');
            $table->attributes['class'] = 'fullwidth generaltable';
            $table->colclasses = array('coursename', 'launchcourse');
            if ($userid) {
                $table->head[] = get_string('status', 'totara_program');
                $table->colclasses[] = 'status';
            }

            $course = $this->course;
            if (empty($course->icon)) {
                $course->icon = 'default';
            }
            $coursedetails = html_writer::empty_tag('img', array('src' => totara_get_icon($course->id, TOTARA_ICON_TYPE_COURSE),
                'class' => 'course_icon', 'alt' => ''));
            $coursedetails .= $accessible ? html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)),
                            format_string($course->fullname)) : format_string($course->fullname);
            $cells[] = new html_table_cell($coursedetails);

            if (is_siteadmin($USER->id) || $userid && $accessible && totara_course_is_viewable($course->id, $userid)) {
                $launch = html_writer::tag('div', $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $course->id)),
                                 get_string('launchcourse', 'totara_program'), null), array('class' => 'prog-course-launch'));
            } else if ($userid && $accessible && !empty($CFG->audiencevisibility) && $course->audiencevisible != COHORT_VISIBLE_NOUSERS) {
                // If the program has been assigned but the user is not yet enrolled in the course,
                // a course with audience visibility set to "Enrolled users" would not allow the user to become enrolled.
                // Instead, when "Launch" is clicked, we redirect to the program requirements page, which will then directly enrol them into the course.
                // This isn't needed for normal visibility because if the course is hidden then it will be inaccessible anyway.
                $params = array('id' => $this->programid, 'cid' => $course->id, 'userid' => $userid, 'sesskey' => $USER->sesskey);
                $requrl = new moodle_url('/totara/program/required.php', $params);
                $button = $OUTPUT->single_button($requrl, get_string('launchcourse', 'totara_program'), null);
                $launch = html_writer::tag('div', $button, array('class' => 'prog-course-launch'));
            } else {
                $launch = html_writer::tag('div', $OUTPUT->single_button(null, get_string('notavailable', 'totara_program'), null,
                                 array('tooltip' => null, 'disabled' => true)), array('class' => 'prog-course-launch'));
            }
            $cells[] = new html_table_cell($launch);

            if ($userid) {
                if (!$status = $DB->get_field('course_completions', 'status', array('userid' => $userid, 'course' => $course->id))) {
                    $status = COMPLETION_STATUS_NOTYETSTARTED;
                }
                $cells[] = new html_table_cell(totara_display_course_progress_bar($userid, $course->id, $status));
            }
            $row = new html_table_row($cells);
            $table->data[] = $row;

            $out .= html_writer::table($table);
        } else {
            $out .= html_writer::tag('p', get_string('nocourses', 'totara_program'));
        }

        $out .= html_writer::end_tag('div');

        return $out;

    }

    public function display_nextsetoperator() {
        return '';
    }

    /**
     * Returns an HTML string suitable for displaying as the element body
     * for a course set in the program overview form
     *
     * @return string
     */
    public function display_form_element() {
        return format_string($this->course->fullname);
    }

    public function print_set_minimal() {

        $prefix = $this->get_set_prefix();

        $out = '';
        $courseid = (is_object($this->course) ? $this->course->id : 0);
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."id", 'value' => $this->id));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."label", 'value' => $this->label));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."courseid", 'value' => $courseid));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."sortorder", 'value' => $this->sortorder));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."contenttype", 'value' => $this->contenttype));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."recurrencetime", 'value' => $this->recurrencetime));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."nextsetoperator", 'value' => $this->nextsetoperator));

        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."timeallowedperiod", 'value' => TIME_SELECTOR_DAYS));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."timeallowednum", 'value' => '30'));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."recurrencetimeperiod", 'value' => TIME_SELECTOR_DAYS));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."recurrencetimenum", 'value' => '365'));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."recurcreatetimeperiod", 'value' => TIME_SELECTOR_DAYS));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix."recurcreatetimenum", 'value' => '1'));

        return $out;
    }

    public function get_courseset_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT, $DB;
        $prefix = $this->get_set_prefix();

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'course_set surround edit-program'));

        $helpbutton = $OUTPUT->help_icon('recurringcourseset', 'totara_program');
        $legend = ((isset($this->label) && ! empty($this->label)) ? $this->label : get_string('legend:recurringcourseset', 'totara_program',
                        $this->sortorder)) . ' ' . $helpbutton;
        $templatehtml .= html_writer::tag('legend', $legend);

        // Recurring programs don't need a nextsetoperator property but we must
        // include it in the form to avoid any problems when the data is submitted
        $templatehtml .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $prefix.'nextsetoperator', 'value' => '0'));

        // Add the delete button for this set
        $templatehtml .= html_writer::start_tag('div', array('class' => 'setbuttons'));

        if ($updateform) {
            $mform->addElement('submit', $prefix.'delete', get_string('delete', 'totara_program'),
                            array('class' => "btn-cancel delete fieldsetbutton setdeletebutton"));
            $template_values['%'.$prefix.'delete%'] = array('name' => $prefix.'delete', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'delete%'."\n";
        $templatehtml .= html_writer::end_tag('div');

        // Add the course set id
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'id', $this->id);
            $mform->setType($prefix.'id', PARAM_INT);
            $mform->setConstant($prefix.'id', $this->id);
            $template_values['%'.$prefix.'id%'] = array('name' => $prefix.'id', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'id%'."\n";
        $formdataobject->{$prefix.'id'} = $this->id;

        // Add the course set sort order
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'sortorder', $this->sortorder);
            $mform->setType($prefix.'sortorder', PARAM_INT);
            $mform->setConstant($prefix.'sortorder', $this->sortorder);
            $template_values['%'.$prefix.'sortorder%'] = array('name' => $prefix.'sortorder', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'sortorder%'."\n";
        $formdataobject->{$prefix.'sortorder'} = $this->sortorder;

        // Add the course set content type
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'contenttype', $this->contenttype);
            $mform->setType($prefix.'contenttype', PARAM_INT);
            $mform->setConstant($prefix.'contenttype', $this->contenttype);
            $template_values['%'.$prefix.'contenttype%'] = array('name' => $prefix.'contenttype', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'contenttype%'."\n";
        $formdataobject->{$prefix.'contenttype'} = $this->contenttype;

        // Add the course set label
        if ($updateform) {
            $mform->addElement('text', $prefix.'label', $this->label, array('size' => '40', 'maxlength' => '255'));
            $mform->setType($prefix.'label', PARAM_TEXT);
            $template_values['%'.$prefix.'label%'] = array('name' => $prefix.'label', 'value' => null);
        }

        $helpbutton = $OUTPUT->help_icon('setlabel', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:setname', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'label'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'label%', array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'label'} = $this->label;

        // Display the course name
        if (is_object($this->course)) {
            if (isset($this->course->fullname)) {
                $courseoptions = $DB->get_records_select_menu('course', 'id <> ?', array(SITEID), 'fullname ASC', 'id,fullname');
                $hascourseoptions = count($courseoptions) > 0;

                $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
                $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
                if ($hascourseoptions) {
                    $labelcontent = get_string('coursename', 'totara_program');
                    $labelcontent .= $OUTPUT->help_icon('recurringcourse', 'totara_program');
                    $templatehtml .= html_writer::tag('label', $labelcontent);
                } else {
                    $templatehtml .= html_writer::tag('label', get_string('coursename', 'totara_program'));
                }
                $templatehtml .= html_writer::end_tag('div');

                // Add the 'Select course' drop down list.
                $templatehtml .= html_writer::start_tag('div', array('class' => 'courseselector felement'));
                if ($hascourseoptions) {
                    if ($updateform) {
                        $mform->addElement('select',  $prefix.'courseid', '', $courseoptions);
                        $template_values['%'.$prefix.'courseid%'] = array('name' => $prefix.'courseid', 'value' => null);
                    }
                    $templatehtml .= '%'.$prefix.'courseid%'."\n";
                    $formdataobject->{$prefix.'courseid'} = $this->course->id;
                } else {
                    $templatehtml .= html_writer::tag('p', get_string('nocoursestoselect', 'totara_program'));
                }
                $templatehtml .= html_writer::end_tag('div');

                $templatehtml .= html_writer::end_tag('div'); // End fitem.
            }
        }

        // Add the time allowance selection group
        if ($updateform) {
            $mform->addElement('text', $prefix.'timeallowednum', $this->timeallowednum, array('size' => 4, 'maxlength' => 3));
            $mform->setType($prefix.'timeallowednum', PARAM_INT);
            $mform->addRule($prefix.'timeallowednum', get_string('required'), 'required', null, 'server');

            $timeallowanceoptions = program_utilities::get_standard_time_allowance_options(true);
            $mform->addElement('select', $prefix.'timeallowedperiod', '', $timeallowanceoptions);
            $mform->setType($prefix.'timeallowedperiod', PARAM_INT);

            $template_values['%'.$prefix.'timeallowednum%'] = array('name' => $prefix.'timeallowednum', 'value' => null);
            $template_values['%'.$prefix.'timeallowedperiod%'] = array('name' => $prefix.'timeallowedperiod', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('minimumtimerequired', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:minimumtimerequired', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'timeallowance'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', '%' . $prefix . 'timeallowednum% %' . $prefix . 'timeallowedperiod%',
            array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'timeallowednum'} = $this->timeallowednum;
        $formdataobject->{$prefix.'timeallowedperiod'} = $this->timeallowedperiod;

        // Add the recurrence period selection group
        if ($updateform) {
            $mform->addElement('text', $prefix.'recurrencetimenum', $this->recurrencetimenum, array('size' => 4, 'maxlength' => 3));
            $mform->setType($prefix.'recurrencetimenum', PARAM_INT);
            $mform->addRule($prefix.'recurrencetimenum', get_string('required'), 'required', null, 'server');

            $timeallowanceoptions = program_utilities::get_standard_time_allowance_options();
            $mform->addElement('select', $prefix.'recurrencetimeperiod', '', $timeallowanceoptions);
            $mform->setType($prefix.'recurrencetimeperiod', PARAM_INT);

            $template_values['%'.$prefix.'recurrencetimenum%'] = array('name' => $prefix.'recurrencetimenum', 'value' => null);
            $template_values['%'.$prefix.'recurrencetimeperiod%'] = array('name' => $prefix.'recurrencetimeperiod', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('recurrence', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:recurrence', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'recurrencetimenum'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', get_string('repeatevery', 'totara_program') . ' %' . $prefix .
            'recurrencetimenum% %' . $prefix . 'recurrencetimeperiod%', array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'recurrencetimenum'} = $this->recurrencetimenum;
        $formdataobject->{$prefix.'recurrencetimeperiod'} = $this->recurrencetimeperiod;

        // Add the recur create period selection group
        if ($updateform) {
            $mform->addElement('text', $prefix.'recurcreatetimenum', $this->recurcreatetimenum, array('size' => 4, 'maxlength' => 3));
            $mform->setType($prefix.'recurcreatetimenum', PARAM_INT);

            $timeallowanceoptions = program_utilities::get_standard_time_allowance_options();
            $mform->addElement('select', $prefix.'recurcreatetimeperiod', '', $timeallowanceoptions);
            $mform->setType($prefix.'recurcreatetimeperiod', PARAM_INT);
            $mform->addRule($prefix.'recurcreatetimeperiod', get_string('required'), 'required', null, 'server');

            $template_values['%'.$prefix.'recurcreatetimenum%'] = array('name' => $prefix.'recurcreatetimenum', 'value' => null);
            $template_values['%'.$prefix.'recurcreatetimeperiod%'] = array('name' => $prefix.'recurcreatetimeperiod', 'value' => null);
        }
        $helpbutton = $OUTPUT->help_icon('coursecreation', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitem'));
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
        $templatehtml .= html_writer::tag('label', get_string('label:recurcreation', 'totara_program') . ' ' . $helpbutton,
            array('for' => $prefix.'recurcreatetimenum'));
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::tag('div', get_string('createcourse', 'totara_program') . ' %' .
            $prefix . 'recurcreatetimenum% %' . $prefix . 'recurcreatetimeperiod% '.
            get_string('beforecourserepeats', 'totara_program'), array('class' => 'felement'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'recurcreatetimenum'} = $this->recurcreatetimenum;
        $formdataobject->{$prefix.'recurcreatetimeperiod'} = $this->recurcreatetimeperiod;

        $templatehtml .= html_writer::start_tag('div', array('class' => 'setbuttons'));
        // Add the update button for this set
        if ($updateform) {
            $mform->addElement('submit', $prefix.'update', get_string('update', 'totara_program'),
                            array('class' => "fieldsetbutton updatebutton"));
            $template_values['%'.$prefix.'update%'] = array('name' => $prefix.'update', 'value' => null);
        }
        $templatehtml .= '%'.$prefix.'update%'."\n";
        $templatehtml .= html_writer::end_tag('div');
        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }

    public function get_course_text($courseset) {
        if ($courseset->completiontype == COMPLETIONTYPE_ALL) {
            return get_string('allcoursesfrom', 'totara_program') . ' "' . format_string($courseset->label) . '"';
        } else {
            return get_string('onecoursesfrom', 'totara_program') . ' "' . format_string($courseset->label) . '"';
        }
    }

    public function get_courses() {
        if (empty($this->course)) {
            return array();
        } else {
            return array($this->course);
        }
    }

    public function delete_course($courseid) {
        if (!empty($this->course) && $this->course->id == $courseid) {
            $this->course = false;

            return true;
        }

        return false;
    }

    public function build_progressinfo() {
        $agg_class = '';
        $customdata = null;
        $agg_method = \totara_core\progressinfo\progressinfo::AGGREGATE_ALL;

        $progressinfo = \totara_core\progressinfo\progressinfo::from_data($agg_method, 0, 0, $customdata, $agg_class);

        // Add courses in the courseset
        if (is_object($this->course)) {
            $progressinfo->add_criteria(parent::get_progressinfo_course_key($this->course));
        }

        return $progressinfo;
    }
}
