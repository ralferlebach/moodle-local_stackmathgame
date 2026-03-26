<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_stackmathgame_get_quiz_config' => [
        'classname' => 'local_stackmathgame\external\get_quiz_config',
        'methodname' => 'execute',
        'description' => 'Return the active STACK Math Game quiz configuration and mapped design data.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_get_profile_state' => [
        'classname' => 'local_stackmathgame\external\get_profile_state',
        'methodname' => 'execute',
        'description' => 'Return the current label-bound game profile for the logged in user.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_submit_answer' => [
        'classname' => 'local_stackmathgame\external\submit_answer',
        'methodname' => 'execute',
        'description' => 'Capture and validate game-side answer submissions for a quiz attempt.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_save_progress' => [
        'classname' => 'local_stackmathgame\external\save_progress',
        'methodname' => 'execute',
        'description' => 'Persist score, XP and JSON progress deltas for the current label profile.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_get_narrative' => [
        'classname' => 'local_stackmathgame\external\get_narrative',
        'methodname' => 'execute',
        'description' => 'Return design narrative lines for a named scene.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
    'local_stackmathgame_prefetch_next_node' => [
        'classname' => 'local_stackmathgame\external\prefetch_next_node',
        'methodname' => 'execute',
        'description' => 'Return the next mapped node or slot for pageless prefetch.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
];
