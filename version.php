<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_stackmathgame';
$plugin->version   = 2026032612;
$plugin->requires = 2024100700; // Moodle 4.5.
$plugin->maturity = MATURITY_ALPHA;
$plugin->release   = '0.8.0';
$plugin->dependencies = [
    'qtype_stack' => ANY_VERSION,
    'qbehaviour_stackmathgame' => ANY_VERSION,
    'filter_shortcodes' => ANY_VERSION,
];
