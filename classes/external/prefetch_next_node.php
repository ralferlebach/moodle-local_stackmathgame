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
 * External function: prefetch_next_node.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Return the next mapped node or next quiz slot as prefetch data.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prefetch_next_node extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid'      => new \external_value(PARAM_INT, 'Quiz id'),
            'currentslot' => new \external_value(
                PARAM_INT,
                'Current slot number',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $quizid      The quiz instance ID.
     * @param int $currentslot The currently active slot.
     * @return array The next-node payload.
     */
    public static function execute(int $quizid, int $currentslot = 0): array {
        global $DB;

        [, , $config, $profile] = api::validate_quiz_access($quizid);

        $progress      = \local_stackmathgame\local\service\profile_service::decode_json_field(
            $profile->progressjson ?? '{}'
        );
        $slotsprogress = (array)($progress['slots'] ?? []);

        $next = $DB->get_records_select(
            'local_stackmathgame_questionmap',
            'quizid = ? AND slotnumber > ?',
            [$quizid, $currentslot],
            'sortorder ASC, slotnumber ASC'
        );

        $record = null;
        foreach ($next as $candidate) {
            $slotkey  = (string)$candidate->slotnumber;
            $slotstate = '';
            if (isset($slotsprogress[$slotkey])) {
                $slotstate = is_array($slotsprogress[$slotkey])
                    ? (string)($slotsprogress[$slotkey]['state'] ?? '')
                    : (string)$slotsprogress[$slotkey];
            }
            if (!in_array($slotstate, ['gradedright', 'complete'], true)) {
                $record = $candidate;
                break;
            }
        }
        if (!$record && $next) {
            $record = reset($next);
        }

        if (!$record) {
            $sql   = 'SELECT id, slot AS slotnumber, questionid'
                   . '  FROM {quiz_slots}'
                   . ' WHERE quizid = ? AND slot > ?'
                   . ' ORDER BY slot ASC';
            $slots = $DB->get_records_sql($sql, [$quizid, $currentslot], 0, 1);
            $slot  = $slots ? reset($slots) : null;

            if ($slot) {
                $slotstate = \local_stackmathgame\local\service\profile_service::get_slot_state(
                    $profile,
                    (int)$slot->slotnumber
                );
                $payload = [
                    'slotnumber' => (int)$slot->slotnumber,
                    'questionid' => (int)$slot->questionid,
                    'nodekey'    => 'slot_' . (int)$slot->slotnumber,
                    'nodetype'   => 'question',
                    'sortorder'  => (int)$slot->slotnumber,
                    'configjson' => json_encode(
                        ['slotstate' => $slotstate],
                        JSON_UNESCAPED_UNICODE
                    ),
                ];
            } else {
                $payload = [
                    'slotnumber' => 0,
                    'questionid' => 0,
                    'nodekey'    => '',
                    'nodetype'   => 'end',
                    'sortorder'  => 0,
                    'configjson' => json_encode(['slotstate' => ''], JSON_UNESCAPED_UNICODE),
                ];
            }
        } else {
            $payload = [
                'slotnumber' => (int)$record->slotnumber,
                'questionid' => (int)$record->questionid,
                'nodekey'    => (string)$record->nodekey,
                'nodetype'   => (string)$record->nodetype,
                'sortorder'  => (int)$record->sortorder,
                'configjson' => (string)($record->configjson ?? '{}'),
            ];
        }

        api::log_event(
            $profile,
            $quizid,
            (int)$config->designid,
            'prefetch_next_node',
            'external.prefetch_next_node',
            ['currentslot' => $currentslot] + $payload
        );

        return [
            'quizid'      => $quizid,
            'currentslot' => $currentslot,
            'nextnode'    => $payload,
        ];
    }

    /**
     * Describe return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid'      => new \external_value(PARAM_INT, 'Quiz id'),
            'currentslot' => new \external_value(PARAM_INT, 'Current slot number'),
            'nextnode'    => get_quiz_config::questionmap_structure(),
        ]);
    }
}
