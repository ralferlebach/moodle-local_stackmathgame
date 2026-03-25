<?php
namespace local_stackmathgame\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Lightweight event helper namespace placeholder.
 */
class events {
    public static function quiz_game_enabled(int $quizid): array {
        return ['quizid' => $quizid, 'time' => time()];
    }
}
