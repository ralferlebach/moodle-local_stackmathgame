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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The old version.
 * @return bool
 */
function xmldb_local_stackmathgame_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026032827) {
        // Rename local_stackmathgame_quizcfg → local_stackmathgame
        // And replace quizid (FK to quiz) with cmid (FK to course_modules).
        // Cmid is the source of truth: it encodes courseid, moduletype, and
        // The instance id (quizid), so no Umbau is needed when other activity
        // Types are added later.

        $oldtable = new xmldb_table('local_stackmathgame_quizcfg');
        if ($dbman->table_exists($oldtable)) {
            // Add cmid column to the old table and populate from course_modules.
            $cmidfield = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
            if (!$dbman->field_exists($oldtable, $cmidfield)) {
                $dbman->add_field($oldtable, $cmidfield);
            }

            // Resolve cmid for each existing row via quiz → course_modules.
            $rows = $DB->get_records('local_stackmathgame_quizcfg', [], '', 'id, quizid');
            foreach ($rows as $row) {
                $cm = get_coursemodule_from_instance('quiz', (int)$row->quizid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $DB->set_field('local_stackmathgame_quizcfg', 'cmid', (int)$cm->id, ['id' => $row->id]);
                }
            }

            // Rename the table.
            $dbman->rename_table($oldtable, 'local_stackmathgame');

            // Add unique index on cmid.
            $newtable = new xmldb_table('local_stackmathgame');
            $index = new xmldb_index('lsmg_cfg_cmid_uix', XMLDB_INDEX_UNIQUE, ['cmid']);
            if (!$dbman->index_exists($newtable, $index)) {
                $dbman->add_index($newtable, $index);
            }
        }

        upgrade_plugin_savepoint(true, 2026032827, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032828) {
        // Patch 2026032828: fix submit_answer to use cmid for config lookup.
        // - submit_answer: use $cm->id (from attempt object) for ensure_default().
        // - api.php: validate_quiz_access uses $cm->id instead of quizid.
        // - submit_answer: function () space + get_qt_data try/catch + state cast.
        // - upgrade.php: license URL http → https.
        upgrade_plugin_savepoint(true, 2026032828, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032829) {
        // Patch 2026032829: fix install.xml quizid references.
        // - local_stackmathgame: removed orphan quizid_fk key and quizid index.
        // - local_stackmathgame_questionmap: indexes renamed from quizid,* to cmid,*.
        // - submit_answer: function () space after keyword.
        // - upgrade.php: boilerplate restored (regex had capitalised lowercase lines).
        upgrade_plugin_savepoint(true, 2026032829, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032830) {
        // Patch 2026032830: stabilise attempt-page injection and repair cmid lookups.
        // - inject_game_assets resolves cmid from the request instead of requiring $PAGE->cm.
        // - game_engine bootstrap receives quizid + instanceid again.
        // - ensure_default callers now pass cmid consistently.
        upgrade_plugin_savepoint(true, 2026032830, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032831) {
        // Patch 2026032831: adapt prefetch_next_node to Moodle quiz_slots schema.
        // - quiz_slots.question is used on newer Moodle versions.
        // - fall back to quiz_slots.questionid on older schemas.
        upgrade_plugin_savepoint(true, 2026032831, 'local', 'stackmathgame');
    }

    return true;
}
