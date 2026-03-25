<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Capability definitions for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // -- Player capabilities --------------------------------------------------

    /**
     * Play the game (submit answers, read/write own game state).
     * Granted to: students by default.
     */
    'local/stackmathgame:play' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'student'      => CAP_ALLOW,
            'teacher'      => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    // -- Teacher / config capabilities ----------------------------------------

    /**
     * Configure the game for a quiz (assign label, choose theme, set mechanics).
     * Granted to: editing teachers and above.
     */
    'local/stackmathgame:configure' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    /**
     * View other users' game states (e.g. for teacher dashboards).
     */
    'local/stackmathgame:viewotherstates' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // -- Admin / Game Designer capabilities -----------------------------------

    /**
     * Manage site-wide labels (create, rename, delete).
     * Granted to: site admins and the 'Game Designer' role.
     */
    'local/stackmathgame:managelabels' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    /**
     * Manage visual themes (create, edit, enable/disable).
     * Intended for 'Game Designer' role + site admin.
     */
    'local/stackmathgame:managethemes' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    /**
     * Reset any player's game state (admin tool).
     */
    'local/stackmathgame:resetstate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
