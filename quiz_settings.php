<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Quiz-level game settings page.
 *
 * Accepts quizid (required) and cmid (optional).
 * If cmid is absent it is derived from quizid automatically.
 *
 * Fixed (v 2026032700):
 * - get_coursemodule_from_instance() only accepts 4 parameters.
 *   The previous call passed false as 4th (= IGNORE_MISSING) and MUST_EXIST
 *   as a silently-ignored 5th argument, causing PHP to return null and then
 *   crash with a fatal error or unexpected exception.
 *   Now uses IGNORE_MISSING explicitly and handles the null-return gracefully,
 *   showing a user-friendly error page instead of a raw exception.
 * - When quiz id exists in the game config but its course_modules row was
 *   deleted (e.g. quiz removed from course), the page now shows an
 *   explanatory message and offers a link back to the course rather than
 *   throwing dml_missing_record_exception.
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// ------------------------------------------------------------------
// Parameters: cmid is optional – derived from quizid when absent.
// ------------------------------------------------------------------
$quizid = required_param('quizid', PARAM_INT);
$cmid   = optional_param('cmid',   0,          PARAM_INT);

// ------------------------------------------------------------------
// Resolve the course-module record.
//
// get_coursemodule_from_instance() signature:
//   function get_coursemodule_from_instance(
//       $modulename,              // 1st
//       $instance,                // 2nd
//       $courseid   = 0,          // 3rd
//       $strictness = IGNORE_MISSING  // 4th  ← ONLY 4 params!
//   )
//
// We must NOT pass MUST_EXIST as a 5th argument – PHP silently ignores
// it, leaving the 4th param as whatever we put there.
// ------------------------------------------------------------------
if ($cmid > 0) {
    // cmid supplied: use the fast get_by_id path.
    // get_coursemodule_from_id() also has 4 params; strictness is 4th.
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, IGNORE_MISSING);
    if (!$cm) {
        // cmid present but record gone (module deleted).
        $cm = null;
    } elseif ((int)$cm->instance !== $quizid) {
        // cmid belongs to a different quiz than what was requested.
        $cm = null;
    }
} else {
    $cm = null;
}

if (!$cm) {
    // Try to resolve cmid from the quiz instance id.
    // *** BUG FIX: pass IGNORE_MISSING as 4th param (not as 5th), then
    // check for null explicitly instead of relying on an exception. ***
    $cm = get_coursemodule_from_instance('quiz', $quizid, 0, IGNORE_MISSING);
}

// ------------------------------------------------------------------
// Handle missing course-module gracefully.
// This occurs when the quiz was deleted or its course_module row was
// removed, but the game config (quizcfg row) still references the
// old quizid.  Rather than crashing, show an informative error page.
// ------------------------------------------------------------------
if (!$cm) {
    // We cannot determine the context without a CM, so use system context
    // for the error page.
    $PAGE->set_url(new moodle_url('/local/stackmathgame/quiz_settings.php',
                                  ['quizid' => $quizid]));
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('gamesettings', 'local_stackmathgame'));
    $PAGE->set_heading(get_string('gamesettings', 'local_stackmathgame'));
    require_login();

    // Optionally clean up the dangling quizcfg row.
    try {
        $DB->delete_records('local_stackmathgame_quizcfg', ['quizid' => $quizid]);
    } catch (\Throwable $e) {
        // Non-fatal: cleanup is best-effort.
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('quiznotfound', 'local_stackmathgame', $quizid),
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

$cmid   = (int)$cm->id;
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cmid);

require_login($course, false, $cm);
require_capability('local/stackmathgame:configurequiz', $context);

$pageurl = new moodle_url('/local/stackmathgame/quiz_settings.php', [
    'quizid' => $quizid,
    'cmid'   => $cmid,
]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('gamesettings', 'local_stackmathgame'));
$PAGE->set_heading(format_string($course->fullname));

// ------------------------------------------------------------------
// Load current config + form dependencies.
// ------------------------------------------------------------------
$config          = \local_stackmathgame\game\quiz_configurator::ensure_default($quizid);
$designs         = \local_stackmathgame\game\theme_manager::get_all_enabled();
$canselectdesign = has_capability('local/stackmathgame:selectdesign', $context);
$canmanagelabels = has_capability('local/stackmathgame:managelabels', $context);

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
];

$form      = new \local_stackmathgame\form\quiz_settings_form(null, $customdata);
$returnurl = new moodle_url('/mod/quiz/view.php', ['id' => $cmid]);

// ------------------------------------------------------------------
// Form processing.
// ------------------------------------------------------------------
if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $newlabelname = trim((string)($data->newlabel ?? ''));
    $labelid      = (int)($data->labelid ?? 0);

    if ($newlabelname !== '' && $labelid <= 0) {
        $existing = $DB->get_record(
            'local_stackmathgame_label',
            ['name' => $newlabelname],
            'id'
        );
        if ($existing) {
            $labelid = (int)$existing->id;
        } else {
            $now         = time();
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

    \local_stackmathgame\game\quiz_configurator::save_for_quiz($quizid, (array)$data);

    redirect(
        $pageurl,
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ------------------------------------------------------------------
// Output.
// ------------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gamesettings', 'local_stackmathgame'));
$form->display();
echo $OUTPUT->footer();
