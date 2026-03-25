<?php
namespace local_stackmathgame\game;

defined('MOODLE_INTERNAL') || die();

/**
 * Small placeholder state service used by the local plugin.
 */
class state_machine {
    public static function export_attempt_context(int $quizid, int $userid): array {
        return [
            'quizid' => $quizid,
            'userid' => $userid,
            'timestamp' => time(),
        ];
    }
}
