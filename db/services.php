<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Web service function registrations for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // Retrieve the full compiled game configuration for a quiz.
    'local_stackmathgame_get_quizconfig' => [
        'classname'       => 'local_stackmathgame\external\get_quizconfig',
        'methodname'      => 'execute',
        'description'     => 'Returns the compiled game configuration (groups, questions, theme) for a quiz.',
        'type'            => 'read',
        'ajax'            => true,
        'loginrequired'   => true,
        'capabilities'    => 'local/stackmathgame:play',
    ],

    // Load the current user's game state for a label.
    'local_stackmathgame_get_gamestate' => [
        'classname'       => 'local_stackmathgame\external\get_gamestate',
        'methodname'      => 'execute',
        'description'     => 'Returns the current player game state for a given label.',
        'type'            => 'read',
        'ajax'            => true,
        'loginrequired'   => true,
        'capabilities'    => 'local/stackmathgame:play',
    ],

    // Persist the current user's game state for a label.
    'local_stackmathgame_save_gamestate' => [
        'classname'       => 'local_stackmathgame\external\save_gamestate',
        'methodname'      => 'execute',
        'description'     => 'Saves the current player game state for a given label.',
        'type'            => 'write',
        'ajax'            => true,
        'loginrequired'   => true,
        'capabilities'    => 'local/stackmathgame:play',
    ],

    // Submit an answer and receive feedback + updated game state delta.
    'local_stackmathgame_submit_answer' => [
        'classname'       => 'local_stackmathgame\external\submit_answer',
        'methodname'      => 'execute',
        'description'     => 'Submits a STACK answer through the browser-side AJAX chain and returns feedback.',
        'type'            => 'write',
        'ajax'            => true,
        'loginrequired'   => true,
        'capabilities'    => 'local/stackmathgame:play',
    ],

    // Autocomplete: list labels matching a search string.
    'local_stackmathgame_get_labels' => [
        'classname'       => 'local_stackmathgame\external\get_labels',
        'methodname'      => 'execute',
        'description'     => 'Returns labels matching the search string (site-wide) for autocomplete.',
        'type'            => 'read',
        'ajax'            => true,
        'loginrequired'   => true,
        'capabilities'    => 'local/stackmathgame:configure',
    ],

    // Create a new label (called when autocomplete tag is new).
    'local_stackmathgame_create_label' => [
        'classname'       => 'local_stackmathgame\external\create_label',
        'methodname'      => 'execute',
        'description'     => 'Creates a new site-wide label.',
        'type'            => 'write',
        'ajax'            => true,
        'loginrequired'   => true,
        'capabilities'    => 'local/stackmathgame:managelabels',
    ],
];
