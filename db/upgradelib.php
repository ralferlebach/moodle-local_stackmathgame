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

/**
 * Keep one configuration row per course module before the unique cmid index is added.
 *
 * The most recently modified row stays, because it holds what a teacher saved last.
 *
 * @return void
 */
function local_stackmathgame_upgrade_dedupe_config_cmid(): void {
    global $DB;

    $dupes = $DB->get_records_sql(
        'SELECT cmid, COUNT(1) AS n
           FROM {local_stackmathgame}
       GROUP BY cmid
         HAVING COUNT(1) > 1'
    );
    foreach ($dupes as $dupe) {
        $rows = $DB->get_records(
            'local_stackmathgame',
            ['cmid' => $dupe->cmid],
            'timemodified DESC, id DESC',
            'id'
        );
        array_shift($rows);
        if ($rows) {
            $DB->delete_records_list('local_stackmathgame', 'id', array_keys($rows));
        }
    }
}

/**
 * Remove session keys from answer payloads already written to the event log.
 *
 * Up to 2026090250 submit_answer logged the whole request as a list of values - the field names
 * were dropped by array_values() - and the browser sent its session key with every answer, so
 * every logged submission contains one (re-audit N01). Looking for a "sesskey" key therefore finds
 * nothing; the rule has to recognise the value itself.
 *
 * The rule, applied to the "answers" entry of every answer_submitted payload:
 *  - a list of plain values: every value that has the shape of a Moodle session key - exactly ten
 *    characters from [A-Za-z0-9], which is what random_string(10) in sesskey() produces - is
 *    removed. That can also remove a genuine answer of the same shape; this is accepted, because
 *    the authoritative copy of every answer is the question attempt step, and keeping a credential
 *    is the worse error.
 *  - a list of name/value pairs: every pair named "sesskey" is removed.
 *  - a map: the "sesskey" key is removed.
 * A payload that was changed is marked with "sesskeyredacted": 1. Nothing else is touched, and
 * running the routine again changes nothing.
 *
 * @return int The number of event log rows rewritten.
 */
function local_stackmathgame_redact_eventlog_sesskeys(): int {
    global $DB;

    $like = $DB->sql_like('payloadjson', ':needle');
    $rows = $DB->get_recordset_select(
        'local_stackmathgame_eventlog',
        "eventtype = :eventtype AND $like",
        ['eventtype' => 'answer_submitted', 'needle' => '%"answers"%'],
        'id ASC',
        'id, payloadjson'
    );
    $changed = 0;
    foreach ($rows as $row) {
        $payload = json_decode((string)$row->payloadjson, true);
        if (!is_array($payload) || !isset($payload['answers']) || !is_array($payload['answers'])) {
            continue;
        }
        $answers = local_stackmathgame_redact_sesskey_values($payload['answers']);
        if ($answers === $payload['answers']) {
            continue;
        }
        $payload['answers'] = $answers;
        $payload['sesskeyredacted'] = 1;
        $DB->set_field(
            'local_stackmathgame_eventlog',
            'payloadjson',
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            ['id' => (int)$row->id]
        );
        $changed++;
    }
    $rows->close();
    return $changed;
}

/**
 * Apply the session key rule of local_stackmathgame_redact_eventlog_sesskeys() to one list.
 *
 * @param array $answers The logged "answers" entry.
 * @return array The entry without anything that is or looks like a session key.
 */
function local_stackmathgame_redact_sesskey_values(array $answers): array {
    $islist = array_keys($answers) === range(0, count($answers) - 1);
    if (!$islist) {
        unset($answers['sesskey']);
        return $answers;
    }
    $kept = [];
    foreach ($answers as $entry) {
        if (is_array($entry)) {
            if ((string)($entry['name'] ?? '') === 'sesskey') {
                continue;
            }
        } else if (is_scalar($entry) && preg_match('/^[A-Za-z0-9]{10}$/', (string)$entry)) {
            continue;
        }
        $kept[] = $entry;
    }
    return $kept;
}
