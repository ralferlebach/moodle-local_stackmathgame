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

/**
 * Load and persist mappings between quiz slots and block_stash items.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stash_mapping_service {
    /**
     * Load all stash mappings for a quiz, keyed by slot number.
     *
     * @param int $quizid The quiz instance ID.
     * @return array<int, \stdClass> Map of slotnumber to mapping record.
     */
    public static function get_for_quiz(int $quizid): array {
        global $DB;
        $byslot = [];
        foreach ($DB->get_records('local_stackmathgame_stashmap', ['quizid' => $quizid]) as $r) {
            $byslot[(int)$r->slotnumber] = $r;
        }
        return $byslot;
    }

    /**
     * Return slot numbers used by a quiz, sorted ascending.
     *
     * @param int $quizid The quiz instance ID.
     * @return int[] Sorted slot numbers.
     */
    public static function get_quiz_slots(int $quizid): array {
        global $DB;
        $slots = array_map(
            'intval',
            $DB->get_fieldset_select('quiz_slots', 'slot', 'quizid = :q', ['q' => $quizid])
        );
        sort($slots);
        return $slots;
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
            $manager = \block_stash\manager::get($courseid);
            if (!$manager->is_enabled()) {
                return [];
            }
            $options = [0 => get_string('stashmapping_noitem', 'local_stackmathgame')];
            foreach ($manager->get_items() as $item) {
                $options[(int)$item->get_id()] = format_string($item->get_name());
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
     * Upsert stash mappings submitted from the quiz settings form.
     *
     * Each entry in $mappings must have keys:
     *   slotnumber (int), stashitemid (int, 0=delete), grantquantity (int), enabled (int 0|1)
     *
     * @param int   $quizid   The quiz instance ID.
     * @param int   $courseid The course ID (stored as stashcourseid).
     * @param array $mappings Array of mapping arrays from form submission.
     * @return void
     */
    public static function save_for_quiz(int $quizid, int $courseid, array $mappings): void {
        global $DB;
        $now = time();

        foreach ($mappings as $entry) {
            $slot = (int)($entry['slotnumber'] ?? 0);
            if ($slot <= 0) {
                continue;
            }
            $itemid = (int)($entry['stashitemid'] ?? 0);
            $qty = max(1, (int)($entry['grantquantity'] ?? 1));
            $enabled = empty($entry['enabled']) ? 0 : 1;

            $existing = $DB->get_record(
                'local_stackmathgame_stashmap',
                ['quizid' => $quizid, 'slotnumber' => $slot]
            );

            if ($itemid <= 0) {
                if ($existing) {
                    $DB->delete_records(
                        'local_stackmathgame_stashmap',
                        ['quizid' => $quizid, 'slotnumber' => $slot]
                    );
                }
                continue;
            }

            if ($existing) {
                $DB->update_record('local_stackmathgame_stashmap', (object)[
                    'id' => $existing->id,
                    'stashcourseid' => $courseid,
                    'stashitemid' => $itemid,
                    'grantquantity' => $qty,
                    'enabled' => $enabled,
                    'timemodified' => $now,
                ]);
            } else {
                $DB->insert_record('local_stackmathgame_stashmap', (object)[
                    'quizid' => $quizid,
                    'slotnumber' => $slot,
                    'stashcourseid' => $courseid,
                    'stashitemid' => $itemid,
                    'grantquantity' => $qty,
                    'mode' => 'firstsolve',
                    'enabled' => $enabled,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
        }
    }
}
