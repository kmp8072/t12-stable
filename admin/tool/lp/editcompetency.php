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
 * This page lets users to manage site wide competencies.
 *
 * @package    tool_lp
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$title = get_string('competencies', 'tool_lp');
$id = optional_param('id', 0, PARAM_INT);
$competencyframeworkid = optional_param('competencyframeworkid', 0, PARAM_INT);
$pagecontextid = required_param('pagecontextid', PARAM_INT);  // Reference to the context we came from.
$parentid = optional_param('parentid', 0, PARAM_INT);

require_login();
\tool_lp\api::require_enabled();
$pagecontext = context::instance_by_id($pagecontextid);

if (empty($competencyframeworkid) && empty($id)) {
    throw new coding_exception('Competencyframeworkid param is required');
}
if (!empty($competencyframeworkid)) {
    $competencyframework = \tool_lp\api::read_framework($competencyframeworkid);
}

$competency = null;
if (!empty($id)) {
    $competency = \tool_lp\api::read_competency($id);
    $competencyframework = $competency->get_framework();
}

$parent = null;
if ($competency) {
    $parent = $competency->get_parent();
} else if ($parentid) {
    $parent = \tool_lp\api::read_competency($parentid);
}

if (empty($id)) {
    $level = $parent ? $parent->get_level() + 1 : 1;
    $pagetitle = get_string('taxonomy_add_' . $competencyframework->get_taxonomy($level), 'tool_lp');
} else {
    $pagetitle = get_string('taxonomy_edit_' . $competencyframework->get_taxonomy($competency->get_level()), 'tool_lp');
}

// Set up the page.
$url = new moodle_url("/admin/tool/lp/editcompetency.php", array(
    'id' => $id,
    'competencyframeworkid' => $competencyframework->get_id(),
    'parentid' => $parentid, 'pagecontextid' => $pagecontextid)
);
$frameworksurl = new moodle_url('/admin/tool/lp/competencyframeworks.php', array('pagecontextid' => $pagecontextid));
$frameworkurl = new moodle_url('/admin/tool/lp/competencies.php', array('competencyframeworkid' => $competencyframework->get_id(),
    'pagecontextid' => $pagecontextid));

$PAGE->navigation->override_active_url($frameworksurl);
$PAGE->set_context($pagecontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($competencyframework->get_shortname(), $frameworkurl);
$output = $PAGE->get_renderer('tool_lp');

$form = new \tool_lp\form\competency($url->out(false), array('competencyframework' => $competencyframework,
    'parent' => $parent, 'persistent' => $competency));

if ($form->is_cancelled()) {
    redirect($frameworkurl);
}

echo $output->header();
echo $output->heading($pagetitle);

$data = $form->get_data();
if ($data) {
    require_sesskey();
    if (empty($competency)) {
        \tool_lp\api::create_competency($data);
        echo $output->notification(get_string('competencycreated', 'tool_lp'), 'notifysuccess');
        echo $output->continue_button($frameworkurl);
    } else {
        \tool_lp\api::update_competency($data);
        echo $output->notification(get_string('competencyupdated', 'tool_lp'), 'notifysuccess');
        echo $output->continue_button($frameworkurl);
    }
} else {
    $form->display();
}

echo $output->footer();
