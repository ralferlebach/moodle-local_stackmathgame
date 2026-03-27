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
 * External function definitions for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [
    'local_stackmathgame_get_quiz_config' => [
        'classname' => 'local_stackmathgame\\external\\get_quiz_config',
        'methodname' => 'execute',
        'description' => 'Return the active STACK Math Game quiz configuration and mapped design data.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_get_profile_state' => [
        'classname' => 'local_stackmathgame\\external\\get_profile_state',
        'methodname' => 'execute',
        'description' => 'Return the current label-bound game profile for the logged in user.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_submit_answer' => [
        'classname' => 'local_stackmathgame\\external\\submit_answer',
        'methodname' => 'execute',
        'description' => 'Capture and validate game-side answer submissions for a quiz attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_save_progress' => [
        'classname' => 'local_stackmathgame\\external\\save_progress',
        'methodname' => 'execute',
        'description' => 'Persist score, XP and JSON progress deltas for the current label profile.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_get_narrative' => [
        'classname' => 'local_stackmathgame\\external\\get_narrative',
        'methodname' => 'execute',
        'description' => 'Return design narrative lines for a named scene.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_get_question_fragment' => [
        'classname' => 'local_stackmathgame\\external\\get_question_fragment',
        'methodname' => 'execute',
        'description' => 'Return a refreshed HTML fragment for the current question where available.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_prefetch_next_node' => [
        'classname' => 'local_stackmathgame\\external\\prefetch_next_node',
        'methodname' => 'execute',
        'description' => 'Return the next mapped node or slot for pageless prefetch.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
];
