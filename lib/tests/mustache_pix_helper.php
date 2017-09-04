<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2017 onwards Totara Learning Solutions LTD
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
 * @copyright 2017 onwards Totara Learning Solutions LTD
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Brian Barnes <brian.barnes@totaralearning.com>
 * @package   core_output
 */

use core\output\mustache_pix_helper;

defined('MOODLE_INTERNAL') || die();

class mustache_pix_helper_testcase extends basic_testcase {

    /**
     * @var core_renderer
     */
    protected static $renderer;

    /**
     * @var \Mustache_Engine
     */
    protected static $engine;


    public static function setUpBeforeClass() {

        global $CFG;

        require_once("{$CFG->dirroot}/lib/mustache/src/Mustache/Autoloader.php");
        Mustache_Autoloader::register();

        self::$renderer = new \core_renderer(new moodle_page(), '/');
        // Get the engine from the renderer. We do this once cause its mad.
        $class = new ReflectionClass(self::$renderer);
        $method = $class->getMethod('get_mustache');
        $method->setAccessible(true);
        self::$engine = $method->invoke(self::$renderer);
    }

    /**
     * Returns a LambdaHelper populated with the given contextdata.
     *
     * @param array|stdClass $contextdata
     * @return Mustache_LambdaHelper
     */
    protected function get_lambda_helper($contextdata = []) {
        return new \Mustache_LambdaHelper(self::$engine, new \Mustache_Context($contextdata));
    }

    /**
     * It should generate the same output as rendering the renderable without customdata.
     *
     * @covers \core\output\mustache_pix_helper::__construct
     * @covers \core\output\mustache_pix_helper::pix
     */
    public function test_pix_output_without_customdata() {

        $identifier = 'movehere';
        $mustachehelper = new mustache_pix_helper(self::$renderer);

        $expected = self::$renderer->render(new pix_icon($identifier, ''));
        $actual = $mustachehelper->pix($identifier, $this->get_lambda_helper());

        $this->assertEquals($expected, $actual);

    }

    /**
     * It should generate the same output as rendering the renderable without customdata.
     *
     * @covers \core\output\mustache_pix_helper::__construct
     * @covers \core\output\mustache_pix_helper::pix
     */
    public function test_pix_output_with_plain_alt() {

        $identifier = 'movehere';
        $alt = 'sometext';
        $helperstring = $identifier . ' , core,' . $alt;

        $mustachehelper = new mustache_pix_helper(self::$renderer);

        $expected = self::$renderer->render(new pix_icon($identifier, $alt));

        $actual = $mustachehelper->pix($helperstring, $this->get_lambda_helper());

        $this->assertEquals($expected, $actual);

    }

    /**
     * It should generate the same output as rendering the renderable without customdata.
     *
     * @covers \core\output\mustache_pix_helper::__construct
     * @covers \core\output\mustache_pix_helper::pix
     */
    public function test_pix_output_with_xss_alt() {

        $identifier = 'movehere';
        $alt = '<script>alert("blah");</script>';
        $helperstring = $identifier . ' , core,' . $alt;
        $mustachehelper = new mustache_pix_helper(self::$renderer);

        $expected = self::$renderer->render(new pix_icon($identifier, $alt));

        $actual = $mustachehelper->pix($helperstring, $this->get_lambda_helper());

        $this->assertEquals($expected, $actual);

    }

    /**
     * It should generate the same output as rendering the renderable with customdata.
     *
     * @covers \core\output\mustache_pix_helper::pix
     */
    public function test_pix_output_with_customdata() {

        $identifier = 'movehere';
        $alt = 'image';
        $customdata = array(
            'class' => 'ft-state-success ft-size-700',
            'alt' => $alt
        );
        $helperstring = "{$identifier}, core, " . json_encode($customdata);

        $mustachehelper = new mustache_pix_helper(self::$renderer);

        $expected = self::$renderer->render(new pix_icon($identifier, $alt, 'core', $customdata));
        $actual = $mustachehelper->pix($helperstring, $this->get_lambda_helper());

        $this->assertEquals($expected, $actual);

    }

    /**
     * It should generate the same output as rendering the renderable with customdata.
     *
     * @covers \core\output\mustache_pix_helper::pix
     */
    public function test_pix_output_with_variable_identifier() {

        $actualidentifier = 'movehere';
        $variableidentifier = '{{test_icon}}';
        $customdata = array(
            'classes' => 'ft-state-success ft-size-700'
        );
        $helperstring = "{$variableidentifier}, core, " . json_encode($customdata);

        $expected = self::$renderer->render(new pix_icon($actualidentifier, '', 'core', $customdata));

        $lambdahelper = $this->get_lambda_helper(['test_icon' => $actualidentifier]);
        $mustachehelper = new mustache_pix_helper(self::$renderer);
        $actual = $mustachehelper->pix($helperstring, $lambdahelper);

        $this->assertEquals($expected, $actual);
    }

    /**
     * It should generate the same output as rendering the renderable with customdata.
     *
     * @covers \core\output\mustache_pix_helper::pix
     */
    public function test_pix_output_with_variable_alt() {

        $actualidentifier = 'movehere';
        $helperstring = '{{test_icon}}, core, { "alt": "{{alt}}" }';

        $expected = self::$renderer->render(new pix_icon($actualidentifier, '', 'core', ['alt' => get_string('settings')]));

        $lambdahelper = $this->get_lambda_helper(['test_icon' => $actualidentifier, 'alt' => get_string('settings')]);
        $mustachehelper = new mustache_pix_helper(self::$renderer);
        $actual = $mustachehelper->pix($helperstring, $lambdahelper);

        $this->assertEquals($expected, $actual);

    }

    /**
     * It should generate the same output as rendering the renderable with customdata.
     *
     * @covers \core\output\mustache_pix_helper::pix
     */
    public function test_pix_output_with_complex_structure() {

        $actualidentifier = 'movehere';
        $helperstring = '{{test_icon}}, core, { "alt": "{{alt}}", "classes": "{{classes}}" }';

        $expected = self::$renderer->render(new pix_icon($actualidentifier, '', 'core', ['alt' => get_string('settings'), 'classes' => 'test testing']));

        $lambdahelper = $this->get_lambda_helper(['test_icon' => $actualidentifier, 'alt' => get_string('settings'), 'classes' => 'test testing']);
        $mustachehelper = new mustache_pix_helper(self::$renderer);
        $actual = $mustachehelper->pix($helperstring, $lambdahelper);

        $this->assertEquals($expected, $actual);

    }

    /**
     * It should generate the same output as rendering the renderable with customdata.
     *
     * @covers \core\output\mustache_pix_helper::pix
     */
    public function test_pix_output_with_variable_structure() {

        $actualidentifier = 'movehere';
        // include some XSS attacks as unsanitised title is needed otherwise JSON isn't correctly sent
        $customdata = array(
            'alt' => '<script>alert("blah");</script>',
            'class' => 'some"testing'
        );
        $helperstring = '{{test_icon}}, core, {{{ title }}}';

        $expected = self::$renderer->render(new pix_icon($actualidentifier, '', 'core', $customdata));

        $lambdahelper = $this->get_lambda_helper(['test_icon' => $actualidentifier, 'title' => json_encode($customdata)]);
        $mustachehelper = new mustache_pix_helper(self::$renderer);
        $actual = $mustachehelper->pix($helperstring, $lambdahelper);

        $this->assertEquals($expected, $actual);

    }
}
