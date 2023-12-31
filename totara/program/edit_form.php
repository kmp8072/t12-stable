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

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/coursecatlib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class program_edit_form extends moodleform {

    function definition() {
        global $CFG, $OUTPUT, $COHORT_VISIBILITY;

        $mform =& $this->_form;
        $action = $this->_customdata['action'];
        $category = $this->_customdata['category'];
        $summaryeditoroptions = $this->_customdata['summaryeditoroptions'];
        $endnoteeditoroptions = $this->_customdata['endnoteeditoroptions'];
        $program = (isset($this->_customdata['program'])) ? $this->_customdata['program'] : false;
        $overviewfiles = (isset($this->_customdata['overviewfiles'])) ? $this->_customdata['overviewfiles'] : false;
        $nojs = (isset($this->_customdata['nojs'])) ? $this->_customdata['nojs'] : 0 ;
        $iscertif = (isset($this->_customdata['iscertif'])) ? $this->_customdata['iscertif'] : 0;

        $config = get_config('moodlecourse');

        if ($program) {
            $context = context_program::instance($program->id);
        } else {
            $context = context_coursecat::instance($category->id);
        }

        // Add some hidden fields
        if ($action != 'add') {
            $mform->addElement('hidden', 'id');
            $mform->setType('id', PARAM_INT);
        }

        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('hidden', 'iscertif', $iscertif);
        $mform->setType('iscertif', PARAM_INT);

        if ($action == 'delete') {
            // Only show delete confirmation
            $mform->addElement('html', get_string('checkprogramdelete', 'totara_program', $program->fullname));
            $buttonarray = array();
            $buttonarray[] = $mform->createElement('submit', 'deleteyes', get_string('yes'));
            $buttonarray[] = $mform->createElement('submit', 'deleteno', get_string('no'));
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
            return;
        }

/// form definition with new program defaults
//--------------------------------------------------------------------------------
        $mform->addElement('header','programdetails', get_string('programdetails', 'totara_program'));

        if ($action == 'edit') {
            $mform->addElement('html', html_writer::start_tag('p', array('class' => 'instructions')) . get_string('instructions:programdetails', 'totara_program') . html_writer::end_tag('p'));
        }

        // Must have create program capability in both categories in order to move program
        if (has_capability('totara/program:createprogram', $context)) {
            $displaylist = array();
            $attributes = array();
            $attributes['class'] = 'totara-limited-width';
            $displaylist = coursecat::make_categories_list('totara/program:createprogram');
            $mform->addElement('select', 'category', get_string('category', 'totara_program'), $displaylist, $attributes);
            $mform->setType('category', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'category', null);
            $mform->setType('category', PARAM_INT);
        }

        if ($action == 'view') {
            $mform->hardFreeze('category');
        } else if ($program and !has_capability('moodle/course:changecategory', $context)) {
        // Use the course permissions to decide if a user can change a program's category
        // (as programs are treated like courses in this respect)
            $mform->hardFreeze('category');
            $mform->setConstant('category', $category->id);
        } else {
            $mform->addHelpButton('category', 'programcategory', 'totara_program');
            $mform->setDefault('category', $category->id);
        }

        $mform->addElement('text','fullname', get_string('fullname', 'totara_program'),'maxlength="254" size="50"');
        $mform->setType('fullname', PARAM_TEXT);
        if ($action == 'view') {
            $mform->hardFreeze('fullname');
        } else {
            $mform->addHelpButton('fullname', 'programfullname', 'totara_program');
            if ($iscertif) {
                $mform->setDefault('fullname', get_string('defaultcertprogramfullname', 'totara_certification'));
            } else {
                $mform->setDefault('fullname', get_string('defaultprogramfullname', 'totara_program'));
            }
            $mform->addRule('fullname', get_string('missingfullname'), 'required', null, 'client');

        }

        $mform->addElement('text','shortname', get_string('shortname', 'totara_program'),'maxlength="100" size="20"');
        $mform->setType('shortname', PARAM_TEXT);
        if ($action=='view') {
            $mform->hardFreeze('shortname');
        } else {
            $mform->addHelpButton('shortname', 'programshortname', 'totara_program');
            if ($iscertif) {
                $mform->setDefault('shortname', get_string('defaultcertprogramshortname', 'totara_certification'));
            } else {
                $mform->setDefault('shortname', get_string('defaultprogramshortname', 'totara_program'));
            }
            $mform->addRule('shortname', get_string('missingshortname', 'totara_program'), 'required', null, 'client');
        }

        $mform->addElement('text','idnumber', get_string('idnumberprogram', 'totara_program'),'maxlength="100"  size="10"');
        $mform->setType('idnumber', PARAM_TEXT);
        if ($action == 'view') {
            $mform->hardFreeze('idnumber');
        } else {
            $mform->addHelpButton('idnumber', 'programidnumber', 'totara_program');
        }

        $mform->addElement('date_selector', 'availablefrom', get_string('availablefrom', 'totara_program'), array('optional' => true));
        $mform->setType('availablefrom', PARAM_INT);
        if ($action == 'view') {
            $mform->hardFreeze('availablefrom');
        } else {
            $mform->addHelpButton('availablefrom', 'programavailability', 'totara_program');
        }

        $mform->addElement('date_selector', 'availableuntil', get_string('availableuntil', 'totara_program'), array('optional' => true));
        $mform->setType('availableuntil', PARAM_INT);
        if ($action == 'view') {
            $mform->hardFreeze('availableuntil');
        } else {
            $mform->addHelpButton('availableuntil', 'programavailability', 'totara_program');
        }

        if ($action == 'view') {
            if ($program) {
                $summary = file_rewrite_pluginfile_urls($program->summary, 'pluginfile.php',
                    $context->id, 'totara_program', 'summary', 0);
                if (!empty($summary)) {
                    $mform->addElement('static', null, get_string('description', 'totara_program'), $summary);
                }
            }
        } else {
            $mform->addElement('editor', 'summary_editor', get_string('description', 'totara_program'), null, $summaryeditoroptions);
            $mform->addHelpButton('summary_editor', 'summary', 'totara_program');
            $mform->setType('summary_editor', PARAM_RAW);
        }

        if ($overviewfilesoptions = prog_program_overviewfiles_options($program)) {
            if ($action == 'view') {
                if (!empty($overviewfiles)) {
                    $contentfiles = '';
                    foreach ($overviewfiles as $file) {
                        $isimage = $file->is_valid_image();
                        $url = moodle_url::make_pluginfile_url($file->get_contextid(), 'totara_program',
                            $file->get_filearea(), 0, $file->get_filepath(), $file->get_filename(), !$isimage);
                        if ($isimage) {
                            $contentfiles = html_writer::tag('div',
                                html_writer::empty_tag('img', array('src' => $url)),
                                array('class' => 'courseimage'));
                        } else {
                            $image = $OUTPUT->pix_icon(file_file_icon($file, 24), $file->get_filename(), 'moodle');
                            $filename = html_writer::tag('span', $image, array('class' => 'fp-icon')).
                                html_writer::tag('span', $file->get_filename(), array('class' => 'fp-filename'));
                            $contentfiles .= html_writer::tag('span',
                                html_writer::link($url, $filename),
                                array('class' => 'coursefile fp-filename-icon'));
                        }
                    }
                    if (!empty($contentfiles)) {
                        $mform->addElement('static', null, get_string('programoverviewfiles', 'totara_program'), $contentfiles);
                    }
                }
            } else {
                $mform->addElement('filemanager', 'overviewfiles_filemanager',
                    get_string('programoverviewfiles', 'totara_program'), null, $overviewfilesoptions);
                $mform->addHelpButton('overviewfiles_filemanager', 'programoverviewfiles', 'totara_program');
            }
        }

        if ($action == 'view') {
            if ($program) {
                $endnote = file_rewrite_pluginfile_urls($program->endnote, 'pluginfile.php',
                    $context->id, 'totara_program', 'endnote', 0);
                if (!empty($endnote)) {
                    $mform->addElement('static', null, get_string('endnote', 'totara_program'), $endnote);
                }
            }
        } else {
            $mform->addElement('editor', 'endnote_editor', get_string('endnote', 'totara_program'), null, $endnoteeditoroptions);
            $mform->addHelpButton('endnote_editor', 'endnote', 'totara_program');
            $mform->setType('endnote_editor', PARAM_RAW);
        }

        // Conditionally add "visible" setting or audience dialog for visible learning.
        if (empty($CFG->audiencevisibility)) {
            if ($action == 'view') {
                $mform->addElement('static', 'visibledisplay', get_string('visible', 'totara_program'), $program->visible ? get_string('yes') : get_string('no'));
            } else {
                $mform->addElement('advcheckbox','visible', get_string('visible', 'totara_program'), null, null, array(0, 1));
                $mform->addHelpButton('visible', 'programvisibility', 'totara_program');
                $mform->setDefault('visible', $config->visible);
                $mform->setType('visible', PARAM_BOOL);
            }
        } else {
            // Define instance type.
            $instancetype = COHORT_ASSN_ITEMTYPE_PROGRAM;
            if (!empty($program->certifid)) {
                $instancetype = COHORT_ASSN_ITEMTYPE_CERTIF;
            }
            if ($action == 'view') {
                $mform->addElement('header', 'visiblecohortshdr', get_string('audiencevisibility', 'totara_cohort'));
                $mform->addElement('static', 'visibledisplay', get_string('audiencevisibility', 'totara_cohort'), $COHORT_VISIBILITY[$program->audiencevisible]);
                $cohorts = totara_cohort_get_visible_learning($program->id, $instancetype);
                if (!empty($cohorts)) {
                    $cohortsclass = new totara_cohort_visible_learning_cohorts();
                    $cohortsclass->build_visible_learning_table($program->id, $instancetype, true);
                    $mform->addElement('html', $cohortsclass->display(true, 'visible'));
                }
                $mform->setExpanded('visiblecohortshdr');
            } else {
                // Only show the Audiences Visibility functionality to users with the appropriate permissions.
                if (has_capability('totara/coursecatalog:manageaudiencevisibility', $context)) {
                    $mform->addElement('header', 'visiblecohortshdr', get_string('audiencevisibility', 'totara_cohort'));
                    $mform->addElement('select', 'audiencevisible', get_string('visibility', 'totara_cohort'), $COHORT_VISIBILITY);
                    $mform->addHelpButton('audiencevisible', 'visiblelearning', 'totara_cohort');

                    if (empty($program->id)) {
                        $mform->setDefault('audiencevisible', $config->visiblelearning);
                        $cohorts = '';
                    } else {
                        $cohorts = totara_cohort_get_visible_learning($program->id, $instancetype);
                        $cohorts = !empty($cohorts) ? implode(',', array_keys($cohorts)) : '';
                    }

                    $mform->addElement('hidden', 'cohortsvisible', $cohorts);
                    $mform->setType('cohortsvisible', PARAM_SEQUENCE);
                    $cohortsclass = new totara_cohort_visible_learning_cohorts();
                    $instanceid = !empty($program->id) ? $program->id : 0;
                    $cohortsclass->build_visible_learning_table($instanceid, $instancetype);
                    $mform->addElement('html', $cohortsclass->display(true, 'visible'));

                    $mform->addElement('button', 'cohortsaddvisible', get_string('cohortsaddvisible', 'totara_cohort'));
                    $mform->setExpanded('visiblecohortshdr');
                }
            }
        }

        $mform->addElement('header', 'appearance', get_string('appearance'));
        $mform->setExpanded('appearance');

        //replacement for old totara/core/icon classes
        $programicon = ($program && !empty($program->icon)) ? $program->icon : 'default';
        totara_add_icon_picker($mform, $action, 'program', $programicon, $nojs, false);

        if ($action == 'view') {
            if ($program && $program->get_image()) {
                $mform->addElement(
                    'static',
                    null,
                    get_string('image', 'totara_program'),
                    html_writer::img($program->get_image(), get_string('imagealt', 'totara_program'))
                );
            } else {
                $mform->addElement(
                    'static',
                    null,
                    get_string('image', 'totara_program'),
                    html_writer::span(get_string('imagenone', 'totara_program'))
                );
            }
        } else {
            $mform->addElement(
                'filemanager',
                'image',
                get_string('image', 'totara_program'),
                null,
                [
                    'accept_types' => 'web_image',
                    'maxfiles' => 1
                ]
            );
            $mform->addHelpButton('image', 'image', 'totara_program');
        }

        if (core_tag_tag::is_enabled('totara_program', 'prog')) {
            if ($action == 'view') {
                $mform->addElement('html', $OUTPUT->tag_list(core_tag_tag::get_item_tags('totara_program', 'prog', $program->id), null, 'programtags'));
            } else {
                $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));
                $mform->addElement('tags', 'tags', get_string('tags'), array('itemtype' => 'prog', 'component' => 'totara_program'));
            }
        }

        // If program extension request is ON, show setting to allow request extension in this program.
        if (!empty($CFG->enableprogramextensionrequests)) {
            $label = get_string('allowextensionrequests', 'totara_program');
            $mform->addElement('header','othersettings', get_string('othersettings', 'totara_program'));

            $mform->addElement('advcheckbox','allowextensionrequests', $label, null, null, array(0, 1));
            $mform->setType('allowextensionrequests', PARAM_BOOL);
            $allowextensionrequests = (!$program || !empty($program->allowextensionrequests)) ? 1 : 0;
            $mform->setDefault('allowextensionrequests', $allowextensionrequests);
            if ($action == 'view') {
                $mform->hardFreeze('allowextensionrequests');
            } else {
                $mform->addHelpButton('allowextensionrequests', 'allowextensionrequests', 'totara_program');
            }
            $mform->setExpanded('othersettings');
        }

        // Customfield support.
        if (!$program) {
            $program = new stdClass();
        }
        if (empty($program->id)) {
            $program->id = 0;
        }
        if (in_array($action, array('add', 'edit'))) {
            customfield_definition($mform, $program, 'program', 0, 'prog');
        } else {
            $customfields = customfield_get_data($program, 'prog', 'program');
            if (!empty($customfields)) {
                $mform->addElement('header', 'customfields', get_string('customfields', 'totara_customfield'));
                foreach ($customfields as $cftitle => $cfvalue) {
                    $mform->addElement('static', null, $cftitle, $cfvalue);
                }
                $mform->setExpanded('customfields');
            }
        }

        if ($action == 'add') {
            $buttonarray = array();
            $buttonarray[] = $mform->createElement('submit', 'savechanges', get_string('savechanges'), 'class="savechanges-overview program-savechanges"');
            $buttonarray[] = $mform->createElement('cancel', 'cancel', get_string('cancel', 'totara_program'), 'class="program-cancel"');
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        } else if ($action == 'edit') {
            $buttonarray = array();
            $buttonarray[] = $mform->createElement('submit', 'savechanges', get_string('savechanges'), 'class="savechanges-overview program-savechanges"');
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        }
    }

    function validation($data, $files) {

        $mform = $this->_form;
        $errors = array();

        if ($data['availablefrom'] != 0 && $data['availableuntil'] != 0) {
            if ($data['availablefrom'] > $data['availableuntil']) {
                $errors['availableuntil'] = get_string('error:availibileuntilearlierthanfrom', 'totara_program');
            }
        }

        $id = isset($data['id']) ? $data['id'] : 0;
        if (!empty($data['idnumber']) && totara_idnumber_exists('prog', $data['idnumber'], $id)) {
            $errors['idnumber'] = get_string('idnumberexists', 'totara_core');
        }

        // Validate any custom fields, this requires the ID to be set.
        $data['id'] = $id;
        $errors += customfield_validation((object)$data, 'program', 'prog');

        return $errors;
    }

    public function definition_after_data() {
        global $DB;

        $mform = $this->_form;

        $progid = $mform->elementExists('id') ? $mform->getElementValue('id') : 0;
        if ($program = $DB->get_record('prog', array('id' => $progid))) {
            customfield_definition_after_data($mform, $program, 'program', 0, 'prog');
        }
    }

}

// Define a form for the edit program details button.
class program_edit_details_button_form extends moodleform {

    function definition() {
        global $OUTPUT;

        $mform =& $this->_form;
        $program = $this->_customdata['program'];

        $button = $OUTPUT->single_button(new moodle_url($this->_form->getAttribute('action')),
            get_string('editprogramdetails', 'totara_program'), 'get');
        $mform->addElement('static', 'progdetailsbutton', '', $button);
    }
}

// Define a form class to display the program content in a non-editable form
class program_content_nonedit_form extends moodleform {

    function definition() {
        global $CFG, $USER, $OUTPUT;

        $mform =& $this->_form;

        $program = $this->_customdata['program'];
        $content = $program->get_content();

        // form definition
        //--------------------------------------------------------------------------------

       $mform->addElement('header','programcontent', get_string('programcontent', 'totara_program'));

       // Get the total time allowed for this certification/program
       if ($program->certifid) {
           $this->display_course_sets($mform, $content, CERTIFPATH_CERT, get_string('oricertpath', 'totara_certification'));
           $this->display_course_sets($mform, $content, CERTIFPATH_RECERT, get_string('recertpath', 'totara_certification'));
       } else {
           $this->display_course_sets($mform, $content, CERTIFPATH_STD, '');
       }

        // Check capabilities.
        if (has_capability('totara/program:configurecontent', $program->get_context())) {
            $button = $OUTPUT->single_button(new moodle_url($this->_form->getAttribute('action'), array('id' => $program->id)),
                get_string('editprogramcontent', 'totara_program'), 'get');
            $mform->addElement('static', 'progcontentbutton', '', $button);
        }

    }

    /**
     * Display course sets for view
     */
    function display_course_sets(&$mform, $content, $certifpath, $formlabel) {

        $coursesets = $content->get_course_sets_path($certifpath);

        $formlabel && $mform->addElement('static', 'pathtitle_'.$certifpath, $formlabel.':', '');
        if (count($coursesets)) {
            foreach ($coursesets as $courseset) {
                $elementname = $courseset->get_set_prefix();
                $formlabel = $courseset->display_form_label();
                $formelement = $courseset->display_form_element();
                $mform->addElement('static', $elementname, $formlabel, $formelement);
            }

            $this->display_time_allowed($mform, $content, $certifpath);

        } else {
            $mform->addElement('static', 'progcontent', '', get_string('nocontent', 'totara_program'));
       }
    }


    /**
     * Display the total time allowed for this program
     */
    function display_time_allowed(&$mform, $content, $certifpath) {

        $total_time_allowed = $content->get_total_time_allowance($certifpath);

        // Only display the time allowance if it is greater than zero
        if ($total_time_allowed > 0) {
            // Break the time allowed details down into human readable form
            $numperiod = program_utilities::get_duration_num_and_period($total_time_allowed);
            $timeallowedstr = html_writer::tag('p', get_string('allowtimeforprogram' . $numperiod->periodkey, 'totara_program', $numperiod->num), array('class' => 'timeallowed'));
            $mform->addElement('static', 'timeallowance_'.$certifpath, '', $timeallowedstr);
        }
    }
}

// Define a form class to display the program assignments
class program_assignments_nonedit_form extends moodleform {

    function definition() {
        global $CFG, $USER, $OUTPUT;

        $mform =& $this->_form;

        $program = $this->_customdata['program'];
        /** @var prog_assignments $assignments */
        $assignments = $program->get_assignments();

// form definition
//--------------------------------------------------------------------------------
        $mform->addElement('header','programassignments', get_string('programassignments', 'totara_program'));

        // Above table indicating group numbers, display real current total assignments.
        $mform->addElement('static', 'realassignments', get_string('totallearnersassigned', 'totara_program'), $assignments->count_total_user_assignments());
        $mform->addHelpButton('realassignments', 'totallearnersassigned', 'totara_program');

        $elementname = 'assignments';
        $formlabel = $assignments->display_form_label();
        $formelement = $assignments->display_form_element();

        $mform->addElement('static', $elementname, $formlabel, $formelement);

        // Check capabilities
        if (has_capability('totara/program:configureassignments', $program->get_context())) {
            $button = $OUTPUT->single_button(new moodle_url($this->_form->getAttribute('action'), array('id' => $program->id)),
                get_string('editprogramassignments', 'totara_program'), 'get');

            $mform->addElement('static', 'progassignbutton', '', $button);
        }
    }


}


// Define a form class to display the program messages
class program_messages_nonedit_form extends moodleform {

    function definition() {
        global $CFG, $USER, $OUTPUT;

        $mform =& $this->_form;

        $program = $this->_customdata['program'];
        $messagesmanager = $program->get_messagesmanager();

// form definition
//--------------------------------------------------------------------------------
        $mform->addElement('header','programmessages', get_string('programmessages', 'totara_program'));

        $elementname = 'messages';
        $formlabel = $messagesmanager->display_form_label();
        $formelement = $messagesmanager->display_form_element();

        $mform->addElement('static', $elementname, $formlabel, $formelement);

        // Check capabilities
        if (has_capability('totara/program:configuremessages', $program->get_context())) {
            $button = $OUTPUT->single_button(new moodle_url($this->_form->getAttribute('action'), array('id' => $program->id)),
                get_string('editprogrammessages', 'totara_program'), 'get');

            $mform->addElement('static', 'progmessagebutton', '', $button);
        }
    }
}



// display the certification details on Overview tab
class program_certifications_nonedit_form extends moodleform {

    function definition() {
        global $CFG, $USER, $DB, $CERTIFRECERT, $OUTPUT;

        $mform =& $this->_form;

        $program = $this->_customdata['program'];

        // form definition
        //--------------------------------------------------------------------------------
        $mform->addElement('header','hdrcertification', get_string('certification', 'totara_certification'));

        $certification = $DB->get_record('certif', array('id' => $program->certifid));
        if (!$certification || $certification->activeperiod == 0) {
            $mform->addElement('static', 'el1', '' , get_string('nocertifdetailsfound', 'totara_certification'));
        } else {
            $parts = explode(' ', $certification->activeperiod);
            $mform->addElement('static', 'el2', get_string('editdetailsactive', 'totara_certification'),
                            $parts[0] . ' ' . mb_strtolower(get_string($parts[1].'s', 'totara_certification'), 'UTF-8'));
            $parts = explode(' ', $certification->windowperiod);
            $mform->addElement('static', 'el3', get_string('editdetailswindow', 'totara_certification'),
                            $parts[0] . ' ' . mb_strtolower(get_string($parts[1].'s', 'totara_certification'), 'UTF-8'));
            $mform->addElement('static', 'el4', get_string('editdetailsrcopt', 'totara_certification'),
                            $CERTIFRECERT[$certification->recertifydatetype]);
        }

        // Check capabilities
        if (has_capability('totara/certification:configurecertification', $program->get_context())) {
            $button = $OUTPUT->single_button(new moodle_url($this->_form->getAttribute('action'),
                 array('id' => $program->id)), get_string('editcertification', 'totara_certification'), 'get');
            $mform->addElement('static', 'certificationbutton', '', $button);
        }
    }
}

// Define a form class to display the program messages
class program_delete_form extends moodleform {

    function definition() {
        global $CFG, $USER;

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', 'delete');
        $mform->setType('action', PARAM_TEXT);

// form definition
//--------------------------------------------------------------------------------
        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'delete', get_string('deleteprogrambutton', 'totara_program'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

    }

}
