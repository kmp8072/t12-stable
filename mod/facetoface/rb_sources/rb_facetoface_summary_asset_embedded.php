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
 */

defined('MOODLE_INTERNAL') || die();

class rb_facetoface_summary_asset_embedded extends rb_base_embedded {

    public function __construct($data) {
        $this->url = '/mod/facetoface/asset.php';
        $this->source = 'facetoface_asset_assignments';
        $this->shortname = 'facetoface_summary_asset';
        $this->fullname = get_string('facetofacesummaryasset', 'rb_source_facetoface_asset_assignments');
        $this->columns = array(
            array('type' => 'facetoface', 'value' => 'name', 'heading' => null),
            array('type' => 'session', 'value' => 'approvalby', 'heading' => null),
            array('type' => 'session', 'value' => 'numattendeeslink', 'heading' => get_string('numberofattendees', 'facetoface')),
            array('type' => 'session', 'value' => 'capacity', 'heading' => null),
            array('type' => 'date', 'value' => 'sessionstartdate', 'heading' => null),
            array('type' => 'session', 'value' => 'bookingstatus', 'heading' => null),
            array('type' => 'session', 'value' => 'overallstatus', 'heading' => null),
        );

        $this->filters = array(
            array('type' => 'asset', 'value' => 'name', 'advanced' => 0),
            array('type' => 'asset', 'value' => 'assetavailable', 'advanced' => 0)
        );

        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_ALL;

        $this->contentsettings = array(
            'date' => array(
                'enable' => 1,
                'when' => 'future'
            )
        );

        $assetid = array_key_exists('assetid', $data) ? $data['assetid'] : null;
        if ($assetid != null) {
            $this->embeddedparams['assetid'] = $assetid;
        }

        parent::__construct();
    }

    public function is_capable($reportfor, $report) {
        $systemcontext = context_system::instance();
        return has_capability('mod/facetoface:addinstance', $systemcontext, $reportfor);
    }
}