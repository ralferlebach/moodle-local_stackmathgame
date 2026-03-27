<?php
namespace local_stackmathgame\local\integration;

defined('MOODLE_INTERNAL') || die();

/**
 * Soft bridge for block_xp.
 */
final class xp_bridge {
    public static function dispatch(\stdClass $profile, int $quizid, int $designid, int $slot, array $slotdata, array $deltas): array {
        if (!availability::has_block_xp()) {
            return ['available' => false, 'dispatched' => false];
        }

        $context = null;
        if ($quizid > 0 && ($cm = get_coursemodule_from_instance('quiz', $quizid, 0, false))) {
            $context = \context_module::instance((int)$cm->id);
        }
        if (!$context) {
            $context = \context_system::instance();
        }

        $payload = [
            'slot' => $slot,
            'state' => (string)($slotdata['state'] ?? ''),
            'scoredelta' => (int)($deltas['score'] ?? 0),
            'xpdelta' => (int)($deltas['xp'] ?? 0),
            'questionid' => (int)($slotdata['questionid'] ?? 0),
            'designid' => $designid,
            'quizid' => $quizid,
        ];

        \local_stackmathgame\event\progress_updated::create([
            'context' => $context,
            'userid' => (int)$profile->userid,
            'relateduserid' => (int)$profile->userid,
            'objectid' => (int)$profile->id,
            'other' => $payload + ['labelid' => (int)$profile->labelid],
        ])->trigger();

        if (!empty($deltas['solved'])) {
            \local_stackmathgame\event\question_solved::create([
                'context' => $context,
                'userid' => (int)$profile->userid,
                'relateduserid' => (int)$profile->userid,
                'objectid' => (int)$profile->id,
                'other' => $payload + ['labelid' => (int)$profile->labelid],
            ])->trigger();
        }

        return ['available' => true, 'dispatched' => true];
    }
}
