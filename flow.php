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
 * Game flow authoring for a quiz: the list of slots and the direction card of one slot.
 *
 * A page of its own rather than more sections on the game settings form. Twenty slots with five
 * sections each would make the settings form unusable, and the two jobs are different: settings
 * are chosen once, the flow is edited repeatedly while a quiz takes shape.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use local_stackmathgame\form\slot_config_form;
use local_stackmathgame\local\service\flow_service;
use local_stackmathgame\local\service\question_map_service;

$cmid = required_param('cmid', PARAM_INT);
$action = optional_param('action', 'list', PARAM_ALPHA);
$slotnumber = optional_param('slot', 0, PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cmid);

require_login($course, false, $cm);
// The capability is checked in the module context the flow belongs to, not in the system
// context: a teacher may author their own quiz without being able to touch anyone else's.
require_capability('local/stackmathgame:configurequiz', $context);

$listurl = new moodle_url('/local/stackmathgame/flow.php', ['cmid' => $cmid]);

$PAGE->set_url($listurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('flow_heading', 'local_stackmathgame'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('flow_heading', 'local_stackmathgame'), $listurl);

// Explicit resynchronisation. ensure_for_cmid() also runs whenever the list is
// rendered, but a teacher who has just reorganised the quiz wants to be told
// what changed rather than left to infer it.
if ($action === 'sync') {
    require_sesskey();
    $summary = question_map_service::ensure_for_cmid($cmid);
    redirect(
        $listurl,
        get_string('flow_synced', 'local_stackmathgame', (object)[
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'deleted' => $summary['deleted'],
        ]),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Bulk apply. Only the fields the teacher actually filled in are applied, so
// setting a scene type on twenty slots does not wipe twenty narratives.
if ($action === 'bulk') {
    require_sesskey();
    $slots = optional_param_array('slots', [], PARAM_INT);
    $scenetype = optional_param('bulkscenetype', '', PARAM_ALPHA);
    $xp = optional_param('bulkxp', '', PARAM_RAW_TRIMMED);

    if (!$slots) {
        redirect(
            $listurl,
            get_string('flow_bulk_noselection', 'local_stackmathgame'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $partial = [];
    if ($scenetype !== '') {
        $partial['scene'] = ['type' => $scenetype];
    }
    if ($xp !== '' && is_numeric($xp)) {
        $partial['rewards'] = ['xp' => max(0, (int)$xp)];
    }
    if (!$partial) {
        redirect(
            $listurl,
            get_string('flow_bulk_nothing', 'local_stackmathgame'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $results = flow_service::apply_to_slots($cmid, $slots, $partial);
    $failed = array_filter($results);
    if ($failed) {
        redirect(
            $listurl,
            get_string('flow_bulk_failed', 'local_stackmathgame', implode(', ', array_keys($failed))),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    redirect(
        $listurl,
        get_string('flow_bulk_applied', 'local_stackmathgame', count($results)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// One slot's direction card.
if ($action === 'editslot' && $slotnumber > 0) {
    $existing = flow_service::get_slot_config($cmid, $slotnumber);
    if ($existing === null) {
        redirect(
            $listurl,
            get_string('flow_err_unknownslot', 'local_stackmathgame', $slotnumber),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $slots = flow_service::get_slots($cmid);
    $slotoptions = [0 => get_string('flow_branchtarget_none', 'local_stackmathgame')];
    foreach ($slots as $number => $slot) {
        $slotoptions[$number] = $number . ': ' . ($slot['questionname'] ?: get_string('flow_untitled', 'local_stackmathgame'));
    }

    $editurl = new moodle_url('/local/stackmathgame/flow.php', [
        'cmid' => $cmid,
        'slot' => $slotnumber,
        'action' => 'editslot',
    ]);
    $PAGE->set_url($editurl);

    $form = new slot_config_form($editurl, [
        'slotoptions' => $slotoptions,
        'stashitems' => \local_stackmathgame\local\service\stash_mapping_service::get_stash_items_for_course(
            (int)$course->id
        ),
    ]);
    $form->set_data(slot_config_form::config_to_form($existing, $cmid, $slotnumber));

    if ($form->is_cancelled()) {
        redirect($listurl);
    }

    if ($data = $form->get_data()) {
        $errors = flow_service::save_slot_config(
            $cmid,
            $slotnumber,
            slot_config_form::form_to_config($data, $existing)
        );
        if ($errors) {
            // Schema errors reaching this point mean the form let something through that the
            // schema rejects. Showing them rather than swallowing them is what keeps the two
            // in step.
            redirect($editurl, implode(' ', $errors), null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('flow_slotheading', 'local_stackmathgame', (object)[
        'slot' => $slotnumber,
        'question' => format_string($slots[$slotnumber]['questionname'] ?? ''),
    ]));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// The slot list.
$renderer = $PAGE->get_renderer('local_stackmathgame');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('flow_heading', 'local_stackmathgame'));
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/stackmathgame/quiz_settings.php', ['cmid' => $cmid]),
        get_string('gamesettings', 'local_stackmathgame'),
        ['class' => 'btn btn-secondary']
    ) . ' ' . html_writer::link(
        new moodle_url('/local/stackmathgame/flow.php', [
            'cmid' => $cmid,
            'action' => 'sync',
            'sesskey' => sesskey(),
        ]),
        get_string('flow_sync', 'local_stackmathgame'),
        ['class' => 'btn btn-secondary']
    ),
    'mb-3'
);
echo $renderer->render(new \local_stackmathgame\output\prerequisite_panel(
    \local_stackmathgame\local\service\prerequisite_checker::check($cmid)
));
echo $renderer->render(new \local_stackmathgame\output\flow_list($cmid));
echo $OUTPUT->footer();
