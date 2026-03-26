<?php
// This file is part of Moodle - http://moodle.org/

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
