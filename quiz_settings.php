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
 * Can be reached either from the quiz settings navigation (provides both
 * quizid and cmid) or from direct links that only provide quizid.
 * If cmid is absent it is derived automatically from quizid so the page
 * never throws "Required parameter cmid is missing".
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// ------------------------------------------------------------------
// Parameter handling: cmid is optional – derive from quizid if absent
// ------------------------------------------------------------------
$quizid = required_param('quizid', PARAM_INT);
$cmid   = optional_param('cmid',   0,  PARAM_INT);

if ($cmid <= 0) {
    // Derive course-module from quizid so direct links still work.
    $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false, MUST_EXIST);
    $cmid = (int)$cm->id;
} else {
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
    // Sanity-check: cm really belongs to the supplied quizid.
    if ((int)$cm->instance !== $quizid) {
        throw new moodle_exception('invalidparameter', 'error');
    }
}

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
// Load current config + form dependencies
// ------------------------------------------------------------------
$config          = \local_stackmathgame\game\quiz_configurator::ensure_default($quizid);
$designs         = \local_stackmathgame\game\theme_manager::get_all_enabled();
$canselectdesign = has_capability('local/stackmathgame:selectdesign', $context);
$canmanagelabels = has_capability('local/stackmathgame:managelabels', $context);

// Build label options for autocomplete element.
$labelrecords = $DB->get_records('local_stackmathgame_label', ['status' => 1], 'name ASC', 'id,name');
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

$form = new \local_stackmathgame\form\quiz_settings_form(null, $customdata);

// URL to go back to after cancel / save.
$returnurl = new moodle_url('/mod/quiz/view.php', ['id' => $cmid]);

// ------------------------------------------------------------------
// Form processing
// ------------------------------------------------------------------
if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    // Handle new label creation when user typed in the newlabel field.
    $newlabelname = trim((string)($data->newlabel ?? ''));
    $labelid = (int)($data->labelid ?? 0);

    if ($newlabelname !== '' && $labelid <= 0) {
        // Check if a label with this name already exists.
        $existing = $DB->get_record('local_stackmathgame_label', ['name' => $newlabelname], 'id');
        if ($existing) {
            $labelid = (int)$existing->id;
        } else {
            $now = time();
            $safeidnumber = clean_param(
                str_replace(' ', '_', strtolower($newlabelname)),
                PARAM_ALPHANUMEXT
            );
            // Ensure idnumber uniqueness.
            $base = $safeidnumber;
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
// Output
// ------------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gamesettings', 'local_stackmathgame'));
$form->display();
echo $OUTPUT->footer();
