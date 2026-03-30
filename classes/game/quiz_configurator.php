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
     * Resolve the course-module id from a quiz instance id.
     *
     * @param int $quizid The quiz instance ID.
     * @return int The cmid, or 0 if not found.
     */
    public static function cmid_from_quizid(int $quizid): int {
        $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false, IGNORE_MISSING);
        return $cm ? (int)$cm->id : 0;
    }

    /**
     * Resolve a course-module record for a supported activity.
     *
     * The course-module ID is the source of truth. When an explicit module name
     * is provided and differs from the stored course-module type, the stored type
     * wins so callers can safely pass default values during migration.
     *
     * @param int $cmid The course-module ID.
     * @param string|null $modname Optional module name hint.
     * @return \stdClass The course-module record.
     */
    public static function get_supported_cm(int $cmid, ?string $modname = null): \stdClass {
        global $DB;

        $sql = "SELECT m.name
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid";
        $actualmodname = $DB->get_field_sql($sql, ['cmid' => $cmid]);
        if (!$actualmodname) {
            throw new \moodle_exception('invalidcoursemodule');
        }

        $modname = (string)$actualmodname;
        $cm = get_coursemodule_from_id($modname, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        $cm->modname = $modname;
        return $cm;
    }

    /**
     * Retrieve the game configuration for a course-module, or null if none exists.
     *
     * The cmid is the source of truth. All lookups use cmid; quizid is
     * derived when quiz-specific tables must be queried.
     *
     * @param int $cmid The course-module ID.
     * @return \stdClass|null The configuration record, or null.
     */
    public static function get_plugin_config(int $cmid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_stackmathgame', ['cmid' => $cmid]) ?: null;
    }

    /**
     * Ensure a default configuration row exists for the given course-module.
     *
     * @param int $cmid The course-module ID.
     * @return \stdClass The configuration record.
     */
    public static function ensure_default(int $cmid, ?string $modname = null): \stdClass {
        global $DB, $USER;

        $record = self::get_plugin_config($cmid);
        if ($record) {
            return $record;
        }

        $cm       = self::get_supported_cm($cmid, $modname);
        $courseid = (int)$cm->course;
        $labelid  = self::ensure_default_label();
        $designid = theme_manager::ensure_default_design();
        $now      = time();
        $data     = (object)[
            'courseid'          => $courseid,
            'cmid'              => $cmid,
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

        $id = $DB->insert_record('local_stackmathgame', $data);
        return $DB->get_record('local_stackmathgame', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Persist updated settings for a course-module game configuration.
     *
     * Guards against labelid=0 to prevent FK violations (PostgreSQL).
     *
     * @param int   $cmid   The course-module ID.
     * @param array $data   Form data to persist.
     * @return \stdClass The updated configuration record.
     */
    public static function save_for_quiz(int $cmid, array $data): \stdClass {
        global $DB, $USER;

        $record = self::ensure_default($cmid);

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

        $DB->update_record('local_stackmathgame', $update);
        return self::get_plugin_config($cmid);
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
