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
        global $OUTPUT, $CFG;

        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }
        if (!isloggedin() || isguestuser()) {
            return;
        }
        if (!function_exists('get_capability_info')) {
            return;
        }

        $systemcontext = \context_system::instance();
        $canviewstudio = get_capability_info('local/stackmathgame:viewstudio') && has_capability('local/stackmathgame:viewstudio', $systemcontext);
        $canmanagethemes = get_capability_info('local/stackmathgame:managethemes') && has_capability('local/stackmathgame:managethemes', $systemcontext);
        if (!$canviewstudio && !$canmanagethemes) {
            return;
        }

        $url = new \moodle_url('/local/stackmathgame/studio.php');
        $icon = new \pix_icon('i/settings', get_string('studio_title', 'local_stackmathgame'));
        $html = \html_writer::link(
            $url,
            $OUTPUT->render($icon),
            ['class' => 'btn btn-link local-stackmathgame-studio-icon', 'title' => get_string('studio_title', 'local_stackmathgame')]
        );
        $hook->add_html($html);
    }

    public static function inject_game_assets(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $USER, $CFG;

        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }
        if (empty($PAGE) || empty($PAGE->cm) || empty($PAGE->cm->modname) || $PAGE->cm->modname !== 'quiz') {
            return;
        }
        if (empty($PAGE->context) || !function_exists('get_capability_info') || !get_capability_info('local/stackmathgame:play') || !has_capability('local/stackmathgame:play', $PAGE->context)) {
            return;
        }

        $config = quiz_configurator::ensure_default((int)$PAGE->cm->instance);
        if (empty($config->enabled)) {
            return;
        }

        $themeurl = '';
        if (!empty($config->designid)) {
            $theme = theme_manager::get_theme((int)$config->designid);
            if ($theme && !empty($theme->slug)) {
                $themeurl = theme_manager::asset_base_url((string)$theme->slug);
            }
        }

        $PAGE->requires->strings_for_js(['submitanswerplaceholder'], 'local_stackmathgame');
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
