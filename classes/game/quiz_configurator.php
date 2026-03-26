<?php
namespace local_stackmathgame\game;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz-level configuration persistence.
 */
class quiz_configurator {
    public static function get_plugin_config(int $quizid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_stackmathgame_quizcfg', ['quizid' => $quizid]) ?: null;
    }

    public static function ensure_default(int $quizid): \stdClass {
        global $DB, $USER;

        $record = self::get_plugin_config($quizid);
        if ($record) {
            return $record;
        }

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id,course', MUST_EXIST);
        $labelid = self::ensure_default_label();
        $designid = theme_manager::ensure_default_design();
        $now = time();
        $data = (object)[
            'courseid' => (int)$quiz->course,
            'quizid' => $quizid,
            'labelid' => $labelid,
            'designid' => $designid,
            'enabled' => 0,
            'requiresbehaviour' => 1,
            'configjson' => json_encode([], JSON_UNESCAPED_UNICODE),
            'teacherdisplayname' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => empty($USER->id) ? null : (int)$USER->id,
            'modifiedby' => empty($USER->id) ? null : (int)$USER->id,
        ];

        $id = $DB->insert_record('local_stackmathgame_quizcfg', $data);
        return $DB->get_record('local_stackmathgame_quizcfg', ['id' => $id], '*', MUST_EXIST);
    }

    public static function save_for_quiz(int $quizid, array $data): \stdClass {
        global $DB, $USER;
        $record = self::ensure_default($quizid);
        $update = (object)[
            'id' => $record->id,
            'labelid' => (int)($data['labelid'] ?? $record->labelid),
            'designid' => (int)($data['designid'] ?? $record->designid),
            'enabled' => empty($data['enabled']) ? 0 : 1,
            'requiresbehaviour' => empty($data['requiresbehaviour']) ? 0 : 1,
            'teacherdisplayname' => $data['teacherdisplayname'] ?? $record->teacherdisplayname,
            'configjson' => json_encode((array)($data['config'] ?? json_decode((string)$record->configjson, true) ?: []), JSON_UNESCAPED_UNICODE),
            'timemodified' => time(),
            'modifiedby' => empty($USER->id) ? null : (int)$USER->id,
        ];
        $DB->update_record('local_stackmathgame_quizcfg', $update);
        return self::get_plugin_config($quizid);
    }

    public static function ensure_default_label(): int {
        global $DB, $SITE;
        $existing = $DB->get_record('local_stackmathgame_label', ['name' => 'default'], 'id');
        if ($existing) {
            return (int)$existing->id;
        }
        $now = time();
        return (int)$DB->insert_record('local_stackmathgame_label', (object)[
            'name' => 'default',
            'idnumber' => 'default',
            'description' => 'Default global STACK Math Game label',
            'status' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => (int)($SITE->id ?? 0),
            'timedeleted' => null,
        ]);
    }
}
