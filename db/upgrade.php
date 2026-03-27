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

    return true;
}
