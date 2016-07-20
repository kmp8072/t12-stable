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
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Petr Skoda <petr.skoda@totaralms.com>>
 * @package   core
 */

use core\output\flex_icon;
use core\output\flex_icon_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit unit tests for \core\output\flex_icon class.
 */
class totara_core_flex_icon_testcase extends advanced_testcase {
    public function test_exists() {
        $this->assertTrue(flex_icon::exists('edit'));
        $this->assertTrue(flex_icon::exists('unsubscribed'));
        $this->assertTrue(flex_icon::exists('core|i/edit'));
        $this->assertTrue(flex_icon::exists('mod_book|icon'));
        $this->assertTrue(flex_icon::exists('mod_book|nav_exit'));

        $this->assertFalse(flex_icon::exists('fdfdsfdsfdsdfs'));
    }

    public function test_constructor() {
        // New icon names.
        $identifier = 'edit';
        $icon = new flex_icon($identifier);
        $this->assertSame($identifier, $icon->identifier);
        $this->assertSame(array(), $icon->customdata);
        $this->assertDebuggingNotCalled();

        $identifier = 'unsubscribed';
        $icon = new flex_icon($identifier);
        $this->assertSame($identifier, $icon->identifier);
        $this->assertSame(array(), $icon->customdata);
        $this->assertDebuggingNotCalled();

        // Legacy icon name.
        $identifier = 'core|i/edit';
        $customdata = array('classes' => 'boldstuff');
        $icon = new flex_icon($identifier, $customdata);
        $this->assertSame($identifier, $icon->identifier);
        $this->assertSame($customdata, $icon->customdata);
        $this->assertDebuggingNotCalled();

        // Deprecated icon.
        $identifier = 'mod_book|nav_exit';
        $customdata = array('classes' => 'deprecatedstuff');
        $icon = new flex_icon($identifier, $customdata);
        $this->assertSame($identifier, $icon->identifier);
        $this->assertSame($customdata, $icon->customdata);
        $this->assertDebuggingNotCalled();

        // Missing icon.
        new flex_icon(flex_icon_helper::MISSING_ICON);
        $this->assertDebuggingNotCalled();

        $identifier = 'fdfdsfdsfdsdfs';
        $customdata = array('classes' => 'missingstuff');
        $icon = new flex_icon($identifier, $customdata);
        $this->assertSame($identifier, $icon->identifier);
        $this->assertSame($customdata, $icon->customdata);
        $this->assertDebuggingCalled("Flex icon '$identifier' not found");
    }

    public function test_get_template() {
        // New icon names.
        $this->assertSame('core/flex_icon', (new flex_icon('edit'))->get_template());
        $this->assertSame('core/flex_icon_stack', (new flex_icon('unsubscribed'))->get_template());

        // Legacy icon name.
        $this->assertSame('core/flex_icon', (new flex_icon('core|i/edit'))->get_template());

        // Deprecated icon.
        $this->assertSame('core/flex_icon', (new flex_icon('mod_book|nav_exit'))->get_template());

        // Missing icon.
        $missingiconstemplate = (new flex_icon(flex_icon_helper::MISSING_ICON))->get_template();
        $this->assertDebuggingNotCalled();
        $this->assertSame($missingiconstemplate, (new flex_icon('fdfdsfdsfdsdfs'))->get_template());
        $this->assertDebuggingCalled();
    }

    public function test_export_for_template() {
        global $PAGE;

        $renderer = $PAGE->get_renderer('core');

        // New icon names.
        $icon = new flex_icon('edit', array('classes' => 'normalstuff'));
        $expected = array(
            'classes' => 'fa fa-edit',
            'customdata' => array('classes' => 'normalstuff'),
        );
        $this->assertSame($expected, $icon->export_for_template($renderer));
        $icon = new flex_icon('unsubscribed', array('classes' => 'compositestuff'));
        $expected = array(
            'classes' => array(
                'stack_first' => 'fa fa-envelope-o ft-stack-main',
                'stack_second' => 'fa fa-times ft-stack-suffix ft-state-danger',
            ),
            'customdata' => array('classes' => 'compositestuff'),
        );
        $this->assertSame($expected, $icon->export_for_template($renderer));

        // Legacy icon name.
        $icon = new flex_icon('core|i/edit');
        $expected = array(
            'classes' => 'fa fa-edit',
            'customdata' => array(),
        );
        $this->assertSame($expected, $icon->export_for_template($renderer));

        // Deprecated icon.
        $icon = new flex_icon('mod_book|nav_exit');
        $expected = array(
            'classes' => 'fa fa-caret-up',
            'customdata' => array(),
        );
        $this->assertSame($expected, $icon->export_for_template($renderer));

        // Missing icon.
        $missingicondata = (new flex_icon(flex_icon_helper::MISSING_ICON))->export_for_template($renderer);
        $this->assertDebuggingNotCalled();
        $icon = new flex_icon('fdfdsfdsfdsdfs');
        $this->assertDebuggingCalled();
        $this->assertSame($missingicondata, $icon->export_for_template($renderer));
    }

    public function test_create_from_pix_icon() {
        $pixicon = new pix_icon('i/edit', 'Alt text');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|i/edit', $flexicon->identifier);
        $this->assertSame(array('alt' => 'Alt text'), $flexicon->customdata);

        $pixicon = new pix_icon('i/edit', 'Alt text');
        $flexicon = flex_icon::create_from_pix_icon($pixicon, 'hokus pokus');
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|i/edit', $flexicon->identifier);
        $this->assertSame(array('classes' => 'hokus pokus', 'alt' => 'Alt text'), $flexicon->customdata);

        $pixicon = new pix_icon('i/edit', 'Alt text');
        $flexicon = flex_icon::create_from_pix_icon($pixicon, array('hokus', 'pokus'));
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|i/edit', $flexicon->identifier);
        $this->assertSame(array('classes' => 'hokus pokus', 'alt' => 'Alt text'), $flexicon->customdata);

        $pixicon = new pix_icon('icon', '', 'book');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('mod_book|icon', $flexicon->identifier);
        $this->assertSame(array('alt' => ''), $flexicon->customdata);

        $pixicon = new pix_icon('icon', '', 'mod_book');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('mod_book|icon', $flexicon->identifier);
        $this->assertSame(array('alt' => ''), $flexicon->customdata);

        $pixicon = new pix_icon('icon', 'Alt text', 'forum', array('class' => 'activityicon otherclass'));
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('mod_forum|icon', $flexicon->identifier);
        $this->assertSame(array('classes' => 'activityicon', 'alt' => 'Alt text'), $flexicon->customdata);

        $pixicon = new pix_icon('f/archive-256', '');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|f/archive', $flexicon->identifier);
        $this->assertSame(array('classes' => 'ft-size-700', 'alt' => ''), $flexicon->customdata);

        $pixicon = new pix_icon('f/archive-32', '');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|f/archive', $flexicon->identifier);
        $this->assertSame(array('classes' => 'ft-size-600', 'alt' => ''), $flexicon->customdata);

        $pixicon = new pix_icon('f/archive-31', '');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|f/archive', $flexicon->identifier);
        $this->assertSame(array('classes' => 'ft-size-600', 'alt' => ''), $flexicon->customdata);

        $pixicon = new pix_icon('f/archive-25', '');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|f/archive', $flexicon->identifier);
        $this->assertSame(array('classes' => 'ft-size-600', 'alt' => ''), $flexicon->customdata);

        $pixicon = new pix_icon('f/archive-24', '');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|f/archive', $flexicon->identifier);
        $this->assertSame(array('classes' => 'ft-size-400', 'alt' => ''), $flexicon->customdata);

        $pixicon = new pix_icon('f/archive-13', '');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertInstanceOf('core\output\flex_icon', $flexicon);
        $this->assertSame('core|f/archive', $flexicon->identifier);
        $this->assertSame(array('alt' => ''), $flexicon->customdata);

        $pixicon = new pix_icon('grrrrgrgrg', 'Some Forum', 'forum');
        $flexicon = flex_icon::create_from_pix_icon($pixicon);
        $this->assertNull($flexicon);
    }

    /**
     * Test the convenience method outputs a pix icon string.
     *
     * This test should not strictly be in this class however as there
     * is not currently a test file for outputrenderers.php and the
     * functionality is related to flex_icons we include it.
     */
    public function test_render_flex_icon() {
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');

        $icon = new flex_icon('edit');
        $expected = $renderer->render_from_template($icon->get_template(), $icon->export_for_template($renderer));
        $this->assertSame($expected, $renderer->render($icon));
    }

    public function test_render_pix_icon() {
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');

        $flexicon = new flex_icon('core|i/edit');
        $pixicon = new pix_icon('i/edit', '');
        $this->assertSame($renderer->render($flexicon), $renderer->render($pixicon));

        $flexicon = new flex_icon('mod_book|icon');
        $pixicon = new pix_icon('icon', '', 'book');
        $this->assertSame($renderer->render($flexicon), $renderer->render($pixicon));
    }
}
