<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Post-install hook.
 */
function xmldb_local_stackmathgame_install(): bool {
    \local_stackmathgame\game\theme_manager::seed_default_themes();
    \local_stackmathgame\game\theme_manager::purge_cache();
    return true;
}
