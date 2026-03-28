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
 * Game Design Studio entry page for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$syscontext = context_system::instance();
require_capability('local/stackmathgame:viewstudio', $syscontext);

$caps = [
    'managethemes'     => has_capability('local/stackmathgame:managethemes', $syscontext),
    'managenarratives' => has_capability('local/stackmathgame:managenarratives', $syscontext),
    'manageassets'     => has_capability('local/stackmathgame:manageassets', $syscontext),
    'managemechanics'  => has_capability('local/stackmathgame:managemechanics', $syscontext),
];

$action   = optional_param('action', 'overview', PARAM_ALPHA);
$designid = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/stackmathgame/studio.php', ['action' => $action]));
$PAGE->set_context($syscontext);
$PAGE->set_title(get_string('studio_title', 'local_stackmathgame'));
$PAGE->set_heading(get_string('studio_title', 'local_stackmathgame'));

/** @var \local_stackmathgame\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_stackmathgame');

// Export action: download design as JSON.
if ($action === 'export' && $designid > 0) {
    require_sesskey();
    require_capability('local/stackmathgame:managethemes', $syscontext);

    $zipcontent = \local_stackmathgame\studio\design_exporter::build_zip($designid);
    if ($zipcontent === null) {
        redirect(
            new moodle_url('/local/stackmathgame/studio.php'),
            get_string('errordesignnotfound', 'local_stackmathgame'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $design   = \local_stackmathgame\studio\theme_manager_studio::export_one($designid);
    $slug     = (string)($design['slug'] ?? 'design');
    $filename = \local_stackmathgame\studio\design_exporter::get_filename($designid, $slug);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($zipcontent));
    echo $zipcontent;
    exit;
}

// Edit/create action.
if ($action === 'edit') {
    require_capability('local/stackmathgame:managethemes', $syscontext);

    $modeoptions = [];
    $allplugins  = \core_component::get_plugin_list('stackmathgamemode');
    foreach (array_keys($allplugins) as $modekey) {
        $component = 'stackmathgamemode_' . $modekey;
        $modeoptions[$component] = $component;
    }
    if (empty($modeoptions)) {
        $modeoptions = [
            'stackmathgamemode_rpg'        => 'stackmathgamemode_rpg',
            'stackmathgamemode_exitgames'  => 'stackmathgamemode_exitgames',
            'stackmathgamemode_wisewizzard' => 'stackmathgamemode_wisewizzard',
        ];
    }

    $existingdesign = null;
    if ($designid > 0) {
        $data = \local_stackmathgame\studio\theme_manager_studio::export_one($designid);
        $existingdesign = $data ? (object)$data : null;
    }

    $formcustomdata = [
        'design'      => $existingdesign,
        'caps'        => $caps,
        'modeoptions' => $modeoptions,
    ];

    $form      = new \local_stackmathgame\form\studio\design_edit_form(null, $formcustomdata);
    $returnurl = new moodle_url('/local/stackmathgame/studio.php');

    if ($form->is_cancelled()) {
        redirect($returnurl);
    }

    if ($formdata = $form->get_data()) {
        $savedid = \local_stackmathgame\studio\theme_manager_studio::save_from_form((array)$formdata);
        redirect(
            new moodle_url('/local/stackmathgame/studio.php', ['action' => 'edit', 'id' => $savedid]),
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($existingdesign && !empty($existingdesign->id)) {
        $form->set_data((array)$existingdesign);
    }

    echo $OUTPUT->header();
    echo $renderer->studio_tabs('edit', $caps, $designid ?: null);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Import action.
if ($action === 'import') {
    require_capability('local/stackmathgame:manageassets', $syscontext);

    $form      = new \local_stackmathgame\form\studio\design_import_form();
    $returnurl = new moodle_url('/local/stackmathgame/studio.php');

    if ($form->is_cancelled()) {
        redirect($returnurl);
    }

    if ($form->is_submitted() && $form->is_validated()) {
        $result = \local_stackmathgame\studio\theme_importer::process_upload();
        if ($result['success']) {
            redirect(
                new moodle_url(
                    '/local/stackmathgame/studio.php',
                    ['action' => 'edit', 'id' => $result['designid']]
                ),
                get_string('changessaved'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        echo $OUTPUT->header();
        echo $renderer->studio_tabs('import', $caps);
        echo $OUTPUT->notification($result['error'], \core\output\notification::NOTIFY_ERROR);
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    echo $OUTPUT->header();
    echo $renderer->studio_tabs('import', $caps);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Default: gallery overview.
$designs = \local_stackmathgame\studio\theme_manager_studio::export_all();

echo $OUTPUT->header();
echo $renderer->studio_tabs('overview', $caps);
echo $renderer->studio_intro($caps);
echo $renderer->design_gallery($designs);

if ($caps['managethemes']) {
    $newurl = new moodle_url('/local/stackmathgame/studio.php', ['action' => 'edit']);
    echo html_writer::div(
        html_writer::link(
            $newurl,
            get_string('addnewdesign', 'local_stackmathgame'),
            ['class' => 'btn btn-secondary mt-3']
        ),
        'mt-3'
    );
}

echo $OUTPUT->footer();
