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
 * Studio icon: rendered via local_stackmathgame_render_navbar_output() in lib.php.
 * Tertiary nav injection: handled in local_stackmathgame_extend_settings_navigation()
 *   in lib.php, where $PAGE->cm is guaranteed to be populated.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_hooks {
    /**
     * Inject game assets into quiz attempt pages.
     *
     * Fires via before_http_headers. Only active on mod-quiz-attempt pages
     * where pagetype is set and the game layer is enabled for the quiz.
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
        $cmid = (int)$PAGE->cm->id;
        if (!has_capability('local/stackmathgame:play', \context_module::instance($cmid))) {
            return;
        }

        $config = quiz_configurator::ensure_default((int)$PAGE->cm->instance);
        if (!$config || empty($config->enabled)) {
            return;
        }

        $design = theme_manager::get_theme((int)$config->designid);
        $themeurl = theme_manager::asset_base_url($design ? (string)$design->slug : 'shared');

        $PAGE->requires->strings_for_js(
            ['nextquestion', 'finishpractice', 'checkanswerhidden', 'gamestatusready'],
            'local_stackmathgame'
        );
        $PAGE->requires->js_call_amd('local_stackmathgame/fantasy_quiz', 'init', [[
            'quizid' => (int)$PAGE->cm->instance,
            'cmid' => $cmid,
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
