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
 * Helpers shared by more than one upgrade step.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the cmid column to the question map, backfill it from quizid, and index it.
 *
 * Two upgrade steps needed exactly this, and the block was copied between them - forty
 * duplicated lines, which PHPCPD reports and which is worse than it looks: a fix applied to one
 * copy leaves the other wrong, and only sites upgrading across the other version notice.
 *
 * The whole routine is idempotent, which is what makes sharing it safe: every write is guarded
 * by an existence check, so running it twice changes nothing the second time.
 *
 * @return void
 */
function local_stackmathgame_upgrade_questionmap_cmid(): void {
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_stackmathgame_questionmap');
    $cmidfield = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
    $quizidfield = new xmldb_field('quizid');
    $cmidslotindex = new xmldb_index('lsmg_qmap_cmid_slot_uix', XMLDB_INDEX_UNIQUE, ['cmid', 'slotnumber']);
    $cmidnodeindex = new xmldb_index('lsmg_qmap_cmid_node_uix', XMLDB_INDEX_UNIQUE, ['cmid', 'nodekey']);
    $cmidtypeindex = new xmldb_index('lsmg_qmap_cmid_type_ix', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'nodetype']);

    if (!$dbman->table_exists($table)) {
        return;
    }

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

    if (!$dbman->field_exists($table, $cmidfield)) {
        return;
    }
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
