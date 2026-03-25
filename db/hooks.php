<?php
/**
 * Hook registrations for local_stackmathgame.
 *
 * Moodle 4.5 uses a hook system for extension points.
 * https://moodledev.io/docs/4.5/apis/core/hooks
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$callbacks = [

    // Inject the game AMD module on quiz attempt pages.
    [
        'hook'        => \core\hook\output\before_http_headers::class,
        'callback'    => \local_stackmathgame\hook\output_hooks::class . '::inject_game_assets',
        'priority'    => 500,
    ],

    // Inject the GameDesign Studio icon in the top navigation bar
    // for users with local/stackmathgame:managethemes capability.
    [
        'hook'        => \core\hook\output\before_standard_top_content_html::class,
        'callback'    => \local_stackmathgame\hook\output_hooks::class . '::inject_studio_icon',
        'priority'    => 500,
    ],

    // Extend quiz settings navigation with "Game Settings" link.
    [
        'hook'        => \core\hook\navigation\primary_extend::class,
        'callback'    => \local_stackmathgame\hook\navigation_hooks::class . '::extend_primary',
        'priority'    => 500,
    ],
];
