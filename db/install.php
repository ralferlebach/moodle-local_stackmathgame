<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Post-install data seeding for local_stackmathgame.
 * Seeds the default fantasy theme so the plugin is usable immediately after install.
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_stackmathgame_install(): void {
    \local_stackmathgame\game\theme_manager::seed_default_theme();
}
