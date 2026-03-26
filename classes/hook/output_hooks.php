<?php
namespace local_stackmathgame\hook;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;

/**
 * Output hook callbacks.
 */
class output_hooks {
    public static function inject_studio_icon(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $OUTPUT;

        if (!isloggedin() || isguestuser() || !has_capability('local/stackmathgame:viewstudio', \context_system::instance())) {
            return;
        }

        $url = new \moodle_url('/local/stackmathgame/studio.php');
        $icon = new \pix_icon('i/settings', get_string('studio_title', 'local_stackmathgame'));
        $html = \html_writer::link(
            $url,
            $OUTPUT->render($icon),
            [
                'class' => 'smg-studio-link',
                'title' => get_string('studio_title', 'local_stackmathgame'),
                'aria-label' => get_string('studio_title', 'local_stackmathgame'),
            ]
        );

        $hook->add_html(\html_writer::div($html, 'smg-studio-link-wrapper'));
    }

    public static function inject_game_assets(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $CFG, $USER;

        if ($PAGE->pagetype !== 'mod-quiz-attempt' || empty($PAGE->cm) || $PAGE->cm->modname !== 'quiz') {
            return;
        }

        $config = quiz_configurator::get_plugin_config((int)$PAGE->cm->instance);
        if (!$config || empty($config->enabled)) {
            return;
        }

        $themeid = (int)($config->designid ?? 0);
        $theme = $themeid > 0 ? theme_manager::get_theme($themeid) : null;
        $themeurl = theme_manager::asset_base_url($theme && !empty($theme->slug) ? $theme->slug : 'fantasy');

        $PAGE->requires->strings_for_js([
            'nextquestion', 'finishpractice', 'checkanswerhidden', 'gamestatusready'
        ], 'local_stackmathgame');
        $PAGE->requires->js_call_amd('local_stackmathgame/fantasy_quiz', 'init', [[
            'quizid' => (int)$PAGE->cm->instance,
            'cmid' => (int)$PAGE->cm->id,
            'userid' => (int)$USER->id,
            'labelid' => (int)($config->labelid ?? 0),
            'sesskey' => sesskey(),
            'wwwroot' => $CFG->wwwroot,
            'themeAssetUrl' => $themeurl,
            'config' => json_decode((string)($config->configjson ?? '{}'), true) ?: [],
        ]]);
    }
}
