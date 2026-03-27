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
 * Output hook callbacks for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\hook;

use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;

/**
 * Output hook callbacks.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_hooks {
    /**
     * Inject a studio link icon into the page header area.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook The hook instance.
     * @return void
     */
    public static function inject_studio_icon(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE, $OUTPUT;

        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        if (empty($OUTPUT) || !method_exists($OUTPUT, 'render')) {
            return;
        }

        $syscontext = \context_system::instance();
        if (
            !self::safe_has_capability('local/stackmathgame:viewstudio', $syscontext)
            && !self::safe_has_capability('local/stackmathgame:managethemes', $syscontext)
        ) {
            return;
        }

        $url  = new \moodle_url('/local/stackmathgame/studio.php');
        $icon = new \pix_icon('i/settings', get_string('studio_title', 'local_stackmathgame'));
        try {
            $iconhtml = $OUTPUT->render($icon);
        } catch (\Throwable $e) {
            debugging('SMG: Could not render studio icon: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }
        $html = \html_writer::link($url, $iconhtml, [
            'class' => 'smg-studio-link btn btn-sm btn-outline-secondary ms-2',
            'title' => get_string('studio_title', 'local_stackmathgame'),
            'aria-label' => get_string('studio_title', 'local_stackmathgame'),
            'style' => 'position:fixed;top:8px;right:8px;z-index:9000;',
        ]);
        $hook->add_html(\html_writer::div($html, 'smg-studio-link-wrapper'));
    }

    /**
     * Inject game assets into quiz attempt pages.
     *
     * @param \core\hook\output\before_http_headers $hook The hook instance.
     * @return void
     */
    public static function inject_game_assets(
        \core\hook\output\before_http_headers $hook
    ): void {
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

        $design   = theme_manager::get_theme((int)$config->designid);
        $themeurl = theme_manager::asset_base_url($design ? (string)$design->slug : 'shared');

        $PAGE->requires->strings_for_js(
            ['nextquestion', 'finishpractice', 'checkanswerhidden', 'gamestatusready'],
            'local_stackmathgame'
        );
        $PAGE->requires->js_call_amd('local_stackmathgame/fantasy_quiz', 'init', [[
            'quizid'       => (int)$PAGE->cm->instance,
            'cmid'         => (int)$PAGE->cm->id,
            'userid'       => (int)$USER->id,
            'labelid'      => (int)($config->labelid ?? 0),
            'designid'     => (int)($config->designid ?? 0),
            'sesskey'      => sesskey(),
            'wwwroot'      => $CFG->wwwroot,
            'themeAssetUrl' => $themeurl,
            'config'       => json_decode((string)($config->configjson ?? '{}'), true) ?: [],
        ]]);
    }

    /**
     * Check a capability safely, returning false instead of throwing.
     *
     * @param string $capability The capability to check.
     * @param \context $context The context to check against.
     * @return bool Whether the current user has the capability.
     */
    private static function safe_has_capability(string $capability, \context $context): bool {
        global $USER;
        if (empty($USER->id)) {
            return false;
        }
        return has_capability($capability, $context);
    }
}
