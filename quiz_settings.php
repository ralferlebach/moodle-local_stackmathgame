<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$quizid = required_param('quizid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cmid);

require_login($course, false, $cm);
require_capability('local/stackmathgame:configurequiz', $context);

$PAGE->set_url(new moodle_url('/local/stackmathgame/quiz_settings.php', ['quizid' => $quizid, 'cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('gamesettings', 'local_stackmathgame'));
$PAGE->set_heading(format_string($course->fullname));

$config = \local_stackmathgame\game\quiz_configurator::ensure_default($quizid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gamesettings', 'local_stackmathgame'));
echo html_writer::div(
    s(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
    'alert alert-secondary'
);
echo $OUTPUT->footer();
