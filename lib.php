<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Library functions and Moodle hook callbacks for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject the game AMD module on quiz attempt pages where the plugin is enabled.
 *
 * Called by Moodle's output system. Registered via hooks (hooks.php).
 * For Moodle 4.5 we use the hook system (\core\hook\output\before_standard_head_html_generation).
 */
function local_stackmathgame_before_standard_html_head(): string {
    global $PAGE, $CFG, $USER;

    // Only act on quiz attempt pages.
    if ($PAGE->pagetype !== 'mod-quiz-attempt') {
        return '';
    }

    // Resolve quiz ID from the current CM.
    $cm = $PAGE->cm;
    if (!$cm || $cm->modname !== 'quiz') {
        return '';
    }

    // Check if this quiz has the plugin enabled.
    $config = \local_stackmathgame\game\quiz_configurator::get_plugin_config($cm->instance);
    if (!$config || empty($config->enabled)) {
        return '';
    }

    // Build the JS init config payload.
    $labelid = (int) ($config->labelid ?? 0);
    if ($labelid < 1) {
        return ''; // No label assigned → do not activate.
    }

    // Resolve theme asset base URL.
    $themeid  = (int) ($config->themeid ?? 0);
    $themeurl = local_stackmathgame_get_theme_asset_url($themeid);

    $jsconfig = [
        'labelid'       => $labelid,
        'quizid'        => (int) $cm->instance,
        'cmid'          => (int) $cm->id,
        'userid'        => (int) $USER->id,
        'sesskey'       => sesskey(),
        'wwwroot'       => $CFG->wwwroot,
        'themeAssetUrl' => $themeurl,
    ];

    // Queue the AMD module initialisation.
    $PAGE->requires->js_call_amd(
        'local_stackmathgame/fantasy_quiz',
        'init',
        [$jsconfig]
    );

    return '';
}

/**
 * Returns the base URL for theme assets.
 * Falls back to the built-in 'fantasy' theme if themeid is 0 or invalid.
 */
function local_stackmathgame_get_theme_asset_url(int $themeid): string {
    global $CFG;

    if ($themeid > 0) {
        $theme = \local_stackmathgame\game\theme_manager::get_theme($themeid);
        if ($theme && !empty($theme->shortname)) {
            return (string) new \moodle_url(
                '/local/stackmathgame/pix/themes/' . $theme->shortname . '/'
            );
        }
    }

    // Default: built-in fantasy theme.
    return (string) new \moodle_url('/local/stackmathgame/pix/themes/fantasy/');
}

/**
 * Add a "Game Settings" link to the quiz settings navigation.
 *
 * Moodle 4.x: extend the settings navigation tree for mod_quiz.
 *
 * @param \settings_navigation $settingsnav
 * @param \context $context
 */
function local_stackmathgame_extend_settings_navigation(
    \settings_navigation $settingsnav,
    \context $context
): void {
    global $PAGE;

    if ($PAGE->cm && $PAGE->cm->modname === 'quiz') {
        $quiznode = $settingsnav->find('modulesettings', \settings_navigation::TYPE_SETTING);
        if ($quiznode) {
            $url = new \moodle_url(
                '/local/stackmathgame/quiz_settings.php',
                ['quizid' => $PAGE->cm->instance, 'cmid' => $PAGE->cm->id]
            );
            $quiznode->add(
                get_string('gamesettings', 'local_stackmathgame'),
                $url,
                \settings_navigation::TYPE_SETTING,
                null,
                'stackmathgame_settings',
                new \pix_icon('i/settings', '')
            );
        }
    }
}
