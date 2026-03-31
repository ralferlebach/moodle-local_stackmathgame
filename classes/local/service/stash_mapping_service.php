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
 * Service for reading and writing stash slot mappings.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

use local_stackmathgame\local\integration\availability;
use xmldb_field;
use xmldb_table;

/**
 * Load and persist mappings between activity slots and block_stash items.
 *
 * The course-module ID is the source of truth. Legacy quiz-based methods are
 * retained as wrappers while existing data is migrated to cmid.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stash_mapping_service {
    /**
     * Load all stash mappings for an activity, keyed by slot number.
     *
     * @param int $cmid The course-module ID.
     * @param int $instanceid Optional legacy quiz instance ID for fallback.
     * @param string $modname The module name.
     * @return array<int, \stdClass> Map of slotnumber to mapping record.
     */
    public static function get_for_activity(int $cmid, int $instanceid = 0, string $modname = 'quiz'): array {
        global $DB;

        $byslot = [];
        $rows = [];

        if ($cmid > 0 && self::stashmap_has_field('cmid')) {
            $rows = $DB->get_records('local_stackmathgame_stashmap', ['cmid' => $cmid]);
        }

        if (!$rows && $modname === 'quiz' && $instanceid > 0 && self::stashmap_has_field('quizid')) {
            $rows = $DB->get_records('local_stackmathgame_stashmap', ['quizid' => $instanceid]);
            if ($rows && $cmid > 0 && self::stashmap_has_field('cmid')) {
                foreach ($rows as $row) {
                    if (empty($row->cmid)) {
                        $DB->set_field('local_stackmathgame_stashmap', 'cmid', $cmid, ['id' => $row->id]);
                        $row->cmid = $cmid;
                    }
                }
            }
        }

        foreach ($rows as $row) {
            $byslot[(int)$row->slotnumber] = $row;
        }
        return $byslot;
    }

    /**
     * Load all stash mappings for a quiz, keyed by slot number.
     *
     * @param int $quizid The quiz instance ID.
     * @return array<int, \stdClass> Map of slotnumber to mapping record.
     */
    public static function get_for_quiz(int $quizid): array {
        return self::get_for_activity(self::resolve_quiz_cmid($quizid), $quizid, 'quiz');
    }

    /**
     * Return the mapping record for a single activity slot.
     *
     * @param int $cmid The course-module ID.
     * @param int $slotnumber The slot number.
     * @param int $instanceid Optional legacy quiz instance ID.
     * @param string $modname The module name.
     * @return \stdClass|null The mapping record or null.
     */
    public static function get_mapping_for_activity_slot(
        int $cmid,
        int $slotnumber,
        int $instanceid = 0,
        string $modname = 'quiz'
    ): ?\stdClass {
        $all = self::get_for_activity($cmid, $instanceid, $modname);
        return $all[$slotnumber] ?? null;
    }

    /**
     * Return slot numbers used by an activity, sorted ascending.
     *
     * @param int $cmid The course-module ID.
     * @param string $modname The module name.
     * @param int $instanceid The activity instance ID.
     * @return int[] Sorted slot numbers.
     */
    public static function get_activity_slots(int $cmid, string $modname = 'quiz', int $instanceid = 0): array {
        if ($modname !== 'quiz' || $instanceid <= 0) {
            return [];
        }
        return array_keys(question_map_service::get_quiz_slot_records($instanceid));
    }

    /**
     * Return slot numbers used by a quiz, sorted ascending.
     *
     * @param int $quizid The quiz instance ID.
     * @return int[] Sorted slot numbers.
     */
    public static function get_quiz_slots(int $quizid): array {
        return self::get_activity_slots(self::resolve_quiz_cmid($quizid), 'quiz', $quizid);
    }

    /**
     * Return block_stash items available in a course as an id-to-name map.
     *
     * Returns an empty array when block_stash is not installed or has no items.
     *
     * @param int $courseid The Moodle course ID.
     * @return array<int, string> Map of itemid to item name (0 = "no item" option).
     */
    public static function get_stash_items_for_course(int $courseid): array {
        if (!availability::has_block_stash()) {
            return [];
        }
        try {
            global $DB;
            $ctx = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$ctx) {
                return [];
            }
            if (
                !$DB->record_exists('block_instances', [
                    'blockname' => 'stash',
                    'parentcontextid' => $ctx->id,
                ])
            ) {
                return [];
            }
            $stash = $DB->get_record('block_stash', ['courseid' => $courseid]);
            if (!$stash) {
                return [];
            }
            $dbitems = $DB->get_records('block_stash_items', ['stashid' => $stash->id], 'name ASC');
            if (!$dbitems) {
                return [];
            }
            $options = [0 => get_string('stashmapping_noitem', 'local_stackmathgame')];
            foreach ($dbitems as $dbitem) {
                $options[(int)$dbitem->id] = format_string($dbitem->name);
            }
            return $options;
        } catch (\Throwable $e) {
            debugging(
                'local_stackmathgame stash_mapping_service: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return [];
        }
    }

    /**
     * Upsert stash mappings submitted from the activity settings form.
     *
     * Each entry in $mappings must have keys:
     *   slotnumber (int), stashitemid (int, 0=delete), grantquantity (int), enabled (int 0|1)
     *
     * @param int $cmid The course-module ID.
     * @param int $courseid The course ID (stored as stashcourseid).
     * @param array $mappings Array of mapping arrays from form submission.
     * @param string $modname The module name.
     * @param int $instanceid The activity instance ID.
     * @return void
     */
    public static function save_for_activity(
        int $cmid,
        int $courseid,
        array $mappings,
        string $modname = 'quiz',
        int $instanceid = 0
    ): void {
        global $DB;
        $now = time();
        $quizid = $modname === 'quiz' ? $instanceid : 0;

        foreach ($mappings as $entry) {
            $slot = (int)($entry['slotnumber'] ?? 0);
            if ($slot <= 0) {
                continue;
            }
            $itemid = (int)($entry['stashitemid'] ?? 0);
            $qty = max(1, (int)($entry['grantquantity'] ?? 1));
            $enabled = empty($entry['enabled']) ? 0 : 1;

            $existing = self::find_existing_mapping($cmid, $quizid, $slot);

            if ($itemid <= 0) {
                if ($existing) {
                    $DB->delete_records('local_stackmathgame_stashmap', ['id' => $existing->id]);
                }
                continue;
            }

            if ($existing) {
                $update = [
                    'id' => $existing->id,
                    'stashcourseid' => $courseid,
                    'stashitemid' => $itemid,
                    'grantquantity' => $qty,
                    'enabled' => $enabled,
                    'timemodified' => $now,
                ];
                if (self::stashmap_has_field('cmid')) {
                    $update['cmid'] = $cmid;
                }
                if (self::stashmap_has_field('quizid') && $quizid > 0) {
                    $update['quizid'] = $quizid;
                }
                $DB->update_record('local_stackmathgame_stashmap', (object)$update);
            } else {
                $insert = [
                    'slotnumber' => $slot,
                    'stashcourseid' => $courseid,
                    'stashitemid' => $itemid,
                    'grantquantity' => $qty,
                    'mode' => 'firstsolve',
                    'enabled' => $enabled,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                if (self::stashmap_has_field('cmid')) {
                    $insert['cmid'] = $cmid;
                }
                if (self::stashmap_has_field('quizid')) {
                    $insert['quizid'] = $quizid;
                }
                $DB->insert_record('local_stackmathgame_stashmap', (object)$insert);
            }
        }
    }

    /**
     * Legacy wrapper that persists mappings for a quiz.
     *
     * @param int $quizid The quiz instance ID.
     * @param int $courseid The course ID.
     * @param array $mappings Mapping payload.
     * @return void
     */
    public static function save_for_quiz(int $quizid, int $courseid, array $mappings): void {
        self::save_for_activity(self::resolve_quiz_cmid($quizid), $courseid, $mappings, 'quiz', $quizid);
    }

    /**
     * Backfill cmid values for all stash mappings that still only carry quizid.
     *
     * @return int Number of rows updated.
     */
    public static function backfill_legacy_quiz_rows(): int {
        global $DB;

        if (!self::stashmap_has_field('cmid') || !self::stashmap_has_field('quizid')) {
            return 0;
        }

        $updated = 0;
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
            $cmid = self::resolve_quiz_cmid((int)$row->quizid);
            if ($cmid > 0) {
                $DB->set_field('local_stackmathgame_stashmap', 'cmid', $cmid, ['id' => $row->id]);
                $updated++;
            }
        }
        return $updated;
    }

    /**
     * Resolve the quiz cmid from a quiz instance ID.
     *
     * @param int $quizid The quiz instance ID.
     * @return int The course-module ID or 0 when unavailable.
     */
    private static function resolve_quiz_cmid(int $quizid): int {
        if ($quizid <= 0) {
            return 0;
        }
        $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false, IGNORE_MISSING);
        return $cm ? (int)$cm->id : 0;
    }

    /**
     * Return whether local_stackmathgame_stashmap has a given field.
     *
     * @param string $fieldname The field name.
     * @return bool True when the field exists.
     */
    private static function stashmap_has_field(string $fieldname): bool {
        global $DB;

        static $cache = [];
        if (array_key_exists($fieldname, $cache)) {
            return $cache[$fieldname];
        }

        $table = new xmldb_table('local_stackmathgame_stashmap');
        $field = new xmldb_field($fieldname);
        $cache[$fieldname] = $DB->get_manager()->field_exists($table, $field);
        return $cache[$fieldname];
    }

    /**
     * Find an existing stash mapping row using cmid first and quizid fallback.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @param int $slotnumber The slot number.
     * @return \stdClass|false The mapping record or false.
     */
    private static function find_existing_mapping(int $cmid, int $quizid, int $slotnumber) {
        global $DB;

        if ($cmid > 0 && self::stashmap_has_field('cmid')) {
            $existing = $DB->get_record('local_stackmathgame_stashmap', [
                'cmid' => $cmid,
                'slotnumber' => $slotnumber,
            ]);
            if ($existing) {
                return $existing;
            }
        }

        if ($quizid > 0 && self::stashmap_has_field('quizid')) {
            return $DB->get_record('local_stackmathgame_stashmap', [
                'quizid' => $quizid,
                'slotnumber' => $slotnumber,
            ]);
        }

        return false;
    }
}
