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
        $oldtable = new xmldb_table('local_stackmathgame_quizcfg');
        if ($dbman->table_exists($oldtable)) {
            $cmidfield = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
            if (!$dbman->field_exists($oldtable, $cmidfield)) {
                $dbman->add_field($oldtable, $cmidfield);
            }

            $rows = $DB->get_records('local_stackmathgame_quizcfg', [], '', 'id, quizid');
            foreach ($rows as $row) {
                $cm = get_coursemodule_from_instance('quiz', (int)$row->quizid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $DB->set_field('local_stackmathgame_quizcfg', 'cmid', (int)$cm->id, ['id' => $row->id]);
                }
            }

            $dbman->rename_table($oldtable, 'local_stackmathgame');

            $newtable = new xmldb_table('local_stackmathgame');
            $index = new xmldb_index('lsmg_cfg_cmid_uix', XMLDB_INDEX_UNIQUE, ['cmid']);
            if (!$dbman->index_exists($newtable, $index)) {
                $dbman->add_index($newtable, $index);
            }
        }

        upgrade_plugin_savepoint(true, 2026032827, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032828) {
        upgrade_plugin_savepoint(true, 2026032828, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032829) {
        upgrade_plugin_savepoint(true, 2026032829, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032830) {
        upgrade_plugin_savepoint(true, 2026032830, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032831) {
        upgrade_plugin_savepoint(true, 2026032831, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032832) {
        $table = new xmldb_table('local_stackmathgame_questionmap');
        $cmidfield = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        $quizidfield = new xmldb_field('quizid');
        $cmidslotindex = new xmldb_index('lsmg_qmap_cmid_slot_uix', XMLDB_INDEX_UNIQUE, ['cmid', 'slotnumber']);
        $cmidnodeindex = new xmldb_index('lsmg_qmap_cmid_node_uix', XMLDB_INDEX_UNIQUE, ['cmid', 'nodekey']);
        $cmidtypeindex = new xmldb_index('lsmg_qmap_cmid_type_ix', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'nodetype']);

        if ($dbman->table_exists($table)) {
            if (!$dbman->field_exists($table, $cmidfield)) {
                $dbman->add_field($table, $cmidfield);
            }

            if ($dbman->field_exists($table, $cmidfield) && $dbman->field_exists($table, $quizidfield)) {
                $rows = $DB->get_records_select(
                    'local_stackmathgame_questionmap',
                    'cmid IS NULL OR cmid = 0',
                    [],
                    '',
                    'id, quizid, cmid'
                );
                foreach ($rows as $row) {
                    if (empty($row->quizid)) {
                        continue;
                    }
                    $cm = get_coursemodule_from_instance('quiz', (int)$row->quizid, 0, false, IGNORE_MISSING);
                    if ($cm) {
                        $DB->set_field('local_stackmathgame_questionmap', 'cmid', (int)$cm->id, ['id' => $row->id]);
                    }
                }
            }

            if ($dbman->field_exists($table, $cmidfield)) {
                if (!$dbman->index_exists($table, $cmidslotindex)) {
                    $dbman->add_index($table, $cmidslotindex);
                }
                if (!$dbman->index_exists($table, $cmidnodeindex)) {
                    $dbman->add_index($table, $cmidnodeindex);
                }
                if (!$dbman->index_exists($table, $cmidtypeindex)) {
                    $dbman->add_index($table, $cmidtypeindex);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026032832, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032833) {
        $table = new xmldb_table('local_stackmathgame_questionmap');
        $cmidfield = new xmldb_field('cmid');
        $quizidfield = new xmldb_field('quizid');

        if (
            $dbman->table_exists($table)
            && $dbman->field_exists($table, $cmidfield)
            && $dbman->field_exists($table, $quizidfield)
        ) {
            $rows = $DB->get_records_select(
                'local_stackmathgame_questionmap',
                'cmid IS NULL OR cmid = 0',
                [],
                '',
                'id, quizid, cmid'
            );
            foreach ($rows as $row) {
                if (empty($row->quizid)) {
                    continue;
                }
                $cm = get_coursemodule_from_instance('quiz', (int)$row->quizid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $DB->set_field('local_stackmathgame_questionmap', 'cmid', (int)$cm->id, ['id' => $row->id]);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026032833, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032834) {
        // Keep the additive cmid migration schema-safe.
        // quizid remains in place for backwards compatibility until a later,
        // dedicated cleanup step removes obsolete indexes and the legacy field.
        upgrade_plugin_savepoint(true, 2026032834, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032835) {
        // No schema changes. This savepoint marks the schema-aware quiz_slots
        // question field fallback used by prefetch_next_node.
        upgrade_plugin_savepoint(true, 2026032835, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032836) {
        // Consolidated release: keep the additive cmid migration and runtime
        // fixes together without additional DDL changes.
        upgrade_plugin_savepoint(true, 2026032836, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032838) {
        $table = new xmldb_table('local_stackmathgame_stashmap');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('slotnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('stashcourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('stashitemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('grantquantity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'firstsolve');
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('quizid_fk', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);
            $table->add_key('stashcourseid_fk', XMLDB_KEY_FOREIGN, ['stashcourseid'], 'course', ['id']);

            $table->add_index('lsmg_smap_quiz_slot_uix', XMLDB_INDEX_UNIQUE, ['quizid', 'slotnumber']);
            $table->add_index('lsmg_smap_item_ix', XMLDB_INDEX_NOTUNIQUE, ['stashitemid', 'enabled']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032838, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032840) {
        $table = new xmldb_table('local_stackmathgame_questionmap');
        $cmidfield = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        $quizidfield = new xmldb_field('quizid');
        $cmidslotindex = new xmldb_index('lsmg_qmap_cmid_slot_uix', XMLDB_INDEX_UNIQUE, ['cmid', 'slotnumber']);
        $cmidnodeindex = new xmldb_index('lsmg_qmap_cmid_node_uix', XMLDB_INDEX_UNIQUE, ['cmid', 'nodekey']);
        $cmidtypeindex = new xmldb_index('lsmg_qmap_cmid_type_ix', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'nodetype']);

        if ($dbman->table_exists($table)) {
            if (!$dbman->field_exists($table, $cmidfield)) {
                $dbman->add_field($table, $cmidfield);
            }

            if ($dbman->field_exists($table, $cmidfield) && $dbman->field_exists($table, $quizidfield)) {
                $rows = $DB->get_records_select(
                    'local_stackmathgame_questionmap',
                    'cmid IS NULL OR cmid = 0',
                    [],
                    '',
                    'id, quizid, cmid'
                );
                foreach ($rows as $row) {
                    if (empty($row->quizid)) {
                        continue;
                    }
                    $cm = get_coursemodule_from_instance('quiz', (int)$row->quizid, 0, false, IGNORE_MISSING);
                    if ($cm) {
                        $DB->set_field('local_stackmathgame_questionmap', 'cmid', (int)$cm->id, ['id' => $row->id]);
                    }
                }
            }

            if ($dbman->field_exists($table, $cmidfield)) {
                if (!$dbman->index_exists($table, $cmidslotindex)) {
                    $dbman->add_index($table, $cmidslotindex);
                }
                if (!$dbman->index_exists($table, $cmidnodeindex)) {
                    $dbman->add_index($table, $cmidnodeindex);
                }
                if (!$dbman->index_exists($table, $cmidtypeindex)) {
                    $dbman->add_index($table, $cmidtypeindex);
                }
            }
        }

        foreach ($DB->get_fieldset_select('local_stackmathgame', 'cmid', 'cmid > 0', []) as $cmid) {
            try {
                \local_stackmathgame\local\service\question_map_service::ensure_for_cmid((int)$cmid);
            } catch (\Throwable $e) {
                debugging(
                    'local_stackmathgame question_map upgrade skipped cmid=' . (int)$cmid . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026032840, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032848) {
        upgrade_plugin_savepoint(true, 2026032848, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032849) {
        // Register activity external functions and wrapper fixes.
        upgrade_plugin_savepoint(true, 2026032849, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032850) {
        unset_config('installrunning', 'local_stackmathgame');

        try {
            \local_stackmathgame\local\service\question_map_service::rebuild_all();
        } catch (\Throwable $e) {
            debugging(
                'local_stackmathgame question_map rebuild during 2026032850 failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }

        upgrade_plugin_savepoint(true, 2026032850, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032856) {
        $table = new xmldb_table('local_stackmathgame_stashmap');
        $cmidfield = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        $quizidfield = new xmldb_field('quizid');
        $cmidslotindex = new xmldb_index('lsmg_smap_cmid_slot_uix', XMLDB_INDEX_UNIQUE, ['cmid', 'slotnumber']);

        if ($dbman->table_exists($table)) {
            if (!$dbman->field_exists($table, $cmidfield)) {
                $dbman->add_field($table, $cmidfield);
            }

            if ($dbman->field_exists($table, $cmidfield) && $dbman->field_exists($table, $quizidfield)) {
                $rows = $DB->get_records_select(
                    'local_stackmathgame_stashmap',
                    'cmid IS NULL OR cmid = 0',
                    [],
                    '',
                    'id, quizid'
                );
                foreach ($rows as $row) {
                    if (empty($row->quizid)) {
                        continue;
                    }
                    $cm = get_coursemodule_from_instance('quiz', (int)$row->quizid, 0, false, IGNORE_MISSING);
                    if ($cm) {
                        $DB->set_field('local_stackmathgame_stashmap', 'cmid', (int)$cm->id, ['id' => $row->id]);
                    }
                }
            }

            if ($dbman->field_exists($table, $cmidfield) && !$dbman->index_exists($table, $cmidslotindex)) {
                $dbman->add_index($table, $cmidslotindex);
            }
        }

        try {
            \local_stackmathgame\local\service\stash_mapping_service::backfill_legacy_quiz_rows();
        } catch (\Throwable $e) {
            debugging(
                'local_stackmathgame stash map backfill during 2026032856 failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }

        upgrade_plugin_savepoint(true, 2026032856, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032858) {
        try {
            \local_stackmathgame\local\service\stash_mapping_service::backfill_activity_rows();
        } catch (\Throwable $e) {
            debugging(
                'local_stackmathgame stash map activity backfill during 2026032858 failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }

        upgrade_plugin_savepoint(true, 2026032858, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032859) {
        upgrade_plugin_savepoint(true, 2026032859, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032861) {
        upgrade_plugin_savepoint(true, 2026032861, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032862) {
        // Register activity-oriented stash mapping web services.
        upgrade_plugin_savepoint(true, 2026032862, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032864) {
        // Register reward-state runtime web services.
        upgrade_plugin_savepoint(true, 2026032864, 'local', 'stackmathgame');
    }

    if ($oldversion < 2026032865) {
        $table = new xmldb_table('local_stackmathgame_eventlog');
        $cmidfield = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'labelid');
        $cmidtimeindex = new xmldb_index('lsmg_evt_cmid_time_ix', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'timecreated']);

        if ($dbman->table_exists($table)) {
            if (!$dbman->field_exists($table, $cmidfield)) {
                $dbman->add_field($table, $cmidfield);
            }
            if ($dbman->field_exists($table, $cmidfield) && !$dbman->index_exists($table, $cmidtimeindex)) {
                $dbman->add_index($table, $cmidtimeindex);
            }

            $rows = $DB->get_records_select(
                'local_stackmathgame_eventlog',
                '(cmid IS NULL OR cmid = 0) AND quizid IS NOT NULL AND quizid > 0',
                [],
                '',
                'id, quizid'
            );
            foreach ($rows as $row) {
                $cm = get_coursemodule_from_instance('quiz', (int)$row->quizid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $DB->set_field('local_stackmathgame_eventlog', 'cmid', (int)$cm->id, ['id' => $row->id]);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026032865, 'local', 'stackmathgame');
    }

    return true;
}
