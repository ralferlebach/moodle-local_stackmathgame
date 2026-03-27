<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

use context_module;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\service\profile_service;

/**
 * Helper facade for external functions.
 *
 * Fixed issues:
 * 1. export_design(null) was missing 'runtimejson' in the returned array.
 *    The design_structure() in get_quiz_config declares runtimejson as a
 *    required field, so a null design without it caused external API
 *    validation failures downstream (e.g. in get_profile_state, save_progress).
 * 2. global_userid() has been moved inside the class as a private static
 *    method to avoid polluting the global namespace.
 */
class api {

    public static function normalise_question_payload(array $payload): array {
        return [
            'questionid' => (int)($payload['questionid'] ?? 0),
            'slot'       => (int)($payload['slot'] ?? 0),
            'answers'    => (array)($payload['answers'] ?? []),
        ];
    }

    public static function get_quiz_context(int $quizid): array {
        $cm      = get_coursemodule_from_instance('quiz', $quizid, 0, false, MUST_EXIST);
        $context = context_module::instance((int)$cm->id);
        return [$cm, $context];
    }

    public static function validate_quiz_access(int $quizid): array {
        [$cm, $context] = self::get_quiz_context($quizid);
        \external_api::validate_context($context);
        require_capability('local/stackmathgame:play', $context);
        $config  = quiz_configurator::ensure_default($quizid);
        $profile = profile_service::get_or_create_for_quiz(self::current_userid(), $quizid);
        $design  = theme_manager::get_theme((int)$config->designid);
        return [$cm, $context, $config, $profile, $design];
    }

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

    public static function export_design(?\stdClass $design): array {
        if (!$design) {
            // *** BUG FIX: runtimejson was missing here. design_structure() in
            // get_quiz_config declares it as a required return field. Callers
            // that receive a null design (quiz with no active design) would
            // get an external API validation exception. ***
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
     * Return current user id safely (0 for guests / CLI).
     * Private helper replacing the old global function global_userid().
     */
    private static function current_userid(): int {
        global $USER;
        return (int)($USER->id ?? 0);
    }
}

/**
 * Backward-compatible global function kept so any external code that
 * still calls global_userid() does not break immediately.
 *
 * @deprecated Use \local_stackmathgame\external\api::validate_quiz_access() instead.
 * @return int
 */
function global_userid(): int {
    global $USER;
    return (int)($USER->id ?? 0);
}
