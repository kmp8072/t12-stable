<?php
/**
 * This file is part of Totara Learn
 *
 * Copyright (C) 2018 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Courteney Brownie <courteney.brownie@totaralearning.com>
 * @author Riana Rossouw <riana.rossouw@totaralearning.com>
 * @package tool_sitepolicy
 */

namespace tool_sitepolicy\form;

use totara_form\form_controller;

/**
 * Controller for version form
 **/
class versionform_controller extends form_controller {

    /** @var element_compilation_js $form */
    protected $form;

    /**
     * This method is responsible for:
     *  - access control
     *  - getting of current data
     *  - getting of parameters
     *
     * and returning of the form instance.
     *
     * @param string $idsuffix string extra for identifier to allow repeated forms on one page
     * @return form
     */
    public function get_ajax_form_instance($idsuffix) {
        // Access control first.
        require_login();
        require_sesskey();
        $syscontext = \context_system::instance();
        require_capability('moodle/site:config', $syscontext);

        $localisedpolicy = optional_param('localisedpolicy', 0, PARAM_INT);
        $versionnumber = optional_param('versionnumber', 0, PARAM_INT);
        $ret = optional_param('ret', '', PARAM_TEXT);
        $currentdata = ['localisedpolicy' => $localisedpolicy, 'versionnumber' => $versionnumber, 'ret' => $ret];
        // Create the form instance.
        $this->form = new versionform($currentdata, null, $idsuffix);
        return $this->form;
    }

    /**
     * Process the submitted form.
     *
     * @return array processed data
     */
    public function process_ajax_data() {
        $result = array();
        $result['data'] = (array)$this->form->get_data();
        $result['files'] = array();
        return $result;
    }

}