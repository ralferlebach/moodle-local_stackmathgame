<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Shortcode registrations for filter_shortcodes plugin.
 *
 * Requires: branchup/moodle-filter_shortcodes
 *
 * Available shortcodes:
 *   [smg_score type="fairies"]
 *   [smg_score type="mana"]
 *   [smg_progress label="linAlg-WS25" total="20"]
 *   [smg_narrative scene="victory"]
 *   [smg_badge type="fairy"]
 *   [smg_leaderboard label="linAlg-WS25" limit="10" type="fairies"]
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$shortcodes = [
    'smg_score' => [
        'callback'    => 'local_stackmathgame\local\shortcodes::smg_score',
        'description' => 'Displays the current player score. Attributes: type (fairies|mana), label (optional).',
        'wraps'       => false,
        'example'     => '[smg_score type="fairies"]',
    ],
    'smg_progress' => [
        'callback'    => 'local_stackmathgame\local\shortcodes::smg_progress',
        'description' => 'Displays a progress bar for solved questions. Attributes: label, total (optional).',
        'wraps'       => false,
        'example'     => '[smg_progress label="linAlg-WS25" total="20"]',
    ],
    'smg_narrative' => [
        'callback'    => 'local_stackmathgame\local\shortcodes::smg_narrative',
        'description' => 'Inserts a narrative text from the active theme. Attributes: scene, world (optional).',
        'wraps'       => false,
        'example'     => '[smg_narrative scene="world_enter" world="Zahlentheorie"]',
    ],
    'smg_badge' => [
        'callback'    => 'local_stackmathgame\local\shortcodes::smg_badge',
        'description' => 'Displays a score badge icon. Attributes: type (fairy|mana), label (optional).',
        'wraps'       => false,
        'example'     => '[smg_badge type="fairy"]',
    ],
    'smg_leaderboard' => [
        'callback'    => 'local_stackmathgame\local\shortcodes::smg_leaderboard',
        'description' => 'Displays a top-N leaderboard. Attributes: label, limit (default 10), type (fairies|mana).',
        'wraps'       => false,
        'example'     => '[smg_leaderboard label="linAlg-WS25" limit="10"]',
    ],
];
