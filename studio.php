<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$systemcontext = context_system::instance();
require_capability('local/stackmathgame:viewstudio', $systemcontext);

$action = optional_param('action', 'overview', PARAM_ALPHA);
$designid = optional_param('id', 0, PARAM_INT);

$pageparams = ['action' => $action];
if ($designid) {
    $pageparams['id'] = $designid;
}
$PAGE->set_url(new moodle_url('/local/stackmathgame/studio.php', $pageparams));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('studio_title', 'local_stackmathgame'));
$PAGE->set_heading(get_string('studio_title', 'local_stackmathgame'));

$caps = [
    'viewstudio' => has_capability('local/stackmathgame:viewstudio', $systemcontext),
    'managethemes' => has_capability('local/stackmathgame:managethemes', $systemcontext),
    'managenarratives' => has_capability('local/stackmathgame:managenarratives', $systemcontext),
    'manageassets' => has_capability('local/stackmathgame:manageassets', $systemcontext),
    'managemechanics' => has_capability('local/stackmathgame:managemechanics', $systemcontext),
];

$renderer = $PAGE->get_renderer('local_stackmathgame');

if ($action === 'export') {
    require_sesskey();
    require_capability('local/stackmathgame:managethemes', $systemcontext);
    $manifest = \local_stackmathgame\studio\theme_manager_studio::export_design_package($designid);
    if (!$manifest) {
        throw new moodle_exception('invalidrecord');
    }
    $filename = clean_filename(($manifest['slug'] ?? 'design') . '.json');
    send_file(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $filename, 0, 0, true, true, 'application/json');
}

$editform = null;
$importform = null;

if ($action === 'edit') {
    require_capability('local/stackmathgame:managethemes', $systemcontext);
    $design = \local_stackmathgame\studio\theme_manager_studio::get_design_for_form($designid ?: null);
    $design->thumbnaildraftid = \local_stackmathgame\studio\theme_manager_studio::prepare_thumbnail_draft($design);
    $editform = new \local_stackmathgame\form\studio\design_edit_form(null, [
        'design' => $design,
        'caps' => $caps,
        'modeoptions' => \local_stackmathgame\studio\theme_manager_studio::get_mode_options(),
    ]);
    $editform->set_data($design);
    if ($editform->is_cancelled()) {
        redirect(new moodle_url('/local/stackmathgame/studio.php', ['action' => 'overview']));
    }
    if ($data = $editform->get_data()) {
        $newid = \local_stackmathgame\studio\theme_manager_studio::save_from_form($data);
        redirect(new moodle_url('/local/stackmathgame/studio.php', ['action' => 'edit', 'id' => $newid]), get_string('studiosavedesign', 'local_stackmathgame'));
    }
}

if ($action === 'import') {
    require_capability('local/stackmathgame:manageassets', $systemcontext);
    $importform = new \local_stackmathgame\form\studio\design_import_form();
    if ($importform->is_cancelled()) {
        redirect(new moodle_url('/local/stackmathgame/studio.php', ['action' => 'overview']));
    }
    if ($data = $importform->get_data()) {
        $result = \local_stackmathgame\studio\theme_importer::process_upload((int)$data->importzip);
        if (!$result['success']) {
            redirect(new moodle_url('/local/stackmathgame/studio.php', ['action' => 'import']), $result['error'], null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect(new moodle_url('/local/stackmathgame/studio.php', ['action' => 'edit', 'id' => $result['themeid']]), get_string('studioimportsuccess', 'local_stackmathgame'));
    }
}

$designs = \local_stackmathgame\studio\theme_manager_studio::list_designs_for_gallery($caps['managethemes']);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studio_title', 'local_stackmathgame'));
echo $renderer->studio_tabs($action, $caps, $designid ?: null);
echo $renderer->studio_intro($caps);

if ($action === 'edit') {
    echo $OUTPUT->heading(get_string('studio_edit_heading', 'local_stackmathgame'), 3);
    $editform->display();
    echo html_writer::tag('h4', get_string('studio_schema_preview', 'local_stackmathgame'));
    echo html_writer::tag('pre', s(json_encode([
        'narrative' => \local_stackmathgame\studio\theme_manager_studio::get_default_narrative_schema(),
        'mechanics' => \local_stackmathgame\studio\theme_manager_studio::get_default_mechanics_schema(),
        'assets' => \local_stackmathgame\studio\theme_manager_studio::get_default_asset_manifest_schema(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), ['class' => 'mt-3']);
} else if ($action === 'import') {
    echo $OUTPUT->heading(get_string('studio_import_heading', 'local_stackmathgame'), 3);
    $importform->display();
} else {
    echo $OUTPUT->heading(get_string('studio_gallery_heading', 'local_stackmathgame'), 3);
    echo $renderer->design_gallery($designs);
}

echo $OUTPUT->footer();
