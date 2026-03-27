<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Game Design Studio entry page.
 *
 * Fixed: was a raw JSON dump. Replaced with a functional page that handles
 * the gallery (overview), design editing, and ZIP import actions.
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$syscontext = context_system::instance();
require_capability('local/stackmathgame:viewstudio', $syscontext);

// Capabilities per section.
$caps = [
    'managethemes'    => has_capability('local/stackmathgame:managethemes',    $syscontext),
    'managenarratives'=> has_capability('local/stackmathgame:managenarratives', $syscontext),
    'manageassets'    => has_capability('local/stackmathgame:manageassets',     $syscontext),
    'managemechanics' => has_capability('local/stackmathgame:managemechanics',  $syscontext),
];

$action   = optional_param('action', 'overview', PARAM_ALPHA);
$designid = optional_param('id',     0,          PARAM_INT);

$PAGE->set_url(new moodle_url('/local/stackmathgame/studio.php', ['action' => $action]));
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('studio_title', 'local_stackmathgame'));
$PAGE->set_heading(get_string('studio_title', 'local_stackmathgame'));

/** @var \local_stackmathgame\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_stackmathgame');

// ------------------------------------------------------------------
// Action: export a single design as JSON (minimal export stub)
// ------------------------------------------------------------------
if ($action === 'export' && $designid > 0) {
    require_sesskey();
    require_capability('local/stackmathgame:managethemes', $syscontext);

    $design = \local_stackmathgame\studio\theme_manager_studio::export_one($designid);
    if (!$design) {
        redirect(new moodle_url('/local/stackmathgame/studio.php'), 'Design not found.', null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $json     = json_encode($design, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $filename = 'smg_design_' . clean_param((string)($design['slug'] ?? 'export'), PARAM_ALPHANUMEXT) . '.json';

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

// ------------------------------------------------------------------
// Action: edit / create a design
// ------------------------------------------------------------------
if ($action === 'edit') {
    require_capability('local/stackmathgame:managethemes', $syscontext);

    // Build mode component options.
    $modeoptions = [];
    $allplugins  = \core_component::get_plugin_list('stackmathgamemode');
    foreach (array_keys($allplugins) as $modekey) {
        $component = 'stackmathgamemode_' . $modekey;
        $modeoptions[$component] = $component;
    }
    if (empty($modeoptions)) {
        // Fallback if no subplugins are detected yet.
        $modeoptions = [
            'stackmathgamemode_rpg'         => 'stackmathgamemode_rpg',
            'stackmathgamemode_exitgames'    => 'stackmathgamemode_exitgames',
            'stackmathgamemode_wisewizzard'  => 'stackmathgamemode_wisewizzard',
        ];
    }

    $existingdesign = null;
    if ($designid > 0) {
        $existingdesign = (object)(\local_stackmathgame\studio\theme_manager_studio::export_one($designid) ?? []);
    }

    $formcustomdata = [
        'design'      => $existingdesign,
        'caps'        => $caps,
        'modeoptions' => $modeoptions,
    ];

    $form = new \local_stackmathgame\form\studio\design_edit_form(null, $formcustomdata);
    $returnurl = new moodle_url('/local/stackmathgame/studio.php');

    if ($form->is_cancelled()) {
        redirect($returnurl);
    }

    if ($data = $form->get_data()) {
        $savedid = \local_stackmathgame\studio\theme_manager_studio::save_from_form((array)$data);
        redirect(
            new moodle_url('/local/stackmathgame/studio.php', ['action' => 'edit', 'id' => $savedid]),
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Populate form defaults from existing record.
    if ($existingdesign && $existingdesign->id) {
        $form->set_data((array)$existingdesign);
    }

    echo $OUTPUT->header();
    echo $renderer->studio_tabs('edit', $caps, $designid ?: null);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// ------------------------------------------------------------------
// Action: import a design ZIP
// ------------------------------------------------------------------
if ($action === 'import') {
    require_capability('local/stackmathgame:manageassets', $syscontext);

    $form = new \local_stackmathgame\form\studio\design_import_form();
    $returnurl = new moodle_url('/local/stackmathgame/studio.php');

    if ($form->is_cancelled()) {
        redirect($returnurl);
    }

    if ($form->is_submitted() && $form->is_validated()) {
        $result = \local_stackmathgame\studio\theme_importer::process_upload();
        if ($result['success']) {
            redirect(
                new moodle_url('/local/stackmathgame/studio.php', [
                    'action' => 'edit',
                    'id'     => $result['designid'],
                ]),
                get_string('changessaved'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            $form->set_data([]);
            echo $OUTPUT->header();
            echo $renderer->studio_tabs('import', $caps);
            echo $OUTPUT->notification($result['error'], \core\output\notification::NOTIFY_ERROR);
            $form->display();
            echo $OUTPUT->footer();
            exit;
        }
    }

    echo $OUTPUT->header();
    echo $renderer->studio_tabs('import', $caps);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// ------------------------------------------------------------------
// Default: overview / gallery
// ------------------------------------------------------------------
$designs = \local_stackmathgame\studio\theme_manager_studio::export_all();

echo $OUTPUT->header();
echo $renderer->studio_tabs('overview', $caps);
echo $renderer->studio_intro($caps);
echo $renderer->design_gallery($designs);

if ($caps['managethemes']) {
    $newurl = new moodle_url('/local/stackmathgame/studio.php', ['action' => 'edit']);
    echo html_writer::div(
        html_writer::link($newurl, get_string('addnewdesign', 'local_stackmathgame'), [
            'class' => 'btn btn-secondary mt-3',
        ]),
        'mt-3'
    );
}

echo $OUTPUT->footer();
