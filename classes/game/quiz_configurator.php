<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Quiz configuration persistence for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\game;

/**
 * Quiz-level configuration persistence.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_configurator {
    /**
     * Retrieve the game configuration for a quiz, or null if none exists.
     *
     * @param int $quizid The quiz instance ID.
     * @return \stdClass|null The configuration record, or null.
     */
    public static function get_plugin_config(int $quizid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_stackmathgame_quizcfg', ['quizid' => $quizid]) ?: null;
    }

    /**
     * Ensure a default configuration row exists for the given quiz and return it.
     *
     * @param int $quizid The quiz instance ID.
     * @return \stdClass The configuration record.
     */
    public static function ensure_default(int $quizid): \stdClass {
        global $DB, $USER;

        $record = self::get_plugin_config($quizid);
        if ($record) {
            return $record;
        }

        $quiz     = $DB->get_record('quiz', ['id' => $quizid], 'id,course', MUST_EXIST);
        $labelid  = self::ensure_default_label();
        $designid = theme_manager::ensure_default_design();
        $now      = time();
        $data     = (object)[
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

    /**
     * Persist updated settings for a quiz game configuration.
     *
     * Guards against labelid=0 to prevent FK violations (PostgreSQL).
     *
     * @param int   $quizid The quiz instance ID.
     * @param array $data   Form data to persist.
     * @return \stdClass The updated configuration record.
     */
    public static function save_for_quiz(int $quizid, array $data): \stdClass {
        global $DB, $USER;

        $record = self::ensure_default($quizid);

        // Guard: if labelid=0, preserve the existing value to avoid FK violation.
        $newlabelid = (int)($data['labelid'] ?? 0);
        if ($newlabelid <= 0) {
            $newlabelid = (int)$record->labelid;
        }

        // Guard: same for designid.
        $newdesignid = (int)($data['designid'] ?? 0);
        if ($newdesignid <= 0) {
            $newdesignid = (int)$record->designid;
        }

        $configarr = (array)(
            $data['config'] ?? json_decode((string)$record->configjson, true) ?: []
        );

        $update = (object)[
            'id'                => $record->id,
            'labelid'           => $newlabelid,
            'designid'          => $newdesignid,
            'enabled'           => empty($data['enabled']) ? 0 : 1,
            'requiresbehaviour' => empty($data['requiresbehaviour']) ? 0 : 1,
            'teacherdisplayname' => $data['teacherdisplayname'] ?? $record->teacherdisplayname,
            'configjson'        => json_encode($configarr, JSON_UNESCAPED_UNICODE),
            'timemodified'      => time(),
            'modifiedby'        => empty($USER->id) ? null : (int)$USER->id,
        ];

        $DB->update_record('local_stackmathgame_quizcfg', $update);
        return self::get_plugin_config($quizid);
    }

    /**
     * Ensure the default label exists and return its ID.
     *
     * @return int The label ID.
     */
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
            'description'  => 'Default global STACK Math Game label.',
            'status'       => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
            'createdby'    => null,
            'timedeleted'  => null,
        ]);
    }
}
