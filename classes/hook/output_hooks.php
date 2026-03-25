<?php
namespace local_stackmathgame\hook;

/**
 * Output hook callbacks.
 *
 * @package    local_stackmathgame
 */
class output_hooks {

    /**
     * Inject the GameDesign Studio icon into the top navigation bar.
     * Only shown to users with local/stackmathgame:managethemes.
     *
     * The icon appears as a small palette/brush icon next to the admin gear,
     * linking to /local/stackmathgame/studio/index.php.
     */
    public static function inject_studio_icon(
        \core\hook\output\before_standard_top_content_html $hook
    ): void {
        global $PAGE, $OUTPUT;

        // Only for logged-in users with the capability.
        if (!isloggedin() || isguestuser()) {
            return;
        }

        $syscontext = \context_system::instance();
        if (!has_capability('local/stackmathgame:managethemes', $syscontext)) {
            return;
        }

        $studiourl = new \moodle_url('/local/stackmathgame/studio/index.php');
        $iconhtml  = $OUTPUT->pix_icon(
            'i/palette',          // Use Moodle's built-in palette icon; replace with custom pix if desired.
            get_string('studio_title', 'local_stackmathgame'),
            'local_stackmathgame' // component for custom icon lookup
        );

        $linkhtml = \html_writer::link(
            $studiourl,
            $iconhtml,
            [
                'class' => 'smg-studio-nav-icon nav-link',
                'title' => get_string('studio_title', 'local_stackmathgame'),
                'aria-label' => get_string('studio_title', 'local_stackmathgame'),
            ]
        );

        // Append to the hook's top content.
        $hook->add_html($linkhtml);
    }

    /**
     * Inject game AMD module on quiz attempt pages.
     * (Moved from lib.php before_standard_html_head for cleaner hook-based approach.)
     */
    public static function inject_game_assets(
        \core\hook\output\before_http_headers $hook
    ): void {
        global $PAGE, $CFG, $USER;

        if ($PAGE->pagetype !== 'mod-quiz-attempt') {
            return;
        }

        $cm = $PAGE->cm;
        if (!$cm || $cm->modname !== 'quiz') {
            return;
        }

        $config = \local_stackmathgame\game\quiz_configurator::get_plugin_config((int) $cm->instance);
        if (!$config || empty($config->enabled)) {
            return;
        }

        $labelid = (int) ($config->labelid ?? 0);
        if ($labelid < 1) {
            return;
        }

        $themeid  = (int) ($config->themeid ?? 0);
        $theme    = \local_stackmathgame\game\theme_manager::get_theme($themeid);
        $themeurl = \local_stackmathgame\game\theme_manager::asset_base_url(
            $theme ? $theme->shortname : 'fantasy'
        );

        $PAGE->requires->js_call_amd('local_stackmathgame/fantasy_quiz', 'init', [[
            'labelid'       => $labelid,
            'quizid'        => (int) $cm->instance,
            'cmid'          => (int) $cm->id,
            'userid'        => (int) $USER->id,
            'sesskey'       => sesskey(),
            'wwwroot'       => $CFG->wwwroot,
            'themeAssetUrl' => $themeurl,
        ]]);
    }
}
