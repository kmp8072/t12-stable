<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2016 onwards Totara Learning Solutions LTD
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
 * @author Brendan Cox <brendan.cox@totaralearning.com>
 * @package totara_job
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/totara/job/lib.php');

class totara_job_lib_testcase extends advanced_testcase {

    /** @var  testing_data_generator */
    private $data_generator;

    public function setUp() {
        parent::setup();
        $this->resetAfterTest();

        $this->data_generator = $this->getDataGenerator();
    }

    public function test_totara_job_display_user_job() {
        // For $user1, we control the names and email so that we can test for them more accurately.
        // For $user2, we allow the generator to create these so that non-latin characters will also
        // be used in many of the tests.
        $userrecord = array(
            'firstname' => 'John',
            'lastname' => 'Smith',
            'email' => 'john@example.com'
        );
        $user1 = $this->data_generator->create_user($userrecord);
        $user2 = $this->data_generator->create_user();

        $jobdata11 = array(
            'userid' => $user1->id,
            'idnumber' => '1a',
            'fullname' => 'Developer'
        );
        $jobassignment_withname1 = \totara_job\job_assignment::create($jobdata11);

        $jobdata12 = array(
            'userid' => $user2->id,
            'idnumber' => '1b',
            'fullname' => 'Tester'
        );
        $jobassignment_withname2 = \totara_job\job_assignment::create($jobdata12);

        $jobdata21 = array(
            'userid' => $user1->id,
            'idnumber' => 2
        );
        $jobassignment_noname1 = \totara_job\job_assignment::create($jobdata21);

        $jobdata22 = array(
            'userid' => $user2->id,
            'idnumber' => '2a'
        );
        $jobassignment_noname2 = \totara_job\job_assignment::create($jobdata22);

        // Use job that does have fullname, can view email address.
        $returnedstring1 = totara_job_display_user_job($user1, $jobassignment_withname1, true);
        $this->assertEquals('John Smith (john@example.com) - Developer', $returnedstring1);

        $returnedstring2 = totara_job_display_user_job($user2, $jobassignment_withname2, true);
        $this->assertEquals(fullname($user2) . ' (' .$user2->email . ') - Tester', $returnedstring2);

        // Use job that does have fullname, cannot view email address.
        $returnedstring = totara_job_display_user_job($user1, $jobassignment_withname1, false);
        $this->assertEquals('John Smith - Developer', $returnedstring);

        $returnedstring2 = totara_job_display_user_job($user2, $jobassignment_withname2, false);
        $this->assertEquals(fullname($user2) . ' - Tester', $returnedstring2);

        // Use job that does not have fullname, can view email address.
        $returnedstring = totara_job_display_user_job($user1, $jobassignment_noname1, true);
        $this->assertEquals('John Smith (john@example.com) - Unnamed job assignment (ID: 2)', $returnedstring);

        $returnedstring2 = totara_job_display_user_job($user2, $jobassignment_noname2, true);
        $this->assertEquals(fullname($user2) . ' (' .$user2->email . ') - Unnamed job assignment (ID: 2a)', $returnedstring2);

        // Use job that does not have fullname, cannot view email address.
        $returnedstring = totara_job_display_user_job($user1, $jobassignment_noname1, false);
        $this->assertEquals('John Smith - Unnamed job assignment (ID: 2)', $returnedstring);

        $returnedstring2 = totara_job_display_user_job($user2, $jobassignment_noname2, false);
        $this->assertEquals(fullname($user2) . ' - Unnamed job assignment (ID: 2a)', $returnedstring2);

        // If the $createjob argument is set to true, the corresponding string will be returned regardless of
        // the value of the job object.

        // Do not use any job, cannot view email address.
        $returnedstring = totara_job_display_user_job($user1, null, false, true);
        $this->assertEquals('John Smith - create empty job assignment', $returnedstring);

        $returnedstring2 = totara_job_display_user_job($user2, null, false, true);
        $this->assertEquals(fullname($user2) . ' - create empty job assignment', $returnedstring2);

        // Use job that does have fullname, can view email address.
        $returnedstring = totara_job_display_user_job($user1, $jobassignment_withname1, true, true);
        $this->assertEquals('John Smith (john@example.com) - create empty job assignment', $returnedstring);

        $returnedstring2 = totara_job_display_user_job($user2, $jobassignment_withname2, true, true);
        $this->assertEquals(fullname($user2) . ' (' .$user2->email . ') - create empty job assignment', $returnedstring2);

        // If no $jobassignment is supplied and $createjob is false, we get a string advising a job assignment
        // needs to be created.
        // Use job that does not have fullname, cannot view email address.
        $returnedstring = totara_job_display_user_job($user1, null, false);
        $this->assertEquals('John Smith - requires job assignment entry', $returnedstring);

        $returnedstring2 = totara_job_display_user_job($user2, null, false);
        $this->assertEquals(fullname($user2) . ' - requires job assignment entry', $returnedstring2);

        // Use job that does not have fullname, cannot view email address.
        $returnedstring = totara_job_display_user_job($user1, null, true);
        $this->assertEquals('John Smith (john@example.com) - requires job assignment entry', $returnedstring);

        $returnedstring2 = totara_job_display_user_job($user2, null, true);
        $this->assertEquals(fullname($user2) . ' (' .$user2->email . ') - requires job assignment entry', $returnedstring2);
    }
}