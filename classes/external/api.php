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
            'slot'       => (int)($payload['slot'] ?? 0),
            'answers'    => (array)($payload['answers'] ?? []),
        ];
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
        $cm = get_coursemodule_from_instance('quiz', $quizid, 0, IGNORE_MISSING);
        if (!$cm) {
            throw new \moodle_exception('quiznotfound', 'local_stackmathgame', '', $quizid);
        }
        $context = context_module::instance((int)$cm->id);
        return [$cm, $context];
    }

    /**
     * Validate access to a quiz and return key runtime objects.
     *
     * @param int $quizid The quiz instance ID.
     * @return array Five-element array: [$cm, $context, $config, $profile, $design].
     */
    public static function validate_quiz_access(int $quizid): array {
        [$cm, $context] = self::get_quiz_context($quizid);
        \external_api::validate_context($context);
        require_capability('local/stackmathgame:play', $context);
        $config  = quiz_configurator::ensure_default($quizid);
        $profile = profile_service::get_or_create_for_quiz(self::current_userid(), $quizid);
        $design  = theme_manager::get_theme((int)$config->designid);
        return [$cm, $context, $config, $profile, $design];
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
            'id'               => (int)$profile->id,
            'userid'           => (int)$profile->userid,
            'labelid'          => (int)$profile->labelid,
            'score'            => (int)$profile->score,
            'xp'               => (int)$profile->xp,
            'levelno'          => (int)$profile->levelno,
            'softcurrency'     => (int)$profile->softcurrency,
            'hardcurrency'     => (int)$profile->hardcurrency,
            'avatarconfigjson' => (string)($profile->avatarconfigjson ?? '{}'),
            'progressjson'     => (string)($profile->progressjson ?? '{}'),
            'statsjson'        => (string)($profile->statsjson ?? '{}'),
            'flagsjson'        => (string)($profile->flagsjson ?? '{}'),
            'lastquizid'       => (int)($profile->lastquizid ?? 0),
            'lastdesignid'     => (int)($profile->lastdesignid ?? 0),
            'lastaccess'       => (int)($profile->lastaccess ?? 0),
            'summaryjson'      => json_encode($summary, JSON_UNESCAPED_UNICODE),
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
                'id'               => 0,
                'name'             => '',
                'slug'             => '',
                'modecomponent'    => '',
                'description'      => '',
                'isbundled'        => 0,
                'isactive'         => 0,
                'narrativejson'    => '{}',
                'uijson'           => '{}',
                'mechanicsjson'    => '{}',
                'assetmanifestjson' => '{}',
                'runtimejson'      => '{}',
            ];
        }
        $config = theme_manager::get_theme_config((int)$design->id);
        return [
            'id'               => (int)$design->id,
            'name'             => (string)$design->name,
            'slug'             => (string)$design->slug,
            'modecomponent'    => (string)$design->modecomponent,
            'description'      => (string)($design->description ?? ''),
            'isbundled'        => (int)$design->isbundled,
            'isactive'         => (int)$design->isactive,
            'narrativejson'    => (string)($design->narrativejson ?? '{}'),
            'uijson'           => (string)($design->uijson ?? '{}'),
            'mechanicsjson'    => (string)($design->mechanicsjson ?? '{}'),
            'assetmanifestjson' => (string)($design->assetmanifestjson ?? '{}'),
            'runtimejson'      => json_encode([
                'modekey'       => (string)($config['modekey'] ?? ''),
                'themeclass'    => (string)($config['themeclass'] ?? ''),
                'thumbnailurl'  => (string)($config['thumbnailurl'] ?? ''),
                'runtimeassets' => (array)($config['runtimeassets'] ?? []),
                'ui'            => (array)($config['ui'] ?? []),
                'mechanics'     => (array)($config['mechanics'] ?? []),
            ], JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Append a row to the game event log.
     *
     * @param \stdClass $profile   The profile record.
     * @param int       $quizid    The quiz ID.
     * @param int       $designid  The active design ID.
     * @param string    $eventtype Short event type string.
     * @param string    $source    Dotted source path for debugging.
     * @param array     $payload   Optional context payload.
     * @param int       $valueint  Optional numeric value.
     * @param string    $valuechar Optional char value.
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
            'userid'      => (int)$profile->userid,
            'labelid'     => (int)$profile->labelid,
            'quizid'      => $quizid,
            'questionid'  => empty($payload['questionid']) ? null : (int)$payload['questionid'],
            'profileid'   => (int)$profile->id,
            'designid'    => $designid ?: null,
            'eventtype'   => $eventtype,
            'source'      => $source,
            'valueint'    => $valueint ?: null,
            'valuechar'   => $valuechar ?: null,
            'payloadjson' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timecreated' => time(),
        ]);
    }

    /**
     * Build the question map array for a quiz.
     *
     * @param int $quizid The quiz ID.
     * @return array Array of node arrays ordered by sortorder, slotnumber.
     */
    public static function get_question_map(int $quizid): array {
        global $DB;
        $records = $DB->get_records(
            'local_stackmathgame_questionmap',
            ['quizid' => $quizid],
            'sortorder ASC, slotnumber ASC'
        );
        $result = [];
        foreach ($records as $record) {
            $result[] = [
                'slotnumber' => (int)$record->slotnumber,
                'questionid' => (int)$record->questionid,
                'nodekey'    => (string)$record->nodekey,
                'nodetype'   => (string)$record->nodetype,
                'sortorder'  => (int)$record->sortorder,
                'configjson' => (string)($record->configjson ?? '{}'),
            ];
        }
        return $result;
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
