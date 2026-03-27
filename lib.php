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
 * Library functions for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a quiz-level settings link and inject the tertiary navigation option.
 *
 * @param settings_navigation $settingsnav The settings navigation tree.
 * @param context $context The current context.
 * @return void
 */
function local_stackmathgame_extend_settings_navigation(
    settings_navigation $settingsnav,
    context $context
): void {
    global $PAGE;

    if (empty($PAGE->cm) || $PAGE->cm->modname !== 'quiz') {
        return;
    }

    if (!has_capability('local/stackmathgame:configurequiz', $context)) {
        return;
    }

    $modulesettings = $settingsnav->find('modulesettings', settings_navigation::TYPE_SETTING);
    if (!$modulesettings) {
        return;
    }

    $url = new moodle_url('/local/stackmathgame/quiz_settings.php', [
        'cmid' => (int)$PAGE->cm->id,
    ]);

    $modulesettings->add(
        get_string('gamesettings', 'local_stackmathgame'),
        $url,
        settings_navigation::TYPE_SETTING,
        null,
        'local_stackmathgame_settings',
        new pix_icon('i/settings', '')
    );

    // Inject an option into the quiz tertiary navigation selector.
    $PAGE->requires->js_call_amd('local_stackmathgame/tertiary_nav', 'init', [[
        'cmid' => (int)$PAGE->cm->id,
        'label' => get_string('gamesettings', 'local_stackmathgame'),
        'url' => $url->out(false),
    ]]);
}
