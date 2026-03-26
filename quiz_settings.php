<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

$quizid = required_param('quizid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cmid);

require_login($course, false, $cm);
require_capability('local/stackmathgame:configurequiz', $context);

$config = \local_stackmathgame\game\quiz_configurator::ensure_default($quizid);
$designs = \local_stackmathgame\game\quiz_configurator::get_available_designs();
$labeloptions = \local_stackmathgame\game\quiz_configurator::get_label_options();

$url = new moodle_url('/local/stackmathgame/quiz_settings.php', ['quizid' => $quizid, 'cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('gamesettings', 'local_stackmathgame'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('admin');

$mform = new \local_stackmathgame\form\quiz_settings_form($url->out(false), [
    'quizid' => $quizid,
    'cmid' => $cmid,
    'config' => $config,
    'designs' => $designs,
    'labeloptions' => $labeloptions,
    'canselectdesign' => has_capability('local/stackmathgame:selectdesign', $context),
    'canmanagelabels' => has_capability('local/stackmathgame:managelabels', $context),
]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/quiz/view.php', ['id' => $cmid]));
}

if ($data = $mform->get_data()) {
    require_sesskey();

    if (!has_capability('local/stackmathgame:selectdesign', $context)) {
        $data->designid = $config->designid;
    }
    if (!has_capability('local/stackmathgame:managelabels', $context)) {
        $data->labelid = $config->labelid;
        $data->newlabel = '';
    }

    \local_stackmathgame\game\quiz_configurator::save_for_quiz($quizid, (int)$USER->id, (array)$data);
    redirect($url, get_string('quizsettingssaved', 'local_stackmathgame'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform->set_data([
    'quizid' => $quizid,
    'cmid' => $cmid,
    'enabled' => (int)$config->enabled,
    'teacherdisplayname' => (string)($config->teacherdisplayname ?? ''),
    'labelid' => (int)$config->labelid,
    'designid' => (int)$config->designid,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gamesettings', 'local_stackmathgame'));
echo $OUTPUT->notification(get_string('quizsettingsintro', 'local_stackmathgame'), \core\output\notification::NOTIFY_INFO);
$mform->display();
echo $OUTPUT->footer();
