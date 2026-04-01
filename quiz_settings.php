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
 * Quiz-level game settings page for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Both cmid and quizid are optional; at least one must be provided.
// Internal work is always done with cmid.
$cmid   = optional_param('cmid', 0, PARAM_INT);
$quizid = optional_param('quizid', 0, PARAM_INT);

if ($cmid <= 0 && $quizid <= 0) {
    throw new moodle_exception('invalidparameter', 'error');
}

// Resolve course-module: cmid takes priority, else derive from quizid.
$cm = null;
if ($cmid > 0) {
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, IGNORE_MISSING);
    if ($cm && $quizid > 0 && (int)$cm->instance !== $quizid) {
        // Mismatch between supplied cmid and quizid.
        $cm = null;
    }
}
if (!$cm && $quizid > 0) {
    $cm = get_coursemodule_from_instance('quiz', $quizid, 0, IGNORE_MISSING);
}

// Handle case where the quiz no longer has a course-module (deleted).
if (!$cm) {
    $PAGE->set_url(new moodle_url('/local/stackmathgame/quiz_settings.php', ['cmid' => $cmid]));
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('gamesettings', 'local_stackmathgame'));
    $PAGE->set_heading(get_string('gamesettings', 'local_stackmathgame'));
    require_login();

    try {
        if ($quizid > 0) {
            $DB->delete_records('local_stackmathgame', ['cmid' => $cmid]);
        }
    } catch (Throwable $e) {
        debugging('Could not remove orphaned quizcfg: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('quiznotfound', 'local_stackmathgame', $quizid ?: $cmid),
        \core\output\notification::NOTIFY_WARNING
    );
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/'),
            get_string('returnhome', 'local_stackmathgame'),
            ['class' => 'btn btn-secondary']
        ),
        'mt-3'
    );
    echo $OUTPUT->footer();
    exit;
}

// Work internally with cmid throughout.
$cmid   = (int)$cm->id;
$quizid = (int)$cm->instance;
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cmid);

require_login($course, false, $cm);
require_capability('local/stackmathgame:configurequiz', $context);

$pageurl = new moodle_url('/local/stackmathgame/quiz_settings.php', [
    'cmid'   => $cmid,
    'quizid' => $quizid,
]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('gamesettings', 'local_stackmathgame'));
$PAGE->set_heading(format_string($course->fullname));

$config          = \local_stackmathgame\game\quiz_configurator::ensure_default($cmid);

// P1: Ensure questionmap rows exist so the slot-config section can be built.
// This is idempotent: existing rows are preserved, only missing ones are added.
\local_stackmathgame\local\service\question_map_service::ensure_for_cmid($cmid);
$designs         = \local_stackmathgame\game\theme_manager::get_all_enabled();
$canselectdesign = has_capability('local/stackmathgame:selectdesign', $context);
$canmanagelabels = has_capability('local/stackmathgame:managelabels', $context);

$stashitems = \local_stackmathgame\local\service\stash_mapping_service::get_stash_items_for_course((int)$course->id);
$stashslots = \local_stackmathgame\local\service\stash_mapping_service::get_activity_slots($cmid, 'quiz', $quizid);
$stashmappings = \local_stackmathgame\local\service\stash_mapping_service::get_for_activity($cmid, $quizid, 'quiz');

$labelrecords = $DB->get_records(
    'local_stackmathgame_label',
    ['status' => 1],
    'name ASC',
    'id,name'
);
$labeloptions = [];
foreach ($labelrecords as $lrec) {
    $labeloptions[(int)$lrec->id] = format_string($lrec->name);
}

$customdata = [
    'config'          => $config,
    'designs'         => $designs,
    'labeloptions'    => $labeloptions,
    'canselectdesign' => $canselectdesign,
    'canmanagelabels' => $canmanagelabels,
    'quizid'          => $quizid,
    'cmid'            => $cmid,
    'stashitems'      => $stashitems,
    'stashslots'      => $stashslots,
    'stashmappings'   => $stashmappings,
    'slotconfigs'     => \local_stackmathgame\local\service\branch_resolver::get_quiz_slot_configs($cmid, $quizid),
    'quizslots'       => \local_stackmathgame\local\service\question_map_service::get_quiz_slot_records($quizid),
];

$form      = new \local_stackmathgame\form\quiz_settings_form(null, $customdata);
$returnurl = new moodle_url('/mod/quiz/view.php', ['id' => $cmid]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $newlabelname = trim((string)($data->newlabel ?? ''));
    $labelid      = (int)($data->labelid ?? 0);

    if ($newlabelname !== '' && $labelid <= 0) {
        $existing = $DB->get_record('local_stackmathgame_label', ['name' => $newlabelname], 'id');
        if ($existing) {
            $labelid = (int)$existing->id;
        } else {
            $now          = time();
            $safeidnumber = clean_param(
                str_replace(' ', '_', strtolower($newlabelname)),
                PARAM_ALPHANUMEXT
            );
            $base   = $safeidnumber;
            $suffix = 1;
            while ($DB->record_exists('local_stackmathgame_label', ['idnumber' => $safeidnumber])) {
                $safeidnumber = $base . '_' . $suffix++;
            }
            $labelid = (int)$DB->insert_record('local_stackmathgame_label', (object)[
                'name'         => $newlabelname,
                'idnumber'     => $safeidnumber,
                'description'  => '',
                'status'       => 1,
                'timecreated'  => $now,
                'timemodified' => $now,
                'createdby'    => (int)$USER->id,
                'timedeleted'  => null,
            ]);
        }
        $data->labelid = $labelid;
    }

    \local_stackmathgame\game\quiz_configurator::save_for_quiz($cmid, (array)$data);

    $stashpayload = [];
    foreach ($stashslots as $slotnumber) {
        $prefix = 'stashmap_' . $slotnumber . '_';
        $stashpayload[] = [
            'slotnumber' => (int)($data->{$prefix . 'slot'} ?? $slotnumber),
            'stashitemid' => (int)($data->{$prefix . 'itemid'} ?? 0),
            'grantquantity' => (int)($data->{$prefix . 'qty'} ?? 1),
            'enabled' => empty($data->{$prefix . 'enabled'}) ? 0 : 1,
        ];
    }
    if ($stashpayload) {
        \local_stackmathgame\local\service\stash_mapping_service::save_for_activity(
            $cmid,
            (int)$course->id,
            $stashpayload,
            'quiz',
            $quizid
        );
    }

    // P2: Save per-slot Regiekarte (scene type, branching, narrative, rewards).
    $slotconfigpayload = [];
    foreach (array_keys($customdata['quizslots'] ?? []) as $slotnumber) {
        $prefix = 'slotcfg_' . $slotnumber . '_';
        if (!isset($data->{$prefix . 'scenetype'})) {
            continue;
        }
        $branchingrules = [];
        foreach (['gradedright', 'gradedwrong', 'default'] as $outcome) {
            $mode   = (string)($data->{$prefix . 'branch_' . $outcome . '_mode'} ?? 'linear');
            $target = (int)($data->{$prefix . 'branch_' . $outcome . '_target'} ?? 0);
            $branchingrules[$outcome] = ['mode' => $mode, 'target' => $target];
        }
        $cfg = [
            'version'   => 1,
            'enabled'   => true,
            'scene'     => ['type' => (string)($data->{$prefix . 'scenetype'} ?? 'challenge')],
            'branching' => $branchingrules,
            'rewards'   => [
                'score' => (int)($data->{$prefix . 'reward_score'} ?? 0),
                'xp'    => (int)($data->{$prefix . 'reward_xp'} ?? 0),
            ],
            'narrative' => [
                'intro'   => (string)($data->{$prefix . 'narrative_intro'} ?? ''),
                'success' => (string)($data->{$prefix . 'narrative_success'} ?? ''),
                'fail'    => (string)($data->{$prefix . 'narrative_fail'} ?? ''),
            ],
        ];
        $slotconfigpayload[(int)$slotnumber] = $cfg;
    }
    if (!empty($slotconfigpayload)) {
        \local_stackmathgame\local\service\question_map_service::save_slot_configs($cmid, $quizid, $slotconfigpayload);
    }

    redirect(
        $pageurl,
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gamesettings', 'local_stackmathgame'));
$form->display();
echo $OUTPUT->footer();
