<?php
namespace local_stackmathgame\game;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz-level configuration persistence.
 */
class quiz_configurator {
    public static function get_plugin_config(int $quizid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_stackmathgame_cfg', ['quizid' => $quizid]) ?: null;
    }

    public static function ensure_default(int $quizid): \stdClass {
        global $DB;

        $record = self::get_plugin_config($quizid);
        if ($record) {
            return $record;
        }

        $now = time();
        $id = $DB->insert_record('local_stackmathgame_cfg', (object)[
            'quizid' => $quizid,
            'enabled' => 0,
            'labelid' => 0,
            'themeid' => 0,
            'configjson' => json_encode([]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return $DB->get_record('local_stackmathgame_cfg', ['id' => $id], '*', MUST_EXIST);
    }
}
