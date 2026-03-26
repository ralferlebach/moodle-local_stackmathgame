<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'stackmathgamemode_wisewizzard';
$plugin->version = 2026032601;
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.1.0';
$plugin->dependencies = [
    'local_stackmathgame' => ANY_VERSION,
];
