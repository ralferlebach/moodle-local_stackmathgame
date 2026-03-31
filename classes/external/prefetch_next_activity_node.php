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
 * External function: prefetch_next_activity_node.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

use local_stackmathgame\local\service\profile_service;
use local_stackmathgame\local\service\question_map_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Return the next mapped node or next quiz slot as prefetch data.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prefetch_next_activity_node extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course-module id'),
            'modname' => new \external_value(PARAM_PLUGIN, 'Activity module name', VALUE_DEFAULT, 'quiz'),
            'instanceid' => new \external_value(PARAM_INT, 'Activity instance id', VALUE_DEFAULT, 0),
            'currentslot' => new \external_value(PARAM_INT, 'Current slot number', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid The course-module ID.
     * @param string $modname The activity module name.
     * @param int $instanceid The activity instance ID.
     * @param int $currentslot The currently active slot.
     * @return array The next-node payload.
     */
    public static function execute(
        int $cmid,
        string $modname = 'quiz',
        int $instanceid = 0,
        int $currentslot = 0
    ): array {
        [, , $config, $profile, , $activity] = api::validate_activity_access($cmid, $modname, $instanceid);
        if (!api::activity_supports_question_flow($activity)) {
            $payload = self::end_payload();
            return array_merge(api::export_activity($activity), [
                'currentslot' => $currentslot,
                'nextnode' => $payload,
            ]);
        }

        $quizid = (int)$activity['quizid'];
        $progress = profile_service::decode_json_field($profile->progressjson ?? '{}');
        $slotsprogress = (array)($progress['slots'] ?? []);
        $next = api::get_question_map_after_slot((int)$activity['cmid'], $quizid, $currentslot);

        $record = null;
        foreach ($next as $candidate) {
            $slotkey = (string)$candidate->slotnumber;
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
            $slot = null;
            foreach (question_map_service::get_quiz_slot_records($quizid) as $candidate) {
                if ((int)$candidate->slotnumber > $currentslot) {
                    $slot = $candidate;
                    break;
                }
            }

            if ($slot) {
                $slotstate = profile_service::get_slot_state($profile, (int)$slot->slotnumber);
                $payload = [
                    'slotnumber' => (int)$slot->slotnumber,
                    'questionid' => (int)$slot->questionid,
                    'nodekey' => 'slot_' . (int)$slot->slotnumber,
                    'nodetype' => 'question',
                    'sortorder' => (int)$slot->slotnumber,
                    'configjson' => json_encode(['slotstate' => $slotstate], JSON_UNESCAPED_UNICODE),
                ];
            } else {
                $payload = self::end_payload();
            }
        } else {
            $payload = [
                'slotnumber' => (int)$record->slotnumber,
                'questionid' => (int)$record->questionid,
                'nodekey' => (string)$record->nodekey,
                'nodetype' => (string)$record->nodetype,
                'sortorder' => (int)$record->sortorder,
                'configjson' => (string)($record->configjson ?? '{}'),
            ];
        }

        api::log_event(
            $profile,
            $quizid,
            (int)$config->designid,
            'prefetch_next_node',
            'external.prefetch_next_activity_node',
            ['currentslot' => $currentslot] + $payload,
            0,
            '',
            $activity
        );

        return array_merge(api::export_activity($activity), [
            'currentslot' => $currentslot,
            'nextnode' => $payload,
        ]);
    }

    /**
     * Return the canonical end payload.
     *
     * @return array End payload.
     */
    private static function end_payload(): array {
        return [
            'slotnumber' => 0,
            'questionid' => 0,
            'nodekey' => '',
            'nodetype' => 'end',
            'sortorder' => 0,
            'configjson' => json_encode(['slotstate' => ''], JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Describe return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'cmid' => new \external_value(PARAM_INT, 'Course-module id'),
            'modname' => new \external_value(PARAM_PLUGIN, 'Activity module name'),
            'instanceid' => new \external_value(PARAM_INT, 'Activity instance id'),
            'quizid' => new \external_value(PARAM_INT, 'Legacy quiz id when applicable'),
            'currentslot' => new \external_value(PARAM_INT, 'Current slot number'),
            'nextnode' => get_quiz_config::questionmap_structure(),
        ]);
    }
}
