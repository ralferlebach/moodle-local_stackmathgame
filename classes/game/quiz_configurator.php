<?php
namespace local_stackmathgame\game;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz-level configuration persistence.
 *
 * Fixed issues:
 * 1. save_for_quiz() accepted labelid=0 from form data without validation.
 *    A labelid of 0 breaks the foreign-key constraint on the quizcfg table
 *    (labelid_fk references local_stackmathgame_label.id) and caused DB
 *    errors on installations with strict FK enforcement (PostgreSQL).
 *    If the incoming labelid is 0 or missing, the existing value is preserved.
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

        $quiz    = $DB->get_record('quiz', ['id' => $quizid], 'id,course', MUST_EXIST);
        $labelid = self::ensure_default_label();
        $designid = theme_manager::ensure_default_design();
        $now  = time();
        $data = (object)[
            'courseid'          => (int)$quiz->course,
            'quizid'            => $quizid,
            'labelid'           => $labelid,
            'designid'          => $designid,
            'enabled'           => 0,
            'requiresbehaviour' => 1,
            'configjson'        => json_encode([], JSON_UNESCAPED_UNICODE),
            'teacherdisplayname' => null,
            'timecreated'       => $now,
            'timemodified'      => $now,
            'createdby'         => empty($USER->id) ? null : (int)$USER->id,
            'modifiedby'        => empty($USER->id) ? null : (int)$USER->id,
        ];

        $id = $DB->insert_record('local_stackmathgame_quizcfg', $data);
        return $DB->get_record('local_stackmathgame_quizcfg', ['id' => $id], '*', MUST_EXIST);
    }

    public static function save_for_quiz(int $quizid, array $data): \stdClass {
        global $DB, $USER;

        $record = self::ensure_default($quizid);

        // *** BUG FIX: guard against labelid=0 to prevent FK violation. ***
        // If the caller passes labelid=0 (e.g. the form was submitted without
        // selecting a label), keep the existing labelid rather than writing an
        // invalid 0 reference into the quizcfg table.
        $newlabelid = (int)($data['labelid'] ?? 0);
        if ($newlabelid <= 0) {
            $newlabelid = (int)$record->labelid;  // Preserve existing value.
        }

        // Same guard for designid.
        $newdesignid = (int)($data['designid'] ?? 0);
        if ($newdesignid <= 0) {
            $newdesignid = (int)$record->designid;
        }

        $update = (object)[
            'id'                => $record->id,
            'labelid'           => $newlabelid,
            'designid'          => $newdesignid,
            'enabled'           => empty($data['enabled'])           ? 0 : 1,
            'requiresbehaviour' => empty($data['requiresbehaviour']) ? 0 : 1,
            'teacherdisplayname' => $data['teacherdisplayname'] ?? $record->teacherdisplayname,
            'configjson'        => json_encode(
                (array)($data['config'] ?? json_decode((string)$record->configjson, true) ?: []),
                JSON_UNESCAPED_UNICODE
            ),
            'timemodified'      => time(),
            'modifiedby'        => empty($USER->id) ? null : (int)$USER->id,
        ];

        $DB->update_record('local_stackmathgame_quizcfg', $update);
        return self::get_plugin_config($quizid);
    }

    public static function ensure_default_label(): int {
        global $DB;
        $existing = $DB->get_record('local_stackmathgame_label', ['name' => 'default'], 'id');
        if ($existing) {
            return (int)$existing->id;
        }
        $now = time();
        return (int)$DB->insert_record('local_stackmathgame_label', (object)[
            'name'         => 'default',
            'idnumber'     => 'default',
            'description'  => 'Default global STACK Math Game label',
            'status'       => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
            'createdby'    => null,
            'timedeleted'  => null,
        ]);
    }
}
