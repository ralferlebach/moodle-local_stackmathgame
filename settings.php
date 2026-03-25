<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_stackmathgame',
        get_string('pluginname', 'local_stackmathgame')
    );

    $settings->add(new admin_setting_heading(
        'local_stackmathgame/overview',
        get_string('settingsheading', 'local_stackmathgame'),
        get_string('settingsdesc', 'local_stackmathgame')
    ));

    $ADMIN->add('localplugins', $settings);
}
