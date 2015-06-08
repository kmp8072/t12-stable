<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2015 onwards Totara Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_core
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests of our upstream hacks.
 */
class totara_core_moodlelib_testcase extends advanced_testcase {

    public function test_delete_user() {
        global $DB, $CFG;

        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user(array('idnumber' => 'abc'));
        $user2 = $this->getDataGenerator()->create_user(array('idnumber' => 'xyz'));

        // Delete user the Moodle way.
        $CFG->authdeleteusers = 'full';
        $result = delete_user($user1);

        // Test user is deleted in DB.
        $this->assertTrue($result);
        $deluser = $DB->get_record('user', array('id' => $user1->id), '*', MUST_EXIST);
        $this->assertEquals(1, $deluser->deleted);
        $this->assertEquals(0, $deluser->picture);
        $this->assertSame('', $deluser->idnumber);
        $this->assertSame(md5($user1->username), $deluser->email);
        $this->assertRegExp('/^' . preg_quote($user1->email, '/') . '\.\d*$/', $deluser->username);

        // Delete user the old Totara way.
        $CFG->authdeleteusers = 'partial';
        $result = delete_user($user2);

        // Test user is deleted in DB.
        $this->assertTrue($result);
        $deluser = $DB->get_record('user', array('id' => $user2->id), '*', MUST_EXIST);
        $this->assertEquals(1, $deluser->deleted);
        $this->assertEquals(0, $deluser->picture);
        $this->assertEquals($user2->picture, $deluser->picture);
        $this->assertSame($user2->idnumber, $deluser->idnumber);
        $this->assertSame($user2->username, $deluser->username);
        $this->assertSame($user2->email, $deluser->email);
    }
}
