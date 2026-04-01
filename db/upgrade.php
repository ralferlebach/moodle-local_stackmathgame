<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// It under the terms of the GNU General Public License as published by
// The Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// But WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// Along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

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

    if ($oldversion < 2026032870) {
        // Patch 2026032870: P1 + P2 + P3 — slot-based game mechanics.
        //
        // P1: quiz_settings.php now calls ensure_for_cmid() on every page
        //     Load so questionmap rows exist before the form is rendered.
        //
        // P2: quiz_settings_form.php gains a per-slot Regiekarte accordion
        //     (scene type, branching rules, narrative texts, rewards).
        //     Question_map_service::save_slot_configs() persists configjson.
        //
        // P3: submit_answer returns nextslot (from branch_resolver) and
        //     Game_engine.js auto-navigates to that slot via the Moodle
        //     Quiz nav button after a correct answer.
        //
        // DB: unique index on cmid added to local_stackmathgame table to
        //     Prevent duplicate config rows on concurrent page loads.

        $table = new xmldb_table('local_stackmathgame');
        $index = new xmldb_index('lsmg_cfg_cmid_uix', XMLDB_INDEX_UNIQUE, ['cmid']);
        if (!$dbman->index_exists($table, $index)) {
            // Remove any duplicate cmid rows first (keep lowest id per cmid).
            $sql = 'SELECT cmid, MIN(id) AS keepid
                      FROM {local_stackmathgame}
                  GROUP BY cmid
                    HAVING COUNT(*) > 1';
            $dupes = $DB->get_records_sql($sql);
            foreach ($dupes as $dupe) {
                $DB->delete_records_select(
                    'local_stackmathgame',
                    'cmid = :cmid AND id <> :keepid',
                    ['cmid' => $dupe->cmid, 'keepid' => $dupe->keepid]
                );
            }
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026032870, 'local', 'stackmathgame');
    }

    return true;
}
