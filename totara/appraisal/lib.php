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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_appraisal
 */
require_once($CFG->dirroot.'/totara/core/lib.php');
require_once($CFG->dirroot.'/totara/question/lib.php');
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');
require_once($CFG->dirroot.'/totara/job/classes/job_assignment.php');
require_once($CFG->dirroot.'/totara/appraisal/lib/assign/lib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/totara/message/messagelib.php');

use totara_job\job_assignment;


class appraisal {
    /**
     * Appraisal Roles
     */
    const ROLE_LEARNER = 1;
    const ROLE_MANAGER = 2;
    const ROLE_TEAM_LEAD = 4;
    const ROLE_APPRAISER = 8;
    const ROLE_ADMINISTRATOR = 16; // Reserved. Not used for now.

    /**
     * Appraisal Access modifiers for roles
     */
    const ACCESS_CANVIEWOTHER = 1;
    const ACCESS_CANANSWER = 2;
    const ACCESS_MUSTANSWER = 6; // Includes appraisal::ACCESS_CANANSWER.

    /**
     * Appraisal status
     */
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_CLOSED = 2;
    const STATUS_COMPLETED = 3;

    /**
     * Appraisal id
     *
     * @var int
     */
    protected $id = 0;

    /**
     * Status
     *
     * @var int
     */
    private $status = self::STATUS_DRAFT;

    /**
     * Appraisal activatied at timestamp
     *
     * @var int
     */
    protected $timestarted = null;

    /**
     * Appraisal closed or completed at timestamp
     *
     * @var int
     */
    protected $timefinished = null;

    /**
     * Appraisal name
     *
     * @var string
     */
    public $name = '';

    /**
     * Appraisal description
     *
     * @var string
     */
    public $description = '';

    /**
     * Create instance of appraisal
     *
     * @param int $id
     */
    public function __construct($id = 0) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }


    /**
     * Allow read access to restricted properties
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (in_array($name, array('id', 'status', 'timestarted', 'timefinished'))) {
            return $this->$name;
        }
    }


    /**
     * Set appraisal properties
     *
     * @param stdClass $todb
     * @return $this
     */
    public function set(stdClass $todb) {
        if (isset($todb->name)) {
            $this->name = $todb->name;
        }
        if (isset($todb->description)) {
            $this->description = $todb->description;
        }
        return $this;
    }


    /**
     * Get stdClass with appraisal properties
     *
     * @return stdClass
     */
    public function get() {
        $obj = new stdClass();
        $obj->name = $this->name;
        $obj->description = $this->description;
        $obj->timestarted = $this->timestarted;
        $obj->timefinished = $this->timefinished;
        $obj->status = $this->status;
        $obj->id = $this->id;

        return $obj;
    }


    /**
     * Saves current appraisal properties
     *
     * @param bool $override update the record even if it is not draft (allows changing status)
     * @return $this
     */
    public function save($override = false) {
        global $DB;

        $todb = $this->get();

        if ($this->id > 0) {
            if (!($override || self::is_draft($todb->id))) {
                throw new appraisal_exception('Cannot make changes to active appraisal');
            }

            $todb->id = $this->id;
            $DB->update_record('appraisal', $todb);

            \totara_appraisal\event\appraisal_updated::create_from_instance($this)->trigger();
        } else {
            $this->id = $DB->insert_record('appraisal', $todb);

            \totara_appraisal\event\appraisal_created::create_from_instance($this)->trigger();
        }
        // Refresh data.
        $this->load($this->id);
        return $this;
    }


    /**
     * Reload appraisal properties from DB
     *
     * @return $this
     */
    public function load() {
        global $DB;
        $appraisal = $DB->get_record('appraisal', array('id' => $this->id));
        if (!$appraisal) {
            throw new appraisal_exception('Cannot load appraisal', 1);
        }
        $this->name = $appraisal->name;
        $this->description = $appraisal->description;
        $this->status = $appraisal->status;
        $this->timestarted = $appraisal->timestarted;
        $this->timefinished = $appraisal->timefinished;
        return $this;
    }


    /**
     * Set current status of appraisal
     *
     * @param int $status appraisal::STATUS_*
     */
    public function set_status($newstatus) {
        $allowedstatus = array(
            self::STATUS_ACTIVE => array(self::STATUS_CLOSED),
            self::STATUS_CLOSED => array(self::STATUS_ACTIVE),
            self::STATUS_DRAFT => array(self::STATUS_ACTIVE),
            self::STATUS_COMPLETED => array()
        );
        if (!in_array($newstatus, $allowedstatus[$this->status])) {
            $a = new stdClass();
            $a->oldstatus = self::display_status($this->status);
            $a->newstatus = self::display_status($newstatus);
            throw new appraisal_exception(get_string('error:cannotchangestatus', 'totara_appraisal', $a));
        } else {
            $this->status = $newstatus;
            if ($newstatus == self::STATUS_CLOSED) {
                $this->timefinished = time();
            } else if ($newstatus == self::STATUS_ACTIVE) {
                $this->timefinished = null;
            }
            $this->save(true);
        }
    }


    /**
     * Activate or reactivate appraisal
     * This function doesn't check if appraisal is valid
     *
     * @param int $time set time of activation (default: current server time). This is only indication, activation is instant.
     */
    public function activate($time = 0) {
        global $DB;

        // Big appraisals with lots of questions can take a long time to activate.
        set_time_limit(0);

        if (!self::is_draft($this->id)) {
            throw new appraisal_exception('Cannot make changes to active appraisal');
        }

        $assign = new totara_assign_appraisal('appraisal', $this);
        $assign->store_user_assignments();
        $assign->store_job_assignments();
        $assign->store_role_assignments();

        $this->create_answers_table();
        $this->activate_questions();

        if (!$this->timestarted) {
            $time = ($time > 0) ? $time : time();
            $this->timestarted = $time;
        }

        // Set all users active stage id to first stage.
        $stages = appraisal_stage::get_stages($this->id);
        $DB->set_field('appraisal_user_assignment', 'activestageid', reset($stages)->id, array('appraisalid' => $this->id));

        // Set the schedules for all relevant messages.
        $sql = "SELECT id FROM {appraisal_event} WHERE triggered = 0 AND event IN (:act, :due) AND appraisalid = :aid";
        $params = array(
            'act' => appraisal_message::EVENT_APPRAISAL_ACTIVATION,
            'due' => appraisal_message::EVENT_STAGE_DUE,
            'aid' => $this->id
        );
        $events = $DB->get_records_sql($sql, $params);
        foreach ($events as $id => $eventdata) {
            $eventmessage = new appraisal_message($id);
            $eventmessage->schedule($eventmessage->get_schedule_from($time));
            $eventmessage->save();
        }

        $this->set_status(self::STATUS_ACTIVE);
        $event = \totara_appraisal\event\appraisal_activation::create(
            array(
                'objectid' => $this->id,
                'context' => context_system::instance(),
                'other' => array(
                    'time' => time(),
                )
            )
        );
        $event->trigger();
    }


    /**
     * Check if it is possible to activate appraisal
     *
     * @param int $time Estimate if appraisal is valid on given time (by default: current server time)
     * @return array with errors / empty if no errors
     */
    public function validate($time = null) {
        global $CFG;

        if (is_null($time)) {
            $time = time();
        }
        $err = array(); // Errors.
        $war = array(); // Warnings.

        // Check that the current status is draft.
        if ($this->status != self::STATUS_DRAFT) {
            $err['status'] = get_string('appraisalinvalid:status', 'totara_appraisal');
        }

        // Check that at least one role can answer at least on one question (this implies check on existance of page and stage).
        $rolescananswer = $this->get_roles_involved(self::ACCESS_CANANSWER);
        if (empty($rolescananswer)) {
            $err['roles'] = get_string('appraisalinvalid:roles', 'totara_appraisal');
        }

        // With the new job assignments feature, appraisals have to be activated
        // before any managers or appraisers can be assigned. Thus, an appraisee
        // has to first see the appraisal and select a job assignment to go with
        // it. Only then can the system infer manager(s) for the appraisal. So
        // unlike the previous versions, validation here is reduced to checking
        // for appraisees.
        $assignment = new totara_assign_appraisal('appraisal', $this);
        if ($assignment->get_current_users_count() < 1) {
            $key = 'learners';
            $errdesc = get_string('appraisalinvalid:learners', 'totara_appraisal');
            if ($CFG->dynamicappraisals) {
                $war[$key] = $errdesc;
            }
            else {
                $err[$key] = $errdesc;
            }
        }

        // Check that all stages are valid.
        $stages = appraisal_stage::fetch_appraisal($this->id);
        $timesdue = array();
        foreach ($stages as $stage) {
            $checkstage = new appraisal_stage($stage->id);
            $err += $checkstage->validate($time);
            if ($checkstage->timedue && in_array($checkstage->timedue, $timesdue)) {
                $err['timedue'] = get_string('appraisalinvalid:stagesamedue', 'totara_appraisal');
            }
            $timesdue[] = $checkstage->timedue;
        }

        return array($err, $war);
    }

    /**
     * Compare existing user assignments against group assignments to see if there have been changes
     *
     * @param $userassignments recordset    -   data from get_current_users()
     * @param $groupassignments recordset   -   data from get_current_users()
     * @return boolean                      -   true if there are differences
     */
    public function compare_assignments($userassignments, $groupassignments) {

        // Expecting a recordset so we need to loop through to convert.
        $ualist = array();
        foreach ($userassignments as $ua) {
            $ualist[$ua->id] = $ua->id;
        }

        // Expecting a recordset so we need to loop through to convert.
        $galist = array();
        foreach ($groupassignments as $ga) {
            $galist[$ga->id] = $ga->id;
        }

        if (array_diff($ualist, $galist)) {
            return true;
        }

        if (array_diff($galist, $ualist)) {
            return true;
        }

        return false;
    }

    /**
     * Check for potential problems with role assignments used on activation and
     * updating of user assignments, and the warnings page.
     *
     * @param boolean $live DEPRECATED since 9.0 (Previously: a flag to switch between live data and groups data)
     * @return array $war array of warning strings to combine with any existing
     *         warnings
     */
    public function validate_roles($live = null) {

        if (isset($live)) {
            debugging('The $live parameter in appraisal::validate_roles has been deprecated since 9.0. Behavior is now to compare required appraisal roles against corresponding roles in appraisees\' job assignments.',
                DEBUG_DEVELOPER);
        }

        $assign = new totara_assign_appraisal('appraisal', $this);
        $missing = $assign->missing_role_assignments();
        if ($missing->appraiseecount === 0) {
            $err = get_string('appraisalinvalid:learners', 'totara_appraisal');
            return ['learners' => $err];
        }

        // Due to the potentially large amounts of missing roles, only the first
        // few missing appraisee roles should be displayed.
        $shownlimit = 10;
        $allmissing = $missing->roles;
        $shown = array_slice($allmissing, 0, $shownlimit, true);

        $fullnames = [];
        foreach (user_get_users_by_id(array_keys($shown)) as $user) {
            $fullnames[$user->id] = fullname($user);
        }

        $war = [];
        $roledesc = $this->get_roles();
        foreach ($shown as $appraiseeid => $roles) {
            $csv = array_reduce(
                $roles,

                function ($accumulated, $role) use ($roledesc) {
                    $desc = get_string($roledesc[$role], 'totara_appraisal');
                    return empty($accumulated) ? $desc : "$accumulated, $desc";
                },

                ''
            );

            $param = new stdClass();
            $param->user = $fullnames[$appraiseeid];
            $param->role = $csv;
            if (in_array($appraiseeid, $missing->nojobselected)) {
                $translationkey = 'appraisalinvalid:missingjob';
            } else {
                $translationkey = 'appraisalinvalid:missingrole';
            }
            $error = get_string(
                $translationkey, 'totara_appraisal', $param
            );

            $count = count($war);
            $war["missingroles$count"] = $error;
        }

        $excess = count($allmissing) - count($shown);
        if ($excess > 0) {
            $err = get_string('xmoremissingroles', 'totara_appraisal', $excess);
            $war['missingrolesmore'] = $err;
        }

        return $war;
    }

    /**
     * Update an appraisee's role assignments for a live appraisal. This is done
     * when the appraisee selects a job assignment in an appraisal.
     *
     * @param int $appraiseeid appraisee id
     */
    public function assignment_changed_for_appraisee($appraiseeid) {
        $assignment = new totara_assign_appraisal('appraisal', $this);
        $assignment->store_role_assignment_for_appraisee($appraiseeid);
    }

    /**
     * Check if automatic job assignment can be done.
     *
     * @return bool
     */
    public static function can_auto_link_job_assignments(): bool {
        global $CFG;
        // If multiple job assignments are allowed, auto-linking should not happen.
        return empty($CFG->totara_job_allowmultiplejobs);
    }

    /**
     * Update user and role assignments for a live appraisal.
     * Used via the cron and appraisals learners tab
     */
    public function check_assignment_changes() {
        global $CFG, $DB;

        // Skip if dynamic appraisals is not activated.
        if (empty($CFG->dynamicappraisals)) {
            return;
        }

        $assign = new totara_assign_appraisal('appraisal', $this);

        // Remember new appraisee ids for potential job assignment linking below.
        // Also need to get new appraisees so that activation notifications can
        // be sent out.
        $linkjobappraiseeids = [];
        $appraiseestonotify = [];

        $added = $assign->get_unstored_users();
        if ($added->valid()) {
            foreach ($added as $newappraisee) {
                $appraiseestonotify[] = $newappraisee->id;

                if (self::can_auto_link_job_assignments()) {
                    $linkjobappraiseeids[] = $newappraisee->id;
                }
            }
        }
        $added->close();

        // Create user assignments and role assignments for new users.
        $added = $assign->get_unstored_users();
        if ($added->valid()) {
            $assign->store_user_assignments($added);

            // Set active stage for all new users.
            $stages = appraisal_stage::get_stages($this->id);
            $DB->set_field('appraisal_user_assignment', 'activestageid', reset($stages)->id, array('appraisalid' => $this->id, 'activestageid' => null));
        }

        // Link job assignments if required.
        $assign->store_job_assignments($linkjobappraiseeids);

        // Find old user assignments that need to be reactivated.
        list($groupjoinsql, $groupparams, $groupalias) = $assign->get_users_from_groups_sql('u', 'id');

        // If old user previously completed the appraisal, timecompleted will be not null.
        // Restore these to status COMPLETED.
        $sql = "UPDATE {appraisal_user_assignment}
                   SET status = ?
                 WHERE status = ?
                   AND appraisalid = ?
                   AND timecompleted IS NOT NULL
                   AND userid IN (
                       SELECT u.id
                         FROM {user} u
                       " . $groupjoinsql . "
                   )";
        $params = array_merge(array(self::STATUS_COMPLETED, self::STATUS_CLOSED, $this->id), $groupparams);
        $DB->execute($sql, $params);

        $sql = "UPDATE {appraisal_user_assignment}
                   SET status = ?
                 WHERE status = ?
                   AND appraisalid = ?
                   AND userid IN (
                       SELECT u.id
                         FROM {user} u
                       " . $groupjoinsql . "
                   )";
        $params = array_merge(array(self::STATUS_ACTIVE, self::STATUS_CLOSED, $this->id), $groupparams);
        $DB->execute($sql, $params);

        // Find removed learners.
        $removed = $assign->get_removed_users();
        // Close user assignment for each user.
        if ($removed->valid()) {
            foreach ($removed as $user) {
                $userassignment = new appraisal_user_assignment($user->userassignmentid);

                $userassignment->close();
            }
        }

        // Role (re)assignments.
        $removedroleassignments = $assign->store_role_assignments();

        // If roles were removed we need to check stage completion for affected user_assignments
        if (!empty ($removedroleassignments)) {
            // Get distinct user_assignment ids with removed roles
            $userassignmentids = array();
            foreach ($removedroleassignments as $removedroleassignment) {
                if (!in_array($removedroleassignment->appraisaluserassignmentid, $userassignmentids)) {
                    $userassignmentids[] = $removedroleassignment->appraisaluserassignmentid;
                }
            }

            foreach ($userassignmentids as $userassignmentid) {
                // If all remaining role_assignments for this stage have completed the stage,
                // we can mark the stage complete
                $userassignment = new appraisal_user_assignment($userassignmentid);
                if (!isset($userassignment->timecompleted)) {
                    $allcompleted = true;

                    $stages = appraisal_stage::get_stages($userassignment->appraisalid);
                    // This return stages in completion order
                    // We may need to know the id of the next stage

                    $stageids = array_keys($stages);
                    $nxtstage = array_shift($stageids);

                    foreach ($stages as $stage) {
                        $nxtstage = array_shift($stageids);

                        // Check whether there are role_assignments for which there are no appraisal_stage_data
                        $sql = "SELECT ara.id
                                  FROM {appraisal_role_assignment} ara
                             LEFT JOIN {appraisal_stage_data} asd ON ara.id = asd.appraisalroleassignmentid and asd.appraisalstageid = :stageid
                                 WHERE ara.appraisaluserassignmentid = :userassignmentid
                                   AND ara.userid != 0
                                   AND asd.id IS NULL";
                        $params = array('stageid' => $stage->id, 'userassignmentid' => $userassignmentid);
                        if ($DB->record_exists_sql($sql, $params)) {
                            // We do not need to check following stages - we already know all were not completed
                            $allcompleted = false;
                            break;
                        }
                        else {
                            // If this user's active stage is now completed we need to move this forward to the next stage
                            if ($userassignment->activestageid == $stage->id && !is_null($nxtstage)) {
                                $sql = "UPDATE {appraisal_user_assignment}
                                           SET activestageid = :newstageid
                                         WHERE id = :userassignmentid";
                                $params = array('newstageid' => $nxtstage, 'userassignmentid' => $userassignmentid);
                                $DB->execute($sql, $params);

                                // We now know that there are uncompleted stages - so we can stop that loop
                                $allcompleted = false;
                                break;
                            }
                        }
                    }

                    if ($allcompleted) {
                        // Update user
                        $sql = "UPDATE {appraisal_user_assignment}
                                   SET timecompleted = :timecompleted,
                                       status = :statuscompleted
                                 WHERE id = :userassignmentid";
                        $params = array('timecompleted' => time(),
                                        'statuscompleted' => self::STATUS_COMPLETED,
                                        'userassignmentid' => $userassignmentid);
                        $DB->execute($sql, $params);
                    }
                }
            }
        }

        // The activate() method sends out activation notifications for learners
        // that were added *before* the appraisal was activated. However learners
        // that were added after activation also have to be notified; that needs
        // to be done here.
        if (!empty($appraiseestonotify)) {
            $filters = [
                'event' => appraisal_message::EVENT_APPRAISAL_ACTIVATION,
                'appraisalid' => $this->id
            ];
            $event = $DB->get_record("appraisal_event", $filters);

            if ($event) {
                $msg = new appraisal_message($event->id);
                $msg->event_appraisal($this->id);

                foreach ($appraiseestonotify as $appraisee) {
                    $msg->send_user_specific_message($appraisee);
                }
            }
        }
    }

    /**
     * Get the appraisal expected completed date, (the due date of the last stage).
     *
     * @return int timestamp
     */
    public function get_expected_completion_date() {
        global $DB;

        $sql = "SELECT MAX(timedue) AS due_date
                  FROM {appraisal_stage}
                 WHERE appraisalid = :appraisalid";
        $params = array('appraisalid' => $this->id);
        $record = $DB->get_record_sql($sql, $params);

        return $record->due_date;
    }

    /**
     * Close the appraisal.
     * Will send alerts to affected users if required.
     *
     * @param object $formdata
     */
    public function close($formdata = null) {
        global $DB;

        if (isset($formdata->sendalert) && $formdata->sendalert) {
            // Set up a closure message to send on the next cron run.
            // Note: There is a custom send function for closure messages, which doesn't use a lot of this data.
            $msg = new appraisal_message();
            $msg->event_appraisal($formdata->id, appraisal_message::EVENT_APPRAISAL_CLOSURE);
            $msg->set_delta(0, 1); // O days.
            $msg->set_roles(array(1, 2, 4, 8), appraisal_message::MESSAGE_SEND_ONLY_INCOMPLETE);
            $msg->set_message(0, $formdata->alerttitle, $formdata->alertbody_editor['text']);
            $msg->schedule($msg->get_schedule_from(time()));
            $msg->save();
        }

        // Mark the status as closed for the appraisal.
        $this->set_status(self::STATUS_CLOSED);

        // Mark the status as closed for all user assignments.
        $sql = "UPDATE {appraisal_user_assignment} SET status = ? WHERE status = ? AND appraisalid = ?";
        $DB->execute($sql, array(self::STATUS_CLOSED, self::STATUS_ACTIVE, $this->id));
    }


    /**
     * Save answers on appraisal.
     *
     * @param stdClass $formdata
     * @param appraisal_role_assignment $roleassignment
     * @param bool $updateprogress false if we just want to save the data without trying to update the progress.
     * @return bool true if answers accepted
     */
    public function save_answers(stdClass $formdata, appraisal_role_assignment $roleassignment, $updateprogress = true) {
        global $DB;
        $pageid = $formdata->pageid;
        $page = new appraisal_page($pageid);
        $stage = new appraisal_stage($page->appraisalstageid);

        if (!$roleassignment) {
            return false;
        }

        // Get data to save.
        if ($stage->is_locked($roleassignment)) {
            return false;
        }
        $answers = $page->export_answers($formdata, $roleassignment);
        // Save.
        $questdata = $DB->get_record('appraisal_quest_data_'.$this->id, array('appraisalroleassignmentid' => $roleassignment->id));
        if (!$questdata) {
            $answers->appraisalroleassignmentid = $roleassignment->id;
            $DB->insert_record('appraisal_quest_data_'.$this->id, $answers);
        } else {
            $answers_array = get_object_vars($answers); // Create an array so we can check if its empty.
            if (!empty($answers_array)) {
                $answers->id = $questdata->id;
                // This db call fails if there are no answers found (page with only info, no user input).
                $DB->update_record('appraisal_quest_data_'.$this->id, $answers);
            }
        }

        if ($updateprogress) {
            // Check if page is valid and user wants to go to next page.
            if (($formdata->submitaction == 'next') || ($formdata->submitaction == 'completestage')) {
                // Mark the page as complete for the given role.
                $roleisfinishedstage = $page->complete_for_role($roleassignment);
            }

            // Check if user wants to complete the stage.
            if (($formdata->submitaction == 'completestage') && $roleisfinishedstage) {
                // Mark the stage as complete for the given role.
                $stage->complete_for_role($roleassignment);
            }
        }
        // Refresh appraisal properties.
        $this->load();
        return true;
    }


    /**
     * Get exisitng answers for current appraisal role assignment
     * @param stdClass $roleassignment
     * @return stdClass
     */
    public function get_answers($pageid, appraisal_role_assignment $roleassignment) {
        global $DB;
        $questdata = $DB->get_record('appraisal_quest_data_'.$this->id, array('appraisalroleassignmentid' => $roleassignment->id));
        if ($questdata) {
            return $this->import_answers($pageid, $questdata, $roleassignment);
        }
        return null;
    }


    /**
     * Import answer
     * @param stdClass $questdata
     * @param stdClass $roleassignment
     * @return stdClass
     */
    public function import_answers($pageid, stdClass $questdata, appraisal_role_assignment $roleassignment) {
        global $CFG;

        $records = appraisal_question::fetch_page($pageid);
        $answers = new stdClass();
        foreach ($records as $record) {
            $question = new appraisal_question($record->id, $roleassignment);
            if ($question->get_element()->get_type() == 'redisplay') {
                $question = new appraisal_question($question->get_element()->param1, $roleassignment);
            }

            if ($question->get_element()->get_type() == "goals") {
                // If a learner's reporting hierarchy changes after a dynamic
                // appraisal is completed, the new reporting hierarchy sees the
                // the completed appraisal and the old hierarchy cannot see it.
                // For static appraisals, the new reporting hierarchy will not
                // be able to see any appraisal - whether active or not. But the
                // appraisals will be visible to the old hierarchy and the goals
                // module must then check against the old hierarchy with respect
                // to access rights.
                if (!$CFG->dynamicappraisals) {
                    $finder = function ($subjectid) {
                        $managerids = [];
                        $teamleaderids = [];
                        $appraiserids = [];

                        $roles = $this->get_user_roles_involved(0, $subjectid);
                        foreach ($roles as $role) {
                            switch ($role->appraisalrole) {
                                case self::ROLE_MANAGER:
                                    $managerids[] = $role->userid;
                                    break;

                                case self::ROLE_TEAM_LEAD:
                                    $teamleaderids[] = $role->userid;
                                    break;

                                case self::ROLE_APPRAISER:
                                    $appraiserids[] = $role->userid;
                                    break;
                            }
                        }

                        return [$managerids, $teamleaderids, $appraiserids];
                    };

                    $question->get_element()->set_reporting_hierarchy_finder($finder);
                }
            }
            $answers = $question->get_element()->set_as_db($questdata)->get_as_form($answers, true);
        }
        return $answers;
    }


    /**
     * Is appraisal locked for data entry.
     *
     * @param object $userassignment
     * @return bool
     */
    public function is_locked($assignment = null) {
        // We don't check for STATUS_DRAFT because we don't want it locked while previewing.
        if (($this->status == self::STATUS_CLOSED) || ($this->status == self::STATUS_COMPLETED)) {
            return true;
        }

        if (isset($assignment)) {
            if ($assignment instanceof appraisal_role_assignment) {
                $userassignment = $assignment->get_user_assignment();
            } else if ($assignment instanceof appraisal_user_assignment) {
                $userassignment = $assignment;
            } else {
                throw new appraisal_exception('Wrong assignment class');
            }
        }

        // Is appraisal locked for the user?
        if (isset($userassignment) && $userassignment->is_closed()) {
            return true;
        }

        return isset($userassignment) && $userassignment->timecompleted > 0;
    }


    /**
     * Mark the appraisal as complete for the given user.
     *
     * @param int $subjectid
     */
    public function complete_for_user($subjectid) {
        global $DB;

        // Mark the user as complete for this appraisal.
        $DB->set_field('appraisal_user_assignment', 'timecompleted', time(),
                array('userid' => $subjectid, 'appraisalid' => $this->id));

        // Mark the user as complete for this appraisal.
        $DB->set_field('appraisal_user_assignment', 'status', self::STATUS_COMPLETED,
                array('userid' => $subjectid, 'appraisalid' => $this->id));
    }

    /**
     * Checks if the role user has permission to view this appraisal for this subject.
     */
    public function can_access($roleassignment) {
        // View staff appraisals capability can be extended here.
        return (bool)$roleassignment;
    }

    /**
     * Get appraisal role assignments for assigned subject user.
     * If previewing then create a template role assignment objects.
     * If not previewing then the records must exist.
     *
     * @param int $subjectid
     * @param bool $preview
     * @return array as role => stdClass role assignment record
     */
    public function get_all_assignments($subjectid, $preview = false) {
        global $USER, $DB;

        $assignments = array();
        if ($preview) {
            $roles = self::get_roles();
            foreach ($roles as $role => $name) {
                $assignments[$role] = appraisal_role_assignment::get_role($this->id, $subjectid, $USER->id, $role, $preview);
            }
        } else {
            $sql = 'SELECT ara.id, ara.appraisalrole
                    FROM {appraisal_role_assignment} ara
                    JOIN {appraisal_user_assignment} aua
                      ON ara.appraisaluserassignmentid = aua.id
                   WHERE aua.appraisalid = ?
                     AND aua.userid = ?';
            $records = $DB->get_records_sql($sql, array($this->id, $subjectid));
            foreach ($records as $data) {
                $assignments[$data->appraisalrole] = new appraisal_role_assignment($data->id);
            }
        }
        return $assignments;
    }


    public function count_incomplete_userassignments() {
        global $DB;

        $params = array('appraisalid' => $this->id, 'timecompleted' => null, 'status' => self::STATUS_ACTIVE);
        return $DB->count_records('appraisal_user_assignment', $params);
    }

    /**
     * Return array of roles involved in current appraisal
     *
     * @param int $rights count only roles that have certain rights
     * @return array of appraisalrole
     */
    public function get_roles_involved($rights = 0) {
        global $DB;

        $sqlrights ='';
        $params = array($this->id);
        if ($rights > 0) {
            $sqlrights = ' AND (aqfr.rights & ? ) = ? ';
            $params[] = $rights;
            $params[] = $rights;
        }
        $sql = "SELECT DISTINCT aqfr.appraisalrole
                  FROM {appraisal_stage} ast
                  LEFT JOIN {appraisal_stage_page} asp
                    ON asp.appraisalstageid = ast.id
                  LEFT JOIN {appraisal_quest_field} aqf
                    ON aqf.appraisalstagepageid = asp.id
                 INNER JOIN {appraisal_quest_field_role} aqfr
                    ON aqfr.appraisalquestfieldid = aqf.id AND aqfr.rights > 0
                 WHERE ast.appraisalid = ? {$sqlrights}
                 ORDER BY aqfr.appraisalrole";
        $rolesrecords = $DB->get_records_sql($sql, $params);

        $out = array();
        foreach ($rolesrecords as $rolerecord) {
            $out[$rolerecord->appraisalrole] = 1;
        }

        return array_keys($out);
    }

    /**
     * Get array of roles involved in the current appraisal for the given user, linking to the users assigned in each role.
     *
     * @param appraisal::ACCESS_XXX $rights only roles that have the given rights
     * @param int $subjectid the userid in the userassignment
     * @return array(appraisalrole => userid)
     */
    public function get_user_roles_involved($rights, $subjectid) {
        global $DB;

        $roles = $this->get_roles_involved($rights);
        if (empty($roles)) {
            return array();
        }
        list($insql, $inparam) = $DB->get_in_or_equal($roles);
        $missingsql = "SELECT ara.appraisalrole, ara.userid
                         FROM {appraisal_role_assignment} ara
                         JOIN {appraisal_user_assignment} aua
                           ON ara.appraisaluserassignmentid = aua.id
                        WHERE aua.userid = ?
                          AND aua.appraisalid = ?
                          AND appraisalrole {$insql}";
        $missingparams = array_merge(array($subjectid, $this->id), $inparam);
        return $DB->get_records_sql($missingsql, $missingparams);
    }


    /**
     * Create answers table
     */
    private function create_answers_table() {
        global $DB;

        if ($this->id < 1) {
            throw new appraisal_exception('Appraisal must be saved before creating answers table', 4);
        }

        $tablename = 'appraisal_quest_data_'.$this->id;
        $table = new xmldb_table($tablename);

        // Appraisal specific fields/keys/indexes.
        $xmldb = array();
        $xmldb[] = new xmldb_field('id', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $xmldb[] = new xmldb_field('timecompleted', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, null, null, 0);
        $xmldb[] = new xmldb_field('appraisalroleassignmentid', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        // Appraisal keys.
        $xmldb[] = new xmldb_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $xmldb[] = new xmldb_key('apprquestdata_approlass'.$this->id.'_fk', XMLDB_KEY_FOREIGN, array('appraisalroleassignmentid'),
            'appraisal_role_assignment', array('id'));

        // Question specific fields/keys/indexes.
        $questions = appraisal_question::fetch_appraisal($this->id, null, null, array(), true);
        $questionman = new question_manager();
        $questxmldb = $questionman->get_xmldb($questions);
        $allfields = array_merge($xmldb, $questxmldb);

        foreach ($allfields as $field) {
            if ($field instanceof xmldb_field) {
                $table->addField($field);
            } else if ($field instanceof xmldb_key) {
                $table->addKey($field);
            } else if ($field instanceof xmldb_index) {
                $table->addIndex($field);
            }
        }

        $dbman = $DB->get_manager();
        $dbman->create_table($table);
    }


    /**
     * Activate questions in case there is something that the question needs to do during activation.
     */
    private function activate_questions() {
        $allquestions = appraisal_question::fetch_appraisal($this->id);

        foreach ($allquestions as $questionrecord) {
            $question = new appraisal_question($questionrecord->id);
            $question->get_element()->activate();
        }
    }


    /**
     * Save snapshot to the file system
     *
     * @param string $filepath snapshot /path/filename.pdf
     * @param int $roleassignmentid snapshot role assignment id
     * @param int $time timestamp
     */
    public function save_snapshot($filepath, $roleassignmentid, $time = 0) {
        global $USER;
        if (!$time) {
            $time = time();
        }
        // Put file into storage.
        $context = context_system::instance();
        $fs = get_file_storage();
        $filename = basename($filepath);

        $meta = array('contextid' => $context->id, 'component' => 'totara_appraisal', 'filearea' => 'snapshot_'.$this->id,
            'itemid' => $roleassignmentid, 'filepath' => '/', 'filename' => $filename, 'timecreated' => $time,
            'userid' => $USER->id);
        $file = $fs->create_file_from_pathname($meta, $filepath);

        return moodle_url::make_pluginfile_url($context->id, 'totara_appraisal', 'snapshot_'.$this->id, $roleassignmentid, '/',
                    $file->get_filename(), true);
    }

    /**
     * Get list of all snapshots
     *
     * @param int $appraisalid
     * @param int $roleassignmentid snapshot role assignment id
     * @return array of stdClass snapshotdata
     */
    public static function list_snapshots($appraisalid, $roleassignmentid) {
        $roleassignmentid = ($roleassignmentid) ? $roleassignmentid : false;

        $fs = get_file_storage();
        $context = context_system::instance();

        $files = $fs->get_area_files($context->id, 'totara_appraisal', 'snapshot_'.$appraisalid, $roleassignmentid, 'timecreated',
                false);

        $snapshots = array();
        foreach ($files as $file) {
            $snapshot = new stdClass();
            $snapshot->url = moodle_url::make_pluginfile_url($context->id, 'totara_appraisal', 'snapshot_'.$appraisalid,
                    $roleassignmentid, '/', $file->get_filename(), true);
            $snapshot->filename = $file->get_filename();
            $snapshot->time = userdate($file->get_timecreated(), get_string('strftimedatetimeshort', 'langconfig'));
            $snapshots[] = $snapshot;

        }
        return $snapshots;
    }
    /**
     * Delete all snapshots related to appraisal
     *
     * @param int $id
     * @return stdClass
     */
    public function delete_all_snapshots() {
        $context = context_system::instance();
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'totara_appraisal', 'snapshot_'.$this->id);
    }

    /**
     * Delete the whole appraisal
     */
    public function delete() {
        global $DB, $TEXTAREA_OPTIONS;

        $snapshot = $this->get();

        // Set status to draft so that is_locked will return false and thus allow deletion.
        // We don't use appraisal->set_status() because it normally doesn't allow changing to draft status.
        if ($this->status !=  self::STATUS_DRAFT) {
            $this->status = self::STATUS_DRAFT;
            $this->save(true);
        }

        // Remove question data table.
        sql_drop_table_if_exists('{appraisal_quest_data_' . $this->id . '}');

        // Remove assignments.
        $assign = new totara_assign_appraisal('appraisal', $this);
        $assign->delete();

        // Remove all stages.
        $stages = appraisal_stage::fetch_appraisal($this->id, true);
        foreach ($stages as $stage) {
            $stage->delete();
        }

        // Remove event messages.
        appraisal_message::delete_appraisal($this->id);

        // Remove snapshots.
        $this->delete_all_snapshots();

        // Remove files.
        $fs = get_file_storage();
        $fs->delete_area_files($TEXTAREA_OPTIONS['context']->id, 'totara_appraisal', 'appraisal', $this->id);

        // Remove appraisal.
        $DB->delete_records('appraisal', array('id' => $this->id));
        $this->id = null;

        \totara_appraisal\event\appraisal_deleted::create_from_instance($snapshot)->trigger();
    }

    /**
     * Get the status of the appraisal.
     *
     * @param mixed $appraisal  - appraisal.id or instance of appraisal
     * @return int              - self::STATUS_DRAFT etc
     */
    public static function get_status($appraisal) {
        if (is_numeric($appraisal)) {
            $appraisal = new appraisal($appraisal);
        }
        if (!($appraisal instanceof appraisal)) {
            throw new appraisal_exception('Appraisal object not found', 2);
        }

        return $appraisal->status;
    }

    /**
     * Check if appraisal is in draft state
     *
     * @param mixed $appraisal  - appraisal.id or instance of appraisal
     * @return bool
     */
    public static function is_draft($appraisal) {
        return (self::get_status($appraisal) == self::STATUS_DRAFT);
    }

    /**
     * Check if appraisal is in an active state
     *
     * @param mixed $appraisal  - appraisal.id or instance of appraisal
     * @return bool
     */
    public static function is_active($appraisal) {
        return (self::get_status($appraisal) == self::STATUS_ACTIVE);
    }

    /**
     * Check if appraisal has been closed.
     *
     * @param mixed $appraisal  - appraisal.id or instance of appraisal
     * @return bool
     */
    public static function is_closed($appraisal) {
        return (self::get_status($appraisal) == self::STATUS_CLOSED);
    }

    /**
     * Get all individual roles supported by system (except two: 'Administrator' is reserved, 'All' is not a role.)
     *
     * @return array(appraisal::ROLE_* => 'display name', ...)
     */
    public static function get_roles() {
        $roles = array(
            self::ROLE_LEARNER => 'rolelearner',
            self::ROLE_MANAGER => 'rolemanager',
            self::ROLE_TEAM_LEAD => 'roleteamlead',
            self::ROLE_APPRAISER => 'roleappraiser'
            );
        return $roles;
    }

    /**
     * Get status name
     *
     * @param int $status
     * @return string
     */
    public static function display_status($status) {
        $result = '';
        switch ($status) {
            case self::STATUS_ACTIVE:
                $result = get_string('active', 'totara_appraisal');
                break;
            case self::STATUS_DRAFT:
                $result = get_string('draft', 'totara_appraisal');
                break;
            case self::STATUS_CLOSED:
                $result = get_string('closed', 'totara_appraisal');
                break;
            case self::STATUS_COMPLETED:
                $result = get_string('completed', 'totara_appraisal');
                break;
        }
        return $result;
    }


    /**
     * Get all appraisals
     *
     * @param string $fields Fields list
     * @return array of stdClass
     */
    public static function fetch_all($fields = '*') {
        global $DB;
        $appraisals = $DB->get_records('appraisal', null, '', $fields);
        return $appraisals;
    }


    /**
     * Get list of questions that can be redisplayed.
     *
     * @return array of items
     */
    public static function get_redisplay_question_list($moduleinfo) {
        $currentpage = new appraisal_page($moduleinfo->pageid);
        $currentstage = new appraisal_stage($currentpage->appraisalstageid);
        $appraisalid = $currentstage->appraisalid;

        $list = array();
        $disabled = false;

        $stages = appraisal_stage::fetch_appraisal($appraisalid);
        foreach ($stages as $stage) {

            $item = new stdClass();
            $item->id = 0;
            $item->name = $stage->name;
            $item->isheading = true;
            $item->disabled = true;
            $list[] = $item;

            $pages = appraisal_page::fetch_stage($stage->id);
            foreach ($pages as $page) {

                if ($page->id == $currentpage->id) {
                    $disabled = true;
                }

                $questionrecords = appraisal_question::fetch_page($page->id);
                foreach ($questionrecords as $questionrecord) {
                    $question = new appraisal_question($questionrecord->id);
                    $item = new stdClass();
                    $item->id = $question->id;
                    $item->isheading = false;
                    $info = $question->get_element()->get_info();
                    $a = new stdClass();
                    $a->name = $question->get_name();
                    $a->type = $info['type'];
                    $item->name = get_string('questionandtype', 'totara_question', $a);
                    $item->disabled = $disabled || ($question->get_element()->get_type() == 'redisplay');
                    $list[] = $item;
                }
            }
        }

        return $list;
    }

    /**
     * Get list of questions that can be aggregated.
     *
     * @param int $currentpageid The id of the current appraisalstagepage
     * @return array or items
     */
    public static function get_aggregation_question_list($currentpageid) {
        $currentpage = new appraisal_page($currentpageid);
        $currentstage = new appraisal_stage($currentpage->appraisalstageid);
        $appraisalid = $currentstage->appraisalid;
        $aggregatetypes = question_aggregate::available_question_types();

        $list = array();
        $strvar = new \stdClass();
        $stages = appraisal_stage::fetch_appraisal($appraisalid);
        foreach ($stages as $stage) {
            $strvar->stagename = $stage->name;

            $pages = appraisal_page::fetch_stage($stage->id);
            foreach ($pages as $page) {
                $strvar->pagename = $page->name;

                if ($page->id == $currentpage->id) {
                    return $list;
                }

                $questionrecords = appraisal_question::fetch_page($page->id);
                foreach ($questionrecords as $questionrecord) {
                    $question = new appraisal_question($questionrecord->id);
                    if (in_array($question->get_element()->datatype, $aggregatetypes)) {
                        $strvar->questionname = $question->name;
                        $list[$question->id] = get_string('aggregateselectordisplay', 'totara_appraisal', $strvar);
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Retrieve the role information and answers for all questions in the aggregation.
     *
     * @param int $userid           The userid connected to the appraisal_user_assignment.
     * @param int $stagepageid      The appraisalstagepageid connected to the appraisal.
     * @param int $questionid       The id of the question.
     * @param array $paramids       An array of question ids we are aggregating.
     * @param bool $usedefaults     Use default value if question not yet answered (value == null)
     * @return array                of database records.
     */
    public static function get_aggregate_question_answers($userid, $stagepageid, $questionid,
                                $paramids, $usedefaults=false) {

        global $USER, $DB;

        // Check the format of paramids (pararm1 in db). It should be an array with the questions to aggregate.
        if (empty($paramids) || is_null($paramids)) {
            print_error('error:noquestionstoaggregate', 'totara_appraisal');
        }

        // Get the appraisalid from the pageid.
        $appsql = "SELECT a.id
                     FROM {appraisal} a
               INNER JOIN {appraisal_stage} ast
                       ON ast.appraisalid = a.id
               INNER JOIN {appraisal_stage_page} asp
                       ON asp.appraisalstageid = ast.id
                    WHERE asp.id = :aspid";
        $appraisalid = $DB->get_field_sql($appsql, array('aspid' => $stagepageid));

        // Set up some variables needed to get the answer data.
        $anstable = 'appraisal_quest_data_' . $appraisalid;
        $ansfields = '';

        list($insql, $inparams) = $DB->get_in_or_equal($paramids);
        $sql = "SELECT id, datatype, param1, defaultdata
                  FROM {appraisal_quest_field}
                 WHERE id {$insql}";
        $types = $DB->get_records_sql($sql, $inparams);

        // $usedefaults defaults to false.
        // For appraisals activated before this patch, this can not be changed
        // Therefore there is no need to include any upgrade steps to ensure that existing appraisals
        // have default values in the db
        $roleparam = array('qid' => $questionid, 'uid' => $userid, 'aid' => $appraisalid);
        foreach ($types as $id => $qst) {
            if (!empty($ansfields)) {
                $ansfields .= ', ';
            }

            if ($qst->datatype == 'ratingnumeric') {
                $ansfields .= "ans.data_{$qst->id}";
                if ($usedefaults) {
                    if (isset($qst->defaultdata)) {
                        $roleparam["default_{$id}"] =  $qst->defaultdata;
                    } else if (isset($qst->param1)) {
                        $range = json_decode($qst->param1, true);
                        $roleparam["default_{$id}"] = isset($range['rangefrom']) ? $range['rangefrom'] : 0;
                    } else {
                        $roleparam["default_{$id}"] = 0;
                    }
                    $ansfields .= ", coalesce(ans.data_{$qst->id}, :default_{$id}) as data_{$qst->id}_default";
                }
            } else if ($qst->datatype == 'ratingcustom') {
                $ansfields .= "ans.data_{$qst->id}score";
                if ($usedefaults) {
                    $roleparam["default_{$id}"] = isset($qst->defaultdata) ? $qst->defaultdata : 0;
                    $ansfields .= ", coalesce(ans.data_{$qst->id}score, :default_{$id}) as data_{$qst->id}score_default";
                }
            } else {
                print_error('error:invalidquestiontype', 'totara_appraisal');
            }
        }

        // Get the role variables and answers.
        $rolesql = "SELECT ara.id, ara.userid, perm.rights, ara.appraisalrole, {$ansfields}
                      FROM {appraisal_role_assignment} ara
                INNER JOIN {appraisal_user_assignment} aua
                        ON ara.appraisaluserassignmentid = aua.id
                INNER JOIN {appraisal_quest_field_role} perm
                        ON perm.appraisalquestfieldid = :qid
                       AND perm.appraisalrole = ara.appraisalrole
                 LEFT JOIN {{$anstable}} ans
                        ON ans.appraisalroleassignmentid = ara.id
                     WHERE aua.userid = :uid
                       AND aua.appraisalid = :aid
                  ORDER BY ara.appraisalrole";
        $raw_answers = $DB->get_records_sql($rolesql, $roleparam);

        // Check role permissions before returning the answers.
        $answers = array();
        $rights = 0;
        $userrole = 0;
        foreach ($raw_answers as $answer) {
            $answer = (array) $answer;
            $id = array_shift($answer);
            $userid = array_shift($answer);
            $perm = array_shift($answer);
            $role = array_shift($answer);

            if ($userid == $USER->id) {
                $rights = $perm;
                $userrole = $role;
            }
            $answers[$role] = $answer;
        }

        if (($rights & appraisal::ACCESS_CANANSWER) == appraisal::ACCESS_CANANSWER) {
            if (($rights & appraisal::ACCESS_CANVIEWOTHER) == appraisal::ACCESS_CANVIEWOTHER) {
                // Then can view everyones answers, return all the results.
                return $answers;
            } else {
                // They can only view their own answers, only return their results.
                return array($userrole => $answers[$userrole]);
            }
        }

        return array();
    }

    /**
     * Get the element for the specified question, to be used by a redisplay question.
     *
     * @param  array $questionids array of questionids
     * @return array              array of items
     */
    public static function get_aggregate_question_elements($questionids) {
        $allroles = self::get_roles();

        foreach ($questionids as $qid) {
            $question = new appraisal_question($qid);
            $element = $question->get_element();

            foreach ($allroles as $role => $rolename) {
                if (isset($question->roles[$role])) {
                    $rolevalue = $question->roles[$role];
                    $element->roleinfo[$role][self::ACCESS_CANVIEWOTHER] = $rolevalue & self::ACCESS_CANVIEWOTHER;
                    $element->roleinfo[$role][self::ACCESS_CANANSWER] = $rolevalue & self::ACCESS_CANANSWER;
                    $element->roleinfo[$role][self::ACCESS_MUSTANSWER] = $rolevalue & self::ACCESS_MUSTANSWER;
                } else {
                    $element->roleinfo[$role][self::ACCESS_CANVIEWOTHER] = 0;
                    $element->roleinfo[$role][self::ACCESS_CANANSWER] = 0;
                    $element->roleinfo[$role][self::ACCESS_MUSTANSWER] = 0;
                }
            }
            $elements[$qid] = $element;
        }

        return $elements;
    }

    /**
     * Get the element for the specified question, to be used by a redisplay question.
     *
     * @return array of items
     */
    public static function get_redisplay_question_element($questionid) {
        $question = new appraisal_question($questionid);

        $element = $question->get_element();
        $allroles = self::get_roles();
        foreach ($allroles as $role => $rolename) {
            if (isset($question->roles[$role])) {
                $rolevalue = $question->roles[$role];
                $element->roleinfo[$role][self::ACCESS_CANVIEWOTHER] = $rolevalue & self::ACCESS_CANVIEWOTHER;
                $element->roleinfo[$role][self::ACCESS_CANANSWER] = $rolevalue & self::ACCESS_CANANSWER;
                $element->roleinfo[$role][self::ACCESS_MUSTANSWER] = $rolevalue & self::ACCESS_MUSTANSWER;
            } else {
                $element->roleinfo[$role][self::ACCESS_CANVIEWOTHER] = 0;
                $element->roleinfo[$role][self::ACCESS_CANANSWER] = 0;
                $element->roleinfo[$role][self::ACCESS_MUSTANSWER] = 0;
            }
        }

        return $element;
    }

    /**
     * Get all appraisal role assignment ids for the appraisal that the specified role assignment belongs to.
     *
     * @param int $baseroleassignmentid
     * @return array of appraisalroleassignmentids
     */
    public static function get_related_roleassignmentids($baseroleassignmentid) {
        $baseroleassignment = new appraisal_role_assignment($baseroleassignmentid);
        $userassignment = $baseroleassignment->get_user_assignment();
        $appraisal = new appraisal($userassignment->appraisalid);
        $roleassignments = $appraisal->get_all_assignments($userassignment->userid);
        $result = array();
        foreach ($roleassignments as $roleassignment) {
            $result[$roleassignment->id] = $roleassignment->id;
        }
        return($result);
    }

    /**
     *  Function alias for review questions.
     */
    public static function get_related_answerids($baseroleassignmentid) {
        if ($baseroleassignmentid > 0) {
            return(self::get_related_roleassignmentids($baseroleassignmentid));
        } else {
            return array();
        }
    }

    /**
     * Count all appraisals
     *
     * @return int
     */
    public static function count_all() {
        $appraisals = self::fetch_all('count(*) AS cnt');
        return count($appraisals) ? current($appraisals)->cnt : 0;
    }


    /**
     * Check if user is able to see any appraisals for their staff.
     *
     * @param int $userid
     * @param bool $forcereload Do not use static cache
     * @return bool
     */
    public static function can_view_staff_appraisals($userid = null, $forcereload = false) {
        global $USER, $DB;

        if (!isloggedin()) {
            return false;
        }

        if (!$userid) {
            $userid = $USER->id;
        }

        static $cache = array();
        if (!$forcereload && isset($cache[$userid])) {
            return $cache[$userid];
        }

        $sql = "SELECT COUNT(ara.id)
                  FROM {appraisal} app
                  JOIN {appraisal_user_assignment} aua
                    ON aua.appraisalid = app.id
                  JOIN {appraisal_role_assignment} ara
                    ON ara.appraisaluserassignmentid = aua.id
                 WHERE app.status <> ?
                   AND ara.userid = ?";
        $count = $DB->count_records_sql($sql, array(self::STATUS_DRAFT, $userid));

        $cache[$userid] = ($count > 0);
        return $cache[$userid];
    }


    /**
     * Check if user is able to see any appraisals as a learner.
     *
     * @param int $userid
     * @param bool $forcereload Do not use static cache
     * @return bool
     */
    public static function can_view_own_appraisals($userid = null, $forcereload = false) {
        global $USER, $DB;

        if (!isloggedin()) {
            return false;
        }

        if (!$userid) {
            $userid = $USER->id;
        }

        static $cache = array();
        if (!$forcereload && isset($cache[$userid])) {
            return $cache[$userid];
        }

        $sql = "SELECT COUNT(aua.id)
                  FROM {appraisal} app
                  JOIN {appraisal_user_assignment} aua
                    ON aua.appraisalid = app.id
                  JOIN {appraisal_role_assignment} ara
                    ON ara.appraisaluserassignmentid = aua.id
                 WHERE app.status <> ?
                   AND aua.userid = ?
                   AND ara.userid = ?
                   AND ara.appraisalrole = ?";
        $count = $DB->count_records_sql($sql, array(self::STATUS_DRAFT, $userid, $userid, self::ROLE_LEARNER));

        $cache[$userid] = ($count > 0);
        return $cache[$userid];
    }


    /**
     * Get the latest appraisal for the given subject.
     * Note: Prioritises active over inactive.
     *
     * @param int $subjectid
     */
    public static function get_latest($subjectid) {
        global $DB;

        $sql = "SELECT aua.appraisalid, aua.timecompleted, app.timestarted
                  FROM {appraisal_user_assignment} aua
                  JOIN {appraisal} app
                    ON aua.appraisalid = app.id
                 WHERE aua.userid = ?
              ORDER BY aua.timecompleted, app.timestarted DESC";
        $results = $DB->get_records_sql($sql, array($subjectid));

        if (!$results) {
            throw new appraisal_exception(get_string('error:subjecthasnoappraisals', 'totara_appraisal'));
        }
        return new appraisal(reset($results)->appraisalid);
    }


    /**
     * Get list of appraisals with their start dates and due dates.
     * Those that the subject has not completed and are active are listed first.
     *
     * @param int $subjectid
     * @param int $role
     * @param array $status
     * @return array
     */
    public static function get_user_appraisals($subjectid, $role, $status = array()) {
        global $DB, $USER;
        $params = array($USER->id, $role);
        $aidchar    = $DB->sql_cast_2char('a.id');
        $useridchar = $DB->sql_cast_2char('aua.userid');
        $sql = 'SELECT ' . $DB->sql_concat($aidchar, "'_'", $useridchar) . ' AS uniqueid,
                       a.id, aua.userid, a.name, a.timestarted, aua.status, MAX(ast.timedue) AS timedue, aua.timecompleted,
                       ara.id as roleassignmentid
                  FROM {appraisal} a
                  JOIN {appraisal_user_assignment} aua
                    ON (aua.appraisalid = a.id)
                  JOIN {appraisal_role_assignment} ara
                    ON (ara.appraisaluserassignmentid = aua.id)
                  LEFT JOIN {appraisal_stage} ast ON (ast.appraisalid = a.id)
                 WHERE ara.userid = ?
                   AND ara.appraisalrole = ?';
        if ($subjectid != $USER->id) {
            $sql .= ' AND aua.userid = ?';
            $params[] = $subjectid;
        }
        if ($status) {
            list($sqlstatus, $paramstatus) = $DB->get_in_or_equal($status);
            $sql .= ' AND a.status ' . $sqlstatus;
            $params = array_merge($params, $paramstatus);
        }
        $sql .= ' GROUP BY a.id, aua.userid, a.name, a.timestarted, aua.status, aua.timecompleted, ara.id
                  ORDER BY CASE WHEN aua.timecompleted IS NULL AND aua.status = ? THEN 0 ELSE 1 END, a.timestarted DESC';
        $params[] = self::STATUS_ACTIVE;
        $appraisals = $DB->get_records_sql($sql, $params);
        return $appraisals;
    }

    /**
     * Same as get_user_appraisals but adds user field to each record.
     *
     * @param int $subjectid
     * @param int $role
     * @param array $status
     * @return array
     */
    public static function get_user_appraisals_extended($subjectid, $role, $status = array()) {
        global $DB;

        $appraisals = self::get_user_appraisals($subjectid, $role, $status);

        foreach ($appraisals as $appraisal) {
            $appraisal->user = $DB->get_record('user', array('id' => $appraisal->userid));
        }

        return $appraisals;
    }

    /**
     * Deletes all user_assignments and associated data for a given user.
     *
     * @param int $userid   - The id of the user to run this for.
     */
    public static function delete_learner_assignments($userid) {
        global $DB;

        $context = context_system::instance();
        $fs = get_file_storage();
        $transaction = $DB->start_delegated_transaction();

        $userassignments = $DB->get_records('appraisal_user_assignment', array('userid' => $userid));
        foreach ($userassignments as $userassignment) {
            $appraisalid = $userassignment->appraisalid;
            $userassignmentid = $userassignment->id;
            // Get all role assignments for this userassignment.
            $roleassignments = $DB->get_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $userassignmentid));

            foreach ($roleassignments as $roleassignment) {
                $roleassignmentid = $roleassignment->id;
                $DB->delete_records('appraisal_scale_data', array('appraisalroleassignmentid' => $roleassignmentid));
                $DB->delete_records('appraisal_quest_data_' . $appraisalid, array('appraisalroleassignmentid' => $roleassignmentid));
                $DB->delete_records('appraisal_stage_data', array('appraisalroleassignmentid' => $roleassignmentid));
                $fs->delete_area_files($context->id, 'totara_appraisal', "snapshot_$appraisalid", $roleassignmentid);
            }

            $DB->delete_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $userassignmentid));
            $DB->delete_records('appraisal_role_changes', array('userassignmentid' => $userassignmentid));
        }
        $DB->delete_records('appraisal_user_assignment', array('userid' => $userid));

        $transaction->allow_commit();
    }

    /**
     * Unassign all role_assignments for a given user, but retain associated data.
     *
     * @param int $userid   - The id of the user to run this for.
     * @param bool $includeownappraisals true if learner role assignments should also be removed.
     */
    public static function unassign_user_roles($userid, bool $includeownappraisals = true) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // Flag all data associated with other users assignments as deleted but keep the data.
        if ($includeownappraisals) {
            $roles = $DB->get_records('appraisal_role_assignment', array('userid' => $userid));
        } else {
            $select = "userid = :userid AND appraisalrole <> :rolelearner";
            $params = array('userid' => $userid, 'rolelearner' => self::ROLE_LEARNER);
            $roles = $DB->get_records_select('appraisal_role_assignment', $select, $params);
        }

        // Create role changed records for all the roles.
        $todb = new stdClass();
        $todb->originaluserid = $userid;
        $todb->newuserid = 0; // Deleted so there is no new user.
        $todb->timecreated = time();

        foreach ($roles as $role) {
            $todb->role = $role->appraisalrole;
            $todb->userassignmentid = $role->appraisaluserassignmentid;

            $DB->insert_record('appraisal_role_changes', $todb);
        }

        // Unassign all the users role assignments.
        if ($includeownappraisals) {
            $sql = "UPDATE {appraisal_role_assignment} SET userid = 0 WHERE userid = ?";
            $DB->execute($sql, array($userid));
        } else {
            $sql = "UPDATE {appraisal_role_assignment} SET userid = 0 WHERE userid = :userid AND appraisalrole <> :rolelearner";
            $params = array('userid' => $userid, 'rolelearner' => self::ROLE_LEARNER);
            $DB->execute($sql, $params);
        }

        $transaction->allow_commit();
    }


    /**
     * Get array of appraisals with their start dates, number of assigned and completed learners
     *
     * @return array
     */
    public static function get_manage_list() {
        global $DB;

        $appraisals = $DB->get_records('appraisal', null, 'status, timestarted DESC, name, id');

        foreach ($appraisals as $appraisal) {
            if ($appraisal->status == self::STATUS_DRAFT) {
                $assign = new totara_assign_appraisal('appraisal', new appraisal($appraisal->id));
                $appraisal->lnum = $assign->get_current_users_count();
                // Not yet activated, so no completed appraisals.
                $appraisal->cnum = 0;
            } else {
                // Restricting the counts to consider only non-closed assignments.
                // This ensures the numbers are consistent with what is shown in the 'Assignments' tab.
                $params = array('appraisalid' => $appraisal->id, 'status' => self::STATUS_CLOSED);
                $sql = "SELECT count(completed) AS total, coalesce(sum(completed), 0) AS completed
                          FROM (SELECT CASE
                                        WHEN timecompleted IS NOT NULL THEN 1
                                        ELSE 0
                                       END AS completed
                                  FROM {appraisal_user_assignment} ua
                                 WHERE ua.appraisalid = :appraisalid
                                   AND ua.status <> :status) AS assignments";
                $row = $DB->get_record_sql($sql, $params);
                $appraisal->lnum = $row->total;
                $appraisal->cnum = $row->completed;
            }
        }
        return $appraisals;
    }


    /**
     * Clone appraisal
     *
     * @param int $appraisalid
     * @param int $daysoffset number of days to add to each stage time due.
     * @return
     */
    public static function duplicate_appraisal($appraisalid, $daysoffset = 0) {
        global $DB, $TEXTAREA_OPTIONS;

        $systemcontext = context_system::instance();
        require_capability('totara/appraisal:cloneappraisal', $systemcontext);

        // Clone the appraisal and set it to draft.
        $appraisal = new appraisal($appraisalid);
        $appraisal->id = 0;
        $appraisal->status = self::STATUS_DRAFT;
        $appraisal->timestarted = null;
        $appraisal->timefinished = null;

        // Get new id.
        $newappraisal = $appraisal->save();

        // Now it's same link.
        unset($appraisal);

        // Copy textarea files.
        $data = new stdClass();
        $data->description = $newappraisal->description;
        $data->descriptionformat = FORMAT_HTML;
        $data = file_prepare_standard_editor($data, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'totara_appraisal', 'appraisal', $appraisalid);

        $data = file_postupdate_standard_editor($data, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'totara_appraisal', 'appraisal', $newappraisal->id);
        $newappraisal->description = $data->description;

        $newappraisal->save();

        // Clone stages (which will clone pages and questions).
        $stages = $DB->get_records('appraisal_stage', array('appraisalid' => $appraisalid));
        foreach ($stages as $stagerecord) {
            $stage = new appraisal_stage($stagerecord->id);

            $stage->duplicate($newappraisal->id, $daysoffset);
        }

        // Set up some keyed arrays to map between old questions and new.
        $originalquestions = appraisal_question::fetch_appraisal($appraisalid);
        $originalkeys = array_keys($originalquestions);
        $newquestions = appraisal_question::fetch_appraisal($newappraisal->id);
        $newkeys = array_keys($newquestions);

        // Redirect redisplay questions to the new appraisal.
        $newredisplay = appraisal_question::fetch_appraisal($newappraisal->id, null, null, array('redisplay'));
        foreach ($newredisplay as $redisplay) {
            $originalindex = array_search($redisplay->param1, $originalkeys);
            $redisplay->param1 = $newkeys[$originalindex];
            $DB->update_record('appraisal_quest_field', $redisplay);
        }

        // Redirect aggregate questions to the new appraisal.
        $newaggregate = appraisal_question::fetch_appraisal($newappraisal->id, null, null, array('aggregate'));
        foreach ($newaggregate as $aggregate) {
            $oldqids = json_decode($aggregate->param1);
            $newqids = array();

            foreach ($oldqids as $oldqid) {
                $originalindex = array_search($oldqid, $originalkeys);
                $newqids[] = $newkeys[$originalindex];
            }

            $aggregate->param1 = json_encode($newqids);
            $DB->update_record('appraisal_quest_field', $aggregate);
        }

        // Clone assigned groups (since the new appraisal is draft, we don't need to clone user or role assignments).
        $assign = new totara_assign_appraisal('appraisal', new appraisal($appraisalid));
        $assign->duplicate($newappraisal);

        // Clone events.
        appraisal_message::duplicate_appraisal($appraisalid, $newappraisal->id);

        return $newappraisal;
    }


    /**
     * Build all appraisal components according given definition
     * Used in example appraisal preparation and testing
     *
     * @param array $def definition
     * @return appraisal
     */
    public static function build(array $def) {
        $appraisal = new appraisal();
        $appraisal->name = $def['name'];
        $appraisal->description = isset($def['description']) ? $def['description'] : '';
        $appraisal->save();

        if (isset($def['stages'])) {
            foreach ($def['stages'] as $stage) {
                appraisal_stage::build($stage, $appraisal->id);
            }
        }
        return $appraisal;
    }


    /**
     * Get list of active appraisals with additional statistics.
     *
     * @return array
     */
    public static function get_active_with_stats() {
        global $DB;

        $now = time();
        $sql = 'SELECT app.*,
                       userstotal.userstotal,
                       userscomplete.userscomplete,
                       userscancelled.userscancelled,
                       usersoverdue.usersoverdue,
                       usersontarget.usersontarget
                  FROM {appraisal} app
                  LEFT JOIN (SELECT COUNT(aua.userid) AS userstotal, aua.appraisalid
                               FROM {appraisal_user_assignment} aua
                              GROUP BY aua.appraisalid) userstotal
                    ON app.id = userstotal.appraisalid
                  LEFT JOIN (SELECT COUNT(aua.userid) AS userscomplete, aua.appraisalid
                               FROM {appraisal_user_assignment} aua
                              WHERE aua.timecompleted IS NOT NULL
                               AND aua.status = :statuscompleted
                              GROUP BY aua.appraisalid) userscomplete
                    ON app.id = userscomplete.appraisalid
                  LEFT JOIN (SELECT COUNT(aua.userid) AS userscancelled, aua.appraisalid
                               FROM {appraisal_user_assignment} aua
                               WHERE aua.status = :statusclosed
                            GROUP BY aua.appraisalid) userscancelled
                    ON app.id = userscancelled.appraisalid
                  LEFT JOIN (SELECT COUNT(aua.userid) AS usersoverdue, ast.appraisalid
                               FROM {appraisal_user_assignment} aua
                               JOIN {appraisal_stage} ast
                                 ON aua.activestageid = ast.id
                              WHERE aua.timecompleted IS NULL
                                AND ast.timedue < :timedue
                                AND aua.status = :statusactive1
                                GROUP BY ast.appraisalid) usersoverdue
                    ON app.id = usersoverdue.appraisalid
                   LEFT JOIN (SELECT COUNT(aua.userid) as usersontarget, aua.appraisalid
                               FROM {appraisal_user_assignment} aua
                               JOIN {appraisal_stage} ast2
                                 ON aua.activestageid = ast2.id
                               WHERE ast2.timedue >= :timedue2
                               AND aua.status = :statusontarget
                               GROUP BY aua.appraisalid) usersontarget
                    ON app.id = usersontarget.appraisalid
                 WHERE app.status = :statusactive2';
        $params = array('statuscompleted' => self::STATUS_COMPLETED,
                        'statusclosed' => self::STATUS_CLOSED,
                        'timedue' => $now,
                        'statusactive1' => self::STATUS_ACTIVE,
                        'timedue2' => $now,
                        'statusactive2' => self::STATUS_ACTIVE,
                        'statusontarget' => self::STATUS_ACTIVE
                    );
        $appraisals = $DB->get_records_sql($sql, $params);

        return $appraisals;
    }


    /**
     * Get list of inactive appraisals with additional statistics.
     *
     * @return array
     */
    public static function get_inactive_with_stats() {
        global $DB;

        // We should not count completed appraisal_user_assignment rows with status CLOSED as completed
        // These indicate that although the user has completed the appraisal, he was later removed from the
        // assigned user group(s) and will be shown to have status 'Assignment Cancelled' in the reports.
        $sql = 'SELECT app.id, app.name, app.status, app.timefinished,
                       COUNT(aua.timecompleted) - COUNT(aca.timecompleted) AS userscomplete,
                       COUNT(aua.id) - COUNT(aca.timecompleted) AS userstotal
                  FROM {appraisal} app
                  JOIN {appraisal_user_assignment} aua
                    ON app.id = aua.appraisalid
                  LEFT JOIN {appraisal_user_assignment} aca
                    ON aua.id = aca.id
                   AND aca.status = :statusclosed1
                   AND aca.timecompleted IS NOT NULL
                 WHERE app.status IN (:statusclosed2, :statuscompleted)
                 GROUP BY app.id, app.name, app.status, app.timefinished';
        $params = array('statusclosed1' => self::STATUS_CLOSED,
                        'statusclosed2' => self::STATUS_CLOSED,
                        'statuscompleted' => self::STATUS_COMPLETED);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the last question out of all questions in the appraisal, up to and including the given page.
     * The question may be on the given page, or on some previous page.
     *
     * @param object $stage
     * @param object $page
     * @return the last question, or null
     */
    public static function get_last_question($stage, $page) {
        // Get all answerable questions.
        $allquestions = appraisal_question::fetch_appraisal($stage->appraisalid, null, self::ACCESS_CANANSWER);

        if ($allquestions) {

            // Start at the last question and work backward discarding any that are after the current page.
            $allquestions = array_reverse($allquestions);
            $numquestion = count($allquestions);
            $pointer = 0;

            while ($pointer < $numquestion) {
                $pointed = $allquestions[$pointer];
                $pointer++;
                if ($pointed->stagetimedue > $stage->timedue) {
                    // It is a future stage.
                    continue;
                }
                if (($pointed->stagetimedue == $stage->timedue) && ($pointed->appraisalstageid > $page->appraisalstageid)) {
                    // It is the same stage due date, but is a higher id (and thus later) stage.
                    continue;
                }
                if (($pointed->appraisalstageid == $page->appraisalstageid) && ($pointed->pagesortorder > $page->sortorder)) {
                    // It is the same stage, but it is a higher page.
                    continue;
                }
                // If we get here then pointed must be the last question on a page up to and including the specified page.
                return new appraisal_question($pointed->id);
            }
        }

        return null;
    }

    /**
     * Prints an error if Appraisal is not enabled
     *
     */
    public static function check_feature_enabled() {
        if (totara_feature_disabled('appraisals')) {
            print_error('appraisalsdisabled', 'totara_appraisal');
        }
    }

    /**
     * This is the code used by the totara appraisal scheduled_messages task.
     * It loops through all the appraisal messages that are scheduled
     * and attempts to send them.
     *
     * @param $time timestamp - this should really only be set in tests
     */
    public static function send_scheduled($time = 0) {
        global $DB, $CFG;

        if (empty($time)) {
            $time = time();
        }

        // Execute the cron if Appraisals are not disabled or static.
        if (totara_feature_disabled('appraisals')) {
            return;
        }

        $active = \appraisal::STATUS_ACTIVE;
        $closed = \appraisal::STATUS_CLOSED;

        $asql = "SELECT ae.*, a.status as appstat
                  FROM {appraisal_event} ae
                  JOIN {appraisal} a
                    ON ae.appraisalid = a.id
                 WHERE ae.triggered = :trig
                   AND ae.timescheduled > 0
                   AND ae.timescheduled <= :now
               ";
        $aparams = array('trig' => 0, 'now' => $time);
        $aevents = $DB->get_records_sql($asql, $aparams);
        foreach ($aevents as $aevent) {
            if ($aevent->appstat == $active) {
                // Handle regular messages.
                $event = new \appraisal_message($aevent->id);
                $event->send_appraisal_wide_message();
            } else if ($aevent->appstat == $closed && $aevent->event == \appraisal_message::EVENT_APPRAISAL_CLOSURE) {
                // Handle closure messages.
                $event = new \appraisal_message($aevent->id);
                $event->send_appraisal_wide_closure_message();
            }
        }

        // Then do scheduled messages that go to specific users.
        // Timescheduled in aue must always be set and triggered in ae is not relevant to these events.
        $usql = "SELECT ae.id, aue.userid, aue.timescheduled
                  FROM {appraisal_event} ae
            INNER JOIN {appraisal} a
                    ON ae.appraisalid = a.id
                  JOIN {appraisal_user_event} aue
                    ON aue.eventid = ae.id
                 WHERE a.status = :stat";
        $uparams = array('stat' => $active);
        $uevents = $DB->get_records_sql($usql, $uparams);
        foreach ($uevents as $uevent) {
            $event = new \appraisal_message($uevent->id);
            $event->schedule($uevent->timescheduled); // Use user-specific timescheduled for is_time calculation.
            if ($event->is_time($time)) {
                $event->send_user_specific_message($uevent->userid);
            }
        }
    }
}


/**
 * Stage within appraisal
 */
class appraisal_stage {
    /**
     * Appraisal stage id
     *
     * @var int
     */
    protected $id = 0;

    /**
     * Appraisal id that this stage is related to
     *
     * @var type
     */
    public $appraisalid = null;

    /**
     * Stage name
     *
     * @var string
     */
    public $name = '';

    /**
     * Stage description
     *
     * @var string
     */
    public $description = '';

    /**
     * Completion date
     *
     * @var int
     */
    public $timedue = null;

    /**
     * Roles lock on complete settings
     * Key is appraisal::ROLE_* code, value 1/0
     *
     * @var array of roles objects
     */
    protected $locks = array();

    /**
     * Create instance of appraisal stage
     *
     * @param int $id
     */
    public function __construct($id = 0) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }


    /**
     * Allow read access to restricted properties
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (in_array($name, array('id'))) {
            return $this->$name;
        }
    }


    /**
     * Set stage properties
     *
     * @param stdClass $todb
     * @return $this
     */
    public function set(stdClass $todb) {
        if (is_null($this->appraisalid) && isset($todb->appraisalid)) {
            $this->appraisalid = $todb->appraisalid;
        }
        if (isset($todb->name)) {
            $this->name = $todb->name;
        }
        if (isset($todb->description)) {
            $this->description = $todb->description;
        }
        if (isset($todb->timedue)) {
            $this->timedue = $todb->timedue;
        }
        // Set roles that should be locked on stage completion.
        if (isset($todb->locks)) {
            $roles = appraisal::get_roles();
            foreach ($roles as $role => $rolename) {
                if (isset($todb->locks[$role])) {
                    $this->locks[$role] = $todb->locks[$role];
                } else {
                    $this->locks[$role] = 0;
                }
            }
        }
        return $this;
    }


    /**
     * Get stdClass with stage properties
     *
     * @return stdClass
     */
    public function get() {
        $obj = new stdClass();
        $obj->id = $this->id;
        $obj->appraisalid = $this->appraisalid;
        $obj->name = $this->name;
        $obj->description = $this->description;
        $obj->timedue = $this->timedue;

        // Get roles that should be locked on stage completion.
        $obj->locks = $this->locks;

        return $obj;
    }


    /**
     * Saves current stage properties
     *
     * @return $this
     */
    public function save() {
        global $DB;

        $todb = $this->get();

        if ($todb->appraisalid < 1) {
            throw new appraisal_exception('Stage must belong to an appraisal', 12);
        }

        if (!appraisal::is_draft($todb->appraisalid)) {
            throw new appraisal_exception('Cannot change stage of active appraisal');
        }

        if (!$todb->timedue) {
            $todb->timedue = null;
        }
        if ($this->id > 0) {
            $todb->id = $this->id;
            $DB->update_record('appraisal_stage', $todb);

            \totara_appraisal\event\stage_updated::create_from_instance($this)->trigger();
        } else {
            $this->id = $DB->insert_record('appraisal_stage', $todb);

            \totara_appraisal\event\stage_created::create_from_instance($this)->trigger();
        }
        // Save roles that should be locked on stage completion.
        $this->save_locks();
        // Refresh data.
        $this->load($this->id);
        return $this;
    }


    /**
     * Reload appraisal stage properties from DB
     *
     * @return $this
     */
    public function load() {
        global $DB;
        $stage = $DB->get_record('appraisal_stage', array('id' => $this->id));
        if (!$stage) {
            throw new appraisal_exception('Cannot load appraisal stage', 11);
        }
        $this->appraisalid = $stage->appraisalid;
        $this->name = $stage->name;
        $this->description = $stage->description;
        $this->timedue = $stage->timedue;
        $this->load_locks();
        return $this;
    }


    /**
     * Clone an appraisal stage
     *
     * @param int $appraisalid The new appraisal id to assign to stage
     * @param int number of days to add to time due.
     * @return appraisal_stage
     */
    public function duplicate($appraisalid, $daysoffset = 0) {
        global $DB, $TEXTAREA_OPTIONS;

        // Get pages for current stage.
        $pages = $DB->get_records('appraisal_stage_page', array('appraisalstageid' => $this->id), 'sortorder');

        // Clone stage.
        $srcstageid = $this->id;
        $this->id = 0;
        $this->appraisalid = $appraisalid;
        $this->timedue = ($this->timedue > 0) ? $this->timedue + $daysoffset * 86400 : 0;
        $this->save();

        // Here, $this is original, $newstage is duplicate. Separate them.
        $newstage = clone($this);
        $this->id = $srcstageid;
        $this->load();

        // Copy textarea files.
        $data = new stdClass();
        $data->description = $newstage->description;
        $data->descriptionformat = FORMAT_HTML;
        $data = file_prepare_standard_editor($data, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'totara_appraisal', 'appraisal_stage', $srcstageid);

        $data = file_postupdate_standard_editor($data, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'totara_appraisal', 'appraisal_stage', $newstage->id);
        $newstage->description = $data->description;

        $newstage->save();

        // Clone pages.
        foreach ($pages as $pagerecord) {
            $page = new appraisal_page($pagerecord->id);
            $page->duplicate($newstage->id);
        }

        // Clone events.
        appraisal_message::duplicate_stage($srcstageid, $newstage->id);
        return $newstage;
    }


    /**
     * Load stage roles permissions/settings from DB
     *
     * @return $this
     */
    protected function load_locks() {
        global $DB;

        $rolesdb = $DB->get_records('appraisal_stage_role_setting', array('appraisalstageid' => $this->id));
        $this->locks = array();
        if ($rolesdb) {
            foreach ($rolesdb as $role) {
                $this->locks[$role->appraisalrole] = $role->locked;
            }
        }

        return $this;
    }


    /**
     * Save locks for roles
     *
     * @return $this
     */
    protected function save_locks() {
        global $DB;

        if (!appraisal::is_draft($this->appraisalid)) {
            throw new appraisal_exception('Cannot change stage of active appraisal');
        }

        $roles = appraisal::get_roles();
        foreach ($roles as $role => $rolename) {
            if (isset($this->locks[$role]) && $this->locks[$role] > 0) {
                $dbrole = $DB->get_record('appraisal_stage_role_setting',
                        array('appraisalstageid' => $this->id, 'appraisalrole' => $role));
                if (!$dbrole) {
                    $dbrole = new stdClass();
                    $dbrole->appraisalstageid = $this->id;
                    $dbrole->appraisalrole = $role;
                    $dbrole->locked = $this->locks[$role];
                    $DB->insert_record('appraisal_stage_role_setting', $dbrole);
                } else if ($dbrole->locked != $this->locks[$role]) {
                    $dbrole->locked = $this->locks[$role];
                    $DB->update_record('appraisal_stage_role_setting', $dbrole);
                }
            } else {
                $DB->delete_records('appraisal_stage_role_setting',
                        array('appraisalstageid' => $this->id, 'appraisalrole' => $role));
            }
        }

        return $this;
    }


    /**
     * Validate stage for activation
     *
     * @param int $time Estimate if appraisal is valid on given time (by default: current server time)
     * @return array with errors / empty if no errors
     */
    public function validate($time = null) {
        if (is_null($time)) {
            $time = time();
        }

        $err = array();
        if (empty($this->timedue) || ($this->timedue > 0 && $this->timedue < $time)) {
                $err['stagedue'.$this->id] = get_string('appraisalinvalid:stagedue', 'totara_appraisal',
                        format_string($this->name));
        }

        // Check that stage has at least one page.
        $pages = appraisal_page::fetch_stage($this->id);
        if (empty($pages)) {
            $err['stageempty' . $this->id] = get_string('appraisalinvalid:stageempty', 'totara_appraisal',
                        format_string($this->name));
        }

        $rolesinvolved = $this->get_roles_involved(appraisal::ACCESS_CANANSWER);
        if (empty($rolesinvolved)) {
            $err['stagenocananswer' . $this->id] =
                    get_string('appraisalinvalid:stagenoonecananswer', 'totara_appraisal',
                        format_string($this->name));
        }

        // Validate each page.
        foreach ($pages as $pagerecord) {
            $page = new appraisal_page($pagerecord->id);
            $err += $page->validate();
        }

        return $err;
    }


    /**
     * Is this stage completed (for all roles or by a certain role user).
     * Stage completed - all roles have done it.
     * Stage completed by role - all required answers are answered and user confirmed that stage is completed.
     * Note: If the specified role is not required to answer questions in this stage then this function will return true.
     *
     * @param object $assignment Either roleassignment or userassignment
     * @return bool
     */
    public function is_completed($assignment) {
        global $DB;

        if ($assignment instanceof appraisal_role_assignment) {
            $roleassignment = $assignment;
            $userassignment = $roleassignment->get_user_assignment();
        } else if ($assignment instanceof appraisal_user_assignment) {
            $roleassignment = null;
            $userassignment = $assignment;
        } else {
            throw new appraisal_exception('Wrong class assignment');
        }

        // Check if the appraisal is completed for this user.
        if (isset($userassignment->timecompleted)) {
            return true;
        }

        // Check if stage is completed for this user.
        $stages = self::get_stages($this->appraisalid);
        if ($userassignment->activestageid != $this->id && $stages[$userassignment->activestageid]->timedue > $this->timedue) {
            return true;
        }

        // If we do not specifiy a role user then the stage is not complete for the user.
        if (!isset($roleassignment)) {
            return false;
        }

        // Check if the role user can answer some questions.
        if ($this->can_be_answered($roleassignment->appraisalrole)) {
            // Check if they have submitted stage data.
            return $DB->record_exists('appraisal_stage_data',
                    array('appraisalroleassignmentid' => $roleassignment->id, 'appraisalstageid' => $this->id));
        } else {
            // The role user is not required to answer any questions, so they are not incomplete.
            return true;
        }
    }


    /**
     * Is appraisal stage locked for the subject and user in the given role
     * Stage considered locked if it's completed and "appraisal is locked after completing" setting enabled.
     *
     * @param object $roleassignment
     * @return bool
     */
    public function is_locked(appraisal_role_assignment $roleassignment) {
        $appraisal = new appraisal($this->appraisalid);
        $userassignment = $roleassignment->get_user_assignment();

        if ($appraisal->is_locked($userassignment)) {
            return true;
        }
        return (isset($this->locks[$roleassignment->appraisalrole]) && $this->locks[$roleassignment->appraisalrole] &&
                ($this->is_completed($roleassignment)) && ($userassignment->activestageid != $this->id));
    }


    /**
     * Is appraisal stage overdue
     *
     * @param int $time time of check
     * @return bool
     */
    public function is_overdue($time = 0) {
        if (!$time) {
            $time = time();
        }
        if (!$this->timedue) {
            return false;
        }
        return ($time > $this->timedue);
    }


    /**
     * Tests if a role may answer on this stage (if the role has at least one editable question).
     *
     * @return bool
     */
    public function can_be_answered($role) {
        return array_key_exists($role, $this->get_may_answer());
    }


    /**
     * Determine if a stage has any questions which are linked to from redirect questions.
     *
     * @param int $stageid The stage to check.
     * @return bool 0 if nothing links to this stage's questions, otherwise non-zero.
     */
    public static function has_redisplayed_items($stageid) {
        global $DB;

        $sql = "SELECT COUNT(aqfr.id)
                  FROM {appraisal_stage_page} asp
                  JOIN {appraisal_quest_field} aqf
                    ON asp.id = aqf.appraisalstagepageid
                  JOIN {appraisal_quest_field} aqfr
                    ON " . $DB->sql_cast_2char('aqf.id') . ' = ' . $DB->sql_compare_text('aqfr.param1') . "
                 WHERE asp.appraisalstageid = ?";

        return $DB->count_records_sql($sql, array($stageid));
    }



    /**
     * Get roles that may answer on stage (roles that have at least one editable question).
     *
     * @return array
     */
    public function get_may_answer() {
        global $DB;

        $sql = 'SELECT DISTINCT aqfr.appraisalrole
                  FROM {appraisal_stage_page} asp
                  LEFT JOIN {appraisal_quest_field} aqf
                    ON aqf.appraisalstagepageid = asp.id
                  LEFT JOIN {appraisal_quest_field_role} aqfr
                    ON aqfr.appraisalquestfieldid = aqf.id
                   AND aqfr.rights > 1
                 WHERE asp.appraisalstageid  = ?
                   AND (aqfr.rights & ? ) = ?';
        $roles = $DB->get_recordset_sql($sql, array($this->id, appraisal::ACCESS_CANANSWER, appraisal::ACCESS_CANANSWER));

        $out = array();
        $strroles = appraisal::get_roles();
        foreach ($roles as $role) {
            $out[$role->appraisalrole] = get_string($strroles[$role->appraisalrole], 'totara_appraisal');
        }

        return $out;
    }


    /**
     * Mark this stage as complete for the given role.
     * Then check if all involved roles are complete and if so then mark the stage as complete.
     *
     * @param appraisal_role_assignment $roleassignment
     */
    public function complete_for_role(appraisal_role_assignment $roleassignment) {
        global $DB;

        if (!$DB->record_exists('appraisal_stage_data',
            array('appraisalroleassignmentid' => $roleassignment->id, 'appraisalstageid' => $this->id))) {
            // Mark the role as complete.
            $stage_data = new stdClass();
            $stage_data->appraisalroleassignmentid = $roleassignment->id;
            $stage_data->timecompleted = time();
            $stage_data->appraisalstageid = $this->id;
            $DB->insert_record('appraisal_stage_data', $stage_data);

            // Check if all involved roles are complete for this user and stage.
            $rolescompletion = $this->get_mandatory_completion($roleassignment->subjectid);
            $complete = true;
            foreach ($rolescompletion as $rolecompletion) {
                if (!isset($rolecompletion->timecompleted)) {
                    $complete = false;
                    break;
                }
            }
            if ($complete) {
                // Mark this stage as complete for this user.
                $this->complete_for_user($roleassignment->subjectid);
            }
        }
    }


    /**
     * Mark this stage as complete for the given user.
     * Then check if this is the last stage of the appraisal and if so then mark the user as complete.
     *
     * @param int $subjectid
     */
    public function complete_for_user($subjectid) {
        global $DB;

        // Mark the user as complete for this stage.
        $stages = self::get_list($this->appraisalid);
        $nextstageid = null;
        $currentstage = reset($stages);
        for ($i = 0; $i < count($stages) - 1; $i++) {
            if ($currentstage->id == $this->id) {
                $currentstage = next($stages);
                $nextstageid = $currentstage->id;
                break;
            }
            $currentstage = next($stages);
        }

        // Check if this was the last stage for this user.
        if (!empty($nextstageid)) {
            $DB->set_field('appraisal_user_assignment', 'activestageid', $nextstageid,
                array('userid' => $subjectid, 'appraisalid' => $this->appraisalid));
        } else {
            // Mark the appraisal as complete for the given user.
            $appraisal = new appraisal($this->appraisalid);
            $appraisal->complete_for_user($subjectid);
        }

        $event = \totara_appraisal\event\appraisal_stage_completion::create(
            array(
                'objectid' => $this->appraisalid,
                'context' => context_system::instance(),
                'userid' => $subjectid,
                'other' => array(
                    'appraisalid' => $this->appraisalid,
                    'stageid' => $this->id,
                    'time' => time(),
                )
            )
        );
        $event->trigger();
    }


    /**
     * Get all roles involved in this stage.
     *
     * @param int $rights Only if they have the specified rights.
     * @return array
     */
    public function get_roles_involved($rights = 0) {
        global $DB;

        $sqlrights = 'aqfr.rights > 0';
        $params = array();
        if ($rights > 0) {
            $sqlrights = '(aqfr.rights & ? ) = ?';
            $params[] = $rights;
            $params[] = $rights;
        }

        $sql = "SELECT DISTINCT aqfr.appraisalrole
                  FROM {appraisal_stage_page} asp
                  LEFT JOIN {appraisal_quest_field} aqf
                    ON aqf.appraisalstagepageid = asp.id
                  JOIN {appraisal_quest_field_role} aqfr
                    ON aqfr.appraisalquestfieldid = aqf.id
                   AND {$sqlrights}
                 WHERE asp.appraisalstageid = ?
                 ORDER BY aqfr.appraisalrole";
        $params[] = $this->id;
        $rolesrecords = $DB->get_records_sql($sql, $params);

        $out = array();
        foreach ($rolesrecords as $rolerecord) {
            $out[$rolerecord->appraisalrole] = 1;
        }
        return array_keys($out);
    }

    /**
     * Get array of roles involved in the current stage for the given user, linking to the users assigned in each role.
     *
     * @param appraisal::ACCESS_XXX $rights only roles that have the given rights
     * @param int $subjectid the userid in the userassignment
     * @return array(appraisalrole => userid)
     */
    public function get_user_roles_involved($rights, $subjectid) {
        global $DB;

        $roles = $this->get_roles_involved($rights);
        if (empty($roles)) {
            return array();
        }
        list($insql, $inparam) = $DB->get_in_or_equal($roles);
        $missingsql = "SELECT ara.appraisalrole, ara.userid
                         FROM {appraisal_role_assignment} ara
                         JOIN {appraisal_user_assignment} aua
                           ON ara.appraisaluserassignmentid = aua.id
                        WHERE aua.userid = ?
                          AND aua.appraisalid = ?
                          AND appraisalrole {$insql}";
        $missingparams = array_merge(array($subjectid, $this->appraisalid), $inparam);
        return $DB->get_records_sql($missingsql, $missingparams);
    }

    public function get_mandatory_completion($subjectid) {
        global $DB;

        $cananswer = $this->get_user_roles_involved(appraisal::ACCESS_CANANSWER, $subjectid);
        $mustanswer = $this->get_user_roles_involved(appraisal::ACCESS_MUSTANSWER, $subjectid);

        $roles = array();

        foreach ($mustanswer as $role) {
            $roles[$role->appraisalrole] = $role->appraisalrole;
        }

        foreach ($cananswer as $role) {
            if (!empty($role->userid)) {
                $roles[$role->appraisalrole] = $role->appraisalrole;
            }
        }

        $sql = 'SELECT ara.appraisalrole, ara.userid, ara.activepageid, asd.timecompleted
                  FROM {appraisal_role_assignment} ara
                  JOIN {appraisal_user_assignment} aua
                    ON ara.appraisaluserassignmentid = aua.id
                  LEFT JOIN (SELECT * FROM {appraisal_stage_data}
                              WHERE appraisalstageid = ?) asd
                    ON ara.id = asd.appraisalroleassignmentid
                 WHERE aua.userid = ?
                   AND aua.appraisalid = ?
                   AND ara.userid <> 0
                 ORDER BY ara.appraisalrole';
        $completiondata = $DB->get_records_sql($sql, array($this->id, $subjectid, $this->appraisalid));
        return array_intersect_key($completiondata, $roles);
    }


    /**
     * Return active/in progress stage
     * Only one stage is active at a time, subsequent stages are not available until the previous one is completed
     *
     * @param int $appraisalid
     * @param appraisal_user_assignment $userassignment
     * @return appraisal_stage or null if all stages completed
     */
    public static function get_active($appraisalid, appraisal_user_assignment $userassignment) {
        $stagesrs = self::fetch_appraisal($appraisalid);
        foreach ($stagesrs as $stagerecord) {
            $stage = new appraisal_stage($stagerecord->id);
            if (!$stage->is_completed($userassignment)) {
                return $stage;
            }
        }
        return null;
    }


    /**
     * Get list of stages with involved roles (only roles that have questions to answer are involved)
     *
     * @param int $appraisalid
     * @return array of stdClass
     */
    public static function get_list($appraisalid) {
        global $DB;

        $sql = 'SELECT ast.id as stageid, ast.name, ast.description, ast.timedue, aspqfr.appraisalrole, ast.appraisalid
                  FROM {appraisal_stage} ast
                  LEFT JOIN
                    (SELECT DISTINCT asp.appraisalstageid, aqfr.appraisalrole
                       FROM {appraisal_stage_page} asp
                       JOIN {appraisal_quest_field} aqf
                         ON aqf.appraisalstagepageid = asp.id
                       JOIN {appraisal_quest_field_role} aqfr
                         ON aqfr.appraisalquestfieldid = aqf.id AND (aqfr.rights & ? ) = ?) aspqfr
                    ON aspqfr.appraisalstageid = ast.id
                 WHERE ast.appraisalid = ?
                 ORDER BY ast.timedue, ast.id, aspqfr.appraisalrole';
        $params = array(appraisal::ACCESS_CANANSWER, appraisal::ACCESS_CANANSWER, $appraisalid);
        $stages = $DB->get_recordset_sql($sql, $params);

        $groupedstages = array();
        foreach ($stages as $stage) {
            if (!isset($groupedstages[$stage->stageid])) {
                $outstage = new stdClass();
                $outstage->id = $stage->stageid;
                $outstage->appraisalid = $stage->appraisalid;
                $outstage->name = $stage->name;
                $outstage->description = $stage->description;
                $outstage->timedue = $stage->timedue;
                $groupedstages[$stage->stageid] = $outstage;
                $groupedstages[$stage->stageid]->roles = array();
            }
            if ($stage->appraisalrole) {
                $groupedstages[$stage->stageid]->roles[$stage->appraisalrole] = 1;
            }
        }
        return $groupedstages;
    }


    /**
     * Get list of appraisal stages that the roles have rights to.
     * By default all stages where a role can read or write at least on one question will be returned.
     *
     * @param int $appraisalid
     * @param array $roles
     * @param int $rights appraisal::ACCESS_* Clarify on certain rights
     * @return array of stdClass
     */
    public static function get_stages($appraisalid, $roles = array(), $rights = 0) {
        global $DB;

        // Take user role(s).
        if (!empty($roles)) {
            list($sqlroles, $paramroles) = $DB->get_in_or_equal($roles);
        } else {
            $paramroles = array();
        }

        $sqlrights = 'aqfr.rights > 0';
        $paramrights = array();
        if ($rights > 0) {
            $sqlrights = '(aqfr.rights & ? ) = ?';
            $paramrights[] = $rights;
            $paramrights[] = $rights;
        }

        $sql = 'SELECT DISTINCT ast.id, ast.timedue FROM {appraisal_stage} ast
                  LEFT JOIN {appraisal_stage_page} asp ON (asp.appraisalstageid = ast.id)
                  LEFT JOIN {appraisal_quest_field} aqf ON (aqf.appraisalstagepageid = asp.id)
                  LEFT JOIN {appraisal_quest_field_role} aqfr ON (aqfr.appraisalquestfieldid = aqf.id)
                 WHERE ast.appraisalid = ?';

        if (!empty($roles)) {
            $sql .= ' AND aqfr.appraisalrole ' . $sqlroles;
        }

        $sql .= ' AND ' . $sqlrights . '
                 ORDER BY ast.timedue, ast.id';

        $params = array_merge(array($appraisalid), $paramroles, $paramrights);
        $stagesrs = $DB->get_records_sql($sql, $params);

        $stages = array();
        foreach ($stagesrs as $stagerecord) {
            $stages[$stagerecord->id] = new appraisal_stage($stagerecord->id);
        }

        return $stages;
    }


    /**
     * Get list of all appraisal stages for all appraisals. Used for report builder.
     *
     * @return array of stdClass
     */
    public static function get_all_stages() {
        global $DB;

        $sql = 'SELECT ast.id, ast.appraisalid, ast.timedue, ast.name AS stagename, app.name AS appraisalname, app.timestarted
                  FROM {appraisal_stage} ast
                  JOIN {appraisal} app
                    ON ast.appraisalid = app.id
                 ORDER BY app.timestarted DESC, ast.timedue';

        return $DB->get_records_sql($sql);
    }


    /**
     * Fetch all appraisal stages
     *
     * @param int $appraisalid
     * @param int $instances Return array of appraisal_stage instead of stdClass
     * @return array of stdClass
     */
    public static function fetch_appraisal($appraisalid, $instances = false) {
        global $DB;
        $stages = $DB->get_records('appraisal_stage', array('appraisalid' => $appraisalid), 'timedue, id');
        if ($instances) {
            $inststages = arraY();
            foreach ($stages as $stagedata) {
                $inststages[$stagedata->id] = new appraisal_stage($stagedata->id);
            }
            return $inststages;
        }
        return $stages;
    }

    /**
     * Get the previous stage
     *
     * @return object appraisal_stage
     */
    public function get_previous() {
        global $DB;

        if (empty($this->id)) {
            return false;
        }

        $sql = "appraisalid = :appraisalid AND timedue <= :timedue AND id <> :id";
        $params = array(
            'appraisalid' => $this->appraisalid,
            'timedue' => $this->timedue,
            'id' => $this->id,
        );
        $record = $DB->get_records_select('appraisal_stage', $sql, $params, 'timedue DESC, id DESC', 'id', 0, 1);

        if ($record) {
            return new self(current($record)->id);
        } else {
            return false;
        }
    }

    /**
     * Remove stage if possible
     *
     * @param int $stageid
     * @return bool
     */
    public function delete() {
        global $DB, $TEXTAREA_OPTIONS;

        $snapshot = $this->get();
        if (!appraisal::is_draft($this->appraisalid)) {
            return false;
        }

        // Remove all questions custom data.
        $pages = appraisal_page::fetch_stage($this->id);
        foreach ($pages as $page) {
            appraisal_page::delete($page->id);
        }

        // Remove event messages.
        appraisal_message::delete_stage($this->id);

        $DB->delete_records('appraisal_stage_role_setting', array('appraisalstageid' => $this->id));

        // Remove files.
        $fs = get_file_storage();
        $fs->delete_area_files($TEXTAREA_OPTIONS['context']->id, 'totara_appraisal', 'appraisal_stage', $this->id);

        $DB->delete_records('appraisal_stage', array('id' => $this->id));
        $this->id = null;

        \totara_appraisal\event\stage_deleted::create_from_instance($snapshot)->trigger();

        return true;
    }


    /**
     * Build stage and related pages, questions according given definition
     * @param array $def
     * @param int $appraisalid
     * @return appraisal_stage
     */
    public static function build(array $def, $appraisalid) {
        $stage = new appraisal_stage();
        $stage->appraisalid = $appraisalid;
        $stage->name = $def['name'];
        $stage->description = isset($def['description']) ? $def['description'] : '';
        $stage->timedue = isset($def['timedue']) ? $def['timedue'] : '';
        $stage->locks = isset($def['locks']) ? $def['locks'] : array();
        $stage->save();
        if (isset($def['pages'])) {
            foreach ($def['pages'] as $page) {
                appraisal_page::build($page, $stage->id);
            }
        }
        return $stage;
    }
}


/**
 * Pages within stages
 */
class appraisal_page {

    /**
     * If active page id is set to this then it indicates that all pages in the current stage are complete.
     */
    const ACTIVEPAGECOMPLETEDID = -1;

    /**
     * Appraisal stage id
     *
     * @var int
     */
    protected $id = 0;

    /**
     * Appraisal stageid that this page is related to
     *
     * @var type
     */
    public $appraisalstageid = null;

    /**
     * Stage name
     *
     * @var string
     */
    public $name = '';

    /**
     * Stage position
     *
     * @var int
     */
    public $sortorder = 0;

    /**
     * Create instance of appraisal page
     *
     * @param int $id
     */
    public function __construct($id = 0) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }


    /**
     * Allow read access to restricted properties
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (in_array($name, array('id'))) {
            return $this->$name;
        }
    }


    /**
     * Get stdClass with page properties
     *
     * @return stdClass
     */
    public function get() {
        $obj = new stdClass();
        $obj->id = $this->id;
        $obj->appraisalstageid = $this->appraisalstageid;
        $obj->name = $this->name;
        $obj->sortorder = $this->sortorder;
        return $obj;
    }


    /**
     * Set page properties
     *
     * @param stdClass $todb
     * @return $this
     */
    public function set(stdClass $todb) {
        if (is_null($this->appraisalstageid) && isset($todb->appraisalstageid)) {
            $this->appraisalstageid = $todb->appraisalstageid;
        }
        if (isset($todb->name)) {
            $this->name = $todb->name;
        }
        if (isset($todb->sortorder)) {
            $this->sortorder = $todb->sortorder;
        }
        return $this;
    }


    /**
     * Saves current page properties
     *
     * @return $this
     */
    public function save() {
        global $DB;

        $todb = $this->get();

        if ($todb->appraisalstageid < 1) {
            throw new appraisal_exception('Page must belong to a stage', 22);
        }

        $stage = new appraisal_stage($todb->appraisalstageid);
        if (!appraisal::is_draft($stage->appraisalid)) {
            throw new appraisal_exception('Cannot change page of active appraisal');
        }

        // Put default name.
        if (!$todb->name) {
            $todb->name = get_string('pagedefaultname', 'totara_appraisal');
        }

        // Fix sort order.
        $sameplace = false;
        if ($this->id) {
            $sameplace = $DB->record_exists('appraisal_stage_page', array('id' => $this->id, 'sortorder' => $this->sortorder,
                'appraisalstageid' => $this->appraisalstageid));
        }
        if (!$sameplace) {
            // Put as last item.
            $sqlorder = 'SELECT sortorder
                    FROM {appraisal_stage_page}
                    WHERE appraisalstageid = ?
                    ORDER BY sortorder DESC';
            $neworder = $DB->get_record_sql($sqlorder, array($todb->appraisalstageid), IGNORE_MULTIPLE);
            if (!$neworder) {
                $todb->sortorder = 0;
            } else {
                $todb->sortorder = $neworder->sortorder + 1;
            }
        }
        if ($this->id > 0) {
            $todb->id = $this->id;
            $DB->update_record('appraisal_stage_page', $todb);

            \totara_appraisal\event\page_updated::create_from_instance($this)->trigger();
        } else {
            $this->id = $DB->insert_record('appraisal_stage_page', $todb);

            \totara_appraisal\event\page_created::create_from_instance($this)->trigger();
        }
        // Refresh data.
        $this->load($this->id);
        return $this;
    }


    /**
     * Reload appraisal page properties from DB
     *
     * @return $this
     */
    public function load() {
        global $DB;
        $page = $DB->get_record('appraisal_stage_page', array('id' => $this->id));
        if (!$page) {
            throw new appraisal_exception('Cannot load appraisal page', 21);
        }
        $this->appraisalstageid = $page->appraisalstageid;
        $this->name = $page->name;
        $this->sortorder = $page->sortorder;
        return $this;
    }


    /**
     * Set page relation to a stage
     *
     * @param int $appraisalstageid
     */
    public function set_stage($appraisalstageid) {
        $this->appraisalstageid = $appraisalstageid;
    }


    /**
     * Clone a stage page given a new appriasal stage id
     * to associate it with
     *
     * @param int $appraisalstageid
     * @return appraisal_page
     */
    public function duplicate($appraisalstageid) {
        global $DB;
        $srcspageid = $this->id;
        // Get questions for current page.
        $questions = $DB->get_records('appraisal_quest_field', array('appraisalstagepageid' => $this->id), 'sortorder');

        // Duplicate page.
        $this->appraisalstageid = $appraisalstageid;
        $this->id = 0;
        $this->save();
        // Here, $this is original, $newpage is duplicate. Separate them.
        $newpage = clone($this);
        $this->id = $srcspageid;
        $this->load();

        // Duplicate questions in the page.
        foreach ($questions as $q) {
            $question = new appraisal_question($q->id);
            $question->duplicate($newpage->id);
        }

        return $newpage;
    }


    /**
     * Validate page for activation.
     *
     * @return array Errors that were found.
     */
    public function validate() {
        $err = array();

        $questionrecords = appraisal_question::fetch_page($this->id);

        // Page must have at least one question.
        if (empty($questionrecords)) {
            $err['page' . $this->id] = get_string('appraisalinvalid:pageempty', 'totara_appraisal',
                        format_string($this->name));
        }

        foreach ($questionrecords as $questionrecord) {
            $question = new appraisal_question($questionrecord->id);
            if ($question->is_invalid_redisplay()) {
                $err['page' . $this->id . '_' . $question->id] = get_string('appraisalinvalid:redisplayfuture',
                        'totara_appraisal', format_string($this->name));
            }
        }

        return $err;
    }


    /**
     * Move page to another stage
     *
     * @param int $stageid
     */
    public function move($stageid) {
        $this->appraisalstageid = $stageid;
        $this->save();
    }


    /**
     * Prepare answers on questions related to page for saving to db
     *
     * @param stdClass $formdata
     * @param stdClass $roleassignment role assignment record
     * @return stdClass fields related to page
     */
    public function export_answers(stdClass $formdata, appraisal_role_assignment $roleassignment) {
        $questions = appraisal_question::fetch_page_role($this->id, $roleassignment);
        $answers = new stdClass();
        foreach ($questions as $question) {
            if (!$question->is_locked($roleassignment)) {
                $rights = $question->roles[$roleassignment->appraisalrole];
                // If a user isn't required to answer a question don't try and set form as it doesn't exist.
                if (($rights & appraisal::ACCESS_CANANSWER) == appraisal::ACCESS_CANANSWER) {
                    $answers = $question->get_element()->set_as_form($formdata)->get_as_db($answers);
                }
            }
        }
        return $answers;
    }


    /**
     * Mark this page as complete for the given role.
     *
     * @param object $roleassingment
     * $@return bool True if this was the last page of the stage for this role.
     */
    public function complete_for_role(appraisal_role_assignment $roleassignment) {
        global $DB;

        // If we're currently on this page then go to the next.
        if (!$roleassignment->activepageid || ($roleassignment->activepageid == $this->id)) {
            $pages = self::get_applicable_pages($this->appraisalstageid, $roleassignment->appraisalrole, 0, false);
            $nextpage = reset($pages);
            for ($i = 0; $i < count($pages); $i++) {
                if ($nextpage->id == $this->id) {
                    $nextpage = next($pages);
                    break;
                }
                $nextpage = next($pages);
            }
            if ($nextpage) {
                $roleassignment->activepageid = $nextpage->id;
                $DB->set_field('appraisal_role_assignment', 'activepageid', $roleassignment->activepageid,
                        array('id' => $roleassignment->id));
            } else {
                $DB->set_field('appraisal_role_assignment', 'activepageid', null,
                        array('id' => $roleassignment->id));
                return true;
            }
        }
        return false;
    }


    /**
     * Is page completed for the subject and the user in the given role.
     * Page considered completed if the role user has a higher activepage->sortorder or the stage is completed
     * or the user is not required to complete the stage.
     *
     * @param appraisal_role_assignment $roleassignment
     * @return bool
     */
    public function is_completed(appraisal_role_assignment $roleassignment) {
        $stage = new appraisal_stage($this->appraisalstageid);
        $userassignment = $roleassignment->get_user_assignment();
        // If the stage is completed for this user then the page must be completed for this user.
        if ($stage->is_completed($roleassignment)) {
            return true;
        }

        // If this stage is not active then it must be a future stage (past stages have already been dealt with).
        if ($userassignment->activestageid != $this->appraisalstageid) {
            return false;
        }

        // If the user can't answer any questions on this page then we return true (indicating we're not waiting for answers).
        if (!$stage->can_be_answered($roleassignment->appraisalrole)) {
            return true;
        }

        // If active page id is not set then the role user is on the first page of the active stage.
        if (empty($roleassignment->activepageid)) {
            return false;
        }

        // Compare the activepage to this page.
        $rolesactivepage = new appraisal_page($roleassignment->activepageid);
        return ($this->sortorder < $rolesactivepage->sortorder);
    }


    /**
     * Is page locked for the subject and the user in the given role.
     * Page considered locked if it's stage is locked.
     *
     * @param appraisal_role_assignment $roleassignment
     * @return boolen
     */
    public function is_locked(appraisal_role_assignment $roleassignment) {
        $stage = new appraisal_stage($this->appraisalstageid);
        return ($stage->is_locked($roleassignment));
    }


    /**
     * Tests if a role may answer on this page (if the role has at least one editable question).
     *
     * @param int $role
     * @return bool
     */
    public function can_be_answered($role) {
        $rolesmustanswer = $this->get_may_answer();
        return array_key_exists($role, $rolesmustanswer);
    }


    /**
     * Get roles that may answer on page (roles that have at least one editable question).
     *
     * @return array of int
     */
    public function get_may_answer() {
        global $DB;
        $sql = 'SELECT DISTINCT aqfr.appraisalrole
                  FROM {appraisal_stage_page} asp
                  LEFT JOIN {appraisal_quest_field} aqf
                    ON aqf.appraisalstagepageid = asp.id
                  LEFT JOIN {appraisal_quest_field_role} aqfr
                    ON aqfr.appraisalquestfieldid = aqf.id
                   AND aqfr.rights > 0
                 WHERE asp.id = ?
                   AND (aqfr.rights & ? ) = ?';
        return $DB->get_records_sql($sql, array($this->id, appraisal::ACCESS_CANANSWER, appraisal::ACCESS_CANANSWER));
    }


    /**
     * Get list of pages
     *
     * @param int $stageid
     * @return array of stdClass
     */
    public static function get_list($stageid) {
        global $DB;

        $sql = 'SELECT asp.id, asp.name
                FROM {appraisal_stage_page} asp
                WHERE asp.appraisalstageid = ?
                ORDER BY asp.sortorder';

        return $DB->get_records_sql($sql, array($stageid));
    }


    /**
     * Fetch pages from stage
     *
     * @param int $stageid
     * @return array of stdClass
     */
    public static function fetch_stage($stageid) {
        global $DB;

        $sql = 'SELECT asp.id, asp.appraisalstageid, asp.name, asp.sortorder, COUNT(aqfr.id) hasredisplay
                  FROM {appraisal_stage_page} asp
                  LEFT JOIN {appraisal_quest_field} aqf
                    ON asp.id = aqf.appraisalstagepageid
                  LEFT JOIN {appraisal_quest_field} aqfr
                    ON (' . $DB->sql_cast_2char('aqf.id') . ' = ' . $DB->sql_compare_text('aqfr.param1') . '
                    AND aqfr.datatype = ?)
                 WHERE asp.appraisalstageid = ?
                 GROUP BY asp.id, asp.appraisalstageid, asp.name, asp.sortorder
                 ORDER BY asp.sortorder';
        return $DB->get_records_sql($sql, array('redisplay', $stageid));
    }


    /**
     * Get list of pages that the role has rights to.
     * Notes: By default, all stages where the role can read or write at least one question will be returned.
     * By default, this returns all pages from all stages up to and including the specified stage.
     *
     * @param int $stageid
     * @param int $role
     * @param int $rights appraisal::ACCESS_* Clarify on certain rights
     * @param int $includepreviouspages if false then only return pages from the given stage, not including previous pages
     * @return array of stdClass
     */
    public static function get_applicable_pages($stageid, $role, $rights = 0, $includepreviouspages = true) {
        global $DB;

        $sqlrights = 'aqfr.rights > 0';
        $paramrights = array();
        if ($rights > 0) {
            $sqlrights = '(aqfr.rights & ? ) = ?';
            $paramrights[] = $rights;
            $paramrights[] = $rights;
        }

        if ($includepreviouspages) {
            $thisstage = new appraisal_stage($stageid);
            $allstages = appraisal_stage::get_stages($thisstage->appraisalid);
            $includestageids = array();
            foreach ($allstages as $stage) {
                $includestageids[] = $stage->id;
                if ($thisstage->id == $stage->id) {
                    break;
                }
            }
            if (empty($includestageids)) {
                return array();
            }
            list($sqlstageids, $paramsstageids) = $DB->get_in_or_equal($includestageids);
        } else {
            $sqlstageids = ' = ? ';
            $paramsstageids = array($stageid);
        }

        $sql = "SELECT DISTINCT ap.*, ast.id AS stageid, ast.timedue
                  FROM {appraisal_stage_page} ap
                  JOIN {appraisal_stage} ast
                    ON ap.appraisalstageid = ast.id
                  JOIN {appraisal_quest_field} aqf
                    ON aqf.appraisalstagepageid = ap.id
                  JOIN {appraisal_quest_field_role} aqfr
                    ON aqfr.appraisalquestfieldid = aqf.id
                 WHERE ap.appraisalstageid {$sqlstageids}
                   AND aqfr.appraisalrole = ? AND {$sqlrights}
                 ORDER BY ast.timedue, ast.id, ap.sortorder";
        $params = array_merge($paramsstageids, array($role), $paramrights);
        $pagesrs = $DB->get_records_sql($sql, $params);

        // Process each appraisal.
        $pages = array();
        foreach ($pagesrs as $pagerecord) {
            $pages[$pagerecord->id] = new appraisal_page($pagerecord->id);
        }
        return $pages;
    }


    /**
     * Change relative position of page within same stage
     *
     * @param int $pageid
     * @param int $pos starts with 0
     */
    public static function reorder($pageid, $pos) {
        $page = new appraisal_page($pageid);
        $stage = new appraisal_stage($page->appraisalstageid);
        if (!appraisal::is_draft($stage->appraisalid)) {
            throw new appraisal_exception('Cannot change page of active appraisal');
        }

        db_reorder($pageid, $pos, 'appraisal_stage_page', 'appraisalstageid');
    }


    /**
     * Remove page if possible
     *
     * @param int $pageid
     * @return bool true if deleted
     */
    public static function delete($pageid) {
        global $DB;

        $page = new appraisal_page($pageid);
        $stage = new appraisal_stage($page->appraisalstageid);
        $snapshot = $page->get();
        if (!appraisal::is_draft($stage->appraisalid)) {
            return false;
        }

        $questionrecords = appraisal_question::fetch_page($pageid);
        foreach ($questionrecords as $questrecord) {
            appraisal_question::delete($questrecord->id);
        }

        $DB->delete_records('appraisal_stage_page', array('id' => $page->id));

        \totara_appraisal\event\page_deleted::create_from_instance($snapshot)->trigger();

        return true;
    }


    /**
     * Build page and related questions according given definition
     * @param array $def
     * @param int $stageid
     * @return object
     */
    public static function build(array $def, $stageid) {
        $page = new appraisal_page();
        $page->appraisalstageid = $stageid;
        $page->name = $def['name'];
        $page->save();
        if (isset($def['questions'])) {
            foreach ($def['questions'] as $quest) {
                appraisal_question::build($quest, $page->id);
            }
        }
        return $page;
    }
}


/**
 * Questions and forms (page content)
 */
class appraisal_question extends question_storage {
    /**
     * Appraisal stageid that this page is related to
     *
     * @var type
     */
    public $appraisalstagepageid = null;

    /**
     * Stage position
     *
     * @var int
     */
    public $sortorder = 0;

    /**
     * Roles access
     * Key is appraisal::ROLE_* code, value BITMASK of appraisal::ACCESS_*
     *
     * @var array of roles objects
     */
    protected $roles = array();

    /**
     * Create question instance
     *
     * @param int $id
     * @param stdClass $roleassignment
     */
    public function __construct($id = 0, appraisal_role_assignment $roleassignment = null) {
        $this->answerfield = 'appraisalroleassignmentid';
        $this->prefix = 'appraisal';
        if ($id) {
            $this->id = $id;
            $this->load($roleassignment);
        }
    }


    /**
     * Get read-only access to restricted properies
     * @param string $name
     */
    public function __get($name) {
        if (in_array($name, array('roles', 'elements'))) {
            return $this->$name;
        }
        return parent::__get($name);
    }

    /**
     * Set question properties from form
     *
     * @param stdClass $todb
     * @return $this
     */
    public function set(stdClass $todb) {
        global $DB;

        if (is_null($this->appraisalstagepageid) && isset($todb->appraisalstagepageid)) {
            $this->appraisalstagepageid = $todb->appraisalstagepageid;
        }

        if (isset($todb->name)) {
            $this->name = $todb->name;
        }

        if (isset($todb->sortorder)) {
            $this->sortorder = $todb->sortorder;
        }

        $this->get_element()->define_set($todb);

        // Set roles access.
        if (isset($todb->roles)) {
            $roles = appraisal::get_roles();
            foreach ($roles as $role => $rolename) {
                if (isset($todb->roles[$role])) {
                    if (is_numeric($todb->roles[$role])) {
                        $this->roles[$role] = $todb->roles[$role];
                    } else if (is_array($todb->roles[$role])) {
                        $this->roles[$role] = 0;
                        foreach ($todb->roles[$role] as $access => $isgranted) {
                            if ($isgranted) {
                                $this->roles[$role] |= $access;
                            }
                        }
                    }
                } else {
                    $this->roles[$role] = 0;
                }
            }
        }

        return $this;
    }

    /**
     * Get stdClass with question properties
     *
     * @param $isform destination is form (otherwise db)
     * @return stdClass
     */
    public function get($isform = false) {
        // Get element settings.

        $obj = new stdClass;
        if ($isform) {
            $this->element->define_get($obj);
        } else {
            $this->export_storage_fields($obj);
        }

        $obj->appraisalstagepageid = $this->appraisalstagepageid;
        $obj->name = $this->name;
        $obj->sortorder = $this->sortorder;
        $obj->description = isset($this->description) ? $this->description : '';
        $obj->descriptionformat = isset($this->descriptionformat) ? $this->descriptionformat : '';
        $obj->defaultdataformat = isset($this->defaultdataformat) ? $this->defaultdataformat : '';
        $obj->appraisalscaleid = isset($this->appraisalscaleid) ? $this->appraisalscaleid : '';

        // Get roles access.
        $obj->roles = $this->roles;
        return $obj;
    }


    /**
     * Save question to database
     *
     * @return appraisal_question
     */
    public function save() {
        global $DB;
        $todb = $this->get();
        $this->export_storage_fields($todb);
        if ($todb->appraisalstagepageid < 1) {
            throw new appraisal_exception('Question must belong to an appraisal page', 32);
        }

        $page = new appraisal_page($todb->appraisalstagepageid);
        $stage = new appraisal_stage($page->appraisalstageid);
        if (!appraisal::is_draft($stage->appraisalid)) {
            throw new appraisal_exception('Cannot change question of active appraisal');
        }

        // Fix sort order.
        $sameplace = false;
        if ($this->id) {
            $sameplace = $DB->record_exists('appraisal_quest_field', array('id' => $this->id, 'sortorder' => $this->sortorder,
                'appraisalstagepageid' => $this->appraisalstagepageid));
        }
        if (!$sameplace) {
            // Put as last item.
            $sqlorder = 'SELECT sortorder
                    FROM {appraisal_quest_field}
                    WHERE appraisalstagepageid = ?
                      AND id <> ?
                    ORDER BY sortorder DESC';
            $neworder = $DB->get_record_sql($sqlorder, array($todb->appraisalstagepageid, (int)$this->id), IGNORE_MULTIPLE);
            if (!$neworder) {
                $todb->sortorder = 0;
            } else {
                $todb->sortorder = $neworder->sortorder + 1;
            }
        }

        if ($this->id > 0) {
            $todb->id = $this->id;
            $DB->update_record('appraisal_quest_field', $todb);

            \totara_appraisal\event\question_updated::create_from_instance($this, $stage->id)->trigger();
        } else {
            $todb->datatype = $this->datatype;
            $this->id = $DB->insert_record('appraisal_quest_field', $todb);

            \totara_appraisal\event\question_created::create_from_instance($this, $stage->id)->trigger();
        }

        // Save roles access for quesiton.
        $this->save_roles();

        return $this;
    }


    /**
     * Load quesiton from database
     *
     * @param appraisal_role_assignment $roleassignment
     * @return appraisal_question $this
     */
    public function load(appraisal_role_assignment $roleassignment = null) {
        global $DB;

        // Load data.
        $quest = $DB->get_record('appraisal_quest_field', array('id' => $this->id));
        if (!$quest) {
            throw new appraisal_exception('Cannot load quest field', 31);
        }

        $this->id = $quest->id;
        $this->name = $quest->name;
        $this->appraisalstagepageid = $quest->appraisalstagepageid;
        $this->sortorder = $quest->sortorder;
        $this->import_storage_fields($quest);

        if ($roleassignment) {
            $questionman = new question_manager($roleassignment->subjectid, $roleassignment->id);
        } else {
            $questionman = new question_manager();
        }
        $this->attach_element($questionman->create_element($this));
        $this->load_roles();
    }


    /**
     * Save roles permissions
     *
     * @return appraisal_question $this
     */
    protected function save_roles() {
        global $DB;

        $page = new appraisal_page($this->appraisalstagepageid);
        $stage = new appraisal_stage($page->appraisalstageid);
        if (!appraisal::is_draft($stage->appraisalid)) {
            throw new appraisal_exception('Cannot change question of active appraisal');
        }

        $roles = appraisal::get_roles();
        foreach ($roles as $role => $rolename) {
            if (isset($this->roles[$role]) && $this->roles[$role] > 0) {
                $dbrole = $DB->get_record('appraisal_quest_field_role',
                        array('appraisalquestfieldid' => $this->id, 'appraisalrole' => $role));

                if (!$dbrole) {
                    $dbrole = new stdClass();
                    $dbrole->appraisalquestfieldid = $this->id;
                    $dbrole->appraisalrole = $role;
                    $dbrole->rights = $this->roles[$role];
                    $DB->insert_record('appraisal_quest_field_role', $dbrole);
                } else if ($dbrole->rights != $this->roles[$role]) {
                    $dbrole->rights = $this->roles[$role];
                    $DB->update_record('appraisal_quest_field_role', $dbrole);
                }
            } else {
                $DB->delete_records('appraisal_quest_field_role',
                        array('appraisalquestfieldid' => $this->id, 'appraisalrole' => $role));
            }
        }
        return $this;
    }


    /**
     * Load stage roles permissions/settings from DB
     *
     * @return appraisal_question $this
     */
    protected function load_roles() {
        global $DB;

        $rolesdb = $DB->get_records('appraisal_quest_field_role', array('appraisalquestfieldid' => $this->id));

        $this->roles = array();

        if ($rolesdb) {
            foreach ($rolesdb as $role) {
                $this->roles[$role->appraisalrole] = $role->rights;
            }
        }

        return $this;
    }


    /**
     * Attach element to question
     *
     * @param mixed $elem question_base, or stdClass, or string with element name
     */
    public function attach_element($elem) {
        if ($elem instanceof question_base) {
            return $this->element = $elem;
        }
        // Add default element (without edit support).
        $manager = new question_manager();
        $this->element = $manager->create_element($this, $elem);
    }


    /**
     * Get attached element instane
     *
     * @return question_base
     */
    public function get_element($roleid = null) {
        return $this->element;
    }


    /**
     * Move question to another page
     *
     * @param int $pageid
     */
    public function move($pageid) {
        $this->appraisalstagepageid = $pageid;
        $this->save();

        // Reorder to put question at top.
        self::reorder($this->id, 0);
    }


    /**
     * Duplicate a question given a new page id to duplicate onto
     *
     * @param int $pageid
     * @return appraisal_question
     */
    public function duplicate($pageid) {
        // Saving original element.
        $oldelement = clone($this->get_element());
        $srcid = $this->id;
        $this->id = 0;
        $this->appraisalstagepageid = $pageid;
        $this->save();
        $newquestion = clone($this);
        $this->id = $srcid;
        $this->load();
        $newquestion->load();
        $element = $newquestion->get_element();
        $element->duplicate($oldelement);

        return $newquestion;
    }


    /**
     * Check if user can view others answer based of assignment of (possibly) other user.
     * For example, manager has "view other" permission on learners answer. This method will check manager's id
     * against learner assignment id and return true (yes, manager userid can view answer given by learners roleassignmnent id).
     *
     * @param int $roleassignmentid roleassignmentid that gave answer on question
     * @param int $userid User that want to see answer
     * @return bool
     */
    public function user_can_view($roleassignmentid, $userid) {
        global $DB;

        $answerroleassignment = new appraisal_role_assignment($roleassignmentid);

        // Users can always see their own answers.
        if ($userid == $answerroleassignment->userid) {
            return true;
        }

        $userassignment = $answerroleassignment->get_user_assignment();

        // We don't know what role the viewer has, and they may have more than one, so find and check them all.
        $sql = "SELECT appraisalrole
                  FROM {appraisal_role_assignment}
                 WHERE appraisaluserassignmentid = :userassignmentid
                   AND userid = :viewerid";
        $viewerroleassignments = $DB->get_records_sql($sql, array('userassignmentid' => $userassignment->id, 'viewerid' => $userid));

        foreach ($viewerroleassignments as $assignment) {
            if (!empty($this->roles[$assignment->appraisalrole]) &&
                ($this->roles[$assignment->appraisalrole] & appraisal::ACCESS_CANVIEWOTHER == appraisal::ACCESS_CANVIEWOTHER)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Determine if the question is locked for data entry.
     *
     * @param appraisal_role_assignment $roleassignment
     * @return bool
     */
    public function is_locked(appraisal_role_assignment $roleassignment) {
        $page = new appraisal_page($this->appraisalstagepageid);
        return $page->is_locked($roleassignment);
    }


    /**
     * Determine if the question is an invalid redisplay question.
     * Will be true if it is a redisplay question and the question it links to is on the same page or a following page.
     *
     * @return boolean true if it is invalid
     */
    public function is_invalid_redisplay() {
        global $DB;

        if ($this->datatype != 'redisplay') {
            return false;
        }

        $sql = "SELECT ast.timedue AS stagetimedue, ast.id AS stagesortorder, asp.sortorder AS pagesortorder, aqf.param1
                  FROM {appraisal_quest_field} aqf
                  JOIN {appraisal_stage_page} asp
                    ON aqf.appraisalstagepageid = asp.id
                  JOIN {appraisal_stage} ast
                    ON asp.appraisalstageid = ast.id
                 WHERE aqf.id = ?";
        $redisplay = $DB->get_record_sql($sql, array($this->id), MUST_EXIST);
        $original = $DB->get_record_sql($sql, array($redisplay->param1), MUST_EXIST);

        // This will check if the stages are out of order or not.
        if (empty($original->stagetimedue)) {
            if (empty($redisplay->stagetimedue)) {
                if ($original->stagesortorder < $redisplay->stagesortorder) {
                    // No original time due, no redisplay time due, in order sortorder.
                    return false;
                } else if ($redisplay->stagesortorder < $original->stagesortorder) {
                    // No original time due, no redisplay time due, out of order sortorder.
                    return true;
                }
                // Else same stage.
            } else {
                // No original time due, has redisplay time due.
                return false;
            }
        } else {
            if (empty($redisplay->stagetimedue)) {
                // Has original time due, no redisplay time due.
                return true;
            } else {
                if ($original->stagetimedue < $redisplay->stagetimedue) {
                    // Has original time due, has redisplay time due, in date order.
                    return false;
                } else if ($redisplay->stagetimedue < $original->stagetimedue) {
                    // Has original time due, has redisplay time due, out of date order.
                    return true;
                } else if ($original->stagetimedue == $redisplay->stagetimedue) {
                    if ($original->stagesortorder < $redisplay->stagesortorder) {
                        // Same time due, in order sortorder.
                        return false;
                    } else if ($redisplay->stagesortorder < $original->stagesortorder) {
                        // Same time due, out of order sortorder.
                        return true;
                    }
                    // Else same stage.
                }
            }
        }

        // We now know we're on the same stage (all different-stage cases were handled above), so check page order.
        if ($original->pagesortorder < $redisplay->pagesortorder) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * Return array of roles involved in current question
     *
     * @param int $rights count only roles that have certain rights
     * @return array of appraisalrole
     */
    public function get_roles_involved($rights = 0) {
        global $DB;

        $sqlrights ='';
        $params = array($this->id);
        if ($rights > 0) {
            $sqlrights = ' AND (aqfr.rights & ? ) = ? ';
            $params[] = $rights;
            $params[] = $rights;
        }
        $sql = "SELECT DISTINCT aqfr.appraisalrole
                  FROM {appraisal_stage} ast
                  LEFT JOIN {appraisal_stage_page} asp
                    ON asp.appraisalstageid = ast.id
                  LEFT JOIN {appraisal_quest_field} aqf
                    ON aqf.appraisalstagepageid = asp.id
                  LEFT JOIN {appraisal_quest_field_role} aqfr
                    ON aqfr.appraisalquestfieldid = aqf.id AND aqfr.rights > 0
                 WHERE aqf.id = ? {$sqlrights}
                 ORDER BY aqfr.appraisalrole";
        $rolesrecords = $DB->get_records_sql($sql, $params);

        $out = array();
        foreach ($rolesrecords as $rolerecord) {
            $out[$rolerecord->appraisalrole] = 1;
        }
        return array_keys($out);
    }

    /**
     * Add roles information to question elements
     * @param appraisal_role_assignment $roleassignment
     * @param array $otherassignments
     * @return bool user's role can answer on this question
     */
    public function populate_roles_element(appraisal_role_assignment $roleassignment,
            $otherassignments, $nouserpic = false) {
        global $OUTPUT, $DB;
        $questroles = $this->roles;
        unset($questroles[$roleassignment->appraisalrole]);
        $rolecodestrings = appraisal::get_roles();
        $isviewonlyquestion = true;
        foreach ($questroles as $eachrole => $rights) {
            if (isset($otherassignments[$eachrole]) && ($rights & appraisal::ACCESS_CANANSWER) == appraisal::ACCESS_CANANSWER) {
                $isviewonlyquestion = false;
                // Show role user icon.
                $subjectid = $otherassignments[$eachrole]->userid;

                // Add information about other roles to element.
                $questioninfo = new question_manager($subjectid, $otherassignments[$eachrole]->id);
                $questioninfo->viewonly = true;
                if (!$nouserpic && $subjectid != 0) {
                    $subject = $DB->get_record('user', array('id' => $subjectid));
                    $questioninfo->userimage = $OUTPUT->user_picture($subject);
                } else {
                   $questioninfo->userimage = '';
                }
                $questioninfo->label = get_string('role_answer_' . $rolecodestrings[$eachrole], 'totara_appraisal');
                $this->get_element()->add_question_role_info($eachrole, $questioninfo);
            }
        }
        return $isviewonlyquestion;
    }

    /**
     * Adding custom validation to the appraisal question.
     *
     * This can be used if appraisal questions need to validate with extra rules which are not defined in the core
     * questions classes.
     *
     * @param question_base $element
     * @param array    $data
     *
     */
    public static function add_custom_validation(question_base $element, array $data) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/question/field/multichoice.class.php');
        $errors = [];

        if ($element instanceof multichoice) {
            // Add validation only for new options
            if (isset($data['selectchoices']) && $data['selectchoices'] == 0) {
                for ($i = 0; $i < $element::MAX_CHOICES; $i++) {
                    if (isset($data['choice'][$i]['option'])) {
                        if (core_text::strlen($data['choice'][$i]['option']) > 255) {
                            $errors["choice[{$i}]"] = get_string('maximumchars', '', 255);
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Get all questions of the page.
     *
     * @param int $pageid
     * @return array
     */
    public static function fetch_page($pageid) {
        global $DB;

        return $DB->get_records('appraisal_quest_field', array('appraisalstagepageid' => $pageid), 'sortorder');
    }


    /**
     * Get all questions from page for role
     *
     * @param int $pageid
     * @param appraisal_role_assignment $roleassignment
     * @return appraisal_question[]
     */
    public static function fetch_page_role($pageid, appraisal_role_assignment $roleassignment) {
        global $DB;

        $sql = 'SELECT aqf.id
                  FROM {appraisal_quest_field} aqf
                  JOIN {appraisal_quest_field_role} aqfr
                    ON aqf.id = aqfr.appraisalquestfieldid
                 WHERE aqf.appraisalstagepageid = ?
                   AND aqfr.appraisalrole = ?
                   AND aqfr.rights > 0
                 ORDER BY aqf.sortorder';
        $records = $DB->get_records_sql($sql, array($pageid, $roleassignment->appraisalrole));

        $questions = array();
        foreach ($records as $record) {
            $question = new appraisal_question($record->id, $roleassignment);
            if ($question->get_element()->get_type() == 'redisplay') {
                $redisplayrolerights = $question->roles;
                $question = new appraisal_question($question->get_element()->param1, $roleassignment);
                foreach ($redisplayrolerights as $role => $redisplayrights) {
                    // We already know that $redisplayrights > 0, since that was checked in the query.
                    // It must include ACCESS_CANANSWER, otherwise ACCESS_CANVIEWOTHER couldn't be set.
                    if ($redisplayrights & appraisal::ACCESS_CANVIEWOTHER) {
                        // Add ACCESS_CANVIEWOTHER to the base question's rights.
                        if (isset($question->roles[$role])) {
                            $question->roles[$role] = $question->roles[$role] | appraisal::ACCESS_CANVIEWOTHER;
                        } else {
                            $question->roles[$role] = appraisal::ACCESS_CANVIEWOTHER;
                        }
                    }
                    // Else just leave the permissions that are on the base question.
                }
            }
            $questions[] = $question;
        }

        return $questions;
    }


    /**
     * Get all questions of appraisal, restricted by options.
     *
     * @param int $appraisalid
     * @param int $role only questions this role is involved with (can see or answer)
     * @param int $rights only questions that roles have the specified rights to
     * @param int $datatypes only questions that have one of the specified datatypes
     * @param bool $instances reutrn appraisal_question instances instead of stdClass
     * @return array
     */
    public static function fetch_appraisal($appraisalid, $role = null, $rights = null, $datatypes = array(), $instances = false) {
        global $DB;

        $params = array();

        $rrjoinsql = '';
        if (isset($role) || isset($rights)) {
            $rrjoinsql .= 'JOIN (SELECT DISTINCT appraisalquestfieldid
                                   FROM {appraisal_quest_field_role}
                                  WHERE 1=1';
            if (isset($role)) {
                $rrjoinsql .= ' AND appraisalrole = ?';
                $params[] = $role;
            }
            if (isset($rights)) {
                $rrjoinsql .= ' AND (rights & ?) = ?';
                $params[] = $rights;
                $params[] = $rights;
            }
            $rrjoinsql .= ') aqfr ON aqf.id = aqfr.appraisalquestfieldid';
        }

        $params[] = $appraisalid;

        $datatypessql = '';
        if (!empty($datatypes)) {
            list($sqldatatypes, $paramsdatatypes) = $DB->get_in_or_equal($datatypes);
            $datatypessql = 'AND aqf.datatype ' . $sqldatatypes;
            $params = array_merge($params, $paramsdatatypes);
        }

        $sql = "SELECT aqf.*, asp.appraisalstageid, asp.sortorder AS pagesortorder, ast.timedue AS stagetimedue
                  FROM {appraisal_quest_field} aqf
                  JOIN {appraisal_stage_page} asp
                    ON aqf.appraisalstagepageid = asp.id
                  JOIN {appraisal_stage} ast
                    ON asp.appraisalstageid = ast.id
                       {$rrjoinsql}
                 WHERE ast.appraisalid = ? {$datatypessql}
                 ORDER BY ast.timedue, ast.id, asp.sortorder, aqf.sortorder";

        $questionrs = $DB->get_records_sql($sql, $params);
        if ($instances) {
            $questions = array();
            foreach ($questionrs as $key => $questdata) {
                $questions[$key] = new appraisal_question($questdata->id);
            }
            return $questions;
        }
        return $questionrs;
    }

    /**
     * MySQL only: Calculate if we are (close to) hitting the internal row size limit
     * when creating mdl_appraisal_quest_data_* table for this appraisal.
     *
     * For MySQL the *internal* storage of one row is usually limited to a bit less
     * than 8KB and creating a table e.g. with 190 columns of type LONGTEXT will just
     * exceed that because of minimum bytes required for pointers to external storage
     * of row data etc.
     *
     * We try to find out if the questions in this appraisal are coming close to
     * causing a row size limit error, so we can give a warning.
     *
     * The mentioned 8KB limit can be adjusted by changing MySQL config innodb_page_size
     * but this is rarely done and given that this is a rare problem anyway, we assume
     * the default configuration. If cases occur where this is not accurate enough,
     * this calculation could be refined by taking non-default config into account.
     *
     * @param int $appraisalid
     * @param int $max_bytes  Mainly for testing purposes. Defaults to 8000.
     * @return bool
     */
    public static function has_too_many_questions($appraisalid, $max_bytes = 8000) {
        global $DB;

        if ($DB->get_dbfamily() != 'mysql') {
            // We haven't had problems with hitting limits on pgsql or sqlsrv yet.
            return false;
        }

        $map_type_to_row_bytes = [
            'text' => 43, // Text
            'datepicker' => 11, // Integer
            'fileupload' => 11,
            'longtext' => 65, // Text + 2*Integer
            'multichoicemulti' => 11,
            'multichoicesingle' => 11,
            'ratingcustom' => 54, // Text + Integer
            'ratingnumeric' => 11,
        ];

        $estimated_bytes = 0;
        $questions = self::fetch_appraisal($appraisalid);
        foreach ($questions as $question) {
            if (isset($map_type_to_row_bytes[$question->datatype])) {
                $estimated_bytes += $map_type_to_row_bytes[$question->datatype];
            }
        }

        return ($estimated_bytes > $max_bytes);
    }


    /**
     * Get list of questions and count the number of redisplay items pointing to it.
     *
     * @param int $pageid
     * @return array of stdClass
     */
    public static function get_list_with_redisplay($pageid) {
        global $DB;

        $sql = 'SELECT aqf.id, aqf.name, aqf.datatype, COUNT(aqfr.id) hasredisplay
                  FROM {appraisal_quest_field} aqf
                  LEFT JOIN {appraisal_quest_field} aqfr
                    ON (' . $DB->sql_cast_2char('aqf.id') . ' = ' . $DB->sql_compare_text('aqfr.param1') . '
                    AND aqfr.datatype = ?)
                 WHERE aqf.appraisalstagepageid = ?
                 GROUP BY aqf.id, aqf.name, aqf.datatype, aqf.sortorder
                 ORDER BY aqf.sortorder';
        return $DB->get_records_sql($sql, array('redisplay', $pageid));
    }


    /**
     * Change relative position of question within same page
     *
     * @param int $id
     * @param int $pos starts with 0
     */
    public static function reorder($questionid, $pos) {
        $question = new appraisal_question($questionid);
        $page = new appraisal_page($question->appraisalstagepageid);
        $stage = new appraisal_stage($page->appraisalstageid);
        if (!appraisal::is_draft($stage->appraisalid)) {
            throw new appraisal_exception('Cannot change page of active appraisal');
        }

        db_reorder($questionid, $pos, 'appraisal_quest_field', 'appraisalstagepageid');
    }


    /**
     * Remove question if possible
     *
     * @param int $questid
     * @return bool true if successful
     */
    public static function delete($questid) {
        global $DB;
        $question = new appraisal_question($questid);
        $snapshot = $question->get();

        $page = new appraisal_page($question->appraisalstagepageid);
        $stage = new appraisal_stage($page->appraisalstageid);
        // We need to be sure that all relations to appraisal answers are cleaned.
        if (!appraisal::is_draft($stage->appraisalid)) {
            return false;
        }

        try {
            $question->get_element()->delete();
        } catch (Exception $e) {
            // Delete even if element was badly broken.
        }
        $DB->delete_records('appraisal_quest_field_role', array('appraisalquestfieldid' => $question->id));
        $DB->delete_records('appraisal_quest_field', array('id' => $question->id));

        \totara_appraisal\event\question_deleted::create_from_instance($snapshot, $stage->id)->trigger();

        return true;
    }


    /**
     * Build questions according given definition
     * @param array $def
     * @param int $stageid
     * @return appraisal_question
     */
    public static function build(array $def, $pageid) {
        $quest = new appraisal_question();
        $quest->appraisalstagepageid = $pageid;
        $quest->name = $def['name'];
        $quest->roles = $def['roles'];
        $quest->attach_element($def['type']);
        $quest->save();
        return $quest;
    }
}


/**
 * Exceptions related to appraisal
 */
class appraisal_exception extends Exception {
}


/**
 * Appraisal event notification
 */
class appraisal_message {
    /**
     * Event types for stages
     */
    const EVENT_APPRAISAL_ACTIVATION = 'appraisal_activation';
    const EVENT_STAGE_COMPLETE = 'appraisal_stage_completion';
    const EVENT_STAGE_DUE = 'stage_due';
    const EVENT_APPRAISAL_CLOSURE = 'appraisal_closure';

    /**
     * Only send messages if they are in the appropriate state.
     * This currently only applies to stage due messages.
     */
    const MESSAGE_SEND_ANY_STATE = 0;
    const MESSAGE_SEND_ONLY_COMPLETE = 1;
    const MESSAGE_SEND_ONLY_INCOMPLETE = -1;

    /**
     * Period types for messages before/after event
     */
    const PERIOD_DAY = 1;
    const PERIOD_WEEK = 2;
    const PERIOD_MONTH = 3;
    /**
     * Event id
     * @var int
     */
    protected $id = 0;
    /**
     * Appraisal id for appraisal activation event
     * @var int
     */
    protected $appraisalid = 0;

    /**
     * Stage if for stage complete and stage due events
     * @var type
     */
    protected $stageid = 0;

    /**
     * Event type
     * @var string
     */
    protected $type = '';

    /**
     * Time before/after event
     * @var int
     */
    protected $delta = 0;

    /**
     * Period of time before/after event
     * @var int
     */
    protected $deltaperiod = 0;

    /**
     * Roles that will receive message on event
     * @var array
     */
    protected $roles = array();

    /**
     * Messages to be sent
     *
     * @var array of stdClass (appraisal_event_message rows)
     */
    protected $messages = array();

    /**
     * Restrictions of sending message according stage
     * @var int
     */
    protected $stageiscompleted = self::MESSAGE_SEND_ANY_STATE;

    /**
     * Event was triggered
     * @var int
     */
    protected $triggered = 0;

    /**
     * Event was triggered during load
     * @var int
     */
    protected $wastriggered = 0;

    /**
     * Time scheduled - when to trigger postponed event (only if it's not immediate).
     * @var int
     */
    protected $timescheduled = 0;

    /**
     * Array of available placeholders.
     * @var array
     */
    public static $placeholders = [
        // Site.
        'sitename',
        'siteurl',

        // Appraisal.
        'appraisalname',
        'appraisaldescription',
        'expectedappraisalcompletiondate',

        // Stage.
        'listofstagenames',
        'currentstagename',
        'expectedstagecompletiondate',
        'previousstagename',

        // The appraisee.
        'userusername',
        'userfirstname',
        'userlastname',
        'userfullname',

        // Manager.
        'managerusername',
        'managerfirstname',
        'managerlastname',
        'managerfullname',

        // Team lead.
        'managersmanagerusername',
        'managersmanagerfirstname',
        'managersmanagerlastname',
        'managersmanagerfullname',

        // Appraiser.
        'appraiserusername',
        'appraiserfirstname',
        'appraiserlastname',
        'appraiserfullname',
    ];

    /**
     * Create instance appraisal notification
     */
    public function __construct($id = 0) {
        if ($id) {
            $this->load($id);
        }
    }


    /**
     * Read-only access to properties
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }


    /**
     * Set event type to appraisal activation
     *
     * @param type $appraisalid
     */
    public function event_appraisal($appraisalid, $type = self::EVENT_APPRAISAL_ACTIVATION) {
        $this->appraisalid = $appraisalid;
        $this->stageid = 0;
        $this->type = $type;
    }


    /**
     * Set event type to stage complete/due
     *
     * @param int $stageid
     * @param string $type appraisal_message::EVENT_STAGE_*
     */
    public function event_stage($stageid, $type) {
        if (!in_array($type, array(self::EVENT_STAGE_COMPLETE, self::EVENT_STAGE_DUE))) {
            throw new appraisal_exception('Unknown event type');
        }
        $stage = new appraisal_stage($stageid);
        $this->appraisalid = $stage->appraisalid;
        $this->stageid = $stageid;
        $this->type = $type;
    }


    /**
     * Set period before/after event it should be run
     *
     * @param int $delta
     * @param int $period
     */
    public function set_delta($delta, $period = 0) {
        if ($delta != 0) {
            if (!in_array($period, array(self::PERIOD_DAY, self::PERIOD_WEEK, self::PERIOD_MONTH))) {
                throw new appraisal_exception('Unknown period before/after event');
            }
        }
        $this->delta = $delta;
        $this->deltaperiod = ($delta == 0) ? 0 : $period;
    }


    /**
     * Set roles that should receive message events.
     * stageiscompleted other than MESSAGE_SEND_ANY_STATE is only used if the event type is EVENT_STAGE_DUE.
     *
     * @param array $roles of int
     * @param int $stageiscompleted self::MESSAGE_SEND_XXX
     */
    public function set_roles(array $roles, $stageiscompleted = self::MESSAGE_SEND_ANY_STATE) {
        $this->roles = $roles;
        $this->stageiscompleted = $stageiscompleted;
    }


    /**
     * Set messages that should be send for each role (0 - for all roles)
     * @param int $role
     * @param string $title
     * @param string $body
     */
    public function set_message($role, $title, $body) {
        if ($role == 0) {
            $keep = isset($this->messages[0]) ? $this->messages[0] : null;
            $this->messages = array();
            $this->messages[0] = $keep;
        } else {
            unset($this->messages[0]);
        }
        if (!isset($this->messages[$role])) {
            $this->messages[$role] = new stdClass();
        }
        $this->messages[$role]->name = $title;
        $this->messages[$role]->content = $body;
    }


    /**
     * Save message event
     */
    public function save() {
        global $DB;
        if ($this->wastriggered && $this->triggered) {
            throw new appraisal_exception('Cannot change event that was triggered');
        }
        if (empty($this->roles)) {
            throw new appraisal_exception('Roles must be defined');
        }
        $eventdb = new stdClass();
        $eventdb->id = $this->id;
        $eventdb->appraisalid = $this->appraisalid;
        $eventdb->appraisalstageid = $this->stageid;
        $eventdb->event = $this->type;
        $eventdb->delta = $this->delta;
        $eventdb->deltaperiod = $this->deltaperiod;
        $eventdb->stageiscompleted = $this->stageiscompleted;
        $eventdb->triggered = $this->triggered;
        $eventdb->timescheduled = $this->timescheduled;

        $transaction = $DB->start_delegated_transaction();
        if ($eventdb->id > 0) {
            $DB->update_record('appraisal_event', $eventdb);
        } else {
            $this->id = $DB->insert_record('appraisal_event', $eventdb);
            $eventdb->id = $this->id;
        }

        self::clean_messages($eventdb->id);
        foreach ($this->messages as $message) {
            $message->appraisaleventid = $eventdb->id;
            $message->id = $DB->insert_record('appraisal_event_message', $message);
        }
        try {
            foreach ($this->roles as $role) {
                $roledb = new stdClass();
                $messageid = isset($this->messages[0]) ? $this->messages[0]->id : $this->messages[$role]->id;
                if (!$messageid) {
                    throw new appraisal_exception('Role must have a message');
                }
                $roledb->appraisaleventmessageid = $messageid;
                $roledb->appraisalrole = $role;
                $DB->insert_record('appraisal_event_rcpt', $roledb);
            }
        } catch (Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
        $transaction->allow_commit();
    }


    /**
     * Load event message from database
     *
     * @param int $id
     */
    public function load($id) {
        global $DB;
        $this->id = $id;
        $eventdb = $DB->get_record('appraisal_event', array('id' => $id), '*', MUST_EXIST);
        $this->appraisalid = $eventdb->appraisalid;
        $this->stageid = $eventdb->appraisalstageid;
        $this->type = $eventdb->event;
        $this->delta = $eventdb->delta;
        $this->deltaperiod = $eventdb->deltaperiod;
        $this->stageiscompleted = $eventdb->stageiscompleted;
        $this->triggered = $eventdb->triggered;
        $this->wastriggered = $eventdb->triggered;
        $this->timescheduled = $eventdb->timescheduled;
        $messages = $DB->get_records('appraisal_event_message', array('appraisaleventid' => $id));
        $ids = array_keys($messages);
        list($msgids, $msgparams) = $DB->get_in_or_equal($ids);
        $rcptsql = 'SELECT * FROM {appraisal_event_rcpt} WHERE appraisaleventmessageid '. $msgids;
        $rcpts = $DB->get_records_sql($rcptsql, $msgparams);
        $this->messages = array();
        $this->roles = array();
        $allsame = true;
        $firstmsgid = current($rcpts)->appraisaleventmessageid;
        foreach ($rcpts as $rcpt) {
            if ($firstmsgid != $rcpt->appraisaleventmessageid) {
                $allsame = false;
            }
            $this->messages[$rcpt->appraisalrole] = $messages[$rcpt->appraisaleventmessageid];
            $this->roles[] = $rcpt->appraisalrole;
        }
        if ($allsame) {
            $this->messages = array($messages[$rcpt->appraisaleventmessageid]);
        }
    }


    /**
     * Reset event if it was triggered
     */
    public function reset() {
        $this->triggered = false;
    }


    /**
     * Schedule event to send message at time (it doesn't affect immediate messages)
     *
     * @param int $time server time
     */
    public function schedule($time) {
        $this->timescheduled = $time;
    }


    /**
     * Get messages sending time based on given time and event settings
     *
     * @param int $basetime time when scheduled event (should) happen
     * @return int time when messages should be sent
     */
    public function get_schedule_from($basetime) {
        $multiplier = 0;
        switch ($this->deltaperiod) {
            case self::PERIOD_DAY:
                $multiplier = 86400;
                break;
            case self::PERIOD_WEEK:
                $multiplier = 604800;
                break;
            case self::PERIOD_MONTH:
                $multiplier = 2592000;
                break;
        }
        if ($this->type == self::EVENT_STAGE_DUE) {
            $stage = new appraisal_stage($this->stageid);
            $basetime = $stage->timedue;
        }
        $delta = $this->delta * $multiplier;
        return $basetime + $delta;
    }


    /**
     * If current time is time for sending messages.
     *
     * @param int $time
     * @return bool
     */
    public function is_time($time) {
        return ($this->timescheduled > 0 && $this->timescheduled <= $time);
    }


    /**
     * Is this event immediate to send message
     *
     * @return bool
     */
    public function is_immediate() {
        return $this->delta == 0 && $this->deltaperiod == 0;
    }



    /**
     * This is an appraisal-wide message.
     * Send it to all users in the appraisal, and flag that it is sent in the appraisal_event triggered field.
     *
     * @param  bool $managers_only true if the messages should only be sent to non-learner roles
     * @return bool true if there were no problems
     */
    public function send_appraisal_wide_message($managers_only=false) {
        global $DB;

        // Don't send this message if it has already been sent.
        // Managers only messages are sent multiple times whenever assignment is done
        if (!$managers_only && $this->triggered) {
            return;
        }

        // Get all user assignments currently in the appraisal.
        $userassignments = $DB->get_records_select('appraisal_user_assignment',
            'appraisalid = :appraisalid AND status != :statusclosed',
            array('appraisalid' => $this->appraisalid, 'statusclosed' => appraisal::STATUS_CLOSED));

        $this->send($userassignments, $managers_only);

        // Update the database, so that we don't send this message again.
        // TODO: Need better handling for managers only
        if (!$managers_only) {
            $this->triggered = 1;
            $this->save();
        }
    }


    /**
     * Function to send closure messages on the cron.
     */
    public function send_appraisal_wide_closure_message() {
        global $DB;

        // Don't send this message if it has already been sent.
        if ($this->triggered) {
            return;
        }

        // Get the message content, which may be different depending on the role.
        $appraisal = new appraisal($this->appraisalid);
        $message = $this->get_message(appraisal::ROLE_LEARNER);

        $alert = new stdClass();
        $alert->userfrom = core_user::get_support_user();
        $alert->fullmessageformat = FORMAT_HTML;

        // Send message to learners.
        $alert->subject = $message->name;
        $alert->fullmessage = $message->content;
        $alert->fullmessagehtml = $message->content;

        $learnersql = "SELECT u.*
                        FROM {appraisal_user_assignment} aua
                   LEFT JOIN {user} u
                          ON aua.userid = u.id
                       WHERE aua.appraisalid = :appraisalid
                         AND aua.timecompleted IS NULL
                         AND aua.status = :status";
        $learnerparams = array('appraisalid' => $this->appraisalid, 'status' => appraisal::STATUS_CLOSED);
        $learners = $DB->get_records_sql($learnersql, $learnerparams);

        foreach ($learners as $learner) {
            $alert->userto = $learner;
            tm_alert_send($alert);
        }

        // Send message to role users (other than learners, and only one message per user).
        $a = new stdClass();
        $a->name = $appraisal->name;
        $a->alerttitle = $message->name;
        $a->alertbody = $message->content;
        $alert->subject = get_string('closealerttitledefault', 'totara_appraisal', $a);

        // Find all users in roles that are not learner, who have a learner who is not finished.
        $staffuid = $DB->sql_group_concat($DB->sql_cast_2char('usr.id'), ',');
        $rolesql = "SELECT ara.userid AS id, {$staffuid} AS staff
                      FROM {appraisal_user_assignment} aua
                      JOIN {appraisal_role_assignment} ara ON aua.id = ara.appraisaluserassignmentid
                      JOIN {user} usr ON aua.userid = usr.id
                     WHERE aua.timecompleted IS NULL
                       AND aua.appraisalid = :appraisalid
                       AND aua.status = :status
                       AND ara.appraisalrole != :roletype
                       AND ara.userid <> 0
                  GROUP BY ara.userid";
        $roleparams = array('appraisalid' => $this->appraisalid, 'status' => appraisal::STATUS_CLOSED, 'roletype' => appraisal::ROLE_LEARNER);
        $roleusers = $DB->get_records_sql($rolesql, $roleparams);

        // Collect all distinct staff ids.
        $staffids = array();
        foreach ($roleusers as $roleuser) {
            $ids = explode(',', $roleuser->staff);
            $staffids = array_merge($staffids, $ids);
        }

        if ($staffids) {
            // Get data from DB for staff names.
            $userfieldssql = get_all_user_name_fields(true);
            list($staffsql, $staffparams) = $DB->get_in_or_equal($staffids);
            $staff = $DB->get_records_sql("SELECT id, {$userfieldssql} FROM {user} WHERE id {$staffsql}", $staffparams);
        } else {
            $staff = array();
        }

        unset($staffids);

        // Create array of staff names keyed by id.
        $staffnames = array();
        foreach ($staff as $staffmember) {
            $staffnames[$staffmember->id] = fullname($staffmember);
        }

        foreach ($roleusers as $roleuser) {
            $staff = explode(',', $roleuser->staff);

            $affectedstaff = array();
            foreach ($staff as $staffmember) {
                $affectedstaff[] = $staffnames[$staffmember];
            }

            $a->staff = implode(', ', $affectedstaff);
            $alert->fullmessage = get_string('closealertadminbody', 'totara_appraisal', $a);
            $alert->fullmessagehtml = $alert->fullmessage;
            // Get full user record for message.
            $alert->userto = core_user::get_user($roleuser->id);
            tm_alert_send($alert);
        }

        $this->triggered = 1;
        $this->save();
    }


    /**
     * Send this message to all roles associated with the given user.
     *
     * @param  bool $managers_only true if the messages should only be sent to non-learner roles
     * @param int $userid the user id of the learner that this event relates to.
     */
    public function send_user_specific_message($userid, $managers_only=false) {
        global $DB;

        $userassignment = $DB->get_record('appraisal_user_assignment',
            array('userid' => $userid, 'appraisalid' => $this->appraisalid));

        if ($userassignment->status != appraisal::STATUS_CLOSED) { // Skip sending if the user's appraisal is closed.
            $this->send(array($userassignment), $managers_only);
        }

        // TODO: Need better handling for managers_only messages
        if (!$managers_only) {
            // Remove the user event record (assuming there is one), so that it's not sent again.
            $DB->delete_records('appraisal_user_event', array('userid' => $userid, 'eventid' => $this->id));
        }
    }


    /**
     * Send this message to all roles related to the given user_assignments.
     *
     * @param array $userassignments The user_assignment records to send the message to.
     * @param bool $managers_only true if the messages should only be sent to non-learner roles
     */
    public function send($userassignments, $managers_only=false) {
        global $DB;

        $appraisal = new appraisal($this->appraisalid);

        $sentaddress = array();
        foreach ($userassignments as $userassignment) {
            // Skip it if the user's assignment is closed.
            if ($userassignment->status == appraisal::STATUS_CLOSED) {
                continue;
            }

            // Get all applicable roles that are involved in the appraisal.
            list($rolessql, $params) = $DB->get_in_or_equal($this->roles, SQL_PARAMS_NAMED, 'role');
            $sql = "SELECT appraisalrole, userid
                          FROM {appraisal_role_assignment}
                         WHERE appraisaluserassignmentid = :userassignmentid
                           AND appraisalrole {$rolessql}";
            $params['userassignmentid'] = $userassignment->id;
            $roleassignments = $DB->get_records_sql($sql, $params);

            // Send a message to each role assignment user.
            foreach ($roleassignments as $roleassignment) {
                // TODO: add triggered value to appraisal_role_assignment table and use that
                //       to determine if message has been sent or not.
                //       For now - simply removing learners from the list
                $role = $roleassignment->appraisalrole;
                if (!$managers_only || $role != appraisal::ROLE_LEARNER) {
                    $rcptuserid = $roleassignment->userid;
                    $rcpt = $DB->get_record('user', array('id' => $rcptuserid));
                    if (!empty($rcpt)) {

                        // Send only if complete/incomplete.
                        if ($this->type == self::EVENT_STAGE_DUE && $this->stageiscompleted != self::MESSAGE_SEND_ANY_STATE) {
                            // Get stage completion.
                            $stage = new appraisal_stage($this->stageid);
                            $roleassignment = appraisal_role_assignment::get_role($this->appraisalid, $userassignment->userid,
                                $rcptuserid, $role);
                            $complete = $stage->is_completed($roleassignment);
                            // Skip completed if set "only to incompleted" and contra versa.
                            if ($this->stageiscompleted == self::MESSAGE_SEND_ONLY_COMPLETE && !$complete ||
                                $this->stageiscompleted == self::MESSAGE_SEND_ONLY_INCOMPLETE && $complete) {
                                continue;
                            }
                        }

                        // Get the message content, which may be different depending on the role.
                        $message = $this->get_message($role);

                        // Do the placeholders substitutions.
                        list($messagesubject, $messagecontent) = $this->message_substitutions(
                            $appraisal, array($message->name, $message->content), $userassignment, $rcpt);

                        // Create a message.
                        $eventdata = new stdClass();
                        $eventdata->component         = 'moodle';
                        $eventdata->name              = 'instantmessage';
                        $eventdata->userfrom          = core_user::get_noreply_user();
                        $eventdata->userto            = $rcpt;
                        $eventdata->subject           = $messagesubject;
                        $eventdata->fullmessage       = $messagecontent;
                        $eventdata->fullmessageformat = FORMAT_PLAIN;
                        // The content is plain text so make sure we convert linebreaks for the HTML content.
                        $eventdata->fullmessagehtml   = html_writer::tag('pre', $messagecontent);
                        $eventdata->smallmessage      = $messagecontent;

                        // Send the message, preventing duplicates. E.g. If someone is a manager and appraiser and there are two
                        // different messages set up then they will receive one of each type. If someone has multiple roles with
                        // all roles set to the same message then they will only receive one message.
                        if (!isset($sentaddress[$rcpt->email]) || !in_array($message->id, $sentaddress[$rcpt->email])) {
                            tm_alert_send($eventdata);

                            if (!isset($sentaddress[$rcpt->email])) {
                                $sentaddress[$rcpt->email] = array();
                            }
                            $sentaddress[$rcpt->email][] = $message->id;
                        }
                    }
                }
            }
        }
    }


    /**
     * Subsitute the placeholders in message templates for the actual data
     *
     * @param object $appraisal The appraisal object
     * @param array $messages An array containing the messages
     * @param object $userassignments The user_assignment records to send the message to.
     * @param object $rcpt The recipient user object
     * @return  string
     */
    public function message_substitutions($appraisal, $messages, $userassignment, $rcpt) {
        global $DB, $CFG;

        if (empty($messages)) {
            return array();
        }

        if (!empty($userassignment->jobassignmentid)) {
            $jobassignment = job_assignment::get_with_id($userassignment->jobassignmentid);
        }

        // Create the data array, placeholder => value. (All empty values to start).
        $data = array();
        foreach (self::$placeholders as $placeholder) {
            $data['[' . $placeholder . ']'] = '';
        }

        // Site.
        $data['[sitename]'] = format_string(get_site()->fullname);
        $data['[siteurl]'] = $CFG->wwwroot;

        // Appraisal.
        $data['[appraisalname]'] = format_string($appraisal->name);
        $data['[appraisaldescription]'] = format_string($appraisal->description);
        $data['[expectedappraisalcompletiondate]'] = userdate($appraisal->get_expected_completion_date(), get_string('strfdateshortmonth', 'langconfig'));

        // Get the stage object.
        $stage = new appraisal_stage($this->stageid);

        // Current stage.
        if ($this->stageid) {
            $data['[currentstagename]'] = format_string($stage->name);
            $data['[expectedstagecompletiondate]'] = userdate($stage->timedue, get_string('strfdateshortmonth', 'langconfig'));
        }

        // All stages.
        $allstagenames = '';
        foreach ($stage->fetch_appraisal($appraisal->id) as $stagerecord) {
            $allstagenames .= "* {$stagerecord->name}\n";
        }
        if (!empty($allstagenames)) {
            $data['[listofstagenames]'] = format_string($allstagenames);
        }

        // Previous stage.
        $previousstage = $stage->get_previous();
        if ($previousstage) {
            $data['[previousstagename]'] = format_string($previousstage->name);
        } else {
            $data['[previousstagename]'] = get_string('placeholders:default_previousstagename', 'totara_appraisal');
        }

        // Appraisee.
        $appraisee = ($rcpt->id != $userassignment->userid) ?
            $DB->get_record('user', array('id' => $userassignment->userid)) : $rcpt;

        $data['[userusername]'] = $appraisee->username;
        $data['[userfirstname]'] = $appraisee->firstname;
        $data['[userlastname]'] = $appraisee->lastname;
        $data['[userfullname]'] = fullname($appraisee);

        // Manager.
        if (!empty($jobassignment->managerjaid)) {

            $managerid = $jobassignment->managerid;
            $manager = ($rcpt->id != $managerid) ? $DB->get_record('user', array('id' => $managerid)) : $rcpt;

            if ($manager) {
                $data['[managerusername]'] = $manager->username;
                $data['[managerfirstname]'] = $manager->firstname;
                $data['[managerlastname]'] = $manager->lastname;
                $data['[managerfullname]'] = fullname($manager);
            }
        } else {
            // The default.
            $data['[managerfullname]'] = get_string('placeholders:default_managerfullname', 'totara_appraisal');
        }

        // Team Lead.
        if (!empty($jobassignment->teamleaderid)) {

            $teamleadid = $jobassignment->teamleaderid;
            $teamlead = ($rcpt->id != $teamleadid) ? $DB->get_record('user', array('id' => $teamleadid)) : $rcpt;

            if ($teamlead) {
                $data['[managersmanagerusername]'] = $teamlead->username;
                $data['[managersmanagerfirstname]'] = $teamlead->firstname;
                $data['[managersmanagerlastname]'] = $teamlead->lastname;
                $data['[managersmanagerfullname]'] = fullname($teamlead);
            }
        } else {
            // The default.
            $data['[managersmanagerfullname]'] = get_string('placeholders:default_teamleadfullname', 'totara_appraisal');
        }

        // Appraiser.
        if (!empty($jobassignment->appraiserid)) {
            $appraiser = ($rcpt->id != $jobassignment->appraiserid) ?
                $DB->get_record('user', array('id' => $jobassignment->appraiserid)) : $rcpt;

            if ($appraiser) {
                $data['[appraiserusername]'] = $appraiser->username;
                $data['[appraiserfirstname]'] = $appraiser->firstname;
                $data['[appraiserlastname]'] = $appraiser->lastname;
                $data['[appraiserfullname]'] = fullname($appraiser);
            }
        } else {
            // The default.
            $data['[appraiserfullname]'] = get_string('placeholders:default_appraiserfullname', 'totara_appraisal');
        }

        // Replace placeholders with values.
        foreach ($messages as $key => $msg) {
            foreach ($data as $placeholder => $value) {
                $msg = str_replace($placeholder, $value, $msg);
            }
            $messages[$key] = $msg;
        }

        return $messages;
    }


    /**
     * Get message prepared for role
     *
     * @param int $role
     * @return stdClass
     */
    public function get_message($role) {
        if (isset($this->messages[$role])) {
            return $this->messages[$role];
        } else if (isset($this->messages[0])) {
            return $this->messages[0];
        }
        return null;
    }


    /**
     * List of event messages related to appraisal
     *
     * @param int $appraisalid
     * @return array
     */
    public static function get_list($appraisalid) {
        global $DB;
        $evtrs = $DB->get_records('appraisal_event', array('appraisalid' => $appraisalid), 'id');
        $events = array();
        foreach ($evtrs as $evtdata) {
            // Don't list closure messages here, to avoid duplicate messages.
            if ($evtdata->event == 'appraisal_closure') {
                continue;
            }

            $events[$evtdata->id] = new appraisal_message($evtdata->id);
        }
        return $events;
    }


    /**
     * Render name by taking message title
     *
     * @return string
     */
    public function get_display_name() {
        $strname = current($this->messages)->title;
        return $strname;
    }


    /**
     * Clean messages for the event
     *
     * @param int $id event id
     */
    protected static function clean_messages($id) {
        global $DB;
        $messages = $DB->get_records('appraisal_event_message', array('appraisaleventid' => $id), 'id');
        foreach ($messages as $message) {
            $DB->delete_records('appraisal_event_rcpt', array('appraisaleventmessageid' => $message->id));
        }
        $DB->delete_records('appraisal_event_message', array('appraisaleventid' => $id));
    }


    /**
     * Delete message
     *
     * @param int $id
     */
    public static function delete($id) {
        global $DB;
        self::clean_messages($id);
        $DB->delete_records('appraisal_event', array('id' => $id));
    }


    /**
     * Delete all event messages related to the stage
     * @param int $stageid
     */
    public static function delete_stage($stageid) {
        global $DB;
        $events = $DB->get_records('appraisal_event', array('appraisalstageid' => $stageid), 'id');
        foreach ($events as $event) {
            self::delete($event->id);
        }
    }


    /**
     * Delete all event messages related to the stage
     *
     * @param int $appraisalid
     */
    public static function delete_appraisal($appraisalid) {
        global $DB;
        $messages = $DB->get_records('appraisal_event', array('appraisalid' => $appraisalid), 'id');
        foreach ($messages as $message) {
            self::delete($message->id);
        }
    }


    /**
     * Make copy of all events for appraisal. Stages will not be duplicated.
     *
     * @param int $srcappraisalid Initial appraisal id
     * @param int $appraisalid Destination appraisal id
     */
    public static function duplicate_appraisal($srcappraisalid, $appraisalid) {
        global $DB;

        // Do not duplicate closure messages, to avoid duplicate messages.
        $sql = "SELECT id
                  FROM {appraisal_event}
                 WHERE appraisalid = :aid
                   AND event != :closure
                   AND (appraisalstageid = 0 OR appraisalstageid IS NULL)";
        $events = $DB->get_records_sql($sql, array('aid' => $srcappraisalid, 'closure' => self::EVENT_APPRAISAL_CLOSURE));
        foreach ($events as $eventdata) {
            $event = new appraisal_message($eventdata->id);
            $event->id = 0;
            $event->appraisalid = $appraisalid;
            $event->timescheduled = 0;
            $event->triggered = 0;
            foreach ($event->messages as $message) {
                $message->id = 0;
            }
            $event->save();
        }
    }


    /**
     * Make copy of all events for stage
     *
     * @param int $srcstageid initial stage id
     * @param int $srcstageid destination stage id
     */
    public static function duplicate_stage($srcstageid, $stageid) {
        global $DB;
        $stage = new appraisal_stage($stageid);
        $appraisalid = $stage->appraisalid;

        $events = $DB->get_records('appraisal_event', array('appraisalstageid' => $srcstageid), '', 'id');
        foreach ($events as $eventdata) {
            $event = new appraisal_message($eventdata->id);
            $event->id = 0;
            $event->appraisalid = $appraisalid;
            $event->stageid = $stageid;
            $event->timescheduled = 0;
            $event->triggered = 0;
            foreach ($event->messages as $message) {
                $message->id = 0;
            }
            $event->save();
        }
    }
}


/**
 * Serves the folder files.
 *
 * @package  mod_folder
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function totara_appraisal_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $USER;
    $systemcontext = context_system::instance();
    // Itemid used as assignmentid.
    $assignmentid = (int)array_shift($args);
    if (!has_capability('totara/appraisal:manageappraisals', $systemcontext)) {
        if (strpos($filearea, 'snapshot_') === 0) {
            // This is PDF snapshot.
            $roleassignment = new appraisal_role_assignment($assignmentid);
            if ($roleassignment->userid != $USER->id) {
                send_file_not_found();
            }
        } else if (strpos($filearea, 'quest_') === 0) {
            $questionid = (int)str_replace('quest_', '', $filearea);
            if (!$question = new appraisal_question($questionid)) {
                send_file_not_found();
            }
            if ($assignmentid != 0 && !$question->user_can_view($assignmentid, $USER->id)) {
                send_file_not_found();
            }
        }
    }
    $filename = array_shift($args);
    $fs = get_file_storage();
    if (!$file = $fs->get_file($context->id, 'totara_appraisal', $filearea, $assignmentid, '/', $filename)) {
        send_file_not_found();
    }

    \core\session\manager::write_close();
    send_stored_file($file, 60*60, 0, true, $options);
}

/**
 * Install an example appraisal in the current site.
 */
function totara_appraisal_install_example_appraisal() {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/totara/question/field/multichoice.class.php');
    require_once($CFG->dirroot . '/totara/question/field/goals.class.php');

    $now = time();

    // We don't want half an appraisal if something goes wrong.
    $transaction = $DB->start_delegated_transaction();

    // Create a sample appraisal.
    $appraisal = new stdClass();
    $appraisal->name = get_string('example:appraisalname', 'totara_appraisal');
    $appraisal->description = get_string('example:appraisaldescription', 'totara_appraisal');
    $appraisal->status = 0;
    $appraisalid = $DB->insert_record('appraisal', $appraisal);

    // Loop through creating stages.
    $stageids = array();
    foreach (range(1, 3) as $num) {
        $stage = new stdClass();
        $stage->appraisalid = $appraisalid;
        $stage->name = get_string("example:stage{$num}name", 'totara_appraisal');
        $stage->description = get_string("example:stage{$num}description", 'totara_appraisal');
        // Set due dates 1 month, 7 months and 13 months from install time.
        $stage->timedue = $now + 60*60*24*30 + ($num-1)*(60*60*24*30*6);
        $stageids[$num] = $DB->insert_record('appraisal_stage', $stage);

        // Lock stages upon completion for learner and manager roles.
        foreach (array(appraisal::ROLE_LEARNER, appraisal::ROLE_MANAGER) as $role) {
            $locked = new stdClass();
            $locked->appraisalstageid = $stageids[$num];
            $locked->appraisalrole = $role;
            $locked->locked = 1;
            $DB->insert_record('appraisal_stage_role_setting', $locked);
        }
    }

    // Loop through creating pages.
    $pagesperstage = array(1 => 4, 2 => 3, 3 => 3);
    $pageids = array();
    foreach ($stageids as $stagenum => $stageid) {
        $pageids[$stagenum] = array();
        $sort = 0;
        foreach (range(1, $pagesperstage[$stagenum]) as $pagenum) {
            $page = new stdClass();
            $page->appraisalstageid = $stageid;
            $page->name = get_string("example:stage{$stagenum}page{$pagenum}name", 'totara_appraisal');
            $page->sortorder = $sort;
            $sort++;
            $pageids[$stagenum][$pagenum] = $DB->insert_record('appraisal_stage_page', $page);
        }
    }

    // Build a scale for use by multichoice question.
    $scale = new stdClass();
    $scale->name = get_string('example:scaleyesnoname', 'totara_appraisal');
    $admin = get_admin();
    $scale->userid = $admin->id;
    $scale->scaletype = multichoice::SCALE_TYPE_MULTICHOICE;
    $scaleid = $DB->insert_record('appraisal_scale', $scale);

    $yes = new stdClass();
    $yes->appraisalscaleid = $scaleid;
    $yes->name = get_string('yes');
    $DB->insert_record('appraisal_scale_value', $yes);

    $no = new stdClass();
    $no->appraisalscaleid = $scaleid;
    $no->name = get_string('no');
    $DB->insert_record('appraisal_scale_value', $no);

    // Array of question content (that can't be calculated).
    $questinfo = array(
        1 => array(
            1 => array(
                1 => array(
                    'datatype' => 'goals',
                    // param4 is 'Include rating' - '0' is for not including the rating.
                    'param4' => '0',
                    // param5 is goal selection options.
                    'param5' => question_goals::SELECT_COMPANY_USERCANCHOOSE | question_goals::SELECT_PERSONAL_USERCANCHOOSE
                ),
            ),
            2 => array(
                1 => array(
                    'datatype' => 'longtext',
                ),
            ),
            3 => array(
                1 => array(
                    'datatype' => 'compfromplan',
                    // param4 is 'Include rating' - '0' is for not including the rating.
                    'param4' => '0',
                ),
            ),
            4 => array(
                1 => array(
                    'datatype' => 'multichoicesingle',
                    'param1' => $scaleid,
                    'param2' => multichoice::SCALE_TYPE_MULTICHOICE,
                    'param3' => '[]',
                ),
            ),
        ),
        2 => array(
            1 => array(
                1 => array(
                    'datatype' => 'goals',
                    'param4' => '0',
                    'param5' => question_goals::SELECT_COMPANY_USERCANCHOOSE | question_goals::SELECT_PERSONAL_USERCANCHOOSE
                ),
                2 => array(
                    'datatype' => 'ratingnumeric',
                    'param1' => '{"rangefrom":0, "rangeto":10}',
                    'param2' => '"1"',
                ),
            ),
            2 => array(
                1 => array(
                    'datatype' => 'compfromplan',
                    'param4' => '0',
                ),
                2 => array(
                    'datatype' => 'ratingnumeric',
                    'param1' => '{"rangefrom":0, "rangeto":10}',
                    'param2' => '"1"',
                ),
            ),
            3 => array(
                1 => array(
                    'datatype' => 'longtext',
                ),
            ),
        ),
        3 => array(
            1 => array(
                1 => array(
                    'datatype' => 'goals',
                    'param4' => '0',
                    'param5' => question_goals::SELECT_COMPANY_USERCANCHOOSE | question_goals::SELECT_PERSONAL_USERCANCHOOSE
                ),
                2 => array(
                    'datatype' => 'ratingnumeric',
                    'param1' => '{"rangefrom":0, "rangeto":10}',
                    'param2' => '"1"',
                ),
            ),
            2 => array(
                1 => array(
                    'datatype' => 'compfromplan',
                    'param4' => '0',
                ),
                2 => array(
                    'datatype' => 'ratingnumeric',
                    'param1' => '{"rangefrom":0, "rangeto":10}',
                    'param2' => '"1"',
                ),
            ),
            3 => array(
                1 => array(
                    'datatype' => 'longtext',
                ),
            ),
        ),
    );

    $questids = array();

    // Loop through creating questions.
    foreach (range(1, 3) as $stagenum) {
        $questids[$stagenum] = array();
        foreach (range(1, $pagesperstage[$stagenum]) as $pagenum) {
            $questids[$stagenum][$pagenum] = array();
            $sort = 0;
            foreach ($questinfo[$stagenum][$pagenum] as $questnum => $questdata) {
                $quest = new stdClass();
                $quest->appraisalstagepageid = $pageids[$stagenum][$pagenum];
                $quest->name = get_string("example:stage{$stagenum}page{$pagenum}quest{$questnum}name",
                    'totara_appraisal');
                $quest->sortorder = $sort;
                $quest->datatype = $questinfo[$stagenum][$pagenum][$questnum]['datatype'];
                $quest->defaultdata = '';
                foreach (range(1, 6) as $paramnum) {
                    $paramname = "param{$paramnum}";
                    if (isset($questinfo[$stagenum][$pagenum][$questnum][$paramname])) {
                        $quest->$paramname = $questinfo[$stagenum][$pagenum][$questnum][$paramname];
                    }
                }
                $questids[$stagenum][$pagenum][$questnum] = $DB->insert_record('appraisal_quest_field', $quest);
                // Create appropriate roles for each question.
                foreach (array(appraisal::ROLE_LEARNER, appraisal::ROLE_MANAGER) as $role) {
                    $questrole = new stdClass();
                    $questrole->appraisalquestfieldid = $questids[$stagenum][$pagenum][$questnum];
                    $questrole->appraisalrole = $role;
                    $questrole->rights = (appraisal::ACCESS_MUSTANSWER | appraisal::ACCESS_CANVIEWOTHER);
                    $DB->insert_record('appraisal_quest_field_role', $questrole);
                }
                $sort++;
            }
        }
    }

    $transaction->allow_commit();
}

/**
 * Role assignment to appraisal
 */
class appraisal_role_assignment {
    /**
     * Role assignment id
     * @var int
     */
    protected $id = 0;

    /**
     * User assigned to appraisal by current role id
     * @var int
     */
    protected $userid = 0;

    /**
     * Role code
     * @var int
     */
    protected $appraisalrole = 0;

    /**
     * User assignment id
     * @var int
     */
    protected $appraisaluserassignmentid = 0;

    /**
     * Current page id of appraisal for role
     * @var int
     */
    public $activepageid = 0;

    /**
     * Is this fake assignment created for preview
     * @var bool
     */
    protected $preview = false;

    /**
     * If this is a fake role assignment then the user assignment needs an appraisal id.
     * @var int
     */
    private $previewappraisalid = 0;

    /**
     * If this is a fake role assignment then the user assignment needs an active stage id.
     * @var int
     */
    private $previewstageid = 0;

    /**
     * Learner userid
     * @var int
     */
    protected $subjectid = 0;

    /**
     * user assignment object
     * @var appriasal_user_assignment
     */
    protected $userassignment = null;

    /**
     * Load role assignment
     * @param int $id
     */
    public function __construct($id = 0) {
        if ($id > 0) {
            $this->load($id);
        }
    }

    /**
     * Access to read-only properties
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (isset($this->$name)) {
            return $this->$name;
        }
    }

    public function __isset($name) {
        return isset($this->$name);
    }

    /**
     * Get role assignment by its id
     *
     * @param int $id
     */
    public function load($id) {
        global $DB;
        $roleass = $DB->get_record('appraisal_role_assignment', array('id' => $id));
        $this->id = $roleass->id;
        $this->userid = $roleass->userid;
        $this->appraisalrole = $roleass->appraisalrole;
        $this->appraisaluserassignmentid = $roleass->appraisaluserassignmentid;
        $this->activepageid = $roleass->activepageid;
        $this->preview = false;
        $userassignment = new appraisal_user_assignment($this->appraisaluserassignmentid);
        $this->subjectid = $userassignment->userid;
    }

    /**
     * Get appraisal role assignment for assigned subject user and user giving answers in the given role.
     * If previewing then create a template role assignment object.
     *
     * @param int $subjectid
     * @param int $userid of the role user
     * @param int $role
     * @param bool $preview
     * @return object role assignment record
     */
    public static function get_role($appraisalid, $subjectid, $userid, $role, $preview = false) {
        global $DB;

        if ($preview) {
            $roleassignment = new appraisal_role_assignment();
            $roleassignment->id = -$role; // We need a unique, non-existing id, so we use < 0.
            $roleassignment->appraisaluserassignmentid = 0;
            $roleassignment->userid = $userid;
            $roleassignment->appraisalrole = $role;
            $roleassignment->activepageid = 0;
            $roleassignment->preview = true;
            $roleassignment->previewappraisalid = $appraisalid;
            $roleassignment->subjectid = $subjectid;
            return $roleassignment;
        }

        $sql = 'SELECT ara.id
                FROM {appraisal_role_assignment} ara
                JOIN {appraisal_user_assignment} aua
                  ON ara.appraisaluserassignmentid = aua.id
               WHERE aua.appraisalid = ?
                 AND aua.userid = ?
                 AND ara.userid = ?
                 AND ara.appraisalrole = ?';
        $record = $DB->get_record_sql($sql, array($appraisalid, $subjectid, $userid, $role));

        if ($record) {
            $roleassignment = new appraisal_role_assignment($record->id);
        } else {
            $roleassignment = null;
        }

        return $roleassignment;
    }

    /**
     * Get user assignment instance
     *
     * @return appraisal_user_assignment
     */
    public function get_user_assignment() {
        global $DB;

        if ($this->preview) {
            $userassignment = appraisal_user_assignment::get_user($this->previewappraisalid, $this->subjectid, $this->preview);
            $userassignment->activestageid = $this->previewstageid;
            return $userassignment;
        } else {
            // Do not cache, as in some places there more than one instances can be changed.
            return new appraisal_user_assignment($this->appraisaluserassignmentid);
        }
    }

    /**
     * When previewing, get_user_assignment produces a fake record. Some functions require that it have
     * a valid activestageid. This function allows you to specify that stage id.
     *
     * @param int $previewstageid
     */
    public function set_previewstageid($previewstageid) {
        $this->previewstageid = $previewstageid;
    }
}

/**
 * User (Learner) assignment to appraisal
 */
class appraisal_user_assignment {
    /**
     * User assignment id
     * @var int
     */
    protected $id = 0;

    /**
     * User assigned to appraisal by current role id
     * @var int
     */
    protected $userid = 0;

    /**
     * Appraisal id
     * @var int
     */
    protected $appraisalid = 0;

    /**
     * Current stage id of appraisal (for all roles always the same by req)
     * @var int
     */
    public $activestageid = 0;

    /**
     * Timestamp of user appraisal completion (all roles)
     * @var int
     */
    protected $timecompleted = 0;

    /**
     * Is this fake assignment created for preview
     * @var bool
     */
    protected $preview = false;

    /**
     * User record
     * @var stdClass
     */
    protected $user = null;

    /**
     * User status
     * @var int
     */
    protected $status = 0;

    /**
     * The user's job assignment associated with this appraisal.
     * @var int
     */
    protected $jobassignmentid = null;

    /**
     * The last modified time that external job assignment record was modified.
     * Used to determine whether appraisal role assignments need to be changed.
     * @var int
     */
    protected $jobassignmentlastmodified = null;

    /**
     * Load role assignment
     * @param int $id
     */
    public function __construct($id = 0) {
        if ($id > 0) {
            $this->load($id);
        }
    }

    /**
     * Access to read-only properties
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (isset($this->$name)) {
            return $this->$name;
        }
    }

    public function __isset($name) {
        return isset($this->$name);
    }

    /**
     * Get role assignment by its id
     *
     * @param int $id
     */
    public function load($id) {
        global $DB;
        $userass = $DB->get_record('appraisal_user_assignment', array('id' => $id));
        $this->id = $userass->id;
        $this->userid = $userass->userid;
        $this->appraisalid = $userass->appraisalid;
        $this->activestageid = $userass->activestageid;
        $this->timecompleted = $userass->timecompleted;
        $this->status = $userass->status;
        $this->jobassignmentid = $userass->jobassignmentid;
        $this->jobassignmentlastmodified = $userass->jobassignmentlastmodified;
        $this->preview = false;
        $this->user = $DB->get_record('user', array('id' => $this->userid));
    }

    /**
     * Get the user assignment record.
     * If previewing then create a template user assignment object.
     *
     * @param int $appraisalid
     * @param int $userid
     * @param bool $preview
     * @return appraisal_user_assignment instance
     */
    public static function get_user($appraisalid, $userid, $preview = false) {
        global $DB;
        if ($preview) {
            $userassignment = new appraisal_user_assignment();
            $userassignment->id = 0;
            $userassignment->userid = $userid;
            $userassignment->appraisalid = $appraisalid;
            $userassignment->activestageid = null;
            $userassignment->timecompleted = null;
            $userassignment->user = $DB->get_record('user', array('id' => $userid));
            $userassignment->preview = true;
            return $userassignment;
        }
        $record = $DB->get_record('appraisal_user_assignment', array('userid' => $userid, 'appraisalid' => $appraisalid));
        return empty($record)
               ? new appraisal_user_assignment()
               : new appraisal_user_assignment($record->id);
    }

    /**
     * Closes the appraisal for a user.
     */
    public function close() {
        $this->update(['status' => appraisal::STATUS_CLOSED]);
    }

    /**
     *
     */
    public function is_closed() {
        return $this->status == appraisal::STATUS_CLOSED;
    }

    /**
     * Associates a job assignment with this appraisal.
     *
     * @param int $jobassignmentid job assignment identifer.
     *
     * @return \appraisal_user_assignment the updated assignment.
     */
    public function with_job_assignment($jobassignmentid) {
        if (empty($jobassignmentid) || !is_numeric($jobassignmentid)) {
            throw new \InvalidArgumentException(
                "invalid parameter [user_assignment job id]: '$jobassignmentid'"
            );
        }

        $values = [
            'jobassignmentid' => (int)$jobassignmentid,
            'jobassignmentlastmodified' => null
        ];
        $this->update($values);

        $appraisal = new appraisal($this->appraisalid);
        $appraisal->assignment_changed_for_appraisee($this->userid);

        // Sending manager messages here - may need to move this to
        // totara_job\job_assignment::totara_assign_appraisal
        // if we need to send manager messages again when any job
        // assignment is updated
        $this->send_manager_messages();

        return $this;
    }

    /**
     * Disassociates the existing job assignment from this appraisal. This could
     * happen for example when the user deletes a job assignment from his user
     * profile.
     *
     * @return \appraisal_user_assignment the updated assignment.
     */
    public function remove_job_assignment() {
        if (!empty($this->jobassignmentid)) {
            $values = [
                'jobassignmentid' => null,
                'jobassignmentlastmodified' => null
            ];
            $this->update($values);
        }

        return $this;
    }

    /**
     * Sets the last modification time for the linked job assignment.
     *
     * Note this is not the time the *jobassignmentid field* was changed but the
     * time a job assignment *record* (to which jobassignmentid points) changed.
     * Because if a parent record changed => job assignment details (eg manager)
     * changed => need to regenerate the role assignments for the appraisee.
     *
     * @param int $time the last modification time in milliseconds since the
     *        Epoch.
     *
     * @return \appraisal_user_assignment the updated assignment.
     */
    public function with_job_assignment_record_time($time) {
        if (empty($time) || !is_numeric($time)) {
            throw new \InvalidArgumentException(
                "invalid parameter [user_assignment job timestamp]: '$time'"
            );
        }

        $this->update(['jobassignmentlastmodified' => (int)$time]);
        return $this;
    }

    /**
     * Automatically links a job assignment with this appraisee assignment. This
     * uses the following rules:
     * - If there is already a job assignment for the appraisee assignment, then
     *   check if the job assignment is valid (ie not deleted). If invalid, then
     *   auto generate a new job assignment if possible.
     * - If there is no existing job assignment for the appraisee, then create a
     *   default job assignment and link that to the appraisee assignment.
     * - If there is a single existing job assignment, then link that.
     * - If there are multiple existing job assignments, then link the first job
     *   assignment if the 'linkfirst' value is true.
     *
     * @param boolean linkfirst if true, links the first existing job assignment
     *        to the appraisee assignment. Note "first" here is the first in the
     *        list that job_assignment::get_all() returns.
     *
     * @return array (\appraisal_user_assignment, \job_assignment) tuple with an
     *         updated appraisee assignment and assigned job. This could be an
     *         empty array if there were multiple job assignments from which to
     *         choose and the passed 'linkfirst' value was false.
     */
    public function with_auto_job_assignment($linkfirst) {
        // For the appraisee who has already specified a job for this appraisal.
        $jobassignmentid = $this->jobassignmentid;
        if (!empty($jobassignmentid)) {
            $job = job_assignment::get_with_id($jobassignmentid, false);
            if (!empty($job)) {
                // Job assignment exists and it has already been linked.
                return [$this, $job];
            }

            // Linked job assignment was removed in the interim and things were
            // not cleaned up. Note processing continues on after this step and
            // creates a new job assignment link if necessary.
            $this->remove_job_assignment();
        }

        // For an appraisee who has not associated any job with the appraisal.
        $appraisee = $this->userid;
        $jobs = job_assignment::get_all($appraisee);
        $noofjobs = count($jobs);

        if ($noofjobs === 0) {
            $job = job_assignment::create_default($appraisee);
            return [$this->with_job_assignment($job->id), $job];
        }
        else if ($noofjobs === 1
                 || ($noofjobs > 1 && $linkfirst)) {
            $job = current($jobs);
            return [$this->with_job_assignment($job->id), $job];
        }
        else {
            // For an appraisee with multiple formal job assignments and cannot
            // link the first one.
            return null;
        }
    }

    /**
     * Updates the persistent record for this assignment.
     *
     * @param \array $fields a mapping of field names to their new values.
     */
    private function update(array $fields) {
        global $DB;

        $dto = new \stdClass();
        $dto->id = $this->id;

        foreach ($fields as $field => $value) {
            $this->$field = $value;
            $dto->$field = $value;
        }

        $DB->update_record('appraisal_user_assignment', $dto);
    }

    /**
     * Send messages to managers, team leads and appraisers once assignment is made
     */
    private function send_manager_messages() {
        global $DB;

        $appraisalid = $this->appraisalid;

        // For now only do Activation messages that needs to be sent to managers
        $sql = "SELECT id FROM {appraisal_event} WHERE event = ? AND appraisalid = ?";
        $params = array(appraisal_message::EVENT_APPRAISAL_ACTIVATION, $appraisalid);
        $events = $DB->get_records_sql($sql, $params);
        foreach ($events as $id => $eventdata) {
            $eventmessage = new appraisal_message($id);
            $eventmessage->send_user_specific_message($this->userid, true);
        }
    }

}
