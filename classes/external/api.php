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
 * Helper facade for external functions.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

use context_module;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\service\profile_service;

/**
 * Helper facade shared by all external function classes.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Normalise a raw question payload to canonical keys.
     *
     * @param array $payload Raw payload array.
     * @return array Normalised array with questionid, slot and answers keys.
     */
    public static function normalise_question_payload(array $payload): array {
        return [
            'questionid' => (int)($payload['questionid'] ?? 0),
            'slot' => (int)($payload['slot'] ?? 0),
            'answers' => (array)($payload['answers'] ?? []),
        ];
    }

    /**
     * Resolve a canonical activity identity.
     *
     * The course-module ID is the source of truth. When it is provided the
     * actual activity type and instance ID are derived from Moodle's
     * course_modules table. Legacy quiz-only callers may continue to pass a
     * quizid; this is normalised to the equivalent quiz activity identity.
     *
     * @param int $cmid The course-module ID.
     * @param string $modname Optional module name hint.
     * @param int $instanceid Optional activity instance ID.
     * @param int $quizid Optional legacy quiz instance ID.
     * @return array Canonical identity with cmid, modname, instanceid and quizid.
     */
    public static function resolve_activity_identity(
        int $cmid = 0,
        string $modname = 'quiz',
        int $instanceid = 0,
        int $quizid = 0
    ): array {
        if ($quizid > 0) {
            $modname = 'quiz';
            $instanceid = $quizid;
        }

        if ($cmid > 0) {
            $cm = self::load_cm_by_id($cmid);
        } else {
            $modname = (string)($modname ?: 'quiz');
            if ($instanceid <= 0) {
                throw new \moodle_exception('invalidcoursemodule');
            }
            $cm = get_coursemodule_from_instance($modname, $instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                throw new \moodle_exception('invalidcoursemodule');
            }
            $cm->modname = $modname;
        }

        $resolvedmodname = (string)($cm->modname ?? $modname);
        $resolvedinstanceid = (int)$cm->instance;

        return [
            'cmid' => (int)$cm->id,
            'modname' => $resolvedmodname,
            'instanceid' => $resolvedinstanceid,
            'quizid' => $resolvedmodname === 'quiz' ? $resolvedinstanceid : 0,
        ];
    }

    /**
     * Build a serialisable export array for a canonical activity identity.
     *
     * @param array $activity Canonical activity identity.
     * @return array Serialisable activity export.
     */
    public static function export_activity(array $activity): array {
        return [
            'cmid' => (int)($activity['cmid'] ?? 0),
            'modname' => (string)($activity['modname'] ?? ''),
            'instanceid' => (int)($activity['instanceid'] ?? 0),
            'quizid' => (int)($activity['quizid'] ?? 0),
        ];
    }


    /**
     * Return the external structure for a stash mapping export array.
     *
     * @return \external_single_structure
     */
    public static function stash_mapping_structure(): \external_single_structure {
        return new \external_single_structure([
            'slotnumber' => new \external_value(PARAM_INT, 'Question slot number'),
            'stashitemid' => new \external_value(PARAM_INT, 'Mapped block_stash item id'),
            'stashcourseid' => new \external_value(PARAM_INT, 'Course id owning the stash item'),
            'grantquantity' => new \external_value(PARAM_INT, 'Granted quantity'),
            'enabled' => new \external_value(PARAM_BOOL, 'Whether the mapping is enabled'),
            'mode' => new \external_value(PARAM_ALPHANUMEXT, 'Granting mode'),
        ]);
    }

    /**
     * Export a stash mapping record as a plain array.
     *
     * @param \stdClass|array $mapping The mapping record.
     * @return array<string, int|string|bool> Export array.
     */
    public static function export_stash_mapping($mapping): array {
        $record = (object)$mapping;
        return [
            'slotnumber' => (int)($record->slotnumber ?? 0),
            'stashitemid' => (int)($record->stashitemid ?? 0),
            'stashcourseid' => (int)($record->stashcourseid ?? 0),
            'grantquantity' => (int)($record->grantquantity ?? 1),
            'enabled' => !empty($record->enabled),
            'mode' => (string)($record->mode ?? 'firstsolve'),
        ];
    }

    /**
     * Export stash mappings for an activity, keyed internally by slot.
     *
     * @param array $activity Canonical activity identity.
     * @param int $courseid The activity course ID.
     * @return array<int, array<string, int|string|bool>> Sequential mapping list.
     */
    public static function export_activity_stash_mappings(array $activity, int $courseid = 0): array {
        global $DB;

        if ($courseid <= 0 && !empty($activity['cmid'])) {
            $cm = get_coursemodule_from_id((string)($activity['modname'] ?? ''), (int)$activity['cmid'], 0, false, IGNORE_MISSING);
            if ($cm) {
                $courseid = (int)$cm->course;
            }
        }

        $mappings = \local_stackmathgame\local\service\stash_mapping_service::get_for_activity(
            (int)($activity['cmid'] ?? 0),
            (int)($activity['instanceid'] ?? 0),
            (string)($activity['modname'] ?? 'quiz')
        );

        if (!$mappings) {
            return [];
        }

        $exports = [];
        foreach ($mappings as $mapping) {
            $export = self::export_stash_mapping($mapping);
            if ($courseid > 0 && $export['stashcourseid'] <= 0) {
                $export['stashcourseid'] = $courseid;
            }
            $exports[] = $export;
        }
        usort($exports, static function (array $a, array $b): int {
            return $a['slotnumber'] <=> $b['slotnumber'];
        });
        return $exports;
    }

    /**
     * Return whether an activity supports quiz-style question flow.
     *
     * This is intentionally stricter than merely checking whether an activity
     * has a configuration row. The runtime question flow currently depends on
     * quiz-specific attempt, slot and question-map semantics.
     *
     * @param array $activity Canonical activity identity.
     * @return bool True when the activity uses quiz question flow.
     */
    public static function activity_supports_question_flow(array $activity): bool {
        return (string)($activity['modname'] ?? '') === 'quiz';
    }

    /**
     * Resolve the course-module and context for a quiz instance.
     *
     * Uses IGNORE_MISSING (4th param only) and throws a moodle_exception when
     * the quiz has no course_modules record rather than crashing with a
     * dml_missing_record_exception.
     *
     * @param int $quizid The quiz instance ID.
     * @return array Two-element array: [$cm, $context].
     * @throws \moodle_exception When no course_modules entry exists.
     */
    public static function get_quiz_context(int $quizid): array {
        [$cm, $context] = self::get_activity_context(0, 'quiz', $quizid);
        return [$cm, $context];
    }

    /**
     * Resolve the course-module and context for an activity.
     *
     * @param int $cmid The course-module ID.
     * @param string $modname Optional module name hint.
     * @param int $instanceid Optional activity instance ID.
     * @param int $quizid Optional legacy quiz instance ID.
     * @return array Three-element array: [$cm, $context, $activity].
     */
    public static function get_activity_context(
        int $cmid = 0,
        string $modname = 'quiz',
        int $instanceid = 0,
        int $quizid = 0
    ): array {
        $activity = self::resolve_activity_identity($cmid, $modname, $instanceid, $quizid);
        $cm = get_coursemodule_from_id(
            $activity['modname'],
            $activity['cmid'],
            0,
            false,
            IGNORE_MISSING
        );
        if (!$cm) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        $cm->modname = $activity['modname'];
        $context = context_module::instance((int)$activity['cmid']);
        return [$cm, $context, $activity];
    }

    /**
     * Validate access to a quiz and return key runtime objects.
     *
     * @param int $quizid The quiz instance ID.
     * @return array Five-element array: [$cm, $context, $config, $profile, $design].
     */
    public static function validate_quiz_access(int $quizid): array {
        [$cm, $context, $config, $profile, $design] = self::validate_activity_access(0, 'quiz', $quizid);
        return [$cm, $context, $config, $profile, $design];
    }

    /**
     * Validate access to an activity and return key runtime objects.
     *
     * @param int $cmid The course-module ID.
     * @param string $modname Optional module name hint.
     * @param int $instanceid Optional activity instance ID.
     * @param int $quizid Optional legacy quiz instance ID.
     * @return array Six-element array: [$cm, $context, $config, $profile, $design, $activity].
     */
    public static function validate_activity_access(
        int $cmid = 0,
        string $modname = 'quiz',
        int $instanceid = 0,
        int $quizid = 0
    ): array {
        [$cm, $context, $activity] = self::get_activity_context($cmid, $modname, $instanceid, $quizid);
        if (class_exists('\core_external\external_api')) {
            \core_external\external_api::validate_context($context);
        } else if (class_exists('\external_api', false)) {
            \external_api::validate_context($context);
        }
        require_capability('local/stackmathgame:play', $context);

        $config = quiz_configurator::ensure_default((int)$activity['cmid'], $activity['modname']);
        $profile = profile_service::get_or_create_for_activity(
            self::current_userid(),
            (int)$activity['cmid'],
            (string)$activity['modname'],
            (int)$activity['instanceid']
        );
        $design = theme_manager::get_theme((int)$config->designid);

        return [$cm, $context, $config, $profile, $design, $activity];
    }

    /**
     * Build a serialisable profile export array.
     *
     * @param \stdClass $profile The profile record.
     * @return array The export array.
     */
    public static function export_profile(\stdClass $profile): array {
        $summary = profile_service::build_summary($profile);
        return [
            'id' => (int)$profile->id,
            'userid' => (int)$profile->userid,
            'labelid' => (int)$profile->labelid,
            'score' => (int)$profile->score,
            'xp' => (int)$profile->xp,
            'levelno' => (int)$profile->levelno,
            'softcurrency' => (int)$profile->softcurrency,
            'hardcurrency' => (int)$profile->hardcurrency,
            'avatarconfigjson' => (string)($profile->avatarconfigjson ?? '{}'),
            'progressjson' => (string)($profile->progressjson ?? '{}'),
            'statsjson' => (string)($profile->statsjson ?? '{}'),
            'flagsjson' => (string)($profile->flagsjson ?? '{}'),
            'lastquizid' => (int)($profile->lastquizid ?? 0),
            'lastdesignid' => (int)($profile->lastdesignid ?? 0),
            'lastaccess' => (int)($profile->lastaccess ?? 0),
            'summaryjson' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Build a serialisable design export array.
     *
     * Returns an empty-value array when $design is null.
     *
     * @param \stdClass|null $design The design record, or null.
     * @return array The export array.
     */
    public static function export_design(?\stdClass $design): array {
        if (!$design) {
            return [
                'id' => 0,
                'name' => '',
                'slug' => '',
                'modecomponent' => '',
                'description' => '',
                'isbundled' => 0,
                'isactive' => 0,
                'narrativejson' => '{}',
                'uijson' => '{}',
                'mechanicsjson' => '{}',
                'assetmanifestjson' => '{}',
                'runtimejson' => '{}',
            ];
        }
        $config = theme_manager::get_theme_config((int)$design->id);
        return [
            'id' => (int)$design->id,
            'name' => (string)$design->name,
            'slug' => (string)$design->slug,
            'modecomponent' => (string)$design->modecomponent,
            'description' => (string)($design->description ?? ''),
            'isbundled' => (int)$design->isbundled,
            'isactive' => (int)$design->isactive,
            'narrativejson' => (string)($design->narrativejson ?? '{}'),
            'uijson' => (string)($design->uijson ?? '{}'),
            'mechanicsjson' => (string)($design->mechanicsjson ?? '{}'),
            'assetmanifestjson' => (string)($design->assetmanifestjson ?? '{}'),
            'runtimejson' => json_encode([
                'modekey' => (string)($config['modekey'] ?? ''),
                'themeclass' => (string)($config['themeclass'] ?? ''),
                'thumbnailurl' => (string)($config['thumbnailurl'] ?? ''),
                'runtimeassets' => (array)($config['runtimeassets'] ?? []),
                'ui' => (array)($config['ui'] ?? []),
                'mechanics' => (array)($config['mechanics'] ?? []),
            ], JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Append a row to the game event log.
     *
     * @param \stdClass $profile The profile record.
     * @param int $quizid The quiz ID.
     * @param int $designid The active design ID.
     * @param string $eventtype Short event type string.
     * @param string $source Dotted source path for debugging.
     * @param array $payload Optional context payload.
     * @param int $valueint Optional numeric value.
     * @param string $valuechar Optional char value.
     * @return void
     */
    public static function log_event(
        \stdClass $profile,
        int $quizid,
        int $designid,
        string $eventtype,
        string $source,
        array $payload = [],
        int $valueint = 0,
        string $valuechar = ''
    ): void {
        global $DB;
        $DB->insert_record('local_stackmathgame_eventlog', (object)[
            'userid' => (int)$profile->userid,
            'labelid' => (int)$profile->labelid,
            'quizid' => $quizid,
            'questionid' => empty($payload['questionid']) ? null : (int)$payload['questionid'],
            'profileid' => (int)$profile->id,
            'designid' => $designid ?: null,
            'eventtype' => $eventtype,
            'source' => $source,
            'valueint' => $valueint ?: null,
            'valuechar' => $valuechar ?: null,
            'payloadjson' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timecreated' => time(),
        ]);
    }

    /**
     * Build the question map array for a quiz attempt context.
     *
     * Uses cmid when the migrated schema is present, and falls back to quizid
     * for installations where the additive migration has not yet completed.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @return array Array of node arrays ordered by sortorder, slotnumber.
     */
    public static function get_question_map(int $cmid, int $quizid): array {
        global $DB;

        if ($cmid > 0) {
            \local_stackmathgame\local\service\question_map_service::ensure_for_cmid($cmid);
        }

        [$keyfield, $keyvalue] = self::questionmap_lookup($cmid, $quizid);
        if ($keyvalue <= 0) {
            return [];
        }

        $records = $DB->get_records(
            'local_stackmathgame_questionmap',
            [$keyfield => $keyvalue],
            'sortorder ASC, slotnumber ASC'
        );
        $result = [];
        foreach ($records as $record) {
            $result[] = [
                'slotnumber' => (int)$record->slotnumber,
                'questionid' => (int)$record->questionid,
                'nodekey' => (string)$record->nodekey,
                'nodetype' => (string)$record->nodetype,
                'sortorder' => (int)$record->sortorder,
                'configjson' => (string)($record->configjson ?? '{}'),
            ];
        }
        return $result;
    }

    /**
     * Return question-map records after a slot for the active lookup key.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @param int $currentslot The current slot number.
     * @return array Array of record objects keyed by id.
     */
    public static function get_question_map_after_slot(int $cmid, int $quizid, int $currentslot): array {
        global $DB;

        if ($cmid > 0) {
            \local_stackmathgame\local\service\question_map_service::ensure_for_cmid($cmid);
        }

        [$keyfield, $keyvalue] = self::questionmap_lookup($cmid, $quizid);
        if ($keyvalue <= 0) {
            return [];
        }

        return $DB->get_records_select(
            'local_stackmathgame_questionmap',
            $keyfield . ' = ? AND slotnumber > ?',
            [$keyvalue, $currentslot],
            'sortorder ASC, slotnumber ASC'
        );
    }

    /**
     * Return the lookup condition for local_stackmathgame_questionmap.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @return array Two-element array: [fieldname, value].
     */
    public static function questionmap_lookup(int $cmid, int $quizid): array {
        if (self::questionmap_uses_cmid()) {
            return ['cmid', $cmid];
        }
        return ['quizid', $quizid];
    }

    /**
     * Return whether the migrated question map schema is available.
     *
     * @return bool True when local_stackmathgame_questionmap.cmid exists.
     */
    public static function questionmap_uses_cmid(): bool {
        global $DB;

        static $usescmid = null;
        if ($usescmid !== null) {
            return $usescmid;
        }

        $table = new \xmldb_table('local_stackmathgame_questionmap');
        $field = new \xmldb_field('cmid');
        $usescmid = $DB->get_manager()->field_exists($table, $field);
        return $usescmid;
    }

    /**
     * Load a course-module record together with its module name.
     *
     * @param int $cmid The course-module ID.
     * @return \stdClass The course-module record.
     */
    private static function load_cm_by_id(int $cmid): \stdClass {
        global $DB;

        $sql = "SELECT cm.*, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid";
        $record = $DB->get_record_sql($sql, ['cmid' => $cmid]);
        if (!$record) {
            throw new \moodle_exception('invalidcoursemodule');
        }

        $cm = get_coursemodule_from_id((string)$record->modname, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        $cm->modname = (string)$record->modname;
        return $cm;
    }

    /**
     * Return the current user ID safely (returns 0 for guests or CLI).
     *
     * @return int The user ID.
     */
    private static function current_userid(): int {
        global $USER;
        return (int)($USER->id ?? 0);
    }
}
