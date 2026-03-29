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
 * Service for backfilling and rebuilding question-map rows from quiz slots.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

use local_stackmathgame\game\quiz_configurator;
use xmldb_field;
use xmldb_table;

/**
 * Builds canonical local_stackmathgame_questionmap rows keyed by cmid.
 *
 * The service preserves existing per-slot configjson, nodekey, nodetype and
 * sortorder where possible, while backfilling cmid and refreshing question IDs
 * from the current quiz_slots schema.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_map_service {
    /**
     * Ensure question-map rows exist and are canonical for one activity.
     *
     * @param int $cmid The quiz course-module ID.
     * @return array Summary counters.
     */
    public static function ensure_for_cmid(int $cmid): array {
        global $DB;

        $cm = quiz_configurator::get_supported_cm($cmid);
        $quizid = (int)$cm->instance;
        $config = quiz_configurator::ensure_default($cmid);
        $slots = self::get_quiz_slot_records($quizid);
        $existingrows = self::get_existing_rows($cmid, $quizid);
        $now = time();
        $hasquizid = self::questionmap_has_field('quizid');

        $summary = [
            'cmid' => $cmid,
            'quizid' => $quizid,
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'backfilled' => 0,
            'slots' => count($slots),
        ];

        $byslot = [];
        $deleteids = [];
        $validslots = array_keys($slots);
        foreach ($existingrows as $row) {
            $slotnumber = (int)$row->slotnumber;
            if (!in_array($slotnumber, $validslots, true)) {
                $deleteids[] = (int)$row->id;
                continue;
            }
            if (!array_key_exists($slotnumber, $byslot)) {
                $byslot[$slotnumber] = $row;
                continue;
            }

            if (self::is_better_row($row, $byslot[$slotnumber], $cmid)) {
                $deleteids[] = (int)$byslot[$slotnumber]->id;
                $byslot[$slotnumber] = $row;
            } else {
                $deleteids[] = (int)$row->id;
            }
        }

        if ($deleteids) {
            list($insql, $params) = $DB->get_in_or_equal($deleteids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_stackmathgame_questionmap', 'id ' . $insql, $params);
            $summary['deleted'] += count($deleteids);
        }

        foreach ($slots as $slotnumber => $slot) {
            $existing = $byslot[$slotnumber] ?? null;
            $configjson = $existing && !empty($existing->configjson)
                ? (string)$existing->configjson
                : json_encode(slot_config_schema::defaults(), JSON_UNESCAPED_UNICODE);
            $nodekey = $existing && !empty($existing->nodekey)
                ? (string)$existing->nodekey
                : 'slot_' . $slotnumber;
            $nodetype = $existing && !empty($existing->nodetype)
                ? (string)$existing->nodetype
                : 'question';
            $sortorder = $existing ? (int)$existing->sortorder : $slotnumber;
            if ($sortorder <= 0) {
                $sortorder = $slotnumber;
            }
            $designid = $existing && isset($existing->designid)
                ? (int)$existing->designid
                : (int)($config->designid ?? 0);
            if ($designid <= 0) {
                $designid = null;
            }

            $record = (object)[
                'cmid' => $cmid,
                'questionid' => (int)$slot->questionid,
                'slotnumber' => $slotnumber,
                'designid' => $designid,
                'nodekey' => $nodekey,
                'nodetype' => $nodetype,
                'sortorder' => $sortorder,
                'configjson' => $configjson,
                'timemodified' => $now,
            ];
            if ($hasquizid) {
                $record->quizid = $quizid;
            }

            if ($existing) {
                $needsupdate = false;
                foreach ((array)$record as $field => $value) {
                    if ($field === 'timemodified') {
                        continue;
                    }
                    $existingvalue = $existing->{$field} ?? null;
                    if ((string)$existingvalue !== (string)$value) {
                        $needsupdate = true;
                        break;
                    }
                }
                if ($needsupdate || empty($existing->cmid)) {
                    $record->id = (int)$existing->id;
                    $DB->update_record('local_stackmathgame_questionmap', $record);
                    $summary['updated']++;
                    if (empty($existing->cmid)) {
                        $summary['backfilled']++;
                    }
                }
                continue;
            }

            $record->timecreated = $now;
            $DB->insert_record('local_stackmathgame_questionmap', $record);
            $summary['created']++;
        }

        return $summary;
    }

    /**
     * Rebuild question-map rows for multiple activities.
     *
     * When $cmids is empty, all configured activities from local_stackmathgame
     * are rebuilt.
     *
     * @param int[] $cmids Optional course-module IDs.
     * @return array Summary with totals and per-cmid entries.
     */
    public static function rebuild_all(array $cmids = []): array {
        global $DB;

        if (empty($cmids)) {
            $cmids = array_map(
                'intval',
                $DB->get_fieldset_select('local_stackmathgame', 'cmid', 'cmid > 0', [])
            );
        }

        $cmids = array_values(array_unique(array_filter($cmids)));
        sort($cmids);

        $summary = [
            'activities' => 0,
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'backfilled' => 0,
            'slots' => 0,
            'results' => [],
        ];

        foreach ($cmids as $cmid) {
            $result = self::ensure_for_cmid((int)$cmid);
            $summary['activities']++;
            $summary['created'] += (int)$result['created'];
            $summary['updated'] += (int)$result['updated'];
            $summary['deleted'] += (int)$result['deleted'];
            $summary['backfilled'] += (int)$result['backfilled'];
            $summary['slots'] += (int)$result['slots'];
            $summary['results'][] = $result;
        }

        return $summary;
    }

    /**
     * Return quiz slot rows with schema-aware question IDs.
     *
     * @param int $quizid The quiz instance ID.
     * @return array<int, \stdClass> Rows keyed by slotnumber.
     */
    public static function get_quiz_slot_records(int $quizid): array {
        global $DB;

        $questionfield = self::get_quiz_slot_question_field();
        $sql = 'SELECT id, slot AS slotnumber, ' . $questionfield . ' AS questionid'
            . ' FROM {quiz_slots}'
            . ' WHERE quizid = ?'
            . ' ORDER BY slot ASC';
        $records = $DB->get_records_sql($sql, [$quizid]);

        $byslot = [];
        foreach ($records as $record) {
            $byslot[(int)$record->slotnumber] = $record;
        }
        return $byslot;
    }

    /**
     * Return whether local_stackmathgame_questionmap has a given field.
     *
     * @param string $fieldname The field name.
     * @return bool True when the field exists.
     */
    public static function questionmap_has_field(string $fieldname): bool {
        global $DB;

        static $cache = [];
        if (array_key_exists($fieldname, $cache)) {
            return $cache[$fieldname];
        }

        $table = new xmldb_table('local_stackmathgame_questionmap');
        $field = new xmldb_field($fieldname);
        $cache[$fieldname] = $DB->get_manager()->field_exists($table, $field);
        return $cache[$fieldname];
    }

    /**
     * Return the slot field that stores the question reference in quiz_slots.
     *
     * @return string SQL-safe field name, or 0 fallback when unavailable.
     */
    public static function get_quiz_slot_question_field(): string {
        global $DB;

        $manager = $DB->get_manager();
        $table = new xmldb_table('quiz_slots');

        if ($manager->field_exists($table, new xmldb_field('question'))) {
            return 'question';
        }
        if ($manager->field_exists($table, new xmldb_field('questionid'))) {
            return 'questionid';
        }
        return '0';
    }

    /**
     * Load existing question-map rows relevant for the activity.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @return array<int, \stdClass> Record list.
     */
    private static function get_existing_rows(int $cmid, int $quizid): array {
        global $DB;

        $conditions = [];
        $params = [];
        if (self::questionmap_has_field('cmid')) {
            $conditions[] = 'cmid = :cmid';
            $params['cmid'] = $cmid;
        }
        if (self::questionmap_has_field('quizid')) {
            $conditions[] = 'quizid = :quizid';
            $params['quizid'] = $quizid;
        }
        if (!$conditions) {
            return [];
        }

        $sql = implode(' OR ', $conditions);
        return $DB->get_records_select('local_stackmathgame_questionmap', $sql, $params, 'slotnumber ASC, id ASC');
    }

    /**
     * Decide which duplicate row should be retained for a slot.
     *
     * @param \stdClass $candidate The candidate row.
     * @param \stdClass $current The currently selected row.
     * @param int $cmid The canonical course-module ID.
     * @return bool True when the candidate should replace the current row.
     */
    private static function is_better_row(\stdClass $candidate, \stdClass $current, int $cmid): bool {
        $candidatecmid = (int)($candidate->cmid ?? 0);
        $currentcmid = (int)($current->cmid ?? 0);

        if ($candidatecmid === $cmid && $currentcmid !== $cmid) {
            return true;
        }
        if ($candidatecmid !== $cmid && $currentcmid === $cmid) {
            return false;
        }
        if (!empty($candidate->configjson) && empty($current->configjson)) {
            return true;
        }
        return (int)$candidate->id < (int)$current->id;
    }
}
