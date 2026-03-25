<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_stackmathgame\hook\output_hooks::class . '::inject_game_assets',
        'priority' => 500,
    ],
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_stackmathgame\hook\output_hooks::class . '::inject_studio_icon',
        'priority' => 500,
    ],
];
