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
 * @author Petr Skoda <petr.skoda@totaralearning.com>
 * @package totara_form
 */

namespace totara_form\form\element\behat_helper;
use Behat\Mink\Exception\ExpectationException;

/**
 * A utc10date element helper.
 *
 * @author Petr Skoda <petr.skoda@totaralearning.com>
 * @copyright 2017 Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package totara_form
 */
class utc10date extends text {

    /**
     * Sets the value of the utc10date input.
     *
     * @param string $value
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function set_value($value) {
        $value = $this->normalise_value_pre_set($value);
        if (!$this->context->running_javascript()) {
            // If JS is not running this is practically just a plain text field.
            parent::set_value($value);
            return;
        }
        // If JS is running then we need to use JS to set the value.
        // It has to be perfectly formatted.
        $text = $this->get_text_input();
        if ($this->context->running_javascript() && !$text->isVisible()) {
            throw new ExpectationException('Attempting to change a ' . $this->mytype . ' that is not visible', $this->context->getSession());
        }
        $value = addslashes($value);
        $id = $this->node->getAttribute('data-element-id');
        $js  = 'var e, t;';
        $js .= 'e = document.getElementById("'.$id.'");';
        $js .= 'e.value = "' . $value . '";';
        $this->context->getSession()->executeScript($js);

        // Trigger the onchange event as triggered when selecting a real value.
        $this->context->getSession()->getDriver()->triggerSynScript(
            $text->getXPath(),
            "Syn.trigger('change', {}, {{ELEMENT}})"
        );
    }

    /**
     * Normalises the given value prior to setting it.
     *
     * @throws ExpectationException
     * @param string $value
     * @return string
     */
    protected function normalise_value_pre_set($value) {
        if (trim($value) === '') {
            return '';
        }
        // Only YYYY-MM-DD and variation with spaces or slashes are accepted.
        $regex = '#^(?P<year>\d{2,4})[\-/ ](?P<month>\d{1,2})[\-/ ](?P<day>\d{1,2})$#';
        if (!preg_match($regex, trim($value), $matches)) {
            throw new ExpectationException('Invalid utc10date value provided, it should be YYYY-MM-DD, "'.$value.'"', $this->context->getSession());
        }
        $year = (int)$matches['year'];
        $month = (int)$matches['month'];
        $day = (int)$matches['day'];

        if ($year < 99) {
            $year += 2000;
        }

        $year = $this->stringify_date_digit($year, 4);
        $month = $this->stringify_date_digit($month);
        $day = $this->stringify_date_digit($day);

        return "{$year}-{$month}-{$day}";
    }

    /**
     * Takes a value and converts it to a string of the expected length.
     *
     * @param int $value
     * @param int $length
     * @return string
     */
    protected function stringify_date_digit($value, $length = 2) {
        $value = (string)$value;
        $valuelen = strlen($value);
        if ($valuelen < $length) {
            $value = str_pad($value, $length, '0', STR_PAD_LEFT);
        }
        return $value;
    }

}