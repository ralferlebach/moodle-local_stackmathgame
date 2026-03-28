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
 * Hook callback definitions for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // Tertiary nav: reads cmid via optional_param() from URL because $PAGE->cm
        // is not populated yet when before_http_headers fires on quiz management pages.
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_stackmathgame\hook\output_hooks::class . '::inject_tertiary_nav',
        'priority' => 600,
    ],
    [
        // Game assets: attempt pages only; pagetype is reliable there.
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_stackmathgame\hook\output_hooks::class . '::inject_game_assets',
        'priority' => 500,
    ],
];
