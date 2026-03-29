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
 * Tertiary nav injection:
 *   Uses before_http_headers with optional_param('cmid') from the URL.
 *   On quiz management pages (edit.php, view.php etc.) $PAGE->cm is NOT yet
 *   populated when before_http_headers fires. The same technique is used by
 *   local_stackmatheditor: read cmid from the URL, verify the CM via a DB
 *   lookup, then call js_call_amd. This outputs the AMD require() call into
 *   the page HTML so tertiary_nav.js runs and injects the dropdown option.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_hooks {
    /**
     * Inject Game Settings option into the quiz tertiary navigation dropdown.
     *
     * Reads cmid from the URL (not from $PAGE->cm which is null at this point).
     * Performs a lightweight DB lookup to confirm the CM is a quiz and check
     * the configurequiz capability before emitting the js_call_amd call.
     *
     * @param \core\hook\output\before_http_headers $hook The hook instance.
     * @return void
     */
    public static function inject_tertiary_nav(
        \core\hook\output\before_http_headers $hook
    ): void {
        global $PAGE;

        if (during_initial_install()) {
            return;
        }

        // Get cmid from the request URL (not from $PAGE->cm – it is not set yet).
        $cmid = optional_param('cmid', 0, PARAM_INT);
        if ($cmid <= 0) {
            return;
        }

        // Exclude attempt pages – they use inject_game_assets instead.
        if ((string)($PAGE->pagetype ?? '') === 'mod-quiz-attempt') {
            return;
        }

        // Verify this cmid belongs to a quiz (fast single-row DB lookup).
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        // Capability check.
        $context = \context_module::instance($cmid);
        if (!has_capability('local/stackmathgame:configurequiz', $context)) {
            return;
        }

        $url = new \moodle_url('/local/stackmathgame/quiz_settings.php', [
            'cmid' => $cmid,
        ]);

        $PAGE->requires->js_call_amd('local_stackmathgame/tertiary_nav', 'init', [[
            'cmid' => $cmid,
            'label' => get_string('gamesettings', 'local_stackmathgame'),
            'url' => $url->out(false),
        ]]);
    }

    /**
     * Inject game assets into quiz attempt pages.
     *
     * Fires via before_http_headers. The pagetype 'mod-quiz-attempt' is set
     * reliably on attempt pages.
     *
     * @param \core\hook\output\before_http_headers $hook The hook instance.
     * @return void
     */
    public static function inject_game_assets(
        \core\hook\output\before_http_headers $hook
    ): void {
        global $PAGE, $CFG, $USER;

        if (during_initial_install()) {
            return;
        }

        // Detect quiz attempt pages via SCRIPT_FILENAME rather than $PAGE->pagetype,
        // because pagetype may not be set yet when before_http_headers fires.
        $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($script !== 'attempt.php') {
            return;
        }

        // Resolve the activity via cmid from the request first. On attempt pages
        // $PAGE->cm is not reliable yet at before_http_headers time.
        $cmid = optional_param('cmid', 0, PARAM_INT);
        if ($cmid <= 0 && !empty($PAGE->cm)) {
            $cmid = (int)$PAGE->cm->id;
        }
        if ($cmid <= 0) {
            return;
        }

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        // Also accept 'mod-quiz-attempt' when pagetype IS set (belt and braces).
        if (
            !empty($PAGE->pagetype)
            && $PAGE->pagetype !== 'mod-quiz-attempt'
            && $PAGE->pagetype !== 'mod-quiz-startattempt'
        ) {
            return;
        }

        $context = \context_module::instance($cmid);
        if (!has_capability('local/stackmathgame:play', $context)) {
            return;
        }

        $quizid = (int)$cm->instance;

        // Cmid is the source of truth for config lookups; quizid is still passed
        // to the frontend because the current web-service contract is quiz-based.
        $config = quiz_configurator::ensure_default($cmid);
        if (!$config || empty($config->enabled)) {
            return;
        }

        $design = theme_manager::get_theme((int)$config->designid);
        $themeurl = theme_manager::asset_base_url($design ? (string)$design->slug : 'shared');

        $PAGE->requires->strings_for_js(
            ['nextquestion', 'finishpractice', 'checkanswerhidden', 'gamestatusready'],
            'local_stackmathgame'
        );
        $PAGE->requires->js_call_amd('local_stackmathgame/game_engine', 'init', [[
            'cmid' => $cmid,
            'quizid' => $quizid,
            'instanceid' => $quizid,
            'modname' => 'quiz',
            'userid' => (int)$USER->id,
            'labelid' => (int)($config->labelid ?? 0),
            'designid' => (int)($config->designid ?? 0),
            'sesskey' => sesskey(),
            'wwwroot' => $CFG->wwwroot,
            'themeAssetUrl' => $themeurl,
            'config' => json_decode((string)($config->configjson ?? '{}'), true) ?: [],
        ]]);
    }

}
