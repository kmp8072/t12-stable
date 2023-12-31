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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage program
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->libdir.'/totaratablelib.php');

/**
* Standard HTML output renderer for totara_core module
*/
class totara_program_renderer extends plugin_renderer_base {
    const COURSECAT_SHOW_PROGRAMS_NONE = 0; /* do not show programs at all */
    const COURSECAT_SHOW_PROGRAMS_COUNT = 5; /* do not show programs but show number of programs next to category name */
    const COURSECAT_SHOW_PROGRAMS_COLLAPSED = 10;
    const COURSECAT_SHOW_PROGRAMS_AUTO = 15; /* will choose between collapsed and expanded automatically */
    const COURSECAT_SHOW_PROGRAMS_EXPANDED = 20;
    const COURSECAT_SHOW_PROGRAMS_EXPANDED_WITH_CAT = 30;

    /**
     * Whether a category content is being initially rendered with children. This is mainly used by the
     * totara_program_renderer::corsecat_tree() to render the appropriate action for the Expand/Collapse all link on
     * page load.
     * @var bool
     */
    protected $categoryexpandedonload = false;

    /**
    * Generates HTML for a cancel button which is displayed on program
    * management edit screens
    *
    * @param str $url
    * @return str HTML fragment
    */
    public function get_cancel_button($params=null, $url='') {
        if (empty($url)) {
            $url = "/totara/program/edit.php";
        }
        $link = new moodle_url($url, $params);
        $output = $this->output->action_link($link, get_string('cancelprogrammanagement', 'totara_program'), null, array('id' => 'cancelprogramedits'));
        $output .= html_writer::empty_tag('br');
        return $output;
    }

    /**
    * Returns html for the dropdown of different completion events
    *
    * @global array $COMPLETION_EVENTS_CLASSNAMES
    * @param string $name
    * @return string
    */
    public function completion_events_dropdown($name="eventtype", $programid = null) {
        global $COMPLETION_EVENTS_CLASSNAMES;
        // The javascript part of this element was initially factored out
        // and added using jQuery when the page was loaded but this didn't work
        // in IE8 so it was added in here instead.
        $dropdown_options = array();
        foreach ($COMPLETION_EVENTS_CLASSNAMES as $class) {
            $event = new $class();
            $dropdown_options[$event->get_id()] = $event->get_name();
        }
        $out = html_writer::select($dropdown_options, $name, null, null, array('id' => $name, 'class' => $name, 'onchange' => 'handle_completion_selection()'));
        $out .= html_writer::script(prog_assignments::get_completion_events_script($name, $programid));
        return $out;
    }

    /**
    * Generates HTML for displaying program status
    *
    * @param object $data - obj used in get_string call
    * @return str HTML fragment
    */
    public function render_current_status($data) {

        $programstatusclass = $data->statusclass;
        $programstatusstring = get_string($data->statusstr, 'totara_program');

        if (($data->statusstr === 'notduetostartuntil') or ($data->statusstr === 'nolongeravailabletolearners')) {
            $notification = $programstatusstring;
        } else {
            $learnerinfo = html_writer::empty_tag('br') . html_writer::start_tag('span', array('class' => 'assignmentcount'));
            $learnerinfo .= get_string('learnersassignedbreakdown', 'totara_program', $data);
            $learnerinfo .= html_writer::end_tag('span');

            $coursevisibilityinfo = html_writer::empty_tag('br') . html_writer::start_tag('span');
            if ($data->audiencevisibilitywarning) {
                $coursevisibilityinfo .= get_string('audiencevisibilityconflictmessage', 'totara_program');
            }
            if ($data->assignmentsdeferred) {
                $coursevisibilityinfo .= get_string('assignmentsdeferred', 'totara_program');
            }
            $coursevisibilityinfo .= html_writer::end_tag('span');

            $notification = $programstatusstring . $learnerinfo . $coursevisibilityinfo;
        }

        $out = $this->output->notification($notification, $programstatusclass);

        // This js variable is added so that is available to javascript and can
        // be retrieved and displayed in the dialog when saving the content
        // (see program/program_content.js)
        $out .= html_writer::script('currentassignmentcount = '.$data->assignments.';');
        return $out;
    }

    /**
     * Generates the HTML for each assignment category class.
     *
     * @param reference object $assignment_class The category class object which called this function.
     * @param array $headers A list of headers to be processed.
     * @param string $buttonname The name of the button that adds data to the group.
     * @param array $data Current data for the group.
     * @param boolean $canadd If adding date to the group is allowed.
     * @return string HTML fragment.
     */
    public function assignment_category_display($assignment_class, $headers, $buttonname, $data, $canadd) {
        global $OUTPUT;

        // If we've got no data to display and the data can't
        // be added to, then don't display the fieldset.
        if (!$canadd && !$data) {
            return '';
        }

        $categoryclassstr = strtolower(str_replace(' ', '', $assignment_class->name));
        $html = html_writer::start_tag('fieldset', array('class' => 'surround assignment_category '.$categoryclassstr,
            'id' => 'category-'. $assignment_class->id));
        $html .= html_writer::start_tag('legend') . $assignment_class->name .  html_writer::end_tag('legend');
        $table = new html_table();
        $table->attributes['class'] = 'table table-striped invisiblepadded fullwidth';
        $colcount = 0;
        // Add the headers
        foreach ($headers as $header) {
            $cell = new html_table_cell($header);
            $cell->attributes['class'] = 'col'.$colcount;
            $table->head[] = $cell;
            $colcount++;
        }

        // And the main data
        if (!empty($data)) {
            foreach ($data as $row) {
                $colcount = 0;
                $cells = array();
                foreach ($row as $cell) {
                    $cell = new html_table_cell($cell);
                    $cell->attributes['class'] = 'col'.$colcount;
                    $cells[] = $cell;
                    $colcount++;
                }
                $row = new html_table_row($cells);
                $table->data[] = $row;
            }
        }
        $html .= $OUTPUT->render($table);
        // Add a button for adding new items to the category if we have a name.
        if ($canadd) {
            $html .= html_writer::start_tag('button', array('id' => 'add-assignment-' . $assignment_class->id));
            $html .= $buttonname . html_writer::end_tag('button');
        }

        $html .= html_writer::end_tag('fieldset');

        return $html;
    }
    /**
     * Generates HTML for edit assignments form
     *
     * @param int|program $programidorinstance Program ID.
     *   Instance of program accepted since Totara 10 (prior to this, only int was accepted).
     * @param organisations_category[]|positions_category[]|cohorts_category[]|managers_category[]|individuals_category[] $categories
     * Assignment categories to display.
     * @param int $certificationpath
     * @return string HTML fragment
     */
    public function display_edit_assignment_form($programidorinstance, $categories, $certificationpath) {
        global $ASSIGNMENT_CATEGORY_CLASSNAMES;

        $dropdown_options = array();
        $out = '';
        $out .= html_writer::start_tag('form', array('name' => 'form_prog_assignments', 'method' => 'post', 'class' => 'mform'));
        $out .= $this->output->heading(get_string('programassignments', 'totara_program'), 3);

        // Show the program time required so people know the minimum to set completion to.

        if (is_numeric($programidorinstance)) {
            $program = new program($programidorinstance);
        } else if (get_class($programidorinstance) === 'program') {
            $program = $programidorinstance;
        } else {
            throw new coding_exception('programidorinstance must be a program id (integer) or instance of program class');
        }

        $canaddcohort = false;

        if ($program->has_expired()) {
            $learners = $program->get_program_learners();
            if (empty($learners)) {
                $learnercount = 0;
            } else {
                $learnercount = count($learners);
            }
            $out .= html_writer::tag('p', get_string('learnerswereassigned', 'totara_program', $learnercount));
        } else {
            $programtime = $program->content->get_total_time_allowance($certificationpath);

            $context = context_program::instance($program->id);
            $contextids = $context->get_parent_context_ids(true);
            foreach ($contextids as $contextid) {
                if (has_capability("moodle/cohort:view", context::instance_by_id($contextid))) {
                    $canaddcohort = true;
                    break;
                }
            }

            if ($programtime > 0) {
                $out .= prog_format_seconds($programtime);
            }

            $out .= html_writer::tag('p', get_string('instructions:programassignments', 'totara_program'));
        }

        $out .= html_writer::start_tag('div', array('id' => 'assignment_categories'));

        // Display the categories!
        $js = '';
        foreach ($categories as $category) {
            if (get_class($category) === 'positions_category' && totara_feature_disabled('positions')) {
                $canadd = false;
            } else if ($program->has_expired()) {
                $canadd = false;
            } else {
                $canadd = true;
            }

            if ((get_class($category) === $ASSIGNMENT_CATEGORY_CLASSNAMES[ASSIGNTYPE_COHORT]) && !$canaddcohort) {
                $canadd = false;
            }

            $category->build_table($program);
            if (!$category->has_items() && $canadd) {
                $dropdown_options[$category->id] = $category->name;
            } else {
                $out .= $category->display($canadd);
                $js .= $category->get_js($program->id);
            }
        }
        if ($js != '') {
            $jsmodule = array(
                'name' => 'totara_programassignment',
                'fullpath' => '/totara/program/assignment/program_assignment.js',
                'requires' => array('json', 'totara_core'));

            $this->page->requires->js_init_code($js, true, $jsmodule);
        }
        $out .= html_writer::end_tag('div');

        if ($program->has_expired()) {
            $out .= html_writer::tag('p', get_string('ifreactivatinglearnerupdate', 'totara_program'));
        } else {
            // Display the drop-down if there's any categories that aren't yet being used
            if (!empty($dropdown_options)) {
                $out .= html_writer::start_tag('div', array('id' => 'category_select'));
                $out .= html_writer::tag('label', get_string('addnew', 'totara_program'), array('for' => 'menucategory_select_dropdown'));
                $out .= html_writer::select($dropdown_options, 'category_select_dropdown', array('initialvalue' => 1));
                $out .= html_writer::tag('span', get_string('toprogram', 'totara_program'));
                $out .= html_writer::tag('button', get_string('add'));
                $out .= html_writer::end_tag('div');
            }

            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "id", 'value' => $program->id));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "sesskey", 'value' => sesskey()));
            $out .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => "savechanges", 'value' => get_string('savechanges'), 'class' => 'savechanges-overview program-savechanges'));
        }

        $out .= html_writer::end_tag('form');
        return $out;
    }

    /**
    * Display the user message box
    *
    * @access public
    * @param stdClass $user Object with at least fields id, picture, imagealt, firstname, lastname
    * @param  a object   data for get_string
    * @return string $out    HTML fragment
    */
    public function display_user_message_box($user, $a) {
        $out = html_writer::start_tag('div', array('class' => 'plan_box plan_box_plain'));
        $out .= $this->output->user_picture($user);
        $out .= html_writer::tag('strong', get_string('youareviewingxsrequiredlearning', 'totara_program', $a));
        $out .= html_writer::end_tag('div');
        return $out;
    }

    /**
    * Generates the HTML to display the current number of exceptions and a link
    * to the exceptions report for the program
    *
    * @param string $url link to exceptions report
    * @param int $excount number of exceptions
    * @return string HTML Fragment
    */
    public function print_exceptions_link($url, $excount) {
        $out = '';
        $out .= html_writer::start_tag('div', array('id' => 'exceptionsreport'));
        $out .= html_writer::start_tag('p');
        $out .= html_writer::start_tag('span', array('class' => 'exceptionscount'));
        $out .= get_string('unresolvedexceptions', 'totara_program', $excount);
        $out .= html_writer::end_tag('span');
        $out .= html_writer::start_tag('span', array('class' => 'exceptionslink'));
        $out .= html_writer::link($url, get_string('viewexceptions', 'totara_program'));
        $out .= html_writer::end_tag('span');
        $out .= html_writer::end_tag('p');
        $out .= html_writer::end_tag('div');
        return $out;
    }
    /**
    * Generates the HTML to display the program search form
    *
    * @param int $programid the program being searched
    * @param string $previoussearch
    * @return string HTML Fragment
    */
    public function print_search($programid, $previoussearch='', $resultcount = 0) {
        $url = new moodle_url('/totara/program/exceptions.php', array('id' => $programid));
        $out = html_writer::start_tag('form', array('action' => $url->out(), 'method' => 'get', 'id' => 'exceptionssearchform'));
        $out .= html_writer::tag('label', get_string('searchforindividual', 'totara_program'), array('for' => 'exception_search'));
        $out .= html_writer::empty_tag('input', array('type' => 'text', 'id' => "exception_search", 'name' => 'search', 'value' => $previoussearch));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "id", 'value' => $programid));
        $out .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('search')));
        $out .= html_writer::end_tag('form');
        if ($previoussearch != '' && $resultcount > 0) {
            $a = new stdClass();
            $a->count = $resultcount;
            $a->query = $previoussearch;
            $out .= html_writer::tag('p', get_string('xresultsfory', 'totara_core', $a));
        }
        return $out;
    }

    /**
    * Generates the HTML to display the exceptions form
    *
    * @param array $exceptions all exceptions
    * @param array $selectedexceptions currently selected exceptions
    * @param int $selectiontype currently selected value in dropdown
    * @return string HTML Fragment
    */
    public function print_exceptions_form($numexceptions, $numselectedexceptions, $programid, $selectiontype, $tabledata) {
        $out = '';

        if ($numexceptions == 0) {
            $out .= html_writer::start_tag('p') . get_string('noprogramexceptions', 'totara_program') . html_writer::end_tag('p');
        } else {
            $out .= html_writer::start_tag('form', array('name' => 'exceptionsform', 'method' => 'post'));
            $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "id", 'value' => $programid));
            $out .= html_writer::start_tag('div', array('class' => 'exceptionactions'));

            $out .= $this->get_exceptiontype_selector($selectiontype);

            $out .= $this->get_exceptionaction_selector();

            $out .= html_writer::start_tag('div');
            $out .= html_writer::empty_tag('input', array('type' => 'submit', 'id' => 'applyactionbutton', 'name' => "submit", 'value' => get_string('proceed', 'totara_program')));
            $out .= html_writer::end_tag('div');

            $out .= html_writer::start_tag('div') . html_writer::start_tag('p');
            $out .= html_writer::start_tag('span', array('id' => 'numselectedexceptions')) . $numselectedexceptions . html_writer::end_tag('span') . ' ' .get_string('learnersselected', 'totara_program');
            $out .= html_writer::end_tag('p') . html_writer::end_tag('div');
            $out .= html_writer::end_tag('div');

            $table = new html_table();
            $table->attributes['class'] = 'table table-striped fullwidth';
            $table->id = 'exceptions';
            $table->head = array(
                get_string('header:hash', 'totara_program'),
                get_string('header:learners', 'totara_program'),
                get_string('header:id','totara_program'),
                get_string('header:issue','totara_program'),
            );

            foreach ($tabledata as $rowdata) {
                $row = array();

                $row[] = html_writer::checkbox("exceptionid", $rowdata->exceptionid, $rowdata->selected);
                $url = new moodle_url('/user/view.php', array('id' => $rowdata->user->id));
                $row[] = html_writer::link($url, fullname($rowdata->user));
                $row[] = '#'.$rowdata->exceptionid;

                html_writer::tag('span', $rowdata->exceptiontype, array('class' => 'type', 'style' => 'display:none;'));
                $row[] = $rowdata->descriptor . html_writer::tag('span', $rowdata->exceptiontype, array('class' => 'type', 'style' => 'display:none;'));
                $table->data[] = $row;
                $table->rowclass[] = 'exceptionrow';
            }

            $out .= html_writer::table($table, true);
            $out .= html_writer::end_tag('form');
        }
        return $out;
    }

    public function get_exceptiontype_selector($selectiontype) {
        global $CFG;

        require_once($CFG->dirroot . '/totara/program/program_exceptions.class.php');

        $out = '';
        $options = array();
        $options[SELECTIONTYPE_NONE] = get_string('select', 'totara_program');
        $options[SELECTIONTYPE_ALL] = get_string('alllearners', 'totara_program');
        $options[SELECTIONTYPE_TIME_ALLOWANCE] = get_string('timeallowance', 'totara_program');
        $options[SELECTIONTYPE_ALREADY_ASSIGNED] = get_string('exceptiontypealreadyassigned', 'totara_program');
        $options[SELECTIONTYPE_COMPLETION_TIME_UNKNOWN] = get_string('completiontimeunknown', 'totara_program');
        $options[SELECTIONTYPE_DUPLICATE_COURSE] = get_string('exceptiontypeduplicatecourse', 'totara_program');
        $out .= html_writer::start_tag('div');
        $out .= html_writer::select($options, 'selectiontype', $selectiontype, null, array('id' => 'selectiontype'));
        $out .= html_writer::end_tag('div');

        return $out;
    }

    public function get_exceptionaction_selector() {
        global $CFG;

        require_once($CFG->dirroot . '/totara/program/program_exceptions.class.php');

        $out = '';
        $options = array();
        $options[SELECTIONACTION_NONE] = get_string('action', 'totara_program');
        $options[SELECTIONACTION_AUTO_TIME_ALLOWANCE] = get_string('exceptionactionsetduedate', 'totara_program');
        $options[SELECTIONACTION_OVERRIDE_EXCEPTION] = get_string('exceptionactionassign', 'totara_program');
        $options[SELECTIONACTION_DISMISS_EXCEPTION] = get_string('exceptionactiondonotassign', 'totara_program');
        $out .= html_writer::start_tag('div');
        $out .= html_writer::select($options, 'selectionaction', null, null, array('id' => 'selectionaction'));
        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
    * Generates the HTML to display the set_completion page
    *
    * @return string HTML Fragment
    */
    public function display_set_completion($programid = null) {
        $out = '';
        $out .= html_writer::start_tag('fieldset');
        $out .= html_writer::start_tag('span', array('class' => 'legend')) . get_string('completeby', 'totara_program') . html_writer::end_tag('span');
        $out .= html_writer::start_tag('div', array('id' => 'prog-completion-fixed-date'));

        $hours = array();
        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = sprintf("%02d", $i);
        }
        $minutes = array();
        for ($i = 0; $i < 60; $i += 5) {
            $minutes[$i] = sprintf("%02d", $i);
        }

        $out .= html_writer::start_tag('span', array('class' => 'datepicker-wrapper'));
        $out .= html_writer::label(get_string('date', 'moodle'), 'completiontime', false, array('class' => 'accesshide'));
        $out .= html_writer::empty_tag('input', array('class' => 'completiontime', 'type' => 'text', 'name' => "completiontime", 'placeholder' => get_string('datepickerlongyearplaceholder', 'totara_core')));
        $out .= html_writer::end_tag('span');

        // Matching display of time in RTL to what is done in lib/form/datetimeselector.php.
        if (right_to_left()) {
            $out .= ' '.get_string('datepickerattime', 'totara_core'). ' ';
            $out .= html_writer::label(get_string('minute', 'moodle'), 'completiontimeminute', false, array('class' => 'accesshide'));
            $out .= html_writer::select($minutes, 'completiontimeminute', array(0 => '00'), false, array('name' => 'completiontimeminute', 'class' => 'completiontimeminute'));
            $out .= ':';
            $out .= html_writer::label(get_string('hour', 'moodle'), 'completiontimehour', false, array('class' => 'accesshide'));
            $out .= html_writer::select($hours, 'completiontimehour', array(0 => '00'), false, array('name' => 'completiontimehour', 'class' => 'completiontimehour'));
        } else {
            $out .= ' '.get_string('datepickerattime', 'totara_core'). ' ';
            $out .= html_writer::label(get_string('hour', 'moodle'), 'completiontimehour', false, array('class' => 'accesshide'));
            $out .= html_writer::select($hours, 'completiontimehour', array(0 => '00'), false, array('name' => 'completiontimehour', 'class' => 'completiontimehour'));
            $out .= ':';
            $out .= html_writer::label(get_string('minute', 'moodle'), 'completiontimeminute', false, array('class' => 'accesshide'));
            $out .= html_writer::select($minutes, 'completiontimeminute', array(0 => '00'), false, array('name' => 'completiontimeminute', 'class' => 'completiontimeminute'));
        }

        $out .= ' ' . html_writer::start_tag('button', array('class' => 'fixeddate')) .
            get_string('setfixedcompletiondate', 'totara_program') . html_writer::end_tag('button');
        $out .= html_writer::end_tag('div');
        $out .= html_writer::end_tag('fieldset');

        $out .= html_writer::start_tag('div', array('id' => 'prog-completion-or-string'));
        $out .= get_string('or', 'totara_program');
        $out .= html_writer::end_tag('div');

        $out .= html_writer::start_tag('div', array('id' => 'prog-completion-relative-date'));
        $out .= html_writer::tag('span', get_string('completewithin', 'totara_program'));
        $out .= program_utilities::print_duration_selector($prefix = '', $periodelementname = 'timeperiod', $periodvalue = '', $numberelementname = 'timeamount', $numbervalue = '1', $includehours = false);
        $out .= ' ' . get_string('of', 'totara_program') . ' ';
        $out .= $this->completion_events_dropdown("eventtype", $programid);
        $out .= html_writer::empty_tag('input', array('id' => 'instance', 'type' => 'hidden', 'name' => "instance", 'value' => ''));
        $out .= html_writer::link('#', '', array('id' => 'instancetitle', 'onclick' => 'handle_completion_selection()'));
        $out .= html_writer::start_tag('button', array('class' => 'relativeeventtime')) . get_string('settimerelativetoevent', 'totara_program') . html_writer::end_tag('button');
        $out .= html_writer::end_tag('div');

        return $out;
    }

    /**
    * Generates the HTML to display the set__extension page
    *
    * @return string HTML Fragment
    */
    public function display_set_extension() {
        $out = '';
        $out .= html_writer::start_tag('div');
        $out .= html_writer::start_tag('label', array('for' => 'extensiontime')) . get_string('extenduntil', 'totara_program') . html_writer::end_tag('label');
        $out .= html_writer::empty_tag('input', array('class' => 'extensiontime', 'type' => 'text', 'name' => 'extensiontime', 'id' => 'extensiontime', 'size' => '20', 'maxlength' => '10', 'placeholder' => get_string('datepickerlongyearplaceholder', 'totara_core')));

        // Create and add hour and minute select elements.
        $hours = array();
        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = sprintf("%02d", $i);
        }
        $minutes = array();
        for ($i = 0; $i < 60; $i += 5) {
            $minutes[$i] = sprintf("%02d", $i);
        }

        // Matching display of time in RTL to what is done in lib/form/datetimeselector.php.
        if (right_to_left()) {
            $out .= ' '.get_string('datepickerattime', 'totara_core'). ' ';
            $out .= html_writer::label(get_string('minute', 'moodle'), 'extensiontimeminute', false, array('class' => 'accesshide'));
            $out .= html_writer::select($minutes, 'extensiontimeminute', array(0 => '00'), false, array('name' => 'extensiontimeminute', 'class' => 'extensiontimeminute'));
            $out .= ':';
            $out .= html_writer::label(get_string('hour', 'moodle'), 'extensiontimehour', false, array('class' => 'accesshide'));
            $out .= html_writer::select($hours, 'extensiontimehour', array(0 => '00'), false, array('name' => 'extensiontimehour', 'class' => 'extensiontimehour'));
        } else {
            $out .= ' '.get_string('datepickerattime', 'totara_core'). ' ';
            $out .= html_writer::label(get_string('hour', 'moodle'), 'extensiontimehour', false, array('class' => 'accesshide'));
            $out .= html_writer::select($hours, 'extensiontimehour', array(0 => '00'), false, array('name' => 'extensiontimehour', 'class' => 'extensiontimehour'));
            $out .= ':';
            $out .= html_writer::label(get_string('minute', 'moodle'), 'extensiontimeminute', false, array('class' => 'accesshide'));
            $out .= html_writer::select($minutes, 'extensiontimeminute', array(0 => '00'), false, array('name' => 'extensiontimeminute', 'class' => 'extensiontimeminute'));
        }

        $out .= html_writer::end_tag('div');
        $out .= html_writer::empty_tag('br');

        $out .= html_writer::start_tag('div');
        $out .= html_writer::start_tag('label', array('for' => 'extensionreason')) . get_string('reasonforextension', 'totara_program') . html_writer::end_tag('label');
        $out .= html_writer::empty_tag('input', array('class' => 'extensionreason', 'type' => 'text', 'name' => 'extensionreason', 'id' => 'extensionreason', 'size' => '80', 'maxlength' => '255'));
        $out .= html_writer::end_tag('div');
        return $out;
    }

    /**
     * Display due date for a program with task info
     *
     * @param int $duedate
     * @return string
     */
    public function display_duedate_highlight_info($duedate) {
        $out = '';
        $now = time();
        if (!empty($duedate)) {
            $out .= html_writer::empty_tag('br');

            if ($duedate < $now) {
                    $out .= $this->notification(get_string('overdue', 'totara_plan'), 'notifyproblem');
            } else {
                $days = floor(($duedate - $now) / DAYSECS);
                if ($days == 0) {
                    $out .= $this->notification(get_string('duetoday', 'totara_plan'), 'notifyproblem');
                } else if ($days > 0 && $days < 10) {
                    $out .= $this->notification(get_string('dueinxdays', 'totara_plan', $days), 'notifynotice');
                }
            }
        }
        return $out;
    }

    /**
     * Returns HTML to display a course category as a part of a tree
     *
     * This is an internal function, to display a particular category and all its contents
     * use {@link core_course_renderer::course_category()}
     *
     * @param coursecat_helper $chelper various display options
     * @param coursecat $coursecat
     * @param int $depth depth of this category in the current tree
     * @param string $type Program or certification
     * @return string
     */
    protected function coursecat_category(programcat_helper $chelper, $coursecat, $depth, $type = 'program') {
        global $CFG;
        require_once($CFG->dirroot . '/totara/coursecatalog/lib.php');
        $has_children = false;

        $this->include_js();

        // Open category tag.
        $classes = array('category');
        if (empty($coursecat->visible)) {
            $classes[] = 'dimmed_category';
        }
        if ($chelper->get_subcat_depth() > 0 && $depth >= $chelper->get_subcat_depth()) {
            // do not load content
            $categorycontent = '';
            $classes[] = 'notloaded';
            if ($coursecat->get_children_count() ||
            ($chelper->get_show_programs() >= self::COURSECAT_SHOW_PROGRAMS_COLLAPSED &&
             prog_get_programs_count($coursecat, $type))) {
                $classes[] = 'with_children';
                $classes[] = 'collapsed';
                $has_children = true;
            }
        } else {
            // Load category content.
            $categorycontent = $this->coursecat_category_content($chelper, $coursecat, $depth, $type);
            $classes[] = 'loaded';
            if (!empty($categorycontent)) {
                $classes[] = 'with_children';
                // Category content loaded with children.
                $this->categoryexpandedonload = true;
                $has_children = true;
            }
        }
        $content = html_writer::start_tag('div', array('class' => join(' ', $classes),
                        'data-categoryid' => $coursecat->id,
                        'data-depth' => $depth));

        // Category name.
        $categoryname = $coursecat->get_formatted_name();
        $categorycount = totara_get_category_item_count($coursecat->id, $type);
        // Don't show category if there is nothing to show and the user is not a site admin
        // or a user with capabilities to add course to this category.
        $categorycontext = context_coursecat::instance($coursecat->id);
        if ($type == 'program') {
            $createcapability = 'totara/program:createprogram';
        } else {
            $createcapability = 'totara/certification:createcertification';
        }
        $capabilities = array('moodle/category:viewhiddencategories', 'moodle/category:manage', $createcapability);
        $nohascapabilities = !is_siteadmin() && !has_any_capability($capabilities, $categorycontext);
        if (!empty($CFG->audiencevisibility) && $categorycount == 0 && $nohascapabilities) {
            return '';
        }
        $categoryname = html_writer::link(new moodle_url('/totara/program/index.php',
                        array('categoryid' => $coursecat->id, 'viewtype' => $type)),
                        $categoryname);
        $categoryname .= html_writer::tag('span', ' (' . $categorycount . ')',
                        array('title' => get_string('numberofprograms', 'totara_program')));
        $content .= html_writer::start_tag('div', array('class' => 'info'));

        $icon = null;
        if ($has_children) {
            $icon = new \core\output\flex_icon('collapsed');
        } else {
            $icon = new \core\output\flex_icon('collapsed-empty');
        }
        $content .= html_writer::tag(($depth > 1) ? 'h4' : 'h3', $this->render($icon) . $categoryname, array('class' => 'categoryname'));
        $content .= html_writer::end_tag('div');

        // Add category content to the output.
        $content .= html_writer::tag('div', $categorycontent, array('class' => 'content'));

        $content .= html_writer::end_tag('div');

        // Return the course category tree HTML.
        return $content;
    }

    /**
     * Renders the list of subcategories in a category
     *
     * @param programcat_helper $chelper various display options
     * @param coursecat $coursecat
     * @param int $depth depth of the category in the current tree
     * @param string $type Program or certification
     * @return string
     */
    protected function coursecat_subcategories(programcat_helper $chelper, $coursecat, $depth, $type = 'program') {
        global $CFG;
        $subcategories = array();
        if (!$chelper->get_categories_display_option('nodisplay')) {
            $subcategories = $coursecat->get_children($chelper->get_categories_display_options());
        }
        $totalcount = $coursecat->get_children_count();
        if (!$totalcount) {
            return '';
        }

        // Prepare content of paging bar or more link if it is needed.
        $paginationurl = $chelper->get_categories_display_option('paginationurl');
        $paginationallowall = $chelper->get_categories_display_option('paginationallowall');
        if ($totalcount > count($subcategories)) {
            if ($paginationurl) {
                // The option 'paginationurl was specified, display pagingbar.
                $perpage = $chelper->get_categories_display_option('limit', $CFG->coursesperpage);
                $page = $chelper->get_categories_display_option('offset') / $perpage;
                $pagingbar = $this->paging_bar($totalcount, $page, $perpage,
                                $paginationurl->out(false, array('perpage' => $perpage)));
                if ($paginationallowall) {
                    $pagingbar .= html_writer::tag('div', html_writer::link($paginationurl->out(false, array('perpage' => 'all')),
                                    get_string('showall', '', $totalcount)), array('class' => 'paging paging-showall'));
                }
            } else if ($viewmoreurl = $chelper->get_categories_display_option('viewmoreurl')) {
                // The option 'viewmoreurl' was specified, display more link (if it is link to category view page, add category id).
                if ($viewmoreurl->compare(new moodle_url('/totara/program/index.php'), URL_MATCH_BASE)) {
                    $viewmoreurl->param('categoryid', $coursecat->id);
                }
                $viewmoretext = $chelper->get_categories_display_option('viewmoretext', new lang_string('viewmore'));
                $morelink = html_writer::tag('div', html_writer::link($viewmoreurl, $viewmoretext),
                        array('class' => 'paging paging-morelink'));
            }
        } else if (($totalcount > $CFG->coursesperpage) && $paginationurl && $paginationallowall) {
            // There are more than one page of results and we are in 'view all' mode, suggest to go back to paginated view mode.
            $pagingbar = html_writer::tag('div', html_writer::link($paginationurl->out(false, array('perpage' => $CFG->coursesperpage)),
                            get_string('showperpage', '', $CFG->coursesperpage)), array('class' => 'paging paging-showperpage'));
        }

        // Display list of subcategories.
        $content = html_writer::start_tag('div', array('class' => 'subcategories'));

        if (!empty($pagingbar)) {
            $content .= $pagingbar;
        }

        foreach ($subcategories as $subcategory) {
            $content .= $this->coursecat_category($chelper, $subcategory, $depth + 1, $type);
        }

        if (!empty($pagingbar)) {
            $content .= $pagingbar;
        }
        if (!empty($morelink)) {
            $content .= $morelink;
        }

        $content .= html_writer::end_tag('div');
        return $content;
    }

    /**
     * Returns HTML to display the subcategories and programs in the given category
     *
     * This method is re-used by AJAX to expand content of not loaded category
     *
     * @param programcat_helper $chelper various display options
     * @param coursecat $coursecat
     * @param int $depth depth of the category in the current tree
     * @param string $type Program or certification
     * @return string
     */
    protected function coursecat_category_content(programcat_helper $chelper, $coursecat, $depth, $type = 'program') {
        $content = '';
        // Subcategories.
        $content .= $this->coursecat_subcategories($chelper, $coursecat, $depth, $type);

        $showprogramsauto = $chelper->get_show_programs() == self::COURSECAT_SHOW_PROGRAMS_AUTO;
        if ($showprogramsauto && $depth) {
            $chelper->set_show_programs(self::COURSECAT_SHOW_PROGRAMS_COLLAPSED);
        }

        if ($chelper->get_show_programs() > totara_program_renderer::COURSECAT_SHOW_PROGRAMS_COUNT) {
            $programs = array();
            $displayoptions = $chelper->get_programs_display_options();
            if (!$chelper->get_programs_display_option('nodisplay') && $coursecat->id != 0) {
                $programs = prog_get_programs($coursecat->id, 'p.sortorder ASC',
                    'p.id, p.category, p.sortorder, p.shortname, p.fullname, p.visible, p.icon, p.audiencevisible',
                    $type, $displayoptions);
            }
            if ($viewmoreurl = $chelper->get_programs_display_option('viewmoreurl')) {
                // The option for 'View more' link was specified, display more link.
                // If it is link to category view page, add category id.
                if ($viewmoreurl->compare(new moodle_url('/totara/program/index.php'), URL_MATCH_BASE)) {
                    $chelper->set_programs_display_option('viewmoreurl',
                            new moodle_url($viewmoreurl, array('categoryid' => $coursecat->id)));
                }
            }
            $content .= $this->coursecat_programs($chelper, $programs,
                    prog_get_programs_count($coursecat, $type));
        }

        if ($showprogramsauto) {
            // Restore the show_programs back to AUTO.
            $chelper->set_show_programs(self::COURSECAT_SHOW_PROGRAMS_AUTO);
        }

        return $content;
    }

    /**
     * Returns HTML to display a tree of subcategories and programs in the given category
     *
     * @param programcat_helper $chelper various display options
     * @param coursecat $coursecat top category (this category's name and description will NOT be added to the tree)
     * @param string $type Program or certification
     * @param int $depth depth of the category in the current tree
     * @return string
     */
    protected function coursecat_tree(programcat_helper $chelper, $coursecat, $type = 'program', $depth = 0) {
        // Reset the category expanded flag for this program/certification category tree first.
        $this->categoryexpandedonload = false;
        $categorycontent = $this->coursecat_category_content($chelper, $coursecat, $depth, $type);
        if (empty($categorycontent)) {
            return '';
        }

        // Generate an id and the required JS call to make this a nice widget.
        $id = html_writer::random_id('course_category_tree');

        $content = '';
        $attributes = $chelper->get_and_erase_attributes('course_category_tree clearfix');
        $content .= html_writer::start_tag('div',
            array('id' => $id, 'data-showcourses' => $chelper->get_show_programs()) + $attributes);

        if ($coursecat->get_children_count()) {
            $classes = array(
                'collapseexpand',
                'expand-all',
            );

            // Check if the category content contains subcategories with children's content loaded.
            if ($this->categoryexpandedonload) {
                $classes[] = 'collapse-all';
                $linkname = get_string('collapseall');
            } else {
                $linkname = get_string('expandall');
            }


            // Show the collapse/expand.
            $content .= html_writer::start_tag('div', array('class' => 'collapsible-actions'));
            $content .= html_writer::link('#', $linkname, array('class' => implode(' ', $classes)));
            $content .= html_writer::end_tag('div');
            $this->page->requires->strings_for_js(array('collapseall', 'expandall'), 'moodle');
        }

        $content .= html_writer::tag('div', $categorycontent, array('class' => 'content'));

        $content .= html_writer::end_tag('div');

        return $content;
    }

    /**
     * Renders HTML to display particular program category - list of it's subcategories and programs
     *
     * Invoked from /totara/program/index.php
     *
     * @param int|stdClass|coursecat $category
     * @param string $viewtype 'program' or 'certification'
     * @param bool $subcategoryforajax true if we want to render just a subcategory for ajax. @deprecated 12.0 No longer used by internal code.
     * @return html string
     */
    public function program_category($category, $viewtype = 'program', $subcategoryforajax = null) {
        global $CFG;
        require_once($CFG->libdir . '/coursecatlib.php');

        if ($subcategoryforajax) {
            debugging('The $subcategoryforajax parameter in totara_program_renderer::program_category has been deprecated.',
                DEBUG_DEVELOPER);
        }

        $coursecat = coursecat::get(is_object($category) ? $category->id : $category);
        $site = get_site();
        $output = '';

        $label = ($viewtype == 'program') ? get_string('programs', 'totara_program') :
                                            get_string('certifications', 'totara_certification');

        if (can_edit_in_category()) {
            // Add 'Manage' button instead of program search form.
            $titlebutton = ($viewtype == 'program' ? get_string('manageprograms', 'admin') :
                get_string('managecertifications', 'totara_certification'));
            $managebutton = $this->single_button(new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype)),
                $titlebutton, 'get');
            $this->page->set_button($managebutton);
        }

        if (!$coursecat->id) {
            if (coursecat::count_all() == 1) {
                // There exists only one category in the system, do not display link to it.
                $coursecat = coursecat::get_default();
                $strfulllistofprograms = get_string('fulllistofprograms', 'totara_program');
                $this->page->set_title("$site->shortname: $strfulllistofprograms");
            } else {
                $this->page->set_title($label);
                $this->page->navbar->add($label, new moodle_url('/totara/program/index.php', array('viewtype' => $viewtype)));
            }
        } else {
            $title = $site->shortname;
            if (coursecat::count_all() > 1) {
                $title .= ": ". $coursecat->get_formatted_name();
            }
            $this->page->set_title($title);

            // Print the category selector.
            if (coursecat::count_all() > 1) {
                $output .= html_writer::start_tag('div', array('class' => 'categorypicker'));
                $select = new single_select(new moodle_url('/totara/program/index.php', array('viewtype' => $viewtype)), 'categoryid',
                                coursecat::make_categories_list(), $coursecat->id, null, 'switchcategory');
                $select->set_label($label . ':');
                $output .= $this->render($select);
                $output .= html_writer::end_tag('div');
            }
        }

        // Print current category description.
        $chelper = new programcat_helper();
        if ($description = $chelper->get_category_formatted_description($coursecat)) {
            $output .= $this->box($description, array('class' => 'generalbox info'));
        }

        // Prepare parameters for programs and categories lists in the tree.
        $chelper->set_show_programs(self::COURSECAT_SHOW_PROGRAMS_AUTO)
            ->set_attributes(array('class' => 'category-browse category-browse-' . $coursecat->id));

        $programdisplayoptions = array();
        $catdisplayoptions = array();
        $browse = optional_param('browse', null, PARAM_ALPHA);
        $perpage = optional_param('perpage', $CFG->coursesperpage, PARAM_INT);
        $page = optional_param('page', 0, PARAM_INT);
        $baseurl = new moodle_url('/totara/program/index.php');
        if ($coursecat->id) {
            $baseurl->param('categoryid', $coursecat->id);
        }
        if ($perpage != $CFG->coursesperpage) {
            $baseurl->param('perpage', $perpage);
        }
        $programdisplayoptions['limit'] = $perpage;
        $catdisplayoptions['limit'] = $perpage;
        $hasitems = ($viewtype == 'program') ? prog_has_programs($coursecat) : certif_has_certifications($coursecat);
        if (($browse === 'certifications' || $browse === 'programs') || !$coursecat->has_children()) {
            $programdisplayoptions['offset'] = $page * $perpage;
            $programdisplayoptions['paginationurl'] = new moodle_url($baseurl, array('browse' => $viewtype, 'viewtype' => $viewtype));
            $catdisplayoptions['nodisplay'] = true;
            $catdisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'categories', 'viewtype' => $viewtype));
            $catdisplayoptions['viewmoretext'] = new lang_string('viewallsubcategories');
        } else if ($browse === 'categories' || !$hasitems) {
            $programdisplayoptions['nodisplay'] = true;
            $catdisplayoptions['offset'] = $page * $perpage;
            $catdisplayoptions['paginationurl'] = new moodle_url($baseurl, array('browse' => $browse, 'viewtype' => $viewtype));
            $programdisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('viewtype' => $viewtype));
            $programdisplayoptions['viewmoretext'] = new lang_string("viewall{$viewtype}s", "totara_{$viewtype}");
        } else {
            // We have a category that has both subcategories and programs, display pagination separately.
            $programdisplayoptions['offset'] = $page * $perpage;
            $programdisplayoptions['paginationurl'] = new moodle_url($baseurl, array('browse' => $browse, 'viewtype' => $viewtype));
            $catdisplayoptions['viewmoreurl'] = new moodle_url($baseurl, array('browse' => 'categories', 'viewtype' => $viewtype, 'page' => 1));
        }
        $chelper->set_programs_display_options($programdisplayoptions)->set_categories_display_options($catdisplayoptions);
        // Add search form.
        $output .= $this->program_search_form($viewtype);

        // Display program category tree.
        $depth = $subcategoryforajax ? $coursecat->depth : 0;
        $output .= $this->coursecat_tree($chelper, $coursecat, $viewtype, $depth);

        // Add action buttons.
        $output .= $this->container_start('buttons');
        $context = get_category_or_system_context($coursecat->id);

        $params = ['category' => !empty($coursecat->id) ? $coursecat->id : $CFG->defaultrequestcategory];
        $params['returnto'] = ($coursecat->id) ? 'category' : 'topcat';
        if ($viewtype == 'program' && has_capability('totara/program:createprogram', $context)) {
            // Print link to create a new program, for the 1st available category.
            $url = new moodle_url('/totara/program/add.php', $params);
            $output .= $this->single_button($url, get_string('addnewprogram', 'totara_program'), 'get');
        } else if (has_capability('totara/certification:createcertification', $context)) {
            $url = new moodle_url('/totara/certification/add.php', $params);
            $output .= $this->single_button($url, get_string('addnewcertification', 'totara_certification'), 'get');
        }
        $output .= $this->container_end();

        return $output;
    }

    public function progcat_ajax($category, $categorytype) {
        global $DB, $CFG;

        $depth = required_param('depth', PARAM_INT);
        $chelper = new programcat_helper();
        $baseurl = new moodle_url('/totara/program/index.php', array('categoryid' => $category->id));
        $coursedisplayoptions = array(
            'limit' => $CFG->coursesperpage,
            'viewmoreurl' => new moodle_url($baseurl, array('browse' => 'categories', 'page' => 1))
        );
        $catdisplayoptions = array(
            'limit' => $CFG->coursesperpage,
            'viewmoreurl' => new moodle_url($baseurl, array('browse' => 'categories', 'page' => 1))
        );

        $chelper->set_show_programs(self::COURSECAT_SHOW_PROGRAMS_AUTO)->
            set_courses_display_options($coursedisplayoptions)->
            set_categories_display_options($catdisplayoptions);

        $content = $this->coursecat_category_content($chelper, $category, $depth, $categorytype);

        return $content;
    }

    /**
     * Renders html to display a program search form
     *
     * @param string $value default value to populate the search field
     * @param string $format display format - 'plain' (default), 'short' or 'navbar'
     * @return string
     */
    function program_search_form($type = 'program', $value = '', $format = 'plain') {
        static $count = 0;
        $formid = 'coursesearch';
        if ((++$count) > 1) {
            $formid .= $count;
        }

        switch ($format) {
            case 'navbar' :
                $formid = 'coursesearchnavbar';
                $inputid = 'navsearchbox';
                $inputsize = 20;
                break;
            case 'short' :
                $inputid = 'shortsearchbox';
                $inputsize = 12;
                break;
            default :
                $inputid = 'coursesearchbox';
                $inputsize = 30;
        }

        if ($type == 'program') {
            $strsearchprograms = get_string('searchprograms', 'totara_program');
        } else {
            $strsearchprograms = get_string('searchcertifications', 'totara_certification');
        }
        $searchurl = new moodle_url('/totara/program/search.php');

        $form = array('id' => $formid, 'action' => $searchurl, 'method' => 'get', 'class' => "form-inline", 'role' => 'form');
        $output = html_writer::start_tag('form', $form);
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'viewtype', 'value' => $type));
        $output .= html_writer::start_div('input-group');
        $output .= html_writer::tag('label', $strsearchprograms, array('for' => $inputid, 'class' => 'sr-only'));
        $search = array('type' => 'text', 'id' => $inputid, 'size' => $inputsize, 'name' => 'search',
            'class' => 'form-control', 'value' => $value, 'placeholder' => $strsearchprograms);
        $output .= html_writer::empty_tag('input', $search);
        $button = array('type' => 'submit', 'class' => 'btn btn-default');
        $output .= html_writer::start_span('input-group-btn');
        $output .= html_writer::tag('button', get_string('go'), $button);
        $output .= html_writer::end_span();
        $output .= html_writer::end_div(); // Close form-group.
        $output .= html_writer::end_tag('form');

        return $output;
    }

    /**
     * Renders html to display search result page
     *
     * @param array $searchcriteria
     * @param string $type Program or certification
     * @return string
     */
    public function search_programs($searchcriteria, $type = 'program') {
        global $CFG;
        $content = '';
        if (!empty($searchcriteria)) {
            require_once($CFG->libdir . '/coursecatlib.php');

            $displayoptions = array('sort' => array('displayname' => 1));
            $perpage = optional_param('perpage', 0, PARAM_RAW);
            if ($perpage !== 'all') {
                $displayoptions['limit'] = ((int)$perpage <= 0) ? $CFG->coursesperpage : (int)$perpage;
                $page = optional_param('page', 0, PARAM_INT);
                $displayoptions['offset'] = $displayoptions['limit'] * $page;
            }
            $displayoptions['paginationurl'] = new moodle_url('/course/search.php', $searchcriteria);
            $displayoptions['paginationallowall'] = true;

            $class = 'course-search-result';
            foreach ($searchcriteria as $key => $value) {
                if (!empty($value)) {
                    $class .= ' course-search-result-'. $key;
                }
            }
            $chelper = new programcat_helper();
            $chelper->set_show_programs(self::COURSECAT_SHOW_PROGRAMS_EXPANDED_WITH_CAT)->
                                        set_programs_display_options($displayoptions)->
                                        set_search_criteria($searchcriteria)->
                                        set_attributes(array('class' => $class));

            $programs = coursecat::search_programs($searchcriteria, $chelper->get_programs_display_options(), $type);
            $totalcount = coursecat::search_programs_count($searchcriteria, $chelper->get_programs_display_options(), $type);
            $programslist = $this->coursecat_programs($chelper, $programs, $totalcount);

            $content .= $this->heading(get_string('searchresults'));
            if (!$totalcount) {
                if (!empty($searchcriteria['search'])) {
                    $content .= html_writer::tag('p', get_string('noprogramsfound', 'totara_program', $searchcriteria['search']));
                } else {
                    $content .= html_writer::tag('p', get_string('novalidprograms', 'totara_program'));
                }
            } else {
                $content .= html_writer::tag('p', get_string('searchresults'). ": $totalcount");
                $content .= $programslist;
            }

            if (!empty($searchcriteria['search'])) {
                // print search form only if there was a search by search string, otherwise it is confusing
                $content .= $this->box_start('generalbox mdl-align');
                $content .= $this->program_search_form($type, $searchcriteria['search']);
                $content .= $this->box_end();
            }
        } else {
            $content .= $this->box_start('generalbox mdl-align');
            $content .= $this->program_search_form($type);
            $content .= html_writer::tag('div', get_string("searchhelp"), array('class' => 'searchhelp'));
            $content .= $this->box_end();
        }
        return $content;
    }

    /**
     * Renders the list of programs
     *
     *
     * If list of programs is specified in $programs; the argument $chelper is only used
     * to retrieve display options and attributes, only methods get_show_programs(),
     * get_programs_display_option() and get_and_erase_attributes() are called.
     *
     * @param coursecat_helper $chelper various display options
     * @param array $programs the list of programs to display
     * @param int|null $totalcount total number of programs (affects display mode if it is AUTO or pagination if applicable),
     *     defaulted to count($programs)
     * @return string
     */
    protected function coursecat_programs(programcat_helper $chelper, $programs, $totalcount = null) {
        global $CFG;
        if ($totalcount === null) {
            $totalcount = count($programs);
        }
        if (!$totalcount) {
            return '';
        }

        if ($chelper->get_show_programs() == self::COURSECAT_SHOW_PROGRAMS_AUTO) {
            if ($totalcount <= $CFG->courseswithsummarieslimit) {
                $chelper->set_show_programs(self::COURSECAT_SHOW_PROGRAMS_EXPANDED);
            } else {
                $chelper->set_show_programs(self::COURSECAT_SHOW_PROGRAMS_COLLAPSED);
            }
        }

        // Prepare content of paging bar if it is needed.
        $paginationurl = $chelper->get_programs_display_option('paginationurl');
        $paginationallowall = $chelper->get_programs_display_option('paginationallowall');
        if ($totalcount > count($programs)) {
            // There are more results that can fit on one page.
            if ($paginationurl) {
                // The option paginationurl was specified, display pagingbar.
                $perpage = $chelper->get_programs_display_option('limit', $CFG->coursesperpage);
                $page = $chelper->get_programs_display_option('offset') / $perpage;
                $pagingbar = $this->paging_bar($totalcount, $page, $perpage,
                                $paginationurl->out(false, array('perpage' => $perpage)));
                if ($paginationallowall) {
                    $pagingbar .= html_writer::tag('div', html_writer::link($paginationurl->out(false, array('perpage' => 'all')),
                                    get_string('showall', '', $totalcount)), array('class' => 'paging paging-showall'));
                }
            } else if ($viewmoreurl = $chelper->get_programs_display_option('viewmoreurl')) {
                // the option for 'View more' link was specified, display more link
                $viewmoretext = $chelper->get_programs_display_option('viewmoretext', new lang_string('viewmore'));
                $morelink = html_writer::tag('div', html_writer::link($viewmoreurl, $viewmoretext),
                                array('class' => 'paging paging-morelink'));
            }
        } else if (($totalcount > $CFG->coursesperpage) && $paginationurl && $paginationallowall) {
            $pagingbar = html_writer::tag('div', html_writer::link($paginationurl->out(false, array('perpage' => $CFG->coursesperpage)),
                            get_string('showperpage', '', $CFG->coursesperpage)), array('class' => 'paging paging-showperpage'));
        }

        $attributes = $chelper->get_and_erase_attributes('courses');
        $content = html_writer::start_tag('div', $attributes);

        if (!empty($pagingbar)) {
            $content .= $pagingbar;
        }

        $programcount = 0;
        foreach ($programs as $program) {
            $programcount ++;
            $classes = ($programcount%2) ? 'odd' : 'even';
            if ($programcount == 1) {
                $classes .= ' first';
            }
            if ($programcount >= count($programs)) {
                $classes .= ' last';
            }
            $content .= $this->coursecat_programbox($chelper, $program, $classes);
        }

        if (!empty($pagingbar)) {
            $content .= $pagingbar;
        }
        if (!empty($morelink)) {
            $content .= $morelink;
        }

        $content .= html_writer::end_tag('div');
        return $content;
    }

    /**
     * Displays one program in the list of programs.
     *
     * This is an internal function, to display an information about just one program
     * please use {@link totara_program_renderer::program_info_box()}
     *
     * @param programcat_helper $chelper various display options
     * @param program_in_list|stdClass $program
     * @param string $additionalclasses additional classes to add to the main <div> tag (usually
     *    depend on the program position in list - first/last/even/odd)
     * @return string
     */
    protected function coursecat_programbox(programcat_helper $chelper, $program, $additionalclasses = '') {
        global $CFG;
        if (!isset($this->strings->summary)) {
            $this->strings = new stdClass();
            $this->strings->summary = get_string('summary');
        }
        if ($chelper->get_show_programs() <= self::COURSECAT_SHOW_PROGRAMS_COUNT) {
            return '';
        }
        if ($program instanceof stdClass) {
            require_once($CFG->libdir. '/coursecatlib.php');
            $program = new program_in_list($program);
        }
        $content = '';
        $classes = trim('panel panel-default coursebox clearfix '. $additionalclasses);
        if ($chelper->get_show_programs() < self::COURSECAT_SHOW_PROGRAMS_EXPANDED) {
            $classes .= ' collapsed';
        }
        $content .= html_writer::start_tag('div', array('class' => $classes, 'data-programid' => $program->id));
        $content .= html_writer::start_tag('div', array('class' => 'panel-heading info'));

        $programname = format_string($program->fullname);
        $programicon = totara_get_icon($program->id, TOTARA_ICON_TYPE_PROGRAM);
        $dimmed = totara_get_style_visibility($program);
        $programnamelink = html_writer::link(new moodle_url('/totara/program/view.php', array('id' => $program->id)),
                        $programname, array('class' => $dimmed,
                        'style' => "background-image:url({$programicon})"));
        $content .= html_writer::tag('div', $programnamelink, array('class' => 'name'));

        // If we display program in collapsed form but the program has summary, display the link to the info page.
        $content .= html_writer::start_tag('div', array('class' => 'moreinfo'));
        if ($chelper->get_show_programs() < self::COURSECAT_SHOW_PROGRAMS_EXPANDED) {
            if ($program->has_summary() || $program->has_program_overviewfiles()) {
                $url = new moodle_url('/totara/program/info.php', array('id' => $program->id));
                $image = $this->output->flex_icon('info-circle');
                $content .= html_writer::link($url, $image, array('title' => $this->strings->summary));
                $this->include_js();
            }
        }
        $content .= html_writer::end_tag('div');
        $content .= html_writer::end_tag('div');

        $content .= html_writer::start_tag('div', array('class' => 'content'));
        $content .= $this->coursecat_programbox_content($chelper, $program);
        $content .= html_writer::end_tag('div');
        $content .= html_writer::end_tag('div');

        return $content;
    }

    /**
     * Returns HTML to display program content (summary and optionally category name)
     *
     * This method is called from coursecat_programbox() and may be re-used in AJAX
     *
     * @param programcat_helper $chelper various display options
     * @param stdClass|program_in_list $program
     * @return string
     */
    public function coursecat_programbox_content(programcat_helper $chelper, $program) {
        global $CFG;
        if ($chelper->get_show_programs() < self::COURSECAT_SHOW_PROGRAMS_EXPANDED) {
            return '';
        }
        if ($program instanceof stdClass) {
            require_once($CFG->libdir . '/coursecatlib.php');
            $program = new program_in_list($program);
        }
        $content = '';

        if ($program->has_summary()) {
            $content .= html_writer::start_tag('div', array('class' => 'summary'));
            $content .= $chelper->get_program_formatted_summary($program,
                            array('overflowdiv' => true, 'noclean' => true, 'para' => false));
            $content .= html_writer::end_tag('div');
        }

        $contentimages = $contentfiles = '';
        foreach ($program->get_program_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            $url = moodle_url::make_pluginfile_url($file->get_contextid(), 'totara_program',
                    $file->get_filearea(), 0, $file->get_filepath(), $file->get_filename(), !$isimage);
            if ($isimage) {
                $contentimages .= html_writer::tag('div',
                                html_writer::empty_tag('img', array('src' => $url)),
                                array('class' => 'courseimage'));
            } else {
                $image = $this->output->pix_icon(file_file_icon($file, 24), $file->get_filename(), 'moodle');
                $filename = html_writer::tag('span', $image, array('class' => 'fp-icon')).
                html_writer::tag('span', $file->get_filename(), array('class' => 'fp-filename'));
                $contentfiles .= html_writer::tag('span',
                                html_writer::link($url, $filename),
                                array('class' => 'coursefile fp-filename-icon'));
            }
        }
        $content .= $contentimages. $contentfiles;

        if ($chelper->get_show_programs() == self::COURSECAT_SHOW_PROGRAMS_EXPANDED_WITH_CAT) {
            require_once($CFG->libdir . '/coursecatlib.php');
            if ($cat = coursecat::get($program->category, IGNORE_MISSING)) {
                $content .= html_writer::start_tag('div', array('class' => 'coursecat'));
                $content .= get_string('category').': '.
                                html_writer::link(new moodle_url('/totara/program/index.php', array('categoryid' => $cat->id)),
                                                $cat->get_formatted_name(), array('class' => $cat->visible ? '' : 'dimmed'));
                                $content .= html_writer::end_tag('div');
            }
        }

        return $content;
    }

    /**
     * Serves requests to /totara/program/category.ajax.php
     *
     * This renders the description in a format that is suitable for a Ajax request
     *
     * @param program $program The program for which you want the summary for
     * @return string $output The summary in a displayable format
     */
    public function program_description_ajax(program $program) {
        $context = context_program::instance($program->id);
        $summary = file_rewrite_pluginfile_urls($program->summary, 'pluginfile.php', $context->id, 'totara_program', 'summary', 0);
        $output = html_writer::start_tag('div', array('class' => 'summary'));
        $output .= html_writer::start_tag('div', array('class' => 'no-overflow'));
        $output .= format_text($summary, FORMAT_HTML);
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Make sure that javascript file for AJAX expanding of courses and categories content is included
     */
    protected function include_js() {
        global $CFG;
        static $jsloaded = false;
        if (!$jsloaded) {
            // We must only load this module once.
            $this->page->requires->yui_module('moodle-totara_program-categoryexpander', 'M.program.categoryexpander.init');
            $jsloaded = true;
        }
    }

    /**
     * Generates HTML to display programs which have problems, including summary info.
     *
     * @param $data Object with a bunch of stuff.
     * @return string HTML fragment
     */
    public function get_completion_checker_results($data) {
        if (!empty($data->rs)) {
            debugging('The $data required by get_completion_checker_results has changed', DEBUG_DEVELOPER);
            return "";
        }

        $out = "";

        $fulllist = $data->fulllist;
        $aggregatelist = $data->aggregatelist;
        $totalcount = $data->totalcount;
        $problemcount = 0;

        // Create the table with individual errors first, but display it last.
        ob_start();
        $errortable = new totara_table('checkall');
        $errortable->define_columns(array('userid', 'progid', 'errors'));
        $errortable->define_headers(array(get_string('user'), get_string('program', 'totara_program'),
            get_string('problem', 'totara_program')));
        $errortable->define_baseurl($data->url);
        $errortable->sortable(false);
        $errortable->setup();

        foreach ($fulllist as $affected) {
            $errortable->add_data(array(html_writer::link($affected->editcompletionurl, $affected->userfullname),
                $affected->programname, $affected->problem));
            $problemcount++;
        }
        $errortable->finish_html();
        $errorhtml = ob_get_clean();

        // Display the summary of results.
        if (!empty($data->progname)) {
            $out .= html_writer::tag('p', get_string('completionfilterbyprogram', 'totara_program', $data->progname));
        }
        if (!empty($data->username)) {
            $out .= html_writer::tag('p', get_string('completionfilterbyuser', 'totara_program', $data->username));
        }
        $out .= html_writer::tag('p', get_string('completionrecordcounttotal', 'totara_program', $totalcount));
        $out .= html_writer::tag('p', get_string('completionrecordcountproblem', 'totara_program', $problemcount));

        // Display the aggregated problems and solution, including link to activate any fixes that are available.
        ob_start();
        if (!empty($aggregatelist)) {
            $aggregatetable = new totara_table('checkall_aggregate');
            $aggregatetable->define_columns(array('category', 'problems', 'count', 'explanation'));
            $aggregatetable->define_headers(array(
                get_string('problemcategory', 'totara_program') . $this->output->help_icon('problemcategory', 'totara_program', null),
                get_string('problem', 'totara_program'),
                get_string('count', 'totara_program'),
                get_string('completionprobleminformation', 'totara_program')));
            $aggregatetable->define_baseurl($data->url);
            $errortable->sortable(false);
            $aggregatetable->setup();

            foreach ($aggregatelist as $key => $value) {
                // Dev note: Change $value->solution to $key to see error keys in summary table.
                $aggregatetable->add_data(array($value->category, $value->problem, $value->count, $value->solution), 'problemaggregation');
            }

            $aggregatetable->finish_html();
        }
        $out .= ob_get_clean();

        // Finally, add the individual errors table to the output.
        $out .= $errorhtml;

        return $out;
    }
}

/**
 * Class storing display options and functions to help display program category and/or programs lists
 *
 * Extending {@link coursecat_helper} class to add program options.
 *
 */
class programcat_helper extends coursecat_helper {
    /** @var string [none, collapsed, expanded] how (if) display programs list */
    protected $showprograms = 10; /* totara_program_renderer::COURSECAT_SHOW_PROGRAMS_EXPANDED */
    /** @var array options to display programs list */
    protected $programsdisplayoptions = array();


    /**
     * Sets how (if) to show the programs - none, collapsed, expanded, etc.
     *
     * @param int $showprograms SHOW_PROGRAMS_NONE, SHOW_PROGRAMS_COLLAPSED, SHOW_PROGRAMS_EXPANDED, etc.
     * @return programcat_helper
     */
    public function set_show_programs($showprograms) {
        $this->showprograms = $showprograms;
        $this->programsdisplayoptions['summary'] = $showprograms >= totara_program_renderer::COURSECAT_SHOW_PROGRAMS_AUTO;
        return $this;
    }

    /**
     * Returns how (if) to show the programs - none, collapsed, expanded, etc.
     *
     * @return int - COURSECAT_SHOW_PROGRAMS_NONE, COURSECAT_SHOW_PROGRAMS_COLLAPSED, COURSECAT_SHOW_PROGRAMS_EXPANDED, etc.
     */
    public function get_show_programs() {
        return $this->showprograms;
    }

    /**
     * Sets options to display list of programs
     *
     * Options are later submitted as argument to coursecat::get_programs() and/or coursecat::search_programs()
     *
     * @param array $options
     * @return programcat_helper
     */
    public function set_programs_display_options($options) {
        $this->programsdisplayoptions = $options;
        $this->set_show_programs($this->showprograms);
        return $this;
    }

    /**
     * Sets one option to display list of programss
     *
     * @see programcat_helper::set_programs_display_options()
     *
     * @param string $key
     * @param mixed $value
     * @return programcat_helper
     */
    public function set_programs_display_option($key, $value) {
        $this->programsdisplayoptions[$key] = $value;
        return $this;
    }

    /**
     * Return the specified option to display list of programs
     *
     * @param string $optionname option name
     * @param mixed $defaultvalue default value for option if it is not specified
     * @return mixed
     */
    public function get_programs_display_option($optionname, $defaultvalue = null) {
        if (array_key_exists($optionname, $this->programsdisplayoptions)) {
            return $this->programsdisplayoptions[$optionname];
        } else {
            return $defaultvalue;
        }
    }

    /**
     * Returns all options to display the programs
     *
     * This array is usually passed to {@link coursecat::get_programs()} or
     * {@link coursecat::search_programs()}
     *
     * @return array
     */
    public function get_programs_display_options() {
        return $this->programsdisplayoptions;
    }

    /**
     * Returns given program's summary with proper embedded files urls and formatted
     *
     * @param program_in_list $program
     * @param array|stdClass $options additional formatting options
     * @return string
     */
    public function get_program_formatted_summary($program, $options = array()) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        if (!$program->has_summary()) {
            return '';
        }
        $options = (array)$options;
        $context = context_program::instance($program->id);
        $summary = file_rewrite_pluginfile_urls($program->summary, 'pluginfile.php', $context->id, 'totara_program', 'summary', 0);
        $summary = format_text($summary, FORMAT_HTML, $options);
        if (!empty($this->searchcriteria['search'])) {
            $summary = highlight($this->searchcriteria['search'], $summary);
        }
        return $summary;
    }
}
