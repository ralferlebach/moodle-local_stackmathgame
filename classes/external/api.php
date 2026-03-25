<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Small helper facade for future external functions.
 */
class api {
    public static function normalise_question_payload(array $payload): array {
        return [
            'questionid' => (int)($payload['questionid'] ?? 0),
            'slot' => (int)($payload['slot'] ?? 0),
            'answers' => (array)($payload['answers'] ?? []),
        ];
    }
}
