<?php
/*
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Simon Player <simon.player@totaralearning.com>
 * @package totara_reportbuilder
 */

namespace totara_reportbuilder\rb\display;

/**
 * Display class intended for showing a user's name and link to their profile
 * When exporting, only the user's full name is displayed (without link)
 *
 * @author Simon Player <simon.player@totaralearning.com>
 * @package totara_reportbuilder
 */
class user_link extends base {

    /**
     * Handles the display
     *
     * @param string $value
     * @param string $format
     * @param \stdClass $row
     * @param \rb_column $column
     * @param \reportbuilder $report
     * @return string
     */
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        // Extra fields expected are fields from totara_get_all_user_name_fields_join() and totara_get_all_user_name_fields_join()
        $extrafields = self::get_extrafields_row($row, $column);
        $isexport = ($format !== 'html');

        if (isset($extrafields->user_id)) {
            $fullname = $value;
        } else {
            $fullname = fullname($extrafields);
        }

        // Don't show links in spreadsheet.
        if ($isexport) {
            return $fullname;
        }

        if ($extrafields->id == 0) {
            // No user id means no link, most likely the fullname is empty anyway.
            return $fullname;
        }
        // A hacky way to detect whether we are displaying the user name link within a course context or not.
        // TL-18965 reported with inconsistency link for username that it goes to system context instead of course
        // context even though the embedded report was view within course.
        $params = array('id' => $extrafields->id);
        if (!CLI_SCRIPT) {
            global $PAGE;
            // Only adding the course id if user the course is not one of the SITE course
            if ($PAGE->course->id != SITEID) {
                $params['course'] = $PAGE->course->id;
            }
        }

        $url = new \moodle_url('/user/view.php', $params);
        if ($fullname === '') {
            return '';
        } else {
            return \html_writer::link($url, $fullname);
        }
    }

    /**
     * Is this column graphable?
     *
     * @param \rb_column $column
     * @param \rb_column_option $option
     * @param \reportbuilder $report
     * @return bool
     */
    public static function is_graphable(\rb_column $column, \rb_column_option $option, \reportbuilder $report) {
        return false;
    }
}
