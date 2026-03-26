<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Returns the next mapped node or next quiz slot as lightweight prefetch data.
 */
class prefetch_next_node extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'currentslot' => new \external_value(PARAM_INT, 'Current slot number', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $quizid, int $currentslot = 0): array {
        global $DB;
        [, , $config, $profile] = api::validate_quiz_access($quizid);
        $next = $DB->get_records_select('local_stackmathgame_questionmap', 'quizid = ? AND slotnumber > ?', [$quizid, $currentslot], 'sortorder ASC, slotnumber ASC', '*', 0, 1);
        $record = $next ? reset($next) : null;

        if (!$record) {
            $sql = 'SELECT id, slot AS slotnumber, questionid FROM {quiz_slots} WHERE quizid = ? AND slot > ? ORDER BY slot ASC';
            $slots = $DB->get_records_sql($sql, [$quizid, $currentslot], 0, 1);
            $slot = $slots ? reset($slots) : null;
            if ($slot) {
                $payload = [
                    'slotnumber' => (int)$slot->slotnumber,
                    'questionid' => (int)$slot->questionid,
                    'nodekey' => 'slot_' . (int)$slot->slotnumber,
                    'nodetype' => 'question',
                    'sortorder' => (int)$slot->slotnumber,
                    'configjson' => '{}',
                ];
            } else {
                $payload = [
                    'slotnumber' => 0,
                    'questionid' => 0,
                    'nodekey' => '',
                    'nodetype' => 'end',
                    'sortorder' => 0,
                    'configjson' => '{}',
                ];
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

        api::log_event($profile, $quizid, (int)$config->designid, 'prefetch_next_node', 'external.prefetch_next_node', ['currentslot' => $currentslot] + $payload);
        return [
            'quizid' => $quizid,
            'currentslot' => $currentslot,
            'nextnode' => $payload,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'currentslot' => new \external_value(PARAM_INT, 'Current slot number'),
            'nextnode' => get_quiz_config::questionmap_structure(),
        ]);
    }
}
