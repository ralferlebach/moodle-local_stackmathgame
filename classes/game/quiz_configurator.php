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
        global $DB;

        $record = self::get_plugin_config($quizid);
        if ($record) {
            return $record;
        }

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id,course', MUST_EXIST);
        $designid = self::get_default_design_id();
        $labelid = self::get_default_label_id();
        $now = time();
        $id = $DB->insert_record('local_stackmathgame_quizcfg', (object)[
            'courseid' => (int)$quiz->course,
            'quizid' => $quizid,
            'labelid' => $labelid,
            'designid' => $designid,
            'enabled' => 0,
            'requiresbehaviour' => 1,
            'configjson' => json_encode([]),
            'teacherdisplayname' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => null,
            'modifiedby' => null,
        ]);

        return $DB->get_record('local_stackmathgame_quizcfg', ['id' => $id], '*', MUST_EXIST);
    }

    public static function save_for_quiz(int $quizid, int $userid, array $data): \stdClass {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id,course', MUST_EXIST);
        $record = self::ensure_default($quizid);
        $now = time();

        $record->courseid = (int)$quiz->course;
        $record->quizid = $quizid;
        $record->labelid = self::resolve_label_id((int)($data['labelid'] ?? 0), (string)($data['newlabel'] ?? ''), $userid);
        $record->designid = max(1, (int)($data['designid'] ?? 0));
        $record->enabled = empty($data['enabled']) ? 0 : 1;
        $record->teacherdisplayname = trim((string)($data['teacherdisplayname'] ?? '')) ?: null;
        $record->timemodified = $now;
        $record->modifiedby = $userid;

        $config = [];
        if (!empty($record->configjson)) {
            $config = json_decode((string)$record->configjson, true) ?: [];
        }
        $config['labelsource'] = trim((string)($data['newlabel'] ?? '')) !== '' ? 'created' : 'selected';
        $record->configjson = json_encode($config, JSON_UNESCAPED_UNICODE);

        $DB->update_record('local_stackmathgame_quizcfg', $record);
        return $DB->get_record('local_stackmathgame_quizcfg', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * @return \stdClass[]
     */
    public static function get_available_designs(): array {
        return theme_manager::get_all_enabled();
    }

    /**
     * @return array<int,string>
     */
    public static function get_label_options(): array {
        global $DB;

        $records = $DB->get_records('local_stackmathgame_label', ['status' => 1], 'name ASC', 'id,name');
        $options = [];
        foreach ($records as $record) {
            $options[(int)$record->id] = format_string((string)$record->name);
        }
        return $options;
    }

    private static function get_default_design_id(): int {
        global $DB;
        $record = $DB->get_records('local_stackmathgame_design', ['isactive' => 1], 'isbundled DESC, id ASC', 'id', 0, 1);
        if (!$record) {
            theme_manager::seed_default_theme();
            $record = $DB->get_records('local_stackmathgame_design', ['isactive' => 1], 'isbundled DESC, id ASC', 'id', 0, 1);
        }
        if (!$record) {
            throw new \moodle_exception('nodesignsavailable', 'local_stackmathgame');
        }
        return (int)reset($record)->id;
    }

    private static function get_default_label_id(): int {
        global $DB;

        $label = $DB->get_records('local_stackmathgame_label', ['status' => 1], 'id ASC', 'id', 0, 1);
        if ($label) {
            return (int)reset($label)->id;
        }

        $now = time();
        return (int)$DB->insert_record('local_stackmathgame_label', (object)[
            'name' => 'default',
            'idnumber' => 'default',
            'description' => null,
            'status' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => null,
            'timedeleted' => null,
        ]);
    }

    private static function resolve_label_id(int $labelid, string $newlabel, int $userid): int {
        global $DB;

        $newlabel = trim($newlabel);
        if ($newlabel !== '') {
            $existing = $DB->get_record_sql(
                "SELECT * FROM {local_stackmathgame_label} WHERE " . $DB->sql_compare_text('name') . " = " . $DB->sql_compare_text(':name'),
                ['name' => $newlabel]
            );
            if ($existing) {
                return (int)$existing->id;
            }

            $now = time();
            return (int)$DB->insert_record('local_stackmathgame_label', (object)[
                'name' => $newlabel,
                'idnumber' => null,
                'description' => null,
                'status' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
                'createdby' => $userid ?: null,
                'timedeleted' => null,
            ]);
        }

        if ($labelid > 0 && $DB->record_exists('local_stackmathgame_label', ['id' => $labelid])) {
            return $labelid;
        }

        return self::get_default_label_id();
    }
}
