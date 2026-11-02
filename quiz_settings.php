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

// The activity is addressed by cmid; a bare quizid is accepted for old links. Both together must
// name the same activity: a mismatch used to fall back to the quizid and opened - and saved - the
// settings of a different quiz than the one in the cmid (issue #14).
$cmid   = optional_param('cmid', 0, PARAM_INT);
$quizid = optional_param('quizid', 0, PARAM_INT);

if ($cmid <= 0 && $quizid <= 0) {
    throw new moodle_exception('invalidparameter', 'error');
}
if ($cmid > 0) {
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
    if ($quizid > 0 && (int)$cm->instance !== $quizid) {
        throw new moodle_exception('invalidparameter', 'error');
    }
} else {
    $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false, MUST_EXIST);
}

$cmid    = (int)$cm->id;
$quizid  = (int)$cm->instance;
$course  = get_course((int)$cm->course);
$context = context_module::instance($cmid);

require_login($course, false, $cm);
require_capability('local/stackmathgame:configurequiz', $context);

$pageurl = new moodle_url('/local/stackmathgame/quiz_settings.php', ['cmid' => $cmid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('gamesettings', 'local_stackmathgame'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

// Read only: opening or cancelling this page writes nothing. Defaults for an activity without a
// configuration are computed, and the row is created by the first save.
$config          = \local_stackmathgame\game\quiz_settings_service::current_values($cmid);
[$designs]       = \local_stackmathgame\game\theme_manager::get_selectable((int)$config->designid);
$canselectdesign = has_capability('local/stackmathgame:selectdesign', $context);
$canmanagelabels = has_capability('local/stackmathgame:managelabels', $context);

// Only the current label is handed to the selector; the others are searched on the server.
$labeloptions = [];
$currentlabelname = '';
if (!empty($config->labelid)) {
    $currentlabel = $DB->get_record('local_stackmathgame_label', ['id' => (int)$config->labelid], 'id, name');
    if ($currentlabel) {
        $currentlabelname = format_string($currentlabel->name);
        $labeloptions[(int)$currentlabel->id] = $currentlabelname;
    }
}

// Rendered here rather than inside the form: the form must not run database checks of its own,
// and the panel is also the thing to look at when the page is opened purely to find out why a
// game is not starting.
$renderer = $PAGE->get_renderer('local_stackmathgame');
$prerequisitehtml = $renderer->render(
    \local_stackmathgame\output\prerequisite_panel::for_cmid($cmid, (int)$config->designid)
);

$customdata = [
    'config'           => $config,
    'prerequisitehtml' => $prerequisitehtml,
    'designs'          => $designs,
    'labeloptions'     => $labeloptions,
    'currentlabelname' => $currentlabelname,
    'canselectdesign'  => $canselectdesign,
    'canmanagelabels'  => $canmanagelabels,
    'quizid'           => $quizid,
    'cmid'             => $cmid,
];

$form      = new \local_stackmathgame\form\quiz_settings_form($pageurl, $customdata);
$returnurl = new moodle_url('/mod/quiz/view.php', ['id' => $cmid]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

$saveerror = null;
if ($data = $form->get_data()) {
    try {
        \local_stackmathgame\game\quiz_settings_service::save($cmid, (array)$data, $context);
        // Said only after label and configuration are both written.
        redirect(
            $pageurl,
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        // The form is shown again with what the teacher entered; nothing was written.
        $saveerror = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gamesettings', 'local_stackmathgame'));
if ($saveerror !== null) {
    echo $OUTPUT->notification($saveerror, \core\output\notification::NOTIFY_ERROR);
}
// The game itself loads nothing from elsewhere; Moodle's MathJax filter may. Said here, where the
// game is set up, because it is a site setting the plugin must not change on its own.
$mathjaxhost = \local_stackmathgame\local\service\site_check::mathjax_external_host();
if ($mathjaxhost !== null) {
    echo $OUTPUT->notification(
        get_string('mathjaxexternal', 'local_stackmathgame', s($mathjaxhost)),
        \core\output\notification::NOTIFY_WARNING
    );
}
// The flow lives on its own page but is reached from here: the settings are what a teacher
// opens first, and a link they have to be told about is a link that does not exist.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/stackmathgame/flow.php', ['cmid' => $cmid]),
        get_string('flow_heading', 'local_stackmathgame'),
        ['class' => 'btn btn-primary']
    ),
    'mb-3'
);
$form->display();
echo $OUTPUT->footer();
