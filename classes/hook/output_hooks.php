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

        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        $syscontext = \context_system::instance();
        if (!self::safe_has_capability('local/stackmathgame:viewstudio', $syscontext)
                && !self::safe_has_capability('local/stackmathgame:managethemes', $syscontext)) {
            return;
        }

        $url = new \moodle_url('/local/stackmathgame/studio.php');
        $icon = new \pix_icon('i/settings', get_string('studio_title', 'local_stackmathgame'));
        $html = \html_writer::link($url, $OUTPUT->render($icon), [
            'class' => 'smg-studio-link',
            'title' => get_string('studio_title', 'local_stackmathgame'),
            'aria-label' => get_string('studio_title', 'local_stackmathgame'),
        ]);
        $hook->add_html(\html_writer::div($html, 'smg-studio-link-wrapper'));
    }

    public static function inject_game_assets(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $CFG, $USER;

        if (during_initial_install() || empty($PAGE->cm) || empty($PAGE->pagetype)) {
            return;
        }
        if ($PAGE->pagetype !== 'mod-quiz-attempt' || $PAGE->cm->modname !== 'quiz') {
            return;
        }
        if (!self::safe_has_capability('local/stackmathgame:play', \context_module::instance((int)$PAGE->cm->id))) {
            return;
        }

        $config = quiz_configurator::ensure_default((int)$PAGE->cm->instance);
        if (!$config || empty($config->enabled)) {
            return;
        }

        $design = theme_manager::get_theme((int)$config->designid);
        $themeurl = theme_manager::asset_base_url($design ? (string)$design->slug : 'shared');

        $PAGE->requires->strings_for_js([
            'nextquestion', 'finishpractice', 'checkanswerhidden', 'gamestatusready'
        ], 'local_stackmathgame');
        $PAGE->requires->js_call_amd('local_stackmathgame/fantasy_quiz', 'init', [[
            'quizid' => (int)$PAGE->cm->instance,
            'cmid' => (int)$PAGE->cm->id,
            'userid' => (int)$USER->id,
            'labelid' => (int)($config->labelid ?? 0),
            'designid' => (int)($config->designid ?? 0),
            'sesskey' => sesskey(),
            'wwwroot' => $CFG->wwwroot,
            'themeAssetUrl' => $themeurl,
            'config' => json_decode((string)($config->configjson ?? '{}'), true) ?: [],
        ]]);
    }

    private static function safe_has_capability(string $capability, \context $context): bool {
        global $USER;
        if (empty($USER->id)) {
            return false;
        }
        return has_capability($capability, $context);
    }
}
