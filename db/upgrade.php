<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_stackmathgame.
 *
 * Version history:
 *   2026032700 – PHP-only bug fixes (no schema change).
 *                Cleans up orphaned quizcfg rows whose quiz no longer has a
 *                course_modules entry. Those orphaned rows caused
 *                dml_missing_record_exception when quiz_settings.php tried
 *                to resolve the course module from the stored quizid.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_stackmathgame_upgrade(int $oldversion): bool {
    global $DB;

    // -----------------------------------------------------------------
    // 2026032700: remove orphaned quizcfg rows whose quiz has no cm entry.
    // -----------------------------------------------------------------
    if ($oldversion < 2026032700) {

        // Find quizcfg rows that reference a quizid for which no
        // course_modules record exists (quiz deleted, module removed, etc.).
        // We do this in batches to avoid memory issues on large sites.
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

            // Log what we are about to remove (useful for admins).
            $quizids = implode(', ', array_unique(array_column($orphans, 'quizid')));
            debugging(
                'local_stackmathgame upgrade 2026032700: removing ' . count($orphanids) .
                ' orphaned quizcfg row(s) for quiz IDs [' . $quizids . '] ' .
                'because no course_modules entry exists for those quizzes.',
                DEBUG_DEVELOPER
            );

            // Delete in one shot (the list is small in practice).
            list($insql, $inparams) = $DB->get_in_or_equal($orphanids);
            $DB->delete_records_select('local_stackmathgame_quizcfg', "id $insql", $inparams);
        }

        // Mark this step complete.
        upgrade_plugin_savepoint(true, 2026032700, 'local', 'stackmathgame');
    }

    return true;
}
