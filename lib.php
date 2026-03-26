<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add a quiz-level settings link for the local plugin.
 *
 * The hook-based primary navigation callback was removed because the
 * repository registered a non-existent callback class. This standard
 * callback is sufficient and stable across Moodle 4.x.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_stackmathgame_extend_settings_navigation(
    settings_navigation $settingsnav,
    context $context
): void {
    global $PAGE;

    if (empty($PAGE->cm) || $PAGE->cm->modname !== 'quiz') {
        return;
    }

    if (!has_capability('local/stackmathgame:configurequiz', $context)) {
        return;
    }

    $modulesettings = $settingsnav->find('modulesettings', settings_navigation::TYPE_SETTING);
    if (!$modulesettings) {
        return;
    }

    $url = new moodle_url('/local/stackmathgame/quiz_settings.php', [
        'quizid' => (int)$PAGE->cm->instance,
        'cmid' => (int)$PAGE->cm->id,
    ]);

    $modulesettings->add(
        get_string('gamesettings', 'local_stackmathgame'),
        $url,
        settings_navigation::TYPE_SETTING,
        null,
        'local_stackmathgame_settings',
        new pix_icon('i/settings', '')
    );
}


/**
 * Serves studio-managed files.
 */
function local_stackmathgame_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        send_file_not_found();
    }
    if (!in_array($filearea, ['designthumbnail', 'designpackage'], true)) {
        send_file_not_found();
    }
    require_login();
    if (!has_capability('local/stackmathgame:viewstudio', $context) && $filearea !== 'designthumbnail') {
        send_file_not_found();
    }
    $itemid = (int)array_shift($args);
    $filepath = '/' . implode('/', array_slice($args, 0, -1)) . '/';
    $filename = end($args);
    if ($filepath === '//') {
        $filepath = '/';
    }
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_stackmathgame', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
