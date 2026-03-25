<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_stackmathgame_submit_answer' => [
        'classname' => 'local_stackmathgame\\external\\submit_answer',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Placeholder answer submission endpoint for the STACK Math Game frontend.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/stackmathgame:play',
    ],
];
