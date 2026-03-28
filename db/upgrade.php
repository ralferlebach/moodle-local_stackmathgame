<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin database schema.
 *
 * Version history:
 *   2026032700 - PHP-only bug fixes; removes orphaned quizcfg rows.
 *
 * @param int $oldversion Previously installed version.
 * @return bool True on success.
 */
function xmldb_local_stackmathgame_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026032700) {
        $sql = "SELECT qcfg.id, qcfg.quizid
                  FROM {local_stackmathgame_quizcfg} qcfg
                 WHERE NOT EXISTS (
                       SELECT 1
                         FROM {course_modules} cm
                         JOIN {modules} md ON md.id = cm.module
                        WHERE cm.instance = qcfg.quizid
                          AND md.name = :modname
                 )";

        $orphans = $DB->get_records_sql($sql, ['modname' => 'quiz']);

        if (!empty($orphans)) {
            $orphanids = array_column($orphans, 'id');
            $quizids = implode(', ', array_unique(array_column($orphans, 'quizid')));
            debugging(
                'local_stackmathgame upgrade 2026032700: removing ' . count($orphanids) .
                ' orphaned quizcfg row(s) for quiz IDs [' . $quizids . '].',
                DEBUG_DEVELOPER
            );
            [$insql, $inparams] = $DB->get_in_or_equal($orphanids);
            $DB->delete_records_select('local_stackmathgame_quizcfg', "id $insql", $inparams);
        }

        upgrade_plugin_savepoint(true, 2026032700, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032704) {
        // Version 2026032704: PHP-only fixes.
        // - Studio icon moved to render_navbar_output() (no fixed positioning).
        // - Tertiary navigation injection made robust (multi-attempt timeout).
        // - PHPUnit test files corrected to test local_stackmathgame classes.
        upgrade_plugin_savepoint(true, 2026032704, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032705) {
        // Version 2026032705: Adds smg_console_diag.js debug tooling (no schema change).
        upgrade_plugin_savepoint(true, 2026032705, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032706) {
        // Version 2026032706: AMD build files (amd/build/*.min.js) added.
        // Previously missing, causing RequireJS to not recognise the modules
        // And silently skipping the tertiary_nav injection entirely.
        upgrade_plugin_savepoint(true, 2026032706, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032707) {
        // Version 2026032707: PHPCS fixes in test files and upgrade.php.
        upgrade_plugin_savepoint(true, 2026032707, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032708) {
        // Version 2026032708: PHPCS fixes in test files; AMD injection moved
        // From extend_settings_navigation to before_http_headers hook for
        // Reliable tertiary navigation injection on quiz management pages.
        upgrade_plugin_savepoint(true, 2026032708, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032804) {
        // Version 2026032804: Fix Behat test files.
        // - Renamed behat_local_stackmatheditor.php to behat_local_stackmathgame.php.
        // - Replaced @local_stackmatheditor tags with @local_stackmathgame.
        // - Replaced stackmatheditor feature content with stackmathgame scenarios.
        upgrade_plugin_savepoint(true, 2026032804, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032814) {
        // Step I: Navigation + Narrative.
        // - lib.php: js_call_amd removed from extend_settings_navigation to prevent
        // Duplicate tertiary nav entries. The before_http_headers hook is the sole
        // Source of the AMD call (uses optional_param cmid, works on quiz/edit.php).
        // - narrative_resolver: new service class with canonical scene constants.
        // Shortcodes::narrative() and get_narrative::execute() use it (DRY).
        // - capability_test: smoke tests for all three navigation access tiers.
        upgrade_plugin_savepoint(true, 2026032814, 'local', 'stackmathgame');
    }

    return true;
}
