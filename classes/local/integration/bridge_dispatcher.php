<?php
namespace local_stackmathgame\local\integration;

defined('MOODLE_INTERNAL') || die();

/**
 * Dispatch optional integration bridges.
 */
final class bridge_dispatcher {
    /**
     * Trigger optional bridges after a processed answer result.
     *
     * @param \stdClass $profile
     * @param int $quizid
     * @param int $designid
     * @param int $slot
     * @param array $slotdata
     * @param array $deltas
     * @return array
     */
    public static function on_answer_result(\stdClass $profile, int $quizid, int $designid, int $slot, array $slotdata, array $deltas): array {
        $result = [
            'xp' => xp_bridge::dispatch($profile, $quizid, $designid, $slot, $slotdata, $deltas),
            'stash' => stash_bridge::dispatch($profile, $quizid, $designid, $slot, $slotdata, $deltas),
        ];
        return $result;
    }
}
