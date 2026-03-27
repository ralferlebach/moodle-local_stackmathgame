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
 * Inject the Game Design Studio link into Moodle's standard navbar area.
 *
 * Called automatically by themes that include {{{ output.navbar_plugin_output }}}.
 *
 * @param \renderer_base $renderer The page renderer.
 * @return string HTML to inject, or empty string if the user lacks capability.
 */
function local_stackmathgame_render_navbar_output(\renderer_base $renderer): string {
    if (during_initial_install() || !isloggedin() || isguestuser()) {
        return '';
    }
    $syscontext = \context_system::instance();
    if (
        !has_capability('local/stackmathgame:viewstudio', $syscontext)
        && !has_capability('local/stackmathgame:managethemes', $syscontext)
    ) {
        return '';
    }
    $url = new moodle_url('/local/stackmathgame/studio.php');
    $title = get_string('studio_title', 'local_stackmathgame');
    $iconhtml = \html_writer::tag('i', '', [
        'class' => 'fa fa-gamepad fa-fw icon',
        'aria-hidden' => 'true',
        'title' => $title,
    ]);
    $linkhtml = \html_writer::link($url, $iconhtml . \html_writer::span($title, 'sr-only'), [
        'class' => 'nav-link smg-studio-nav-link',
        'title' => $title,
        'aria-label' => $title,
    ]);
    return \html_writer::div($linkhtml, 'd-flex align-items-center smg-studio-nav-wrapper');
}

/**
 * Add quiz-level game settings link to the settings navigation tree.
 *
 * The tertiary navigation dropdown injection is handled separately via the
 * before_http_headers hook in classes/hook/output_hooks.php, which is more
 * reliable than doing it here.
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
}
