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
 * External learning plans webservice API tests.
 *
 * @package tool_lp
 * @copyright 2015 Damyon Wiese
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use tool_lp\api;
use tool_lp\external;
use tool_lp\plan;
use tool_lp\related_competency;
use tool_lp\user_competency;
use tool_lp\user_competency_plan;
use tool_lp\plan_competency;
use tool_lp\template_competency;

/**
 * External learning plans webservice API tests.
 *
 * @package tool_lp
 * @copyright 2015 Damyon Wiese
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_lp_external_testcase extends externallib_advanced_testcase {

    /** @var stdClass $creator User with enough permissions to create insystem context. */
    protected $creator = null;

    /** @var stdClass $learningplancreator User with enough permissions to create incategory context. */
    protected $catcreator = null;

    /** @var stdClass $category Category */
    protected $category = null;

    /** @var stdClass $user User with enough permissions to view insystem context */
    protected $user = null;

    /** @var stdClass $catuser User with enough permissions to view incategory context */
    protected $catuser = null;

    /** @var int Creator role id */
    protected $creatorrole = null;

    /** @var int User role id */
    protected $userrole = null;

    /** @var string scaleconfiguration */
    protected $scaleconfiguration1 = null;

    /** @var string scaleconfiguration */
    protected $scaleconfiguration2 = null;

    /** @var string catscaleconfiguration */
    protected $scaleconfiguration3 = null;

    /** @var string catscaleconfiguration */
    protected $catscaleconfiguration4 = null;

    /**
     * Setup function- we will create a course and add an assign instance to it.
     */
    protected function setUp() {
        global $DB;

        $this->resetAfterTest(true);

        // Create some users.
        $creator = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $catuser = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();
        $othercategory = $this->getDataGenerator()->create_category();
        $catcreator = $this->getDataGenerator()->create_user();

        $syscontext = context_system::instance();
        $catcontext = context_coursecat::instance($category->id);
        $othercatcontext = context_coursecat::instance($othercategory->id);

        // Fetching default authenticated user role.
        $userroles = get_archetype_roles('user');
        $this->assertCount(1, $userroles);
        $authrole = array_pop($userroles);

        // Reset all default authenticated users permissions.
        unassign_capability('tool/lp:competencygrade', $authrole->id);
        unassign_capability('tool/lp:competencysuggestgrade', $authrole->id);
        unassign_capability('tool/lp:competencymanage', $authrole->id);
        unassign_capability('tool/lp:competencyread', $authrole->id);
        unassign_capability('tool/lp:planmanage', $authrole->id);
        unassign_capability('tool/lp:planmanagedraft', $authrole->id);
        unassign_capability('tool/lp:planmanageown', $authrole->id);
        unassign_capability('tool/lp:planview', $authrole->id);
        unassign_capability('tool/lp:planviewdraft', $authrole->id);
        unassign_capability('tool/lp:planviewown', $authrole->id);
        unassign_capability('tool/lp:planviewowndraft', $authrole->id);
        unassign_capability('tool/lp:templatemanage', $authrole->id);
        unassign_capability('tool/lp:templateread', $authrole->id);
        unassign_capability('moodle/cohort:manage', $authrole->id);

        // Creating specific roles.
        $this->creatorrole = create_role('Creator role', 'creatorrole', 'learning plan creator role description');
        $this->userrole = create_role('User role', 'userrole', 'learning plan user role description');

        assign_capability('tool/lp:competencymanage', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:competencyread', CAP_ALLOW, $this->userrole, $syscontext->id);
        assign_capability('tool/lp:planmanage', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:planmanagedraft', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:planview', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:planviewdraft', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:templatemanage', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:competencygrade', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:competencysuggestgrade', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:templateread', CAP_ALLOW, $this->userrole, $syscontext->id);
        assign_capability('tool/lp:competencysuggestgrade', CAP_ALLOW, $this->userrole, $syscontext->id);
        assign_capability('tool/lp:planviewown', CAP_ALLOW, $this->userrole, $syscontext->id);
        assign_capability('tool/lp:planviewowndraft', CAP_ALLOW, $this->userrole, $syscontext->id);

        role_assign($this->creatorrole, $creator->id, $syscontext->id);
        role_assign($this->creatorrole, $catcreator->id, $catcontext->id);
        role_assign($this->userrole, $user->id, $syscontext->id);
        role_assign($this->userrole, $catuser->id, $catcontext->id);

        $this->creator = $creator;
        $this->catcreator = $catcreator;
        $this->user = $user;
        $this->catuser = $catuser;
        $this->category = $category;
        $this->othercategory = $othercategory;

        $this->getDataGenerator()->create_scale(array("id" => "1", "scale" => "value1, value2"));
        $this->getDataGenerator()->create_scale(array("id" => "2", "scale" => "value3, value4"));
        $this->getDataGenerator()->create_scale(array("id" => "3", "scale" => "value5, value6"));
        $this->getDataGenerator()->create_scale(array("id" => "4", "scale" => "value7, value8"));

        $this->scaleconfiguration1 = '[{"scaleid":"1"},{"name":"value1","id":1,"scaledefault":1,"proficient":0},' .
                '{"name":"value2","id":2,"scaledefault":0,"proficient":1}]';
        $this->scaleconfiguration2 = '[{"scaleid":"2"},{"name":"value3","id":1,"scaledefault":1,"proficient":0},' .
                '{"name":"value4","id":2,"scaledefault":0,"proficient":1}]';
        $this->scaleconfiguration3 = '[{"scaleid":"3"},{"name":"value5","id":1,"scaledefault":1,"proficient":0},' .
                '{"name":"value6","id":2,"scaledefault":0,"proficient":1}]';
        $this->scaleconfiguration4 = '[{"scaleid":"4"},{"name":"value8","id":1,"scaledefault":1,"proficient":0},' .
                '{"name":"value8","id":2,"scaledefault":0,"proficient":1}]';
        accesslib_clear_all_caches_for_unit_testing();
    }

    protected function create_competency_framework($number = 1, $system = true) {
        $scalepropname = 'scaleconfiguration' . $number;
        $framework = array(
            'shortname' => 'shortname' . $number,
            'idnumber' => 'idnumber' . $number,
            'description' => 'description' . $number,
            'descriptionformat' => FORMAT_HTML,
            'scaleid' => $number,
            'scaleconfiguration' => $this->$scalepropname,
            'visible' => true,
            'contextid' => $system ? context_system::instance()->id : context_coursecat::instance($this->category->id)->id
        );
        $result = external::create_competency_framework($framework);
        return (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $result);
    }

    protected function create_plan($number, $userid, $templateid, $status, $duedate) {
        $plan = array(
            'name' => 'name' . $number,
            'description' => 'description' . $number,
            'descriptionformat' => FORMAT_HTML,
            'userid' => $userid,
            'templateid' => empty($templateid) ? null : $templateid,
            'status' => $status,
            'duedate' => $duedate
        );
        $result = external::create_plan($plan);
        return (object) external_api::clean_returnvalue(external::create_plan_returns(), $result);
    }

    protected function create_template($number, $system) {
        $template = array(
            'shortname' => 'shortname' . $number,
            'description' => 'description' . $number,
            'descriptionformat' => FORMAT_HTML,
            'duedate' => 0,
            'visible' => true,
            'contextid' => $system ? context_system::instance()->id : context_coursecat::instance($this->category->id)->id
        );
        $result = external::create_template($template);
        return (object) external_api::clean_returnvalue(external::create_template_returns(), $result);
    }

    protected function update_template($templateid, $number) {
        $template = array(
            'id' => $templateid,
            'shortname' => 'shortname' . $number,
            'description' => 'description' . $number,
            'descriptionformat' => FORMAT_HTML,
            'visible' => true
        );
        $result = external::update_template($template);
        return external_api::clean_returnvalue(external::update_template_returns(), $result);
    }

    protected function update_plan($planid, $number, $userid, $templateid, $status, $duedate) {
        $plan = array(
            'id' => $planid,
            'name' => 'name' . $number,
            'description' => 'description' . $number,
            'descriptionformat' => FORMAT_HTML,
            'userid' => $userid,
            'templateid' => $templateid,
            'status' => $status,
            'duedate' => $duedate
        );
        $result = external::update_plan($plan);
        return external_api::clean_returnvalue(external::update_plan_returns(), $result);
    }

    protected function update_competency_framework($id, $number = 1, $system = true) {
        $scalepropname = 'scaleconfiguration' . $number;
        $framework = array(
            'id' => $id,
            'shortname' => 'shortname' . $number,
            'idnumber' => 'idnumber' . $number,
            'description' => 'description' . $number,
            'descriptionformat' => FORMAT_HTML,
            'scaleid' => $number,
            'scaleconfiguration' => $this->$scalepropname,
            'visible' => true,
            'contextid' => $system ? context_system::instance()->id : context_coursecat::instance($this->category->id)->id
        );
        $result = external::update_competency_framework($framework);
        return external_api::clean_returnvalue(external::update_competency_framework_returns(), $result);
    }

    protected function create_competency($number, $frameworkid) {
        $competency = array(
            'shortname' => 'shortname' . $number,
            'idnumber' => 'idnumber' . $number,
            'description' => 'description' . $number,
            'descriptionformat' => FORMAT_HTML,
            'competencyframeworkid' => $frameworkid,
            'sortorder' => 0
        );
        $result = external::create_competency($competency);
        return (object) external_api::clean_returnvalue(external::create_competency_returns(), $result);
    }

    protected function update_competency($id, $number) {
        $competency = array(
            'id' => $id,
            'shortname' => 'shortname' . $number,
            'idnumber' => 'idnumber' . $number,
            'description' => 'description' . $number,
            'descriptionformat' => FORMAT_HTML,
            'sortorder' => 0
        );
        $result = external::update_competency($competency);
        return external_api::clean_returnvalue(external::update_competency_returns(), $result);
    }

    /**
     * Test we can't create a competency framework with only read permissions.
     */
    public function test_create_competency_frameworks_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->user);

        $result = $this->create_competency_framework(1, true);
    }

    /**
     * Test we can't create a competency framework with only read permissions.
     */
    public function test_create_competency_frameworks_with_read_permissions_in_category() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->catuser);
        $result = $this->create_competency_framework(1, false);
    }

    /**
     * Test we can create a competency framework with manage permissions.
     */
    public function test_create_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(1, $result->scaleid);
        $this->assertEquals($this->scaleconfiguration1, $result->scaleconfiguration);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can create a competency framework with manage permissions.
     */
    public function test_create_competency_frameworks_with_manage_permissions_in_category() {
        $this->setUser($this->catcreator);
        $result = $this->create_competency_framework(1, false);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->catcreator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(1, $result->scaleid);
        $this->assertEquals($this->scaleconfiguration1, $result->scaleconfiguration);
        $this->assertEquals(true, $result->visible);

        try {
            $result = $this->create_competency_framework(1, true);
            $this->fail('User cannot create a framework at system level.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we cannot create a competency framework with nasty data.
     */
    public function test_create_competency_frameworks_with_nasty_data() {
        $this->setUser($this->creator);
        $this->setExpectedException('invalid_parameter_exception');
        $framework = array(
            'shortname' => 'short<a href="">',
            'idnumber' => 'id;"number',
            'description' => 'de<>\\..scription',
            'descriptionformat' => FORMAT_HTML,
            'scaleid' => 1,
            'scaleconfiguration' => $this->scaleconfiguration1,
            'visible' => true,
            'contextid' => context_system::instance()->id
        );
        $result = external::create_competency_framework($framework);
    }

    /**
     * Test we can read a competency framework with manage permissions.
     */
    public function test_read_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);

        $id = $result->id;
        $result = external::read_competency_framework($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_framework_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(1, $result->scaleid);
        $this->assertEquals($this->scaleconfiguration1, $result->scaleconfiguration);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can read a competency framework with manage permissions.
     */
    public function test_read_competency_frameworks_with_manage_permissions_in_category() {
        $this->setUser($this->creator);

        $insystem = $this->create_competency_framework(1, true);
        $incat = $this->create_competency_framework(2, false);

        $this->setUser($this->catcreator);
        $id = $incat->id;
        $result = external::read_competency_framework($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_framework_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname2', $result->shortname);
        $this->assertEquals('idnumber2', $result->idnumber);
        $this->assertEquals('description2', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(2, $result->scaleid);
        $this->assertEquals($this->scaleconfiguration2, $result->scaleconfiguration);
        $this->assertEquals(true, $result->visible);

        try {
            $id = $insystem->id;
            $result = external::read_competency_framework($id);
            $result = (object) external_api::clean_returnvalue(external::read_competency_framework_returns(), $result);
            $this->fail('User cannot read a framework at system level.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we can read a competency framework with read permissions.
     */
    public function test_read_competency_frameworks_with_read_permissions() {
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);

        // Switch users to someone with less permissions.
        $this->setUser($this->user);
        $id = $result->id;
        $result = external::read_competency_framework($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_framework_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(1, $result->scaleid);
        $this->assertEquals($this->scaleconfiguration1, $result->scaleconfiguration);
        $this->assertEquals(true, $result->visible);
    }
    /**
     * Test we can read a competency framework with read permissions.
     */
    public function test_read_competency_frameworks_with_read_permissions_in_category() {
        $this->setUser($this->creator);

        $insystem = $this->create_competency_framework(1, true);
        $incat = $this->create_competency_framework(2, false);

        // Switch users to someone with less permissions.
        $this->setUser($this->catuser);
        $id = $incat->id;
        $result = external::read_competency_framework($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_framework_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname2', $result->shortname);
        $this->assertEquals('idnumber2', $result->idnumber);
        $this->assertEquals('description2', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(2, $result->scaleid);
        $this->assertEquals($this->scaleconfiguration2, $result->scaleconfiguration);
        $this->assertEquals(true, $result->visible);

        // Switching to user with no permissions.
        try {
            $result = external::read_competency_framework($insystem->id);
            $this->fail('Current user cannot should not be able to read the framework.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we can delete a competency framework with manage permissions.
     */
    public function test_delete_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);

        $id = $result->id;
        $result = external::delete_competency_framework($id);
        $result = external_api::clean_returnvalue(external::delete_competency_framework_returns(), $result);

        $this->assertTrue($result);
    }

    /**
     * Test we can delete a competency framework with manage permissions.
     */
    public function test_delete_competency_frameworks_with_manage_permissions_in_category() {
        $this->setUser($this->creator);

        $insystem = $this->create_competency_framework(1, true);
        $incat = $this->create_competency_framework(2, false);

        $this->setUser($this->catcreator);
        $id = $incat->id;
        $result = external::delete_competency_framework($id);
        $result = external_api::clean_returnvalue(external::delete_competency_framework_returns(), $result);

        $this->assertTrue($result);

        try {
            $id = $insystem->id;
            $result = external::delete_competency_framework($id);
            $result = external_api::clean_returnvalue(external::delete_competency_framework_returns(), $result);
            $this->fail('Current user cannot should not be able to delete the framework.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we can delete a competency framework with read permissions.
     */
    public function test_delete_competency_frameworks_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);

        $id = $result->id;
        // Switch users to someone with less permissions.
        $this->setUser($this->user);
        $result = external::delete_competency_framework($id);
    }

    /**
     * Test we can update a competency framework with manage permissions.
     */
    public function test_update_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);

        $result = $this->update_competency_framework($result->id, 2, true);

        $this->assertTrue($result);
    }

    /**
     * Test we can update a competency framework with manage permissions.
     */
    public function test_update_competency_frameworks_with_manage_permissions_in_category() {
        $this->setUser($this->creator);

        $insystem = $this->create_competency_framework(1, true);
        $incat = $this->create_competency_framework(2, false);

        $this->setUser($this->catcreator);
        $id = $incat->id;

        $result = $this->update_competency_framework($incat->id, 3, false);

        $this->assertTrue($result);

        try {
            $result = $this->update_competency_framework($insystem->id, 4, true);
            $this->fail('Current user should not be able to update the framework.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    public function test_update_framework_scale() {
        $this->setUser($this->creator);
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');

        $s1 = $this->getDataGenerator()->create_scale();

        $f1 = $lpg->create_framework(array('scaleid' => 1));
        $f2 = $lpg->create_framework(array('scaleid' => 1));
        $c1 = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id()));
        $c2 = $lpg->create_competency(array('competencyframeworkid' => $f2->get_id()));

        $this->assertEquals(1, $f1->get_scaleid());

        // Make the scale of f2 being used.
        $lpg->create_user_competency(array('userid' => $this->user->id, 'competencyid' => $c2->get_id()));

        // Changing the framework where the scale is not used.
        $result = $this->update_competency_framework($f1->get_id(), 3, true);

        $f1 = new \tool_lp\competency_framework($f1->get_id());
        $this->assertEquals(3, $f1->get_scaleid());

        // Changing the framework where the scale is used.
        try {
            $result = $this->update_competency_framework($f2->get_id(), 4, true);
            $this->fail('The scale cannot be changed once used.');
        } catch (\tool_lp\invalid_persistent_exception $e) {
            $this->assertRegexp('/scaleid/', $e->getMessage());
        }
    }

    /**
     * Test we can update a competency framework with read permissions.
     */
    public function test_update_competency_frameworks_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);

        $this->setUser($this->user);
        $result = $this->update_competency_framework($result->id, 2, true);
    }

    /**
     * Test we can list and count competency frameworks with manage permissions.
     */
    public function test_list_and_count_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);
        $result = $this->create_competency_framework(2, true);
        $result = $this->create_competency_framework(3, true);
        $result = $this->create_competency_framework(4, false);

        $result = external::count_competency_frameworks(array('contextid' => context_system::instance()->id), 'self');
        $result = external_api::clean_returnvalue(external::count_competency_frameworks_returns(), $result);

        $this->assertEquals($result, 3);

        $result = external::list_competency_frameworks('shortname', 'ASC', 0, 10,
            array('contextid' => context_system::instance()->id), 'self', false);
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(1, $result->scaleid);
        $this->assertEquals($this->scaleconfiguration1, $result->scaleconfiguration);
        $this->assertEquals(true, $result->visible);
    }

    public function list_competency_frameworks_with_query() {
        $this->setUser($this->creator);
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $framework1 = $lpg->create_framework(array(
            'shortname' => 'shortname_beetroot',
            'idnumber' => 'idnumber_cinnamon',
            'description' => 'description',
            'descriptionformat' => FORMAT_HTML,
            'visible' => true,
            'contextid' => context_system::instance()->id
        ));
        $framework2 = $lpg->create_framework(array(
            'shortname' => 'shortname_citrus',
            'idnumber' => 'idnumber_beer',
            'description' => 'description',
            'descriptionformat' => FORMAT_HTML,
            'visible' => true,
            'contextid' => context_system::instance()->id
        ));

        // Search on both ID number and shortname.
        $result = external::list_competency_frameworks('shortname', 'ASC', 0, 10,
            array('contextid' => context_system::instance()->id), 'self', false, 'bee');
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);
        $this->assertCount(2, $result);
        $f = (object) array_shift($result);
        $this->assertEquals($framework1->get_id(), $f->get_id());
        $f = (object) array_shift($result);
        $this->assertEquals($framework2->get_id(), $f->get_id());

        // Search on ID number.
        $result = external::list_competency_frameworks('shortname', 'ASC', 0, 10,
            array('contextid' => context_system::instance()->id), 'self', false, 'beer');
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);
        $this->assertCount(1, $result);
        $f = (object) array_shift($result);
        $this->assertEquals($framework2->get_id(), $f->get_id());

        // Search on shortname.
        $result = external::list_competency_frameworks('shortname', 'ASC', 0, 10,
            array('contextid' => context_system::instance()->id), 'self', false, 'cinnamon');
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);
        $this->assertCount(1, $result);
        $f = (object) array_shift($result);
        $this->assertEquals($framework1->get_id(), $f->get_id());

        // No match.
        $result = external::list_competency_frameworks('shortname', 'ASC', 0, 10,
            array('contextid' => context_system::instance()->id), 'self', false, 'pwnd!');
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);
        $this->assertCount(0, $result);
    }

    /**
     * Test we can list and count competency frameworks with read permissions.
     */
    public function test_list_and_count_competency_frameworks_with_read_permissions() {
        $this->setUser($this->creator);
        $result = $this->create_competency_framework(1, true);
        $result = $this->create_competency_framework(2, true);
        $result = $this->create_competency_framework(3, true);
        $result = $this->create_competency_framework(4, false);

        $this->setUser($this->user);
        $result = external::count_competency_frameworks(array('contextid' => context_system::instance()->id), 'self');
        $result = external_api::clean_returnvalue(external::count_competency_frameworks_returns(), $result);
        $this->assertEquals($result, 3);

        $result = external::list_competency_frameworks('shortname', 'ASC', 0, 10,
            array('contextid' => context_system::instance()->id), 'self', false);
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(1, $result->scaleid);
        $this->assertEquals($this->scaleconfiguration1, $result->scaleconfiguration);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can't create a competency with only read permissions.
     */
    public function test_create_competency_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $framework = $this->create_competency_framework(1, true);
        $this->setUser($this->user);
        $competency = $this->create_competency(1, $framework->id);
    }

    /**
     * Test we can create a competency with manage permissions.
     */
    public function test_create_competency_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $competency = $this->create_competency(1, $framework->id);

        $this->assertGreaterThan(0, $competency->timecreated);
        $this->assertGreaterThan(0, $competency->timemodified);
        $this->assertEquals($this->creator->id, $competency->usermodified);
        $this->assertEquals('shortname1', $competency->shortname);
        $this->assertEquals('idnumber1', $competency->idnumber);
        $this->assertEquals('description1', $competency->description);
        $this->assertEquals(FORMAT_HTML, $competency->descriptionformat);
        $this->assertEquals(0, $competency->parentid);
        $this->assertEquals($framework->id, $competency->competencyframeworkid);
    }


    /**
     * Test we can create a competency with manage permissions.
     */
    public function test_create_competency_with_manage_permissions_in_category() {
        $this->setUser($this->creator);

        $insystem = $this->create_competency_framework(1, true);
        $incat = $this->create_competency_framework(2, false);

        $this->setUser($this->catcreator);

        $competency = $this->create_competency(1, $incat->id);

        $this->assertGreaterThan(0, $competency->timecreated);
        $this->assertGreaterThan(0, $competency->timemodified);
        $this->assertEquals($this->catcreator->id, $competency->usermodified);
        $this->assertEquals('shortname1', $competency->shortname);
        $this->assertEquals('idnumber1', $competency->idnumber);
        $this->assertEquals('description1', $competency->description);
        $this->assertEquals(FORMAT_HTML, $competency->descriptionformat);
        $this->assertEquals(0, $competency->parentid);
        $this->assertEquals($incat->id, $competency->competencyframeworkid);

        try {
            $competency = $this->create_competency(2, $insystem->id);
            $this->fail('User should not be able to create a competency in system context.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we cannot create a competency with nasty data.
     */
    public function test_create_competency_with_nasty_data() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $this->setExpectedException('invalid_parameter_exception');
        $competency = array(
            'shortname' => 'shortname<a href="">',
            'idnumber' => 'id;"number',
            'description' => 'de<>\\..scription',
            'descriptionformat' => FORMAT_HTML,
            'competencyframeworkid' => $framework->id,
            'sortorder' => 0
        );
        $result = external::create_competency($competency);
        $result = (object) external_api::clean_returnvalue(external::create_competency_returns(), $result);
    }

    /**
     * Test we can read a competency with manage permissions.
     */
    public function test_read_competencies_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $competency = $this->create_competency(1, $framework->id);

        $id = $competency->id;
        $result = external::read_competency($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(0, $result->parentid);
        $this->assertEquals($framework->id, $result->competencyframeworkid);
    }

    /**
     * Test we can read a competency with manage permissions.
     */
    public function test_read_competencies_with_manage_permissions_in_category() {
        $this->setUser($this->creator);

        $sysframework = $this->create_competency_framework(1, true);
        $insystem = $this->create_competency(1, $sysframework->id);

        $catframework = $this->create_competency_framework(2, false);
        $incat = $this->create_competency(2, $catframework->id);

        $this->setUser($this->catcreator);
        $id = $incat->id;
        $result = external::read_competency($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname2', $result->shortname);
        $this->assertEquals('idnumber2', $result->idnumber);
        $this->assertEquals('description2', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(0, $result->parentid);
        $this->assertEquals($catframework->id, $result->competencyframeworkid);

        try {
            external::read_competency($insystem->id);
            $this->fail('User should not be able to read a competency in system context.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we can read a competency with read permissions.
     */
    public function test_read_competencies_with_read_permissions() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $competency = $this->create_competency(1, $framework->id);

        // Switch users to someone with less permissions.
        $this->setUser($this->user);
        $id = $competency->id;
        $result = external::read_competency($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(0, $result->parentid);
        $this->assertEquals($framework->id, $result->competencyframeworkid);
    }

    /**
     * Test we can read a competency with read permissions.
     */
    public function test_read_competencies_with_read_permissions_in_category() {
        $this->setUser($this->creator);
        $sysframework = $this->create_competency_framework(1, true);
        $insystem = $this->create_competency(1, $sysframework->id);
        $catframework = $this->create_competency_framework(2, false);
        $incat = $this->create_competency(2, $catframework->id);

        // Switch users to someone with less permissions.
        $this->setUser($this->catuser);
        $id = $incat->id;
        $result = external::read_competency($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname2', $result->shortname);
        $this->assertEquals('idnumber2', $result->idnumber);
        $this->assertEquals('description2', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(0, $result->parentid);
        $this->assertEquals($catframework->id, $result->competencyframeworkid);

        try {
            external::read_competency($insystem->id);
            $this->fail('User should not be able to read a competency in system context.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we can delete a competency with manage permissions.
     */
    public function test_delete_competency_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $result = $this->create_competency(1, $framework->id);

        $id = $result->id;
        $result = external::delete_competency($id);
        $result = external_api::clean_returnvalue(external::delete_competency_returns(), $result);

        $this->assertTrue($result);
    }

    /**
     * Test we can delete a competency with manage permissions.
     */
    public function test_delete_competency_with_manage_permissions_in_category() {
        $this->setUser($this->creator);

        $sysframework = $this->create_competency_framework(1, true);
        $insystem = $this->create_competency(1, $sysframework->id);
        $catframework = $this->create_competency_framework(2, false);
        $incat = $this->create_competency(2, $catframework->id);

        $this->setUser($this->catcreator);
        $id = $incat->id;
        $result = external::delete_competency($id);
        $result = external_api::clean_returnvalue(external::delete_competency_returns(), $result);

        $this->assertTrue($result);

        try {
            $result = external::delete_competency($insystem->id);
            $this->fail('User should not be able to delete a competency in system context.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we can delete a competency with read permissions.
     */
    public function test_delete_competency_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $result = $this->create_competency(1, $framework->id);

        $id = $result->id;
        // Switch users to someone with less permissions.
        $this->setUser($this->user);
        $result = external::delete_competency($id);
    }

    /**
     * Test we can update a competency with manage permissions.
     */
    public function test_update_competency_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $result = $this->create_competency(1, $framework->id);

        $result = $this->update_competency($result->id, 2);

        $this->assertTrue($result);
    }

    /**
     * Test we can update a competency with manage permissions.
     */
    public function test_update_competency_with_manage_permissions_in_category() {
        $this->setUser($this->creator);

        $sysframework = $this->create_competency_framework(1, true);
        $insystem = $this->create_competency(1, $sysframework->id);
        $catframework = $this->create_competency_framework(2, false);
        $incat = $this->create_competency(2, $catframework->id);

        $this->setUser($this->catcreator);

        $result = $this->update_competency($incat->id, 2);

        $this->assertTrue($result);

        try {
            $result = $this->update_competency($insystem->id, 3);
            $this->fail('User should not be able to update a competency in system context.');
        } catch (required_capability_exception $e) {
            // All good.
        }
    }

    /**
     * Test we can update a competency with read permissions.
     */
    public function test_update_competency_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $result = $this->create_competency(1, $framework->id);

        $this->setUser($this->user);
        $result = $this->update_competency($result->id, 2);
    }

    /**
     * Test count competencies with filters.
     */
    public function test_count_competencies_with_filters() {
        $this->setUser($this->creator);

        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $f1 = $lpg->create_framework();
        $f2 = $lpg->create_framework();
        $c1 = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id()));
        $c2 = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id(), 'shortname' => 'A'));
        $c3 = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id()));
        $c4 = $lpg->create_competency(array('competencyframeworkid' => $f2->get_id()));
        $c5 = $lpg->create_competency(array('competencyframeworkid' => $f2->get_id()));

        $result = external::count_competencies(array(array('column' => 'competencyframeworkid', 'value' => $f2->get_id())));
        $result = external_api::clean_returnvalue(external::count_competencies_returns(), $result);
        $this->assertEquals(2, $result);

        $result = external::count_competencies(array(array('column' => 'competencyframeworkid', 'value' => $f1->get_id())));
        $result = external_api::clean_returnvalue(external::count_competencies_returns(), $result);
        $this->assertEquals(3, $result);

        $result = external::count_competencies(array(array('column' => 'shortname', 'value' => 'A')));
        $result = external_api::clean_returnvalue(external::count_competencies_returns(), $result);
        $this->assertEquals(1, $result);
    }

    /**
     * Test we can list and count competencies with manage permissions.
     */
    public function test_list_and_count_competencies_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $result = $this->create_competency(1, $framework->id);
        $result = $this->create_competency(2, $framework->id);
        $result = $this->create_competency(3, $framework->id);

        $result = external::count_competencies(array());
        $result = external_api::clean_returnvalue(external::count_competencies_returns(), $result);

        $this->assertEquals($result, 3);

        array('id' => $result = external::list_competencies(array(), 'shortname', 'ASC', 0, 10, context_system::instance()->id));
        $result = external_api::clean_returnvalue(external::list_competencies_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
    }

    /**
     * Test we can list and count competencies with read permissions.
     */
    public function test_list_and_count_competencies_with_read_permissions() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $result = $this->create_competency(1, $framework->id);
        $result = $this->create_competency(2, $framework->id);
        $result = $this->create_competency(3, $framework->id);

        $this->setUser($this->user);

        $result = external::count_competencies(array());
        $result = external_api::clean_returnvalue(external::count_competencies_returns(), $result);

        $this->assertEquals($result, 3);

        array('id' => $result = external::list_competencies(array(), 'shortname', 'ASC', 0, 10, context_system::instance()->id));
        $result = external_api::clean_returnvalue(external::list_competencies_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
    }

    /**
     * Test we can search for competencies.
     */
    public function test_search_competencies_with_read_permissions() {
        $this->setUser($this->creator);
        $framework = $this->create_competency_framework(1, true);
        $result = $this->create_competency(1, $framework->id);
        $result = $this->create_competency(2, $framework->id);
        $result = $this->create_competency(3, $framework->id);

        $this->setUser($this->user);

        $result = external::search_competencies('short', $framework->id);
        $result = external_api::clean_returnvalue(external::search_competencies_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals('idnumber1', $result->idnumber);
        $this->assertEquals('description1', $result->description);
    }

    /**
     * Test plans creation and updates.
     */
    public function test_create_and_update_plans() {
        $syscontext = context_system::instance();

        $this->setUser($this->creator);
        $plan0 = $this->create_plan(1, $this->creator->id, 0, plan::STATUS_ACTIVE, 0);

        $this->setUser($this->user);

        try {
            $plan1 = $this->create_plan(2, $this->user->id, 0, plan::STATUS_DRAFT, 0);
            $this->fail('Exception expected due to not permissions to create draft plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        assign_capability('tool/lp:planmanageowndraft', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->user);

        $plan2 = $this->create_plan(3, $this->user->id, 0, plan::STATUS_DRAFT, 0);

        try {
            $plan3 = $this->create_plan(4, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
            $this->fail('Exception expected due to not permissions to create active plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        try {
            $plan3 = $this->update_plan($plan2->id, 4, $this->user->id, 0, plan::STATUS_COMPLETE, 0);
            $this->fail('We cannot complete a plan using api::update_plan().');
        } catch (coding_exception $e) {
            $this->assertTrue(true);
        }

        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $plan3 = $this->create_plan(4, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        try {
            $plan4 = $this->create_plan(6, $this->creator->id, 0, plan::STATUS_COMPLETE, 0);
            $this->fail('Plans cannot be created as complete.');
        } catch (coding_exception $e) {
            $this->assertRegexp('/A plan cannot be created as complete./', $e->getMessage());
        }

        try {
            $plan0 = $this->update_plan($plan0->id, 1, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        unassign_capability('tool/lp:planmanageown', $this->userrole, $syscontext->id);
        unassign_capability('tool/lp:planmanageowndraft', $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            // Cannot be updated even if they created it.
            $this->update_plan($plan2->id, 1, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
            $this->fail('The user can not update their own plan without permissions.');
        } catch (required_capability_exception $e) {
            $this->assertRegexp('/Manage learning plans./', $e->getMessage());
        }
    }

    /**
     * Test complete plan.
     */
    public function test_complete_plan() {
        $syscontext = context_system::instance();

        $this->setUser($this->creator);

        $this->setUser($this->user);

        assign_capability('tool/lp:planmanageowndraft', CAP_ALLOW, $this->userrole, $syscontext->id);
        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->user);

        $plan = $this->create_plan(1, $this->user->id, 0, plan::STATUS_ACTIVE, 0);

        $result = external::complete_plan($plan->id);
        $this->assertTrue($result);
    }

    /**
     * Test reopen plan.
     */
    public function test_reopen_plan() {
        $syscontext = context_system::instance();

        $this->setUser($this->creator);

        $this->setUser($this->user);

        assign_capability('tool/lp:planmanageowndraft', CAP_ALLOW, $this->userrole, $syscontext->id);
        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->user);

        $plan = $this->create_plan(1, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        external::complete_plan($plan->id);

        $result = external::reopen_plan($plan->id);
        $this->assertTrue($result);
    }

    /**
     * Test that we can read plans.
     */
    public function test_read_plans() {
        global $OUTPUT;
        $this->setUser($this->creator);

        $syscontext = context_system::instance();

        $plan1 = $this->create_plan(1, $this->user->id, 0, plan::STATUS_DRAFT, 0);
        $plan2 = $this->create_plan(2, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        $plan3 = $this->create_plan(3, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        external::complete_plan($plan3->id);
        $plan3 = (object) external::read_plan($plan3->id);

        $data = external::read_plan($plan1->id);
        $this->assertEquals((array)$plan1, external::read_plan($plan1->id));
        $data = external::read_plan($plan2->id);
        $this->assertEquals((array)$plan2, external::read_plan($plan2->id));
        $data = external::read_plan($plan3->id);
        $this->assertEquals((array)$plan3, external::read_plan($plan3->id));

        $this->setUser($this->user);

        // The normal user can not edit these plans.
        $plan1->canmanage = false;
        $plan2->canmanage = false;
        $plan3->canmanage = false;
        $plan1->canbeedited = false;
        $plan2->canbeedited = false;
        $plan3->canbeedited = false;
        $plan1->canrequestreview = true;
        $plan2->canrequestreview = true;
        $plan3->canrequestreview = true;
        $plan1->canreview = false;
        $plan2->canreview = false;
        $plan3->canreview = false;
        $plan1->iscompleteallowed = false;
        $plan2->iscompleteallowed = false;
        $plan3->iscompleteallowed = false;
        $plan1->isrequestreviewallowed = true;
        $plan2->isrequestreviewallowed = true;
        $plan3->isrequestreviewallowed = true;
        $plan1->isapproveallowed = false;
        $plan2->isapproveallowed = false;
        $plan3->isapproveallowed = false;
        $plan1->isunapproveallowed = false;
        $plan2->isunapproveallowed = false;
        $plan3->isunapproveallowed = false;
        $plan3->isreopenallowed = false;
        $plan1->commentarea['canpost'] = false;
        $plan1->commentarea['canview'] = true;

        // Prevent the user from seeing their own non-draft plans.
        assign_capability('tool/lp:plancommentown', CAP_PROHIBIT, $this->userrole, $syscontext->id, true);
        assign_capability('tool/lp:planviewown', CAP_PROHIBIT, $this->userrole, $syscontext->id, true);
        assign_capability('tool/lp:planviewowndraft', CAP_ALLOW, $this->userrole, $syscontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertEquals((array)$plan1, external::read_plan($plan1->id));

        try {
            external::read_plan($plan2->id);
            $this->fail('Exception expected due to not permissions to read plan');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        try {
            external::read_plan($plan3->id);
            $this->fail('Exception expected due to not permissions to read plan');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Allow user to see their plan.
        assign_capability('tool/lp:plancommentown', CAP_ALLOW, $this->userrole, $syscontext->id, true);
        assign_capability('tool/lp:planviewown', CAP_ALLOW, $this->userrole, $syscontext->id, true);
        assign_capability('tool/lp:planmanageowndraft', CAP_PROHIBIT, $this->userrole, $syscontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $plan1->commentarea['canpost'] = true;
        $plan1->commentarea['canview'] = true;
        $plan2->commentarea['canpost'] = true;
        $plan2->isrequestreviewallowed = false;
        $plan3->commentarea['canpost'] = true;
        $plan3->isrequestreviewallowed = false;
        $plan1->commentarea['canpostorhascomments'] = true;
        $plan2->commentarea['canpostorhascomments'] = true;
        $plan3->commentarea['canpostorhascomments'] = true;

        $this->assertEquals((array)$plan1, external::read_plan($plan1->id));
        $this->assertEquals((array)$plan2, external::read_plan($plan2->id));
        $this->assertEquals((array)$plan3, external::read_plan($plan3->id));

        // Allow use to manage their own draft plan.
        assign_capability('tool/lp:planviewown', CAP_PROHIBIT, $this->userrole, $syscontext->id, true);
        assign_capability('tool/lp:planmanageown', CAP_PROHIBIT, $this->userrole, $syscontext->id, true);
        assign_capability('tool/lp:planmanageowndraft', CAP_ALLOW, $this->userrole, $syscontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $plan1->canmanage = true;
        $plan1->canbeedited = true;
        $plan1->canrequestreview = true;
        $plan1->isrequestreviewallowed = true;
        $this->assertEquals((array)$plan1, external::read_plan($plan1->id));
        try {
            external::read_plan($plan2->id);
            $this->fail('Exception expected due to not permissions to read plan');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        try {
            external::read_plan($plan3->id);
            $this->fail('Exception expected due to not permissions to read plan');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Allow use to manage their plan.
        assign_capability('tool/lp:planviewown', CAP_PROHIBIT, $this->userrole, $syscontext->id, true);
        assign_capability('tool/lp:planmanageowndraft', CAP_PROHIBIT, $this->userrole, $syscontext->id, true);
        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->userrole, $syscontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $plan1->canmanage = false;
        $plan1->canbeedited = false;
        $plan1->canrequestreview = true;
        $plan1->canreview = true;
        $plan1->isrequestreviewallowed = true;
        $plan1->isapproveallowed = true;
        $plan1->iscompleteallowed = false;

        $plan2->canmanage = true;
        $plan2->canbeedited = true;
        $plan2->canreview = true;
        $plan2->iscompleteallowed = true;
        $plan2->isunapproveallowed = true;

        $plan3->canmanage = true;
        $plan3->canreview = true;
        $plan3->isreopenallowed = true;

        $this->assertEquals((array)$plan1, external::read_plan($plan1->id));
        $this->assertEquals((array)$plan2, external::read_plan($plan2->id));
        $this->assertEquals((array)$plan3, external::read_plan($plan3->id));
    }

    public function test_delete_plans() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();

        $plan1 = $this->create_plan(1, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        $plan2 = $this->create_plan(2, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        $plan3 = $this->create_plan(3, $this->creator->id, 0, plan::STATUS_ACTIVE, 0);

        $this->assertTrue(external::delete_plan($plan1->id));

        unassign_capability('tool/lp:planmanage', $this->creatorrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            external::delete_plan($plan2->id);
            $this->fail('Exception expected due to not permissions to manage plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        $this->setUser($this->user);

        // Can not delete plans created by other users.
        try {
            external::delete_plan($plan2->id);
            $this->fail('Exception expected due to not permissions to manage plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(external::delete_plan($plan2->id));

        // Can not delete plans created for other users.
        try {
            external::delete_plan($plan3->id);
            $this->fail('Exception expected due to not permissions to manage plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        $plan4 = $this->create_plan(4, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        $this->assertTrue(external::delete_plan($plan4->id));
    }

    public function test_delete_plan_removes_relations() {
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('tool_lp');

        $user = $dg->create_user();
        $plan = $lpg->create_plan(array('userid' => $user->id));
        $framework = $lpg->create_framework();
        $comp1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $comp2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $comp3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $pc1 = $lpg->create_plan_competency(array('planid' => $plan->get_id(), 'competencyid' => $comp1->get_id()));
        $pc2 = $lpg->create_plan_competency(array('planid' => $plan->get_id(), 'competencyid' => $comp2->get_id()));
        $pc3 = $lpg->create_plan_competency(array('planid' => $plan->get_id(), 'competencyid' => $comp3->get_id()));

        // Complete the plan to generate user_competency_plan entries.
        api::complete_plan($plan);

        // Confirm the data we have.
        $this->assertEquals(3, plan_competency::count_records(array('planid' => $plan->get_id())));
        $this->assertEquals(3, user_competency_plan::count_records(array('planid' => $plan->get_id(), 'userid' => $user->id)));

        // Delete the plan now.
        api::delete_plan($plan->get_id());
        $this->assertEquals(0, plan_competency::count_records(array('planid' => $plan->get_id())));
        $this->assertEquals(0, user_competency_plan::count_records(array('planid' => $plan->get_id(), 'userid' => $user->id)));
    }

    public function test_list_plan_competencies() {
        $this->setUser($this->creator);

        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('tool_lp');

        $f1 = $lpg->create_framework();
        $f2 = $lpg->create_framework();

        $c1a = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id()));
        $c1b = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id()));
        $c1c = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id()));
        $c2a = $lpg->create_competency(array('competencyframeworkid' => $f2->get_id()));
        $c2b = $lpg->create_competency(array('competencyframeworkid' => $f2->get_id()));

        $tpl = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $tpl->get_id(), 'competencyid' => $c1a->get_id()));
        $lpg->create_template_competency(array('templateid' => $tpl->get_id(), 'competencyid' => $c1c->get_id()));
        $lpg->create_template_competency(array('templateid' => $tpl->get_id(), 'competencyid' => $c2b->get_id()));

        $plan = $lpg->create_plan(array('userid' => $this->user->id, 'templateid' => $tpl->get_id()));

        $uc1a = $lpg->create_user_competency(array('userid' => $this->user->id, 'competencyid' => $c1a->get_id(),
            'status' => user_competency::STATUS_IN_REVIEW, 'reviewerid' => $this->creator->id));
        $uc1b = $lpg->create_user_competency(array('userid' => $this->user->id, 'competencyid' => $c1b->get_id()));
        $uc2b = $lpg->create_user_competency(array('userid' => $this->user->id, 'competencyid' => $c2b->get_id(),
            'grade' => 2, 'proficiency' => 1));
        $ux1a = $lpg->create_user_competency(array('userid' => $this->creator->id, 'competencyid' => $c1a->get_id()));

        $result = external::list_plan_competencies($plan->get_id());
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);

        $this->assertCount(3, $result);
        $this->assertEquals($c1a->get_id(), $result[0]['competency']['id']);
        $this->assertEquals($this->user->id, $result[0]['usercompetency']['userid']);
        $this->assertArrayNotHasKey('usercompetencyplan', $result[0]);
        $this->assertEquals($c1c->get_id(), $result[1]['competency']['id']);
        $this->assertEquals($this->user->id, $result[1]['usercompetency']['userid']);
        $this->assertArrayNotHasKey('usercompetencyplan', $result[1]);
        $this->assertEquals($c2b->get_id(), $result[2]['competency']['id']);
        $this->assertEquals($this->user->id, $result[2]['usercompetency']['userid']);
        $this->assertArrayNotHasKey('usercompetencyplan', $result[2]);
        $this->assertEquals(user_competency::STATUS_IN_REVIEW, $result[0]['usercompetency']['status']);
        $this->assertEquals(null, $result[1]['usercompetency']['grade']);
        $this->assertEquals(2, $result[2]['usercompetency']['grade']);
        $this->assertEquals(1, $result[2]['usercompetency']['proficiency']);

        // Check the return values when the plan status is complete.
        $completedplan = $lpg->create_plan(array('userid' => $this->user->id, 'templateid' => $tpl->get_id(),
                'status' => plan::STATUS_COMPLETE));

        $uc1a = $lpg->create_user_competency_plan(array('userid' => $this->user->id, 'competencyid' => $c1a->get_id(),
                'planid' => $completedplan->get_id()));
        $uc1b = $lpg->create_user_competency_plan(array('userid' => $this->user->id, 'competencyid' => $c1c->get_id(),
                'planid' => $completedplan->get_id()));
        $uc2b = $lpg->create_user_competency_plan(array('userid' => $this->user->id, 'competencyid' => $c2b->get_id(),
                'planid' => $completedplan->get_id(), 'grade' => 2, 'proficiency' => 1));
        $ux1a = $lpg->create_user_competency_plan(array('userid' => $this->creator->id, 'competencyid' => $c1a->get_id(),
                'planid' => $completedplan->get_id()));

        $result = external::list_plan_competencies($completedplan->get_id());
        $result = external::clean_returnvalue(external::list_plan_competencies_returns(), $result);

        $this->assertCount(3, $result);
        $this->assertEquals($c1a->get_id(), $result[0]['competency']['id']);
        $this->assertEquals($this->user->id, $result[0]['usercompetencyplan']['userid']);
        $this->assertArrayNotHasKey('usercompetency', $result[0]);
        $this->assertEquals($c1c->get_id(), $result[1]['competency']['id']);
        $this->assertEquals($this->user->id, $result[1]['usercompetencyplan']['userid']);
        $this->assertArrayNotHasKey('usercompetency', $result[1]);
        $this->assertEquals($c2b->get_id(), $result[2]['competency']['id']);
        $this->assertEquals($this->user->id, $result[2]['usercompetencyplan']['userid']);
        $this->assertArrayNotHasKey('usercompetency', $result[2]);
        $this->assertEquals(null, $result[1]['usercompetencyplan']['grade']);
        $this->assertEquals(2, $result[2]['usercompetencyplan']['grade']);
        $this->assertEquals(1, $result[2]['usercompetencyplan']['proficiency']);
    }

    public function test_add_competency_to_template() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();

        // Create a template.
        $template = $this->create_template(1, true);

        // Create a competency.
        $framework = $this->create_competency_framework(1, true);
        $competency = $this->create_competency(1, $framework->id);

        // Add the competency.
        external::add_competency_to_template($template->id, $competency->id);

        // Check that it was added.
        $this->assertEquals(1, external::count_competencies_in_template($template->id));

        // Unassign capability.
        unassign_capability('tool/lp:templatemanage', $this->creatorrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Check we can not add the competency now.
        try {
            external::add_competency_to_template($template->id, $competency->id);
            $this->fail('Exception expected due to not permissions to manage template competencies');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
    }

    public function test_remove_competency_from_template() {
        $syscontext = context_system::instance();
        $this->setUser($this->creator);
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');

        // Create a template.
        $template = $this->create_template(1, true);

        // Create a competency.
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));

        // Add the competency.
        external::add_competency_to_template($template->id, $competency->get_id());

        // Check that it was added.
        $this->assertEquals(1, external::count_competencies_in_template($template->id));

        // Check that we can remove the competency.
        external::remove_competency_from_template($template->id, $competency->get_id());

        // Check that it was removed.
        $this->assertEquals(0, external::count_competencies_in_template($template->id));

        // Unassign capability.
        unassign_capability('tool/lp:templatemanage', $this->creatorrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Check we can not remove the competency now.
        try {
            external::add_competency_to_template($template->id, $competency->get_id());
            $this->fail('Exception expected due to not permissions to manage template competencies');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
    }

    /**
     * Test we can re-order competency frameworks.
     */
    public function test_reorder_template_competencies() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();
        $onehour = time() + 60 * 60;

        // Create a template.
        $template = $this->create_template(1, true);

        // Create a competency framework.
        $framework = $this->create_competency_framework(1, true);

        // Create multiple competencies.
        $competency1 = $this->create_competency(1, $framework->id);
        $competency2 = $this->create_competency(2, $framework->id);
        $competency3 = $this->create_competency(3, $framework->id);
        $competency4 = $this->create_competency(4, $framework->id);

        // Add the competencies.
        external::add_competency_to_template($template->id, $competency1->id);
        external::add_competency_to_template($template->id, $competency2->id);
        external::add_competency_to_template($template->id, $competency3->id);
        external::add_competency_to_template($template->id, $competency4->id);

        // Test if removing competency from template don't create sortorder holes.
        external::remove_competency_from_template($template->id, $competency3->id);
        $templcomp4 = template_competency::get_record(
                                array(
                                    'templateid' => $template->id,
                                    'competencyid' => $competency4->id
                                ));

        $this->assertEquals(2, $templcomp4->get_sortorder());

        // This is a move up.
        external::reorder_template_competency($template->id, $competency4->id, $competency2->id);
        $result = external::list_competencies_in_template($template->id);
        $result = external_api::clean_returnvalue(external::list_competencies_in_template_returns(), $result);

        $r1 = (object) $result[0];
        $r2 = (object) $result[1];
        $r3 = (object) $result[2];

        $this->assertEquals($competency1->id, $r1->id);
        $this->assertEquals($competency4->id, $r2->id);
        $this->assertEquals($competency2->id, $r3->id);

        // This is a move down.
        external::reorder_template_competency($template->id, $competency1->id, $competency4->id);
        $result = external::list_competencies_in_template($template->id);
        $result = external_api::clean_returnvalue(external::list_competencies_in_template_returns(), $result);

        $r1 = (object) $result[0];
        $r2 = (object) $result[1];
        $r3 = (object) $result[2];

        $this->assertEquals($competency4->id, $r1->id);
        $this->assertEquals($competency1->id, $r2->id);
        $this->assertEquals($competency2->id, $r3->id);

        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->user);
        external::reorder_template_competency($template->id, $competency1->id, $competency2->id);
    }

    /**
     * Test we can duplicate learning plan template.
     */
    public function test_duplicate_learning_plan_template() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();
        $onehour = time() + 60 * 60;

        // Create a template.
        $template = $this->create_template(1, true);

        // Create a competency framework.
        $framework = $this->create_competency_framework(1, true);

        // Create multiple competencies.
        $competency1 = $this->create_competency(1, $framework->id);
        $competency2 = $this->create_competency(2, $framework->id);
        $competency3 = $this->create_competency(3, $framework->id);

        // Add the competencies.
        external::add_competency_to_template($template->id, $competency1->id);
        external::add_competency_to_template($template->id, $competency2->id);
        external::add_competency_to_template($template->id, $competency3->id);

        // Duplicate the learning plan template.
        $duplicatedtemplate = external::duplicate_template($template->id);

        $result = external::list_competencies_in_template($template->id);
        $resultduplicated = external::list_competencies_in_template($duplicatedtemplate->id);

        $this->assertEquals(count($result), count($resultduplicated));
        $this->assertContains($template->shortname, $duplicatedtemplate->shortname);
        $this->assertEquals($duplicatedtemplate->description, $template->description);
        $this->assertEquals($duplicatedtemplate->descriptionformat, $template->descriptionformat);
        $this->assertEquals($duplicatedtemplate->visible, $template->visible);
    }

    /**
     * Test that we can return scale values for a scale with the scale ID.
     */
    public function test_get_scale_values() {
        global $DB;
        // Create a scale.
        $record = new stdClass();
        $record->courseid = 0;
        $record->userid = $this->creator->id;
        $record->name = 'Test scale';
        $record->scale = 'Poor, Not good, Okay, Fine, Excellent';
        $record->description = '<p>Test scale description.</p>';
        $record->descriptionformat = 1;
        $record->timemodified = time();
        $scaleid = $DB->insert_record('scale', $record);
        // Expected return value.
        $expected = array(array(
                'id' => 1,
                'name' => 'Poor'
            ), array(
                'id' => 2,
                'name' => 'Not good'
            ), array(
                'id' => 3,
                'name' => 'Okay'
            ), array(
                'id' => 4,
                'name' => 'Fine'
            ), array(
                'id' => 5,
                'name' => 'Excellent'
            )
        );
        // Call the webservice.
        $result = external::get_scale_values($scaleid);
        $this->assertEquals($expected, $result);
    }

    /**
     * Create a template.
     */
    public function test_create_template() {
        $syscontextid = context_system::instance()->id;
        $catcontextid = context_coursecat::instance($this->category->id)->id;

        // A user without permission.
        $this->setUser($this->user);
        try {
            $result = $this->create_template(1, true);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // A user without permission in a category.
        $this->setUser($this->catuser);
        try {
            $result = $this->create_template(1, false);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // A user with permissions in the system.
        $this->setUser($this->creator);
        $result = $this->create_template(1, true);
        $this->assertEquals('shortname1', $result->shortname);
        $this->assertEquals($syscontextid, $result->contextid);
        $this->assertNotEmpty($result->id);

        $result = $this->create_template(2, false);
        $this->assertEquals('shortname2', $result->shortname);
        $this->assertEquals($catcontextid, $result->contextid);
        $this->assertNotEmpty($result->id);

        // A user with permissions in the category.
        $this->setUser($this->catcreator);
        try {
            $result = $this->create_template(3, true);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        $result = $this->create_template(3, false);
        $this->assertEquals('shortname3', $result->shortname);
        $this->assertEquals($catcontextid, $result->contextid);
        $this->assertNotEmpty($result->id);
    }

    /**
     * Read a template.
     */
    public function test_read_template() {
        $syscontextid = context_system::instance()->id;
        $catcontextid = context_coursecat::instance($this->category->id)->id;

        // Set a due date for the next year.
        $date = new DateTime('now');
        $date->modify('+1 year');
        $duedate = $date->getTimestamp();

        // Creating two templates.
        $this->setUser($this->creator);
        $systemplate = $this->create_template(1, true);
        $cattemplate = $this->create_template(2, false);

        // User without permissions to read in system.
        assign_capability('tool/lp:templateread', CAP_PROHIBIT, $this->userrole, $syscontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->user);
        $this->assertFalse(has_capability('tool/lp:templateread', context_system::instance()));
        try {
            external::read_template($systemplate->id);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }
        try {
            external::read_template($cattemplate->id);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // User with permissions to read in a category.
        assign_capability('tool/lp:templateread', CAP_PREVENT, $this->userrole, $syscontextid, true);
        assign_capability('tool/lp:templateread', CAP_ALLOW, $this->userrole, $catcontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('tool/lp:templateread', context_system::instance()));
        $this->assertTrue(has_capability('tool/lp:templateread', context_coursecat::instance($this->category->id)));
        try {
            external::read_template($systemplate->id);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        $result = external::read_template($cattemplate->id);
        $result = external_api::clean_returnvalue(external::read_template_returns(), $result);
        $this->assertEquals($cattemplate->id, $result['id']);
        $this->assertEquals('shortname2', $result['shortname']);
        $this->assertEquals('description2', $result['description']);
        $this->assertEquals(FORMAT_HTML, $result['descriptionformat']);
        $this->assertEquals(1, $result['visible']);
        $this->assertEquals(0, $result['duedate']);
        $this->assertEquals(userdate(0), $result['duedateformatted']);

        // User with permissions to read in the system.
        assign_capability('tool/lp:templateread', CAP_ALLOW, $this->userrole, $syscontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability('tool/lp:templateread', context_system::instance()));
        $result = external::read_template($systemplate->id);
        $result = external_api::clean_returnvalue(external::read_template_returns(), $result);
        $this->assertEquals($systemplate->id, $result['id']);
        $this->assertEquals('shortname1', $result['shortname']);
        $this->assertEquals('description1', $result['description']);
        $this->assertEquals(FORMAT_HTML, $result['descriptionformat']);
        $this->assertEquals(true, $result['visible']);
        $this->assertEquals(0, $result['duedate']);
        $this->assertEquals(userdate(0), $result['duedateformatted']);

        $result = external::read_template($cattemplate->id);
        $result = external_api::clean_returnvalue(external::read_template_returns(), $result);
        $this->assertEquals($cattemplate->id, $result['id']);
        $this->assertEquals('shortname2', $result['shortname']);
        $this->assertEquals('description2', $result['description']);
        $this->assertEquals(FORMAT_HTML, $result['descriptionformat']);
        $this->assertEquals(true, $result['visible']);
        $this->assertEquals(0, $result['duedate']);
        $this->assertEquals(userdate(0), $result['duedateformatted']);
    }

    /**
     * Update a template.
     */
    public function test_update_template() {
        $syscontextid = context_system::instance()->id;
        $catcontextid = context_coursecat::instance($this->category->id)->id;

        // Set a due date for the next year.
        $date = new DateTime('now');
        $date->modify('+1 year');
        $duedate = $date->getTimestamp();

        // Creating two templates.
        $this->setUser($this->creator);
        $systemplate = $this->create_template(1, true);
        $cattemplate = $this->create_template(2, false);

        // Trying to update in a without permissions.
        $this->setUser($this->user);
        try {
            $this->update_template($systemplate->id, 3);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        try {
            $this->update_template($cattemplate->id, 3);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // User with permissions to update in category.
        $this->setUser($this->catcreator);
        try {
            $this->update_template($systemplate->id, 3);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        $result = $this->update_template($cattemplate->id, 3);
        $this->assertTrue($result);
        $result = external::read_template($cattemplate->id);
        $result = external_api::clean_returnvalue(external::read_template_returns(), $result);
        $this->assertEquals($cattemplate->id, $result['id']);
        $this->assertEquals('shortname3', $result['shortname']);
        $this->assertEquals("description3", $result['description']);
        $this->assertEquals(FORMAT_HTML, $result['descriptionformat']);
        $this->assertEquals(true, $result['visible']);
        $this->assertEquals(0, $result['duedate']);
        $this->assertEquals(userdate(0), $result['duedateformatted']);

        // User with permissions to update in the system.
        $this->setUser($this->creator);
        $result = $this->update_template($systemplate->id, 4);
        $this->assertTrue($result);
        $result = external::read_template($systemplate->id);
        $result = external_api::clean_returnvalue(external::read_template_returns(), $result);
        $this->assertEquals($systemplate->id, $result['id']);
        $this->assertEquals('shortname4', $result['shortname']);
        $this->assertEquals('description4', $result['description']);
        $this->assertEquals(FORMAT_HTML, $result['descriptionformat']);
        $this->assertEquals(true, $result['visible']);
        $this->assertEquals(0, $result['duedate']);
        $this->assertEquals(userdate(0), $result['duedateformatted']);

        $result = $this->update_template($cattemplate->id, 5);
        $this->assertTrue($result);
        $result = external::read_template($cattemplate->id);
        $result = external_api::clean_returnvalue(external::read_template_returns(), $result);
        $this->assertEquals($cattemplate->id, $result['id']);
        $this->assertEquals('shortname5', $result['shortname']);
        $this->assertEquals('description5', $result['description']);
        $this->assertEquals(FORMAT_HTML, $result['descriptionformat']);
        $this->assertEquals(1, $result['visible']);
        $this->assertEquals(0, $result['duedate']);
        $this->assertEquals(userdate(0), $result['duedateformatted']);
    }

    /**
     * Delete a template.
     */
    public function test_delete_template() {
        global $DB;
        $syscontextid = context_system::instance()->id;
        $catcontextid = context_coursecat::instance($this->category->id)->id;

        // Creating a few templates.
        $this->setUser($this->creator);
        $sys1 = $this->create_template(1, true);
        $cat1 = $this->create_template(2, false);
        $cat2 = $this->create_template(3, false);
        $this->assertTrue($DB->record_exists('tool_lp_template', array('id' => $sys1->id)));
        $this->assertTrue($DB->record_exists('tool_lp_template', array('id' => $cat1->id)));
        $this->assertTrue($DB->record_exists('tool_lp_template', array('id' => $cat2->id)));

        // User without permissions.
        $this->setUser($this->user);
        try {
            external::delete_template($sys1->id);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }
        try {
            external::delete_template($cat1->id);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // User with category permissions.
        $this->setUser($this->catcreator);
        try {
            external::delete_template($sys1->id);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        $result = external::delete_template($cat1->id);
        $result = external_api::clean_returnvalue(external::delete_template_returns(), $result);
        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('tool_lp_template', array('id' => $cat1->id)));

        // User with system permissions.
        $this->setUser($this->creator);
        $result = external::delete_template($sys1->id);
        $result = external_api::clean_returnvalue(external::delete_template_returns(), $result);
        $this->assertTrue($result);
        $result = external::delete_template($cat2->id);
        $result = external_api::clean_returnvalue(external::delete_template_returns(), $result);
        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('tool_lp_template', array('id' => $sys1->id)));
        $this->assertFalse($DB->record_exists('tool_lp_template', array('id' => $cat2->id)));
    }

    /**
     * List templates.
     */
    public function test_list_templates() {
        $syscontextid = context_system::instance()->id;
        $catcontextid = context_coursecat::instance($this->category->id)->id;

        // Creating a few templates.
        $this->setUser($this->creator);
        $sys1 = $this->create_template(1, true);
        $sys2 = $this->create_template(2, true);
        $cat1 = $this->create_template(3, false);
        $cat2 = $this->create_template(4, false);

        // User without permission.
        $this->setUser($this->user);
        assign_capability('tool/lp:templateread', CAP_PROHIBIT, $this->userrole, $syscontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        try {
            external::list_templates('id', 'ASC', 0, 10, array('contextid' => $syscontextid), 'children', false);
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // User with category permissions.
        assign_capability('tool/lp:templateread', CAP_PREVENT, $this->userrole, $syscontextid, true);
        assign_capability('tool/lp:templateread', CAP_ALLOW, $this->userrole, $catcontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        $result = external::list_templates('id', 'ASC', 0, 10, array('contextid' => $syscontextid), 'children', false);
        $result = external_api::clean_returnvalue(external::list_templates_returns(), $result);
        $this->assertCount(2, $result);
        $this->assertEquals($cat1->id, $result[0]['id']);
        $this->assertEquals($cat2->id, $result[1]['id']);

        // User with system permissions.
        assign_capability('tool/lp:templateread', CAP_ALLOW, $this->userrole, $syscontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        $result = external::list_templates('id', 'DESC', 0, 3, array('contextid' => $catcontextid), 'parents', false);
        $result = external_api::clean_returnvalue(external::list_templates_returns(), $result);
        $this->assertCount(3, $result);
        $this->assertEquals($cat2->id, $result[0]['id']);
        $this->assertEquals($cat1->id, $result[1]['id']);
        $this->assertEquals($sys2->id, $result[2]['id']);
    }

    /**
     * List templates using competency.
     */
    public function test_list_templates_using_competency() {
        $this->setUser($this->creator);

        // Create a template.
        $template1 = $this->create_template(1, true);
        $template2 = $this->create_template(2, true);
        $template3 = $this->create_template(3, true);
        $template4 = $this->create_template(4, true);

        // Create a competency.
        $framework = $this->create_competency_framework(1, true);
        $competency1 = $this->create_competency(1, $framework->id);
        $competency2 = $this->create_competency(2, $framework->id);

        // Add the competency.
        external::add_competency_to_template($template1->id, $competency1->id);
        external::add_competency_to_template($template2->id, $competency1->id);
        external::add_competency_to_template($template3->id, $competency1->id);

        external::add_competency_to_template($template4->id, $competency2->id);

        $listcomp1 = external::list_templates_using_competency($competency1->id);
        $listcomp2 = external::list_templates_using_competency($competency2->id);

        // Test count_templates_using_competency.
        $counttempcomp1 = external::count_templates_using_competency($competency1->id);
        $counttempcomp2 = external::count_templates_using_competency($competency2->id);

        $comptemp1 = $listcomp1[0];
        $comptemp2 = $listcomp1[1];
        $comptemp3 = $listcomp1[2];

        $comptemp4 = $listcomp2[0];

        $this->assertCount(3, $listcomp1);
        $this->assertCount(1, $listcomp2);
        $this->assertEquals(3, $counttempcomp1);
        $this->assertEquals(1, $counttempcomp2);
        $this->assertEquals($template1->id, $comptemp1->id);
        $this->assertEquals($template2->id, $comptemp2->id);
        $this->assertEquals($template3->id, $comptemp3->id);
        $this->assertEquals($template4->id, $comptemp4->id);
    }

    public function test_count_templates() {
        $syscontextid = context_system::instance()->id;
        $catcontextid = context_coursecat::instance($this->category->id)->id;

        // Creating a few templates.
        $this->setUser($this->creator);
        $sys1 = $this->create_template(1, true);
        $sys2 = $this->create_template(2, true);
        $cat1 = $this->create_template(3, false);
        $cat2 = $this->create_template(4, false);
        $cat3 = $this->create_template(5, false);

        // User without permission.
        $this->setUser($this->user);
        assign_capability('tool/lp:templateread', CAP_PROHIBIT, $this->userrole, $syscontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        try {
            external::count_templates(array('contextid' => $syscontextid), 'children');
            $this->fail('Invalid permissions');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // User with category permissions.
        assign_capability('tool/lp:templateread', CAP_PREVENT, $this->userrole, $syscontextid, true);
        assign_capability('tool/lp:templateread', CAP_ALLOW, $this->userrole, $catcontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        $result = external::count_templates(array('contextid' => $syscontextid), 'children');
        $result = external_api::clean_returnvalue(external::count_templates_returns(), $result);
        $this->assertEquals(3, $result);

        // User with system permissions.
        assign_capability('tool/lp:templateread', CAP_ALLOW, $this->userrole, $syscontextid, true);
        accesslib_clear_all_caches_for_unit_testing();
        $result = external::count_templates(array('contextid' => $catcontextid), 'parents');
        $result = external_api::clean_returnvalue(external::count_templates_returns(), $result);
        $this->assertEquals(5, $result);
    }

    /**
     * Test that we can add related competencies.
     *
     * @return void
     */
    public function test_add_related_competency() {
        global $DB;
        $this->setUser($this->creator);

        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $framework = $lpg->create_framework();
        $framework2 = $lpg->create_framework();
        $competency1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $competency2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $competency3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $competency4 = $lpg->create_competency(array('competencyframeworkid' => $framework2->get_id()));

        // The lower one always as competencyid.
        $result = external::add_related_competency($competency1->get_id(), $competency2->get_id());
        $result = external_api::clean_returnvalue(external::add_related_competency_returns(), $result);
        $this->assertTrue($result);
        $this->assertTrue($DB->record_exists_select(
            related_competency::TABLE, 'competencyid = :cid AND relatedcompetencyid = :rid',
            array(
                'cid' => $competency1->get_id(),
                'rid' => $competency2->get_id()
            )
        ));
        $this->assertFalse($DB->record_exists_select(
            related_competency::TABLE, 'competencyid = :cid AND relatedcompetencyid = :rid',
            array(
                'cid' => $competency2->get_id(),
                'rid' => $competency1->get_id()
            )
        ));

        $result = external::add_related_competency($competency3->get_id(), $competency1->get_id());
        $result = external_api::clean_returnvalue(external::add_related_competency_returns(), $result);
        $this->assertTrue($result);
        $this->assertTrue($DB->record_exists_select(
            related_competency::TABLE, 'competencyid = :cid AND relatedcompetencyid = :rid',
            array(
                'cid' => $competency1->get_id(),
                'rid' => $competency3->get_id()
            )
        ));
        $this->assertFalse($DB->record_exists_select(
            related_competency::TABLE, 'competencyid = :cid AND relatedcompetencyid = :rid',
            array(
                'cid' => $competency3->get_id(),
                'rid' => $competency1->get_id()
            )
        ));

        // We can not allow a duplicate relation, not even in the other direction.
        $this->assertEquals(1, $DB->count_records_select(related_competency::TABLE,
            'competencyid = :cid AND relatedcompetencyid = :rid',
            array('cid' => $competency1->get_id(), 'rid' => $competency2->get_id())));
        $this->assertEquals(0, $DB->count_records_select(related_competency::TABLE,
            'competencyid = :cid AND relatedcompetencyid = :rid',
            array('rid' => $competency1->get_id(), 'cid' => $competency2->get_id())));
        $result = external::add_related_competency($competency2->get_id(), $competency1->get_id());
        $result = external_api::clean_returnvalue(external::add_related_competency_returns(), $result);
        $this->assertTrue($result);
        $this->assertEquals(1, $DB->count_records_select(related_competency::TABLE,
            'competencyid = :cid AND relatedcompetencyid = :rid',
            array('cid' => $competency1->get_id(), 'rid' => $competency2->get_id())));
        $this->assertEquals(0, $DB->count_records_select(related_competency::TABLE,
            'competencyid = :cid AND relatedcompetencyid = :rid',
            array('rid' => $competency1->get_id(), 'cid' => $competency2->get_id())));

        // Check that we cannot create links across frameworks.
        try {
            external::add_related_competency($competency1->get_id(), $competency4->get_id());
            $this->fail('Exception expected due mis-use of shared competencies');
        } catch (tool_lp\invalid_persistent_exception $e) {
            // Yay!
        }

        // User without permission.
        $this->setUser($this->user);

        // Check we can not add the related competency now.
        try {
            external::add_related_competency($competency1->get_id(), $competency3->get_id());
            $this->fail('Exception expected due to not permissions to manage template competencies');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

    }

    /**
     * Test that we can remove related competencies.
     *
     * @return void
     */
    public function test_remove_related_competency() {
        $this->setUser($this->creator);

        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $framework = $lpg->create_framework();
        $c1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $rc1 = $lpg->create_related_competency(array('competencyid' => $c1->get_id(), 'relatedcompetencyid' => $c2->get_id()));
        $rc2 = $lpg->create_related_competency(array('competencyid' => $c2->get_id(), 'relatedcompetencyid' => $c3->get_id()));

        $this->assertEquals(2, related_competency::count_records());

        // Returns false when the relation does not exist.
        $result = external::remove_related_competency($c1->get_id(), $c3->get_id());
        $result = external_api::clean_returnvalue(external::remove_related_competency_returns(), $result);
        $this->assertFalse($result);

        // Returns true on success.
        $result = external::remove_related_competency($c2->get_id(), $c3->get_id());
        $result = external_api::clean_returnvalue(external::remove_related_competency_returns(), $result);
        $this->assertTrue($result);
        $this->assertEquals(1, related_competency::count_records());

        // We don't need to specify competencyid and relatedcompetencyid in the right order.
        $result = external::remove_related_competency($c2->get_id(), $c1->get_id());
        $result = external_api::clean_returnvalue(external::remove_related_competency_returns(), $result);
        $this->assertTrue($result);
        $this->assertEquals(0, related_competency::count_records());
    }

    /**
     * Test that we can search and include related competencies.
     *
     * @return void
     */
    public function test_search_competencies_including_related() {
        $this->setUser($this->creator);

        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $framework = $lpg->create_framework();
        $c1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c4 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c5 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));

        // We have 1-2, 1-3, 2-4, and no relation between 2-3 nor 1-4 nor 5.
        $rc12 = $lpg->create_related_competency(array('competencyid' => $c1->get_id(), 'relatedcompetencyid' => $c2->get_id()));
        $rc13 = $lpg->create_related_competency(array('competencyid' => $c1->get_id(), 'relatedcompetencyid' => $c3->get_id()));
        $rc24 = $lpg->create_related_competency(array('competencyid' => $c2->get_id(), 'relatedcompetencyid' => $c4->get_id()));

        $result = external::search_competencies('comp', $framework->get_id(), true);
        $result = external_api::clean_returnvalue(external::search_competencies_returns(), $result);

        $this->assertCount(5, $result);

    }

    /**
     * Test that we can add competency to plan if we have the right capability.
     *
     * @return void
     */
    public function test_add_competency_to_plan() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $usermanage = $dg->create_user();
        $user = $dg->create_user();

        $syscontext = context_system::instance();

        // Creating specific roles.
        $managerole = $dg->create_role(array(
            'name' => 'User manage',
            'shortname' => 'manage'
        ));

        assign_capability('tool/lp:planmanage', CAP_ALLOW, $managerole, $syscontext->id);
        assign_capability('tool/lp:planview', CAP_ALLOW, $managerole, $syscontext->id);

        $dg->role_assign($managerole, $usermanage->id, $syscontext->id);

        $this->setUser($usermanage);
        $plan = array (
            'userid' => $usermanage->id,
            'status' => \tool_lp\plan::STATUS_ACTIVE
        );
        $pl1 = $lpg->create_plan($plan);
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(
                array('competencyframeworkid' => $framework->get_id())
                );
        $this->assertTrue(external::add_competency_to_plan($pl1->get_id(), $competency->get_id()));

        // A competency cannot be added to plan based on template.
        $template = $lpg->create_template();
        $plan = array (
            'userid' => $usermanage->id,
            'status' => \tool_lp\plan::STATUS_ACTIVE,
            'templateid' => $template->get_id()
        );
        $pl2 = $lpg->create_plan($plan);
        try {
            external::add_competency_to_plan($pl2->get_id(), $competency->get_id());
            $this->fail('A competency cannot be added to plan based on template');
        } catch (coding_exception $ex) {
            $this->assertTrue(true);
        }

        // User without capability cannot add competency to a plan.
        $this->setUser($user);
        try {
            external::add_competency_to_plan($pl1->get_id(), $competency->get_id());
            $this->fail('User without capability cannot add competency to a plan');
        } catch (required_capability_exception $ex) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test that we can add competency to plan if we have the right capability.
     *
     * @return void
     */
    public function test_remove_competency_from_plan() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $usermanage = $dg->create_user();
        $user = $dg->create_user();

        $syscontext = context_system::instance();

        // Creating specific roles.
        $managerole = $dg->create_role(array(
            'name' => 'User manage',
            'shortname' => 'manage'
        ));

        assign_capability('tool/lp:planmanage', CAP_ALLOW, $managerole, $syscontext->id);
        assign_capability('tool/lp:planview', CAP_ALLOW, $managerole, $syscontext->id);

        $dg->role_assign($managerole, $usermanage->id, $syscontext->id);

        $this->setUser($usermanage);
        $plan = array (
            'userid' => $usermanage->id,
            'status' => \tool_lp\plan::STATUS_ACTIVE
        );
        $pl1 = $lpg->create_plan($plan);
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(
                array('competencyframeworkid' => $framework->get_id())
                );
        $lpg->create_plan_competency(
                array(
                    'planid' => $pl1->get_id(),
                    'competencyid' => $competency->get_id()
                    )
                );
        $this->assertTrue(external::remove_competency_from_plan($pl1->get_id(), $competency->get_id()));
        $this->assertCount(0, $pl1->get_competencies());
    }

    /**
     * Test that we can add competency to plan if we have the right capability.
     *
     * @return void
     */
    public function test_reorder_plan_competency() {
        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $usermanage = $dg->create_user();
        $user = $dg->create_user();

        $syscontext = context_system::instance();

        // Creating specific roles.
        $managerole = $dg->create_role(array(
            'name' => 'User manage',
            'shortname' => 'manage'
        ));

        assign_capability('tool/lp:planmanage', CAP_ALLOW, $managerole, $syscontext->id);
        assign_capability('tool/lp:planview', CAP_ALLOW, $managerole, $syscontext->id);

        $dg->role_assign($managerole, $usermanage->id, $syscontext->id);

        $this->setUser($usermanage);
        $plan = array (
            'userid' => $usermanage->id,
            'status' => \tool_lp\plan::STATUS_ACTIVE
        );
        $pl1 = $lpg->create_plan($plan);
        $framework = $lpg->create_framework();
        $c1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c4 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c5 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));

        $lpg->create_plan_competency(array('planid' => $pl1->get_id(), 'competencyid' => $c1->get_id(), 'sortorder' => 1));
        $lpg->create_plan_competency(array('planid' => $pl1->get_id(), 'competencyid' => $c2->get_id(), 'sortorder' => 2));
        $lpg->create_plan_competency(array('planid' => $pl1->get_id(), 'competencyid' => $c3->get_id(), 'sortorder' => 3));
        $lpg->create_plan_competency(array('planid' => $pl1->get_id(), 'competencyid' => $c4->get_id(), 'sortorder' => 4));
        $lpg->create_plan_competency(array('planid' => $pl1->get_id(), 'competencyid' => $c5->get_id(), 'sortorder' => 5));

        // Test if removing competency from plan don't create sortorder holes.
        external::remove_competency_from_plan($pl1->get_id(), $c4->get_id());
        $plancomp5 = plan_competency::get_record(
                                array(
                                    'planid' => $pl1->get_id(),
                                    'competencyid' => $c5->get_id()
                                ));

        $this->assertEquals(3, $plancomp5->get_sortorder());

        $this->assertTrue(external::reorder_plan_competency($pl1->get_id(), $c2->get_id(), $c5->get_id()));
        $this->assertTrue(external::reorder_plan_competency($pl1->get_id(), $c3->get_id(), $c1->get_id()));
        $plancompetencies = plan_competency::get_records(
                array(
                    'planid' => $pl1->get_id()
                ),
                'sortorder',
                'ASC'
                );
        $plcmp1 = $plancompetencies[0];
        $plcmp2 = $plancompetencies[1];
        $plcmp3 = $plancompetencies[2];
        $plcmp4 = $plancompetencies[3];

        $this->assertEquals($plcmp1->get_competencyid(), $c3->get_id());
        $this->assertEquals($plcmp2->get_competencyid(), $c1->get_id());
        $this->assertEquals($plcmp3->get_competencyid(), $c5->get_id());
        $this->assertEquals($plcmp4->get_competencyid(), $c2->get_id());
    }

    /**
     * Test resolving sortorder when we creating competency.
     */
    public function test_fix_sortorder_when_creating_competency() {
        $this->resetAfterTest(true);
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');
        $framework = $lpg->create_framework();

        $c1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'sortorder' => 20));
        $c3 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'sortorder' => 1));

        $this->assertEquals(0, $c1->get_sortorder());
        $this->assertEquals(1, $c2->get_sortorder());
        $this->assertEquals(2, $c3->get_sortorder());
    }

    /**
     * Test resolving sortorder when we delete competency.
     */
    public function test_fix_sortorder_when_delete_competency() {
        $this->resetAfterTest(true);
        $this->setUser($this->creator);
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');

        $framework = $lpg->create_framework();

        $c1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c2a = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'parentid' => $c2->get_id()));
        $c2b = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'parentid' => $c2->get_id()));
        $c2c = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'parentid' => $c2->get_id()));
        $c2d = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'parentid' => $c2->get_id()));

        $this->assertEquals(0, $c1->get_sortorder());
        $this->assertEquals(1, $c2->get_sortorder());
        $this->assertEquals(0, $c2a->get_sortorder());
        $this->assertEquals(1, $c2b->get_sortorder());
        $this->assertEquals(2, $c2c->get_sortorder());
        $this->assertEquals(3, $c2d->get_sortorder());

        $result = external::delete_competency($c1->get_id());
        $result = external_api::clean_returnvalue(external::delete_competency_returns(), $result);

        $c2->read();
        $c2a->read();
        $c2b->read();
        $c2c->read();
        $c2d->read();

        $this->assertEquals(0, $c2->get_sortorder());
        $this->assertEquals(0, $c2a->get_sortorder());
        $this->assertEquals(1, $c2b->get_sortorder());
        $this->assertEquals(2, $c2c->get_sortorder());
        $this->assertEquals(3, $c2d->get_sortorder());

        $result = external::delete_competency($c2b->get_id());
        $result = external_api::clean_returnvalue(external::delete_competency_returns(), $result);

        $c2->read();
        $c2a->read();
        $c2c->read();
        $c2d->read();

        $this->assertEquals(0, $c2->get_sortorder());
        $this->assertEquals(0, $c2a->get_sortorder());
        $this->assertEquals(1, $c2c->get_sortorder());
        $this->assertEquals(2, $c2d->get_sortorder());
    }

    /**
     * Test resolving sortorder when moving a competency.
     */
    public function test_fix_sortorder_when_moving_competency() {
        $this->resetAfterTest(true);
        $this->setUser($this->creator);
        $lpg = $this->getDataGenerator()->get_plugin_generator('tool_lp');

        $framework = $lpg->create_framework();

        $c1 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c1a = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'parentid' => $c1->get_id()));
        $c1b = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'parentid' => $c1->get_id()));
        $c2 = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id()));
        $c2a = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'parentid' => $c2->get_id()));
        $c2b = $lpg->create_competency(array('competencyframeworkid' => $framework->get_id(), 'parentid' => $c2->get_id()));

        $this->assertEquals(0, $c1->get_sortorder());
        $this->assertEquals(0, $c1a->get_sortorder());
        $this->assertEquals(1, $c1b->get_sortorder());
        $this->assertEquals(1, $c2->get_sortorder());
        $this->assertEquals(0, $c2a->get_sortorder());
        $this->assertEquals(1, $c2b->get_sortorder());

        $result = external::set_parent_competency($c2a->get_id(), $c1->get_id());
        $result = external_api::clean_returnvalue(external::set_parent_competency_returns(), $result);

        $c1->read();
        $c1a->read();
        $c1b->read();
        $c2->read();
        $c2a->read();
        $c2b->read();

        $this->assertEquals(0, $c1->get_sortorder());
        $this->assertEquals(0, $c1a->get_sortorder());
        $this->assertEquals(1, $c1b->get_sortorder());
        $this->assertEquals(2, $c2a->get_sortorder());
        $this->assertEquals(1, $c2->get_sortorder());
        $this->assertEquals(0, $c2b->get_sortorder());

        // Move a root node.
        $result = external::set_parent_competency($c2->get_id(), $c1b->get_id());
        $result = external_api::clean_returnvalue(external::set_parent_competency_returns(), $result);

        $c1->read();
        $c1a->read();
        $c1b->read();
        $c2->read();
        $c2a->read();
        $c2b->read();

        $this->assertEquals(0, $c1->get_sortorder());
        $this->assertEquals(0, $c1a->get_sortorder());
        $this->assertEquals(1, $c1b->get_sortorder());
        $this->assertEquals(0, $c2->get_sortorder());
        $this->assertEquals(0, $c2b->get_sortorder());
        $this->assertEquals(2, $c2a->get_sortorder());
    }

    public function test_search_users_by_capability() {
        global $CFG;
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $ux = $dg->create_user();
        $u1 = $dg->create_user(array('idnumber' => 'Cats', 'firstname' => 'Bob', 'lastname' => 'Dyyylan',
            'email' => 'bobbyyy@dyyylan.com', 'phone1' => '123456', 'phone2' => '78910', 'department' => 'Marketing',
            'institution' => 'HQ'));

        // First we search with no capability assigned.
        $this->setUser($ux);
        $result = external::search_users('yyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(0, $result['users']);
        $this->assertEquals(0, $result['count']);

        // Now we assign a different capability.
        $usercontext = context_user::instance($u1->id);
        $systemcontext = context_system::instance();
        $customrole = $this->assignUserCapability('tool/lp:planview', $usercontext->id);

        $result = external::search_users('yyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(0, $result['users']);
        $this->assertEquals(0, $result['count']);

        // Now we assign a matching capability in the same role.
        $usercontext = context_user::instance($u1->id);
        $this->assignUserCapability('tool/lp:planmanage', $usercontext->id, $customrole);

        $result = external::search_users('yyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);

        // Now assign another role with the same capability (test duplicates).
        role_assign($this->creatorrole, $ux->id, $usercontext->id);
        $result = external::search_users('yyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);

        // Now lets try a different user with only the role at system level.
        $ux2 = $dg->create_user();
        role_assign($this->creatorrole, $ux2->id, $systemcontext->id);
        $this->setUser($ux2);
        $result = external::search_users('yyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);

        // Now lets try a different user with only the role at user level.
        $ux3 = $dg->create_user();
        role_assign($this->creatorrole, $ux3->id, $usercontext->id);
        $this->setUser($ux3);
        $result = external::search_users('yyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);

        // Switch back.
        $this->setUser($ux);

        // Now add a prevent override (will change nothing because we still have an ALLOW).
        assign_capability('tool/lp:planmanage', CAP_PREVENT, $customrole, $usercontext->id);
        $result = external::search_users('yyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);

        // Now change to a prohibit override (should prevent access).
        assign_capability('tool/lp:planmanage', CAP_PROHIBIT, $customrole, $usercontext->id);
        $result = external::search_users('yyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);

    }

    /**
     * Ensures that overrides, as well as system permissions, are respected.
     */
    public function test_search_users_by_capability_the_comeback() {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        $master = $dg->create_user();
        $manager = $dg->create_user();
        $slave1 = $dg->create_user(array('lastname' => 'MOODLER'));
        $slave2 = $dg->create_user(array('lastname' => 'MOODLER'));
        $slave3 = $dg->create_user(array('lastname' => 'MOODLER'));

        $syscontext = context_system::instance();
        $slave1context = context_user::instance($slave1->id);
        $slave2context = context_user::instance($slave2->id);
        $slave3context = context_user::instance($slave3->id);

        // Creating a role giving the site config.
        $roleid = $dg->create_role();
        assign_capability('moodle/site:config', CAP_ALLOW, $roleid, $syscontext->id, true);

        // Create a role override for slave 2.
        assign_capability('moodle/site:config', CAP_PROHIBIT, $roleid, $slave2context->id, true);

        // Assigning the role.
        // Master -> System context.
        // Manager -> User context.
        role_assign($roleid, $master->id, $syscontext);
        role_assign($roleid, $manager->id, $slave1context);

        // Flush accesslib.
        accesslib_clear_all_caches_for_unit_testing();

        // Confirm.
        // Master has system permissions.
        $this->setUser($master);
        $this->assertTrue(has_capability('moodle/site:config', $syscontext));
        $this->assertTrue(has_capability('moodle/site:config', $slave1context));
        $this->assertFalse(has_capability('moodle/site:config', $slave2context));
        $this->assertTrue(has_capability('moodle/site:config', $slave3context));

        // Manager only has permissions in slave 1.
        $this->setUser($manager);
        $this->assertFalse(has_capability('moodle/site:config', $syscontext));
        $this->assertTrue(has_capability('moodle/site:config', $slave1context));
        $this->assertFalse(has_capability('moodle/site:config', $slave2context));
        $this->assertFalse(has_capability('moodle/site:config', $slave3context));

        // Now do the test.
        $this->setUser($master);
        $result = external::search_users('MOODLER', 'moodle/site:config');
        $this->assertCount(2, $result['users']);
        $this->assertEquals(2, $result['count']);
        $this->assertArrayHasKey($slave1->id, $result['users']);
        $this->assertArrayHasKey($slave3->id, $result['users']);

        $this->setUser($manager);
        $result = external::search_users('MOODLER', 'moodle/site:config');
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);
        $this->assertArrayHasKey($slave1->id, $result['users']);
    }

    public function test_search_users() {
        global $CFG;
        $this->resetAfterTest(true);

        $dg = $this->getDataGenerator();
        $ux = $dg->create_user();
        $u1 = $dg->create_user(array('idnumber' => 'Cats', 'firstname' => 'Bob', 'lastname' => 'Dyyylan',
            'email' => 'bobbyyy@dyyylan.com', 'phone1' => '123456', 'phone2' => '78910', 'department' => 'Marketing',
            'institution' => 'HQ'));
        $u2 = $dg->create_user(array('idnumber' => 'Dogs', 'firstname' => 'Alice', 'lastname' => 'Dyyylan',
            'email' => 'alyyyson@dyyylan.com', 'phone1' => '33333', 'phone2' => '77777', 'department' => 'Development',
            'institution' => 'O2'));
        $u3 = $dg->create_user(array('idnumber' => 'Fish', 'firstname' => 'Thomas', 'lastname' => 'Xow',
            'email' => 'fishyyy@moodle.com', 'phone1' => '77777', 'phone2' => '33333', 'department' => 'Research',
            'institution' => 'Bob'));

        // We need to give the user the capability we are searching for on each of the test users.
        $this->setAdminUser();
        $usercontext = context_user::instance($u1->id);
        $dummyrole = $this->assignUserCapability('tool/lp:planmanage', $usercontext->id);
        $usercontext = context_user::instance($u2->id);
        $this->assignUserCapability('tool/lp:planmanage', $usercontext->id, $dummyrole);
        $usercontext = context_user::instance($u3->id);
        $this->assignUserCapability('tool/lp:planmanage', $usercontext->id, $dummyrole);

        $this->setUser($ux);
        $usercontext = context_user::instance($u1->id);
        $this->assignUserCapability('tool/lp:planmanage', $usercontext->id, $dummyrole);
        $usercontext = context_user::instance($u2->id);
        $this->assignUserCapability('tool/lp:planmanage', $usercontext->id, $dummyrole);
        $usercontext = context_user::instance($u3->id);
        $this->assignUserCapability('tool/lp:planmanage', $usercontext->id, $dummyrole);

        $this->setAdminUser();

        // No identity fields.
        $CFG->showuseridentity = '';
        $result = external::search_users('cats', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(0, $result['users']);
        $this->assertEquals(0, $result['count']);

        // Filter by name.
        $CFG->showuseridentity = '';
        $result = external::search_users('dyyylan', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(2, $result['users']);
        $this->assertEquals(2, $result['count']);
        $this->assertEquals($u2->id, $result['users'][0]['id']);
        $this->assertEquals($u1->id, $result['users'][1]['id']);

        // Filter by institution and name.
        $CFG->showuseridentity = 'institution';
        $result = external::search_users('bob', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(2, $result['users']);
        $this->assertEquals(2, $result['count']);
        $this->assertEquals($u1->id, $result['users'][0]['id']);
        $this->assertEquals($u3->id, $result['users'][1]['id']);

        // Filter by id number.
        $CFG->showuseridentity = 'idnumber';
        $result = external::search_users('cats', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals($u1->id, $result['users'][0]['id']);
        $this->assertEquals($u1->idnumber, $result['users'][0]['idnumber']);
        $this->assertEmpty($result['users'][0]['email']);
        $this->assertEmpty($result['users'][0]['phone1']);
        $this->assertEmpty($result['users'][0]['phone2']);
        $this->assertEmpty($result['users'][0]['department']);
        $this->assertEmpty($result['users'][0]['institution']);

        // Filter by email.
        $CFG->showuseridentity = 'email';
        $result = external::search_users('yyy', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(3, $result['users']);
        $this->assertEquals(3, $result['count']);
        $this->assertEquals($u2->id, $result['users'][0]['id']);
        $this->assertEquals($u2->email, $result['users'][0]['email']);
        $this->assertEquals($u1->id, $result['users'][1]['id']);
        $this->assertEquals($u1->email, $result['users'][1]['email']);
        $this->assertEquals($u3->id, $result['users'][2]['id']);
        $this->assertEquals($u3->email, $result['users'][2]['email']);

        // Filter by any.
        $CFG->showuseridentity = 'idnumber,email,phone1,phone2,department,institution';
        $result = external::search_users('yyy', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(3, $result['users']);
        $this->assertEquals(3, $result['count']);
        $this->assertArrayHasKey('idnumber', $result['users'][0]);
        $this->assertArrayHasKey('email', $result['users'][0]);
        $this->assertArrayHasKey('phone1', $result['users'][0]);
        $this->assertArrayHasKey('phone2', $result['users'][0]);
        $this->assertArrayHasKey('department', $result['users'][0]);
        $this->assertArrayHasKey('institution', $result['users'][0]);

        // Switch to a user that cannot view identity fields.
        $this->setUser($ux);
        $CFG->showuseridentity = 'idnumber,email,phone1,phone2,department,institution';

        // Only names are included.
        $result = external::search_users('fish');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(0, $result['users']);
        $this->assertEquals(0, $result['count']);

        $result = external::search_users('bob', 'tool/lp:planmanage');
        $result = external_api::clean_returnvalue(external::search_users_returns(), $result);
        $this->assertCount(1, $result['users']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals($u1->id, $result['users'][0]['id']);
        $this->assertEmpty($result['users'][0]['idnumber']);
        $this->assertEmpty($result['users'][0]['email']);
        $this->assertEmpty($result['users'][0]['phone1']);
        $this->assertEmpty($result['users'][0]['phone2']);
        $this->assertEmpty($result['users'][0]['department']);
        $this->assertEmpty($result['users'][0]['institution']);
    }

    public function test_grade_competency_in_plan() {
        global $CFG;

        $this->setUser($this->creator);

        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('tool_lp');

        $f1 = $lpg->create_framework();

        $c1 = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id()));

        $tpl = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $tpl->get_id(), 'competencyid' => $c1->get_id()));

        $plan = $lpg->create_plan(array('userid' => $this->user->id, 'templateid' => $tpl->get_id(), 'name' => 'Evil'));

        $uc = $lpg->create_user_competency(array('userid' => $this->user->id, 'competencyid' => $c1->get_id()));

        $evidence = external::grade_competency_in_plan($plan->get_id(), $c1->get_id(), 1, false, 'Evil note');

        $this->assertEquals('The competency grade was manually suggested in the plan \'Evil\'.', $evidence->description);
        $this->assertEquals('A', $evidence->gradename);
        $this->assertEquals('Evil note', $evidence->note);
        $evidence = external::grade_competency_in_plan($plan->get_id(), $c1->get_id(), 1, true);

        $this->assertEquals('The competency grade was manually set in the plan \'Evil\'.', $evidence->description);
        $this->assertEquals('A', $evidence->gradename);

        $this->setUser($this->user);
        $evidence = external::grade_competency_in_plan($plan->get_id(), $c1->get_id(), 1, false);
        $this->assertEquals('The competency grade was manually suggested in the plan \'Evil\'.', $evidence->description);
        $this->assertEquals('A', $evidence->gradename);

        $this->setExpectedException('required_capability_exception');
        $evidence = external::grade_competency_in_plan($plan->get_id(), $c1->get_id(), 1, true);
    }

    public function test_data_for_user_competency_summary_in_plan() {
        global $CFG;

        $this->setUser($this->creator);

        $dg = $this->getDataGenerator();
        $lpg = $dg->get_plugin_generator('tool_lp');

        $f1 = $lpg->create_framework();

        $c1 = $lpg->create_competency(array('competencyframeworkid' => $f1->get_id()));

        $tpl = $lpg->create_template();
        $lpg->create_template_competency(array('templateid' => $tpl->get_id(), 'competencyid' => $c1->get_id()));

        $plan = $lpg->create_plan(array('userid' => $this->user->id, 'templateid' => $tpl->get_id(), 'name' => 'Evil'));

        $uc = $lpg->create_user_competency(array('userid' => $this->user->id, 'competencyid' => $c1->get_id()));

        $evidence = external::grade_competency_in_plan($plan->get_id(), $c1->get_id(), 1, false);
        $evidence = external::grade_competency_in_plan($plan->get_id(), $c1->get_id(), 2, true);

        $summary = external::data_for_user_competency_summary_in_plan($c1->get_id(), $plan->get_id());
        $this->assertTrue($summary->usercompetencysummary->cangrade);
        $this->assertTrue($summary->usercompetencysummary->cansuggest);
        $this->assertEquals('Evil', $summary->plan->name);
        $this->assertEquals('B', $summary->usercompetencysummary->usercompetency->gradename);
        $this->assertEquals('B', $summary->usercompetencysummary->evidence[0]->gradename);
        $this->assertEquals('A', $summary->usercompetencysummary->evidence[1]->gradename);
    }

    /**
     * Search cohorts.
     */
    public function test_search_cohorts() {
        $this->resetAfterTest(true);

        $syscontext = array('contextid' => context_system::instance()->id);
        $catcontext = array('contextid' => context_coursecat::instance($this->category->id)->id);
        $othercatcontext = array('contextid' => context_coursecat::instance($this->othercategory->id)->id);

        $cohort1 = $this->getDataGenerator()->create_cohort(array_merge($syscontext, array('name' => 'Cohortsearch 1')));
        $cohort2 = $this->getDataGenerator()->create_cohort(array_merge($catcontext, array('name' => 'Cohortsearch 2')));
        $cohort3 = $this->getDataGenerator()->create_cohort(array_merge($othercatcontext, array('name' => 'Cohortsearch 3')));

        // Check for parameter $includes = 'parents'.

        // A user without permission in the system.
        $this->setUser($this->user);
        try {
            $result = external::search_cohorts("Cohortsearch", $syscontext, 'parents');
            $this->fail('Invalid permissions in system');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // A user without permission in a category.
        $this->setUser($this->catuser);
        try {
            $result = external::search_cohorts("Cohortsearch", $catcontext, 'parents');
            $this->fail('Invalid permissions in category');
        } catch (required_capability_exception $e) {
            // All good.
        }

        // A user with permissions in the system.
        $this->setUser($this->creator);
        $result = external::search_cohorts("Cohortsearch", $syscontext, 'parents');
        $this->assertEquals(1, count($result['cohorts']));
        $this->assertEquals('Cohortsearch 1', $result['cohorts'][$cohort1->id]->name);

        // A user with permissions in the category.
        $this->setUser($this->catcreator);
        $result = external::search_cohorts("Cohortsearch", $catcontext, 'parents');
        $this->assertEquals(2, count($result['cohorts']));
        $cohorts = array();
        foreach ($result['cohorts'] as $cohort) {
            $cohorts[] = $cohort->name;
        }
        $this->assertTrue(in_array('Cohortsearch 1', $cohorts));
        $this->assertTrue(in_array('Cohortsearch 2', $cohorts));

        // Check for parameter $includes = 'self'.
        $this->setUser($this->creator);
        $result = external::search_cohorts("Cohortsearch", $othercatcontext, 'self');
        $this->assertEquals(1, count($result['cohorts']));
        $this->assertEquals('Cohortsearch 3', $result['cohorts'][$cohort3->id]->name);

        // Check for parameter $includes = 'all'.
        $this->setUser($this->creator);
        $result = external::search_cohorts("Cohortsearch", $syscontext, 'all');
        $this->assertEquals(3, count($result['cohorts']));

        // Detect invalid parameter $includes.
        $this->setUser($this->creator);
        try {
            $result = external::search_cohorts("Cohortsearch", $syscontext, 'invalid');
            $this->fail('Invalid parameter includes');
        } catch (coding_exception $e) {
            // All good.
        }
    }

}