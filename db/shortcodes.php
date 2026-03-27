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
 * Shortcode definitions for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$shortcodes = [
    'smgscore' => [
        'callback' => 'local_stackmathgame\\shortcodes::score',
        'description' => 'shortcode_smgscore',
    ],
    'smgxp' => [
        'callback' => 'local_stackmathgame\\shortcodes::xp',
        'description' => 'shortcode_smgxp',
    ],
    'smglevel' => [
        'callback' => 'local_stackmathgame\\shortcodes::level',
        'description' => 'shortcode_smglevel',
    ],
    'smgprogress' => [
        'callback' => 'local_stackmathgame\\shortcodes::progress',
        'description' => 'shortcode_smgprogress',
    ],
    'smgnarrative' => [
        'callback' => 'local_stackmathgame\\shortcodes::narrative',
        'description' => 'shortcode_smgnarrative',
    ],
    'smgavatar' => [
        'callback' => 'local_stackmathgame\\shortcodes::avatar',
        'description' => 'shortcode_smgavatar',
    ],
    'smgleaderboard' => [
        'callback' => 'local_stackmathgame\\shortcodes::leaderboard',
        'description' => 'shortcode_smgleaderboard',
    ],
];
