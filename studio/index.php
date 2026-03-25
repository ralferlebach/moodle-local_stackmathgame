<?php
/**
 * GameDesign Studio — main entry point.
 *
 * Accessible at /local/stackmathgame/studio/index.php
 * Requires: local/stackmathgame:managethemes
 *
 * Layout:
 *   ┌─────────────────────────────────────────────────┐
 *   │  🎨 GameDesign Studio                [+ New]    │
 *   ├─────────────────────────────────────────────────┤
 *   │  [Fantasy Card]  [Sci-Fi Card]  [Dungeon Card]  │
 *   │  ▶ Edit          ▶ Edit          ▶ Edit          │
 *   │  ↓ Export ZIP    ↓ Export ZIP    ↓ Export ZIP    │
 *   ├─────────────────────────────────────────────────┤
 *   │  [↑ Import ZIP]                                 │
 *   └─────────────────────────────────────────────────┘
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$PAGE->set_context(\context_system::instance());
$PAGE->set_url(new \moodle_url('/local/stackmathgame/studio/index.php'));
$PAGE->set_title(get_string('studio_title', 'local_stackmathgame'));
$PAGE->set_heading(get_string('studio_title', 'local_stackmathgame'));
$PAGE->set_pagelayout('admin');

require_login();
require_capability('local/stackmathgame:managethemes', \context_system::instance());

// Handle ZIP import action.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'import') {
    require_sesskey();
    $result = \local_stackmathgame\studio\theme_importer::process_upload();
    if ($result['success']) {
        \core\notification::success(get_string('theme_imported', 'local_stackmathgame'));
    } else {
        \core\notification::error($result['error']);
    }
    redirect(new \moodle_url('/local/stackmathgame/studio/index.php'));
}

// Handle theme toggle (enable/disable).
if ($action === 'toggle') {
    require_sesskey();
    $themeid = required_param('themeid', PARAM_INT);
    \local_stackmathgame\studio\theme_manager_studio::toggle_enabled($themeid);
    redirect(new \moodle_url('/local/stackmathgame/studio/index.php'));
}

// Fetch all themes.
$themes   = \local_stackmathgame\game\theme_manager::get_all_enabled();
$allthemes = $DB->get_records('local_stackmathgame_theme', null, 'sortorder ASC');

// Build template data.
$themecards = [];
foreach ($allthemes as $theme) {
    $cfg  = json_decode($theme->configjson, true) ?? [];
    $base = \local_stackmathgame\game\theme_manager::asset_base_url($theme->shortname);

    $themecards[] = [
        'id'            => (int) $theme->id,
        'shortname'     => $theme->shortname,
        'name'          => format_string($theme->name),
        'description'   => format_string($cfg['description'] ?? ''),
        'thumbnail_url' => $base . ($cfg['thumbnail'] ?? 'thumbnail.png'),
        'enabled'       => (bool) $theme->enabled,
        'is_builtin'    => in_array($theme->shortname, ['fantasy'], true),
        'edit_url'      => (new \moodle_url('/local/stackmathgame/studio/edit_theme.php',
                              ['themeid' => $theme->id]))->out(false),
        'export_url'    => (new \moodle_url('/local/stackmathgame/studio/export_theme.php',
                              ['themeid' => $theme->id, 'sesskey' => sesskey()]))->out(false),
        'toggle_url'    => (new \moodle_url('/local/stackmathgame/studio/index.php',
                              ['action' => 'toggle', 'themeid' => $theme->id,
                               'sesskey' => sesskey()]))->out(false),
    ];
}

$templatedata = [
    'themes'        => $themecards,
    'new_url'       => (new \moodle_url('/local/stackmathgame/studio/edit_theme.php'))->out(false),
    'import_url'    => (new \moodle_url('/local/stackmathgame/studio/import_theme.php'))->out(false),
    'has_themes'    => !empty($themecards),
    'sesskey'       => sesskey(),
    'studio_strings' => [
        'edit'      => get_string('theme_edit',    'local_stackmathgame'),
        'export'    => get_string('theme_export',  'local_stackmathgame'),
        'import'    => get_string('theme_import',  'local_stackmathgame'),
        'new_theme' => get_string('theme_new',     'local_stackmathgame'),
        'enabled'   => get_string('enabled',       'local_stackmathgame'),
        'disabled'  => get_string('disabled',      'local_stackmathgame'),
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_stackmathgame/studio_index', $templatedata);
echo $OUTPUT->footer();
