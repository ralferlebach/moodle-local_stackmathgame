<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();
require_capability('local/stackmathgame:viewstudio', $context);

$PAGE->set_url(new moodle_url('/local/stackmathgame/studio.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('studio_title', 'local_stackmathgame'));
$PAGE->set_heading(get_string('studio_title', 'local_stackmathgame'));

$themes = \local_stackmathgame\studio\theme_manager_studio::export_all();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studio_title', 'local_stackmathgame'));
echo html_writer::tag('pre', s(json_encode($themes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
echo $OUTPUT->footer();
