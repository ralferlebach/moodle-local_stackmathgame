<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Returns the next mapped node or next quiz slot as lightweight prefetch data.
 *
 * Fixed issue:
 * When no quiz_slots row exists after the current slot (i.e. quiz is at the
 * last question), $slotstate was referenced in the 'end' node payload without
 * ever being assigned – causing an "Undefined variable $slotstate" notice that
 * could manifest as a broken JSON payload.
 */
class prefetch_next_node extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid'      => new \external_value(PARAM_INT, 'Quiz id'),
            'currentslot' => new \external_value(PARAM_INT,
                                 'Current slot number', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $quizid, int $currentslot = 0): array {
        global $DB;

        [, , $config, $profile] = api::validate_quiz_access($quizid);

        $progress      = \local_stackmathgame\local\service\profile_service::decode_json_field(
            $profile->progressjson ?? '{}'
        );
        $slotsprogress = (array)($progress['slots'] ?? []);

        // First try: look in the game's own question map.
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
        // If all mapped nodes are solved, fall back to the first mapped one.
        if (!$record && $next) {
            $record = reset($next);
        }

        if (!$record) {
            // Second try: fall back to raw quiz_slots.
            $sql   = 'SELECT id, slot AS slotnumber, questionid'
                   . '  FROM {quiz_slots}'
                   . ' WHERE quizid = ? AND slot > ?'
                   . ' ORDER BY slot ASC';
            $slots = $DB->get_records_sql($sql, [$quizid, $currentslot], 0, 1);
            $slot  = $slots ? reset($slots) : null;

            if ($slot) {
                // *** BUG FIX: $slotstate was not initialised before use here. ***
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
                // *** BUG FIX: $slotstate was undefined here in the original code. ***
                $payload = [
                    'slotnumber' => 0,
                    'questionid' => 0,
                    'nodekey'    => '',
                    'nodetype'   => 'end',
                    'sortorder'  => 0,
                    'configjson' => json_encode(
                        ['slotstate' => ''],  // No slot – state is empty string.
                        JSON_UNESCAPED_UNICODE
                    ),
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

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid'      => new \external_value(PARAM_INT, 'Quiz id'),
            'currentslot' => new \external_value(PARAM_INT, 'Current slot number'),
            'nextnode'    => get_quiz_config::questionmap_structure(),
        ]);
    }
}
