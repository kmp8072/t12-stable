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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Simon Player <simon.player@totaralearning.com>
 *
 * @package block_current_learning
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Current learning block class.
 */
class block_current_learning extends block_base {

    /**
     * The period which when within will lead to a visual alert.
     */
    const DEFAULT_ALERT_PERIOD = WEEKSECS; // One week.

    /**
     * The period which when within will lead to a visual warning.
     */
    const DEFAULT_WARNING_PERIOD = 2592000; // One month. (30 * DAYSECS)

    /**
     * The user id of the user this block is being displayed for.
     * ALWAYS the current user.
     * @var int
     */
    private $userid;

    /**
     * The sortorder for content.
     * @var string
     */
    private $sortorder = 'shortname';

    /**
     * The number of items to display per page.
     * @var int
     */
    private $itemsperpage = 10;

    /**
     * An array of context data - used primarily for unit tests.
     * @var array
     */
    private $contextdata;

    /**
     * Initialises a new block instance.
     */
    public function init() {
        $this->title = new lang_string('pluginname', 'block_current_learning');

        if (empty($this->config)) {
            $this->config = new stdClass();
        }

        if (empty($this->config->alerperiod)) {
            $this->config->alertperiod = self::DEFAULT_ALERT_PERIOD;
        }

        if (empty($this->config->warningperiod)) {
            $this->config->warningperiod = self::DEFAULT_WARNING_PERIOD;
        }
    }

    /**
     * Set this block to have configuration.
     *
     * @return false
     */
    public function has_config() {
        return false;
    }

    /**
     * The main content for the block.
     *
     * @return \stdClass Object containing the block content.
     */
    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        if (empty($this->userid)) {
            // This is the default flow, the userid is typically only set for testing.
            // If it is not set we will use the current user, seeing as it is the current user we will also check that they
            // are logged in, and that they are not the guest user.
            if (!isloggedin() || isguestuser()) {
                return $this->content;
            }
            $this->userid = $USER->id;
        }

        // Create the learning data.
        $items = $this->get_user_learning_items();

        // Our block content array.
        $contextdata = [
            'instanceid' => $this->instance->id,
            'learningitems' => []
        ];

        // Create the template data.
        foreach ($items as $item) {
            $itemclass = get_class($item);
            $template = false;
            switch ($itemclass) {
                case 'core_course\user_learning\item':
                case 'totara_plan\user_learning\course':
                    $template = 'block_current_learning/course_row';
                    break;
                case 'totara_program\user_learning\item':
                case 'totara_certification\user_learning\item':
                    $template = 'block_current_learning/program_row';
                    break;
                default:
                    break;
            }

            // If we don't know the template then we can't render them.
            if ($template !== false) {
                $itemdata = $item->export_for_template();
                // Add block specific display info here for each item.
                // Add status for duetext.
                if ($item instanceof \totara_core\user_learning\item_has_dueinfo && !empty($itemdata->dueinfo)) {
                    $duedate_state = \block_current_learning\helper::get_duedate_state($item->duedate, $this->config);
                    $itemdata->dueinfo->state = $duedate_state['state'];
                    $itemdata->dueinfo->alert = $duedate_state['alert'];
                }

                // Add separate title and icon for programs and certifications (since we use the same template)
                if ($item instanceof \totara_program\user_learning\item) {
                    $itemdata->title = get_string('thisisaprogram', 'block_current_learning');
                    $itemdata->icon = 'program';
                }

                if ($item instanceof \totara_certification\user_learning\item) {
                    $itemdata->title = get_string('thisisacertification', 'block_current_learning');
                    $itemdata->icon = 'certification';
                }

                $itemdata->template = $template;
                $contextdata['learningitems'][] = $itemdata;
            }
        }

        // Create the pagination data if we have items to display.
        if (!empty($contextdata['learningitems'])) {
            $pagination = $this->pagination($contextdata['learningitems']);
            $contextdata['pagination'] = $pagination;
        }

        // The full data.
        $this->contextdata = $contextdata;
        $contextdata['contextdata'] = json_encode($contextdata);

        // The initial view data, limited by itemsperpage.
        $contextdata['learningitems'] = array_slice($contextdata['learningitems'], 0, $this->itemsperpage);
        if (!empty($contextdata['learningitems'])) {
            $contextdata['haslearningitems'] = true;
        } else {
            $rollink = new moodle_url('/totara/plan/record/index.php', array('userid' => $USER->id));
            $contextdata['rollink'] = $rollink->out();
        }

        $core_renderer = $this->page->get_renderer('core');
        $this->content->text = $core_renderer->render_from_template('block_current_learning/block', $contextdata);

        return $this->content;
    }

    /**
     * Takes an array of user learning instances and ensures no instance appears twice.
     *
     * If more than one are found then the primary for each type is kept.
     *
     * @param \totara_core\user_learning\item_base[] $items
     * @return \totara_core\user_learning\item_base[]
     */
    private function ensure_user_learning_items_unique(array $items) {
        // First iterate over the items and ensure no item appears twice.
        $instances = [];
        foreach ($items as $key => $item) {
            $component = $item->get_component();
            $type = $item->get_type();

            if (!isset($instances[$component][$type][$item->id])) {
                $instances[$component][$type][$item->id] = $key;
            } else {
                // There are two and they are not the same :(
                $oldisprimary = $items[$instances[$component][$type][$item->id]]->is_primary_user_learning_item();
                $newisprimary = $item->is_primary_user_learning_item();
                if ($oldisprimary && $newisprimary) {
                    // We should never ever be here!
                    debugging('Two primary user learning instance with matching identifiers found - this should never happen.', DEBUG_DEVELOPER);
                    // Unset this one just so we can progress.
                    unset($items[$key]);
                } else if ($newisprimary) {
                    // The new item is primary and the old is not, unset the old.
                    unset($items[$instances[$component][$type][$item->id]]);
                    $instances[$component][$type][$item->id] = $key;
                } else {
                    // The old is primary and the new is not, unset the new.
                    unset($items[$key]);
                }
            }
        }

        return $items;
    }

    /**
     * Filters the collective user learning items altering the structure to meet this blocks purpose.
     *
     * @param \totara_core\user_learning\item_base[] $items
     * @return \totara_core\user_learning\item_base[]
     */
    private function filter_collective_content(array $items) {
        global $DB, $CFG;

        if (empty($items)) {
            return [];
        }

        // First up we need to remove any courses from the top level that are within a program or certification that
        // is not complete or unavailable.
        $progcertcourses = [];
        foreach ($items as $item) {
            if ($item instanceof \totara_program\user_learning\item || $item instanceof \totara_certification\user_learning\item) {
                $courses = $item->get_courseset_courses(false);
                foreach ($courses as $course) {
                    $progcertcourses[$course->id] = $course;
                }
            }
        }

        // Ensure the list of user learning items is unique.
        $items = $this->ensure_user_learning_items_unique($items);

        $counts = [];
        if (!empty($CFG->gradebookroles)) {
            // Gets all course where a user has an active enrolment and is assigned a gradeable role.
            // There is a little gotcha here - we are only looking at roles assigned via an enrolment
            // and not roles that have been assigned manually within the course.
            $gradebookroles = explode(",", $CFG->gradebookroles);
            $userscourses = enrol_get_all_users_courses($this->userid, true);
            if (!empty($userscourses)) {
                list($gradebookrolessql, $gradebookrolesparams) = $DB->get_in_or_equal($gradebookroles, SQL_PARAMS_NAMED);
                list($coursesql, $courseparams) = $DB->get_in_or_equal(array_keys($userscourses), SQL_PARAMS_NAMED);
                $sql = "SELECT e.courseid, COUNT(e.id) AS gradeablecount
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id
                     WHERE e.courseid {$coursesql}
                       AND ue.userid = :userid
                       AND e.roleid {$gradebookrolessql}
                  GROUP BY e.courseid";
                $params = array_merge($gradebookrolesparams, $courseparams, ['userid' => $this->userid]);
                $counts = $DB->get_records_sql_menu($sql, $params);
            }
        }

        // Now make the manipulations required by this block.
        foreach ($items as $key => $item) {

            if ($item instanceof \core_course\user_learning\item) {
                // Remove courses that are part of progs or certifications.
                if (array_key_exists($item->id, $progcertcourses)) {
                    unset($items[$key]);
                    continue;
                }

                // Remove completed courses, regardless of how they got here.
                if ($item->is_complete() === true) {
                    // Once removed continue so that we don't do anything more with this item.
                    unset($items[$key]);
                    continue;
                }

                if (empty($counts[$item->id]) && (!$item->has_owner() || !($item->get_owner() instanceof \totara_plan\user_learning\item))) {
                    // The user does not hold a gradeable role and this course is not part of a plan.
                    unset($items[$key]);
                    continue;
                }
            }

            // Remove completed courseset courses.
            if (method_exists($item, 'remove_completed_courses')) {
                $item->remove_completed_courses();
            }

            // Remove progs/certs that have no coursesets.
            if (method_exists($item, 'get_coursesets')) {
                if (empty($item->get_coursesets())) {
                    unset($items[$key]);
                };
            }
        }

        return $items;
    }

    /**
     * Combines the data of the separate getters.
     *
     * @return \totara_core\user_learning\item_base[]
     */
    private function get_user_learning_items() {

        /** @var \totara_core\user_learning\item_base[] $classes */
        $classes = core_component::get_namespace_classes('user_learning', 'totara_core\user_learning\item_base');
        /** @var \totara_core\user_learning\item_base[] $items */
        $items = [];
        foreach ($classes as $class) {
            // First up we only want primary user learning items.
            if (!$class::is_a_primary_user_learning_class()) {
                continue;
            }

            /** @var \totara_core\user_learning\item_base[] $classitems */
            $classitems = $class::all($this->userid);
            $items = array_merge($items, array_values($classitems));
        }

        // Expand the items are required to create a specialised list for this block.
        $items = $this->expand_item_specialisations($items);

        // Sort the data.
        core_collator::asort_objects_by_property($items, $this->sortorder);

        // Filter the content to exclude duplications, completed courses and other block specific criteria.
        $items = $this->filter_collective_content($items);

        return $items;
    }

    /**
     * Expands any item specific user learning item data as required for this block.
     *
     * @param \totara_core\user_learning\item_base[] $items
     * @return \totara_core\user_learning\item_base[]
     */
    private function expand_item_specialisations(array $items) {
        foreach ($items as $item) {
            if ($item instanceof \totara_plan\user_learning\item) {
                $courses = $item->get_courses();
                $items = array_merge($items, array_values($courses));
            }
        }
        return $items;
    }


    /**
     * Creates the data needed for the pagination template.
     *
     * @param stdClass[] $learning_data An array of learning data context items.
     * @return stdClass A pagination context data object.
     */
    private function pagination(array $learning_data) {

        $data = new stdClass();

        $data->totalitems = count($learning_data);
        $data->itemsperpage = $this->itemsperpage;
        $data->currentpage = 1;
        $data->pages = null;
        $data->text = 0;
        $data->pages = array();

        if ($data->totalitems === 0) {
            return $data;
        }

        // Figure out how many pages we have.
        $pages = (int)ceil($data->totalitems / $this->itemsperpage);

        if ($pages <= 1) {
            $pages = 1;
            $data->onepage = 1;
        }

        $data->nextclass = $data->currentpage == $pages ? 'disabled' : '';
        $data->previousclass = $data->currentpage == 1 ? 'disabled' : '';


        // The display text.
        $data->text = get_string("displayingxofx", "block_current_learning", array(
            'start' => 1,
            'end' => ($data->totalitems < $data->itemsperpage) ? $data->totalitems : $data->itemsperpage,
            'total' => $data->totalitems
        ));

        $pages = range(1, $pages);

        foreach ($pages as $page) {
            $pageinfo = new \stdClass();
            $pageinfo->page = $page;
            $pageinfo->link = '';
            if ($page == $data->currentpage) {
                $pageinfo->active = 'active';
            }
            $data->pages[] = $pageinfo;
        }

        return $data;
    }
}