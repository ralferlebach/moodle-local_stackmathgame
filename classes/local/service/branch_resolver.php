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
 * Branch resolver service for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

/**
 * Resolves the next quiz slot from a player outcome and slot configjson.
 *
 * Resolution priority:
 *   1. Outcome-specific rule (gradedright / gradedwrong / complete).
 *   2. Fallback to branching.default.
 *   3. Linear progression (next slot by slotnumber).
 *   4. 0 when no next slot exists (quiz finished).
 *
 * The JS counterpart in game_engine.js performs client-side resolution using
 * the same configjson data. This server-side resolver is used by submit_answer
 * to provide a canonical nextslot in the web service response.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class branch_resolver {
    /**
     * Resolve the next slot number after a player outcome.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @param int $currentslot The current slot number.
     * @param string $outcome 'gradedright', 'gradedwrong', or 'complete'.
     * @param \stdClass $profile The player profile (reserved for future use).
     * @return int The next slot number, or 0 when the quiz should finish.
     */
    public static function resolve_next_slot(
        int $cmid,
        int $quizid,
        int $currentslot,
        string $outcome,
        \stdClass $profile
    ): int {
        global $DB;

        $validoutcomes = [
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            slot_config_schema::OUTCOME_GRADEDWRONG,
            slot_config_schema::OUTCOME_COMPLETE,
        ];
        if (!in_array($outcome, $validoutcomes, true)) {
            $outcome = slot_config_schema::OUTCOME_DEFAULT;
        }

        question_map_service::ensure_for_cmid($cmid);

        [$keyfield, $keyvalue] = self::questionmap_lookup($cmid, $quizid);
        $maprow = $DB->get_record(
            'local_stackmathgame_questionmap',
            [$keyfield => $keyvalue, 'slotnumber' => $currentslot]
        );

        if ($maprow && !empty($maprow->configjson)) {
            $config = slot_config_schema::parse((string)$maprow->configjson);
            if ($config) {
                $next = self::apply_rule($config, $outcome, $quizid);
                if ($next !== null) {
                    return $next;
                }
            }
        }

        return self::next_linear_slot($quizid, $currentslot);
    }

    /**
     * Load all slot configs for a quiz as a map of slotnumber to config array.
     *
     * Used by inject_game_assets to embed slot rules in the AMD bootstrap call.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @return array<int, array> Map of slotnumber to normalised config.
     */
    public static function get_quiz_slot_configs(int $cmid, int $quizid): array {
        global $DB;

        question_map_service::ensure_for_cmid($cmid);

        [$keyfield, $keyvalue] = self::questionmap_lookup($cmid, $quizid);
        $result = [];
        foreach ($DB->get_records('local_stackmathgame_questionmap', [$keyfield => $keyvalue]) as $row) {
            $config = !empty($row->configjson)
                ? slot_config_schema::parse((string)$row->configjson)
                : null;
            $result[(int)$row->slotnumber] = $config ?? slot_config_schema::defaults();
        }
        return $result;
    }

    /**
     * Apply a branching rule and return the target slot number, or null for linear.
     *
     * @param array $config Normalised slot config.
     * @param string $outcome The player outcome key.
     * @param int $quizid Used to validate slot targets.
     * @return int|null Target slot, or null for linear fallback.
     */
    private static function apply_rule(array $config, string $outcome, int $quizid): ?int {
        global $DB;

        $rule = $config['branching'][$outcome]
            ?? $config['branching'][slot_config_schema::OUTCOME_DEFAULT]
            ?? [];
        $mode = (string)($rule['mode'] ?? slot_config_schema::BRANCH_MODE_LINEAR);

        if ($mode === slot_config_schema::BRANCH_MODE_END) {
            return 0;
        }

        if ($mode === slot_config_schema::BRANCH_MODE_SLOT) {
            $target = (int)($rule['target'] ?? 0);
            if ($target > 0 && $DB->record_exists('quiz_slots', ['quizid' => $quizid, 'slot' => $target])) {
                return $target;
            }
        }

        return null;
    }

    /**
     * Return the next slot number in linear order, or 0 if this is the last.
     *
     * @param int $quizid The quiz instance ID.
     * @param int $currentslot The current slot number.
     * @return int Next slot, or 0 if no next slot exists.
     */
    private static function next_linear_slot(int $quizid, int $currentslot): int {
        global $DB;
        $slots = array_map(
            'intval',
            $DB->get_fieldset_select(
                'quiz_slots',
                'slot',
                'quizid = :q AND slot > :s',
                ['q' => $quizid, 's' => $currentslot]
            )
        );
        if (!$slots) {
            return 0;
        }
        sort($slots);
        return $slots[0];
    }

    /**
     * Return the lookup condition for local_stackmathgame_questionmap.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @return array Two-element array: [fieldname, value].
     */
    private static function questionmap_lookup(int $cmid, int $quizid): array {
        global $DB;

        static $usescmid = null;
        if ($usescmid === null) {
            $table = new \xmldb_table('local_stackmathgame_questionmap');
            $field = new \xmldb_field('cmid');
            $usescmid = $DB->get_manager()->field_exists($table, $field);
        }

        if ($usescmid) {
            return ['cmid', $cmid];
        }
        return ['quizid', $quizid];
    }
}
