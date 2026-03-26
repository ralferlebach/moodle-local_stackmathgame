<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use local_stackmathgame\local\service\profile_service;

/**
 * Persist game progress deltas for the current quiz/label profile.
 */
class save_progress extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'scoredelta' => new \external_value(PARAM_INT, 'Score delta', VALUE_DEFAULT, 0),
            'xpdelta' => new \external_value(PARAM_INT, 'XP delta', VALUE_DEFAULT, 0),
            'softcurrencydelta' => new \external_value(PARAM_INT, 'Soft currency delta', VALUE_DEFAULT, 0),
            'hardcurrencydelta' => new \external_value(PARAM_INT, 'Hard currency delta', VALUE_DEFAULT, 0),
            'progressjson' => new \external_value(PARAM_RAW, 'Progress patch as JSON object', VALUE_DEFAULT, '{}'),
            'flagsjson' => new \external_value(PARAM_RAW, 'Flags patch as JSON object', VALUE_DEFAULT, '{}'),
            'statsjson' => new \external_value(PARAM_RAW, 'Stats patch as JSON object', VALUE_DEFAULT, '{}'),
            'eventtype' => new \external_value(PARAM_ALPHANUMEXT, 'Logged event type', VALUE_DEFAULT, 'progress_saved'),
        ]);
    }

    public static function execute(int $quizid, int $scoredelta = 0, int $xpdelta = 0, int $softcurrencydelta = 0,
            int $hardcurrencydelta = 0, string $progressjson = '{}', string $flagsjson = '{}',
            string $statsjson = '{}', string $eventtype = 'progress_saved'): array {
        require_sesskey();
        [, , $config, $profile, $design] = api::validate_quiz_access($quizid);
        $updated = profile_service::apply_progress((int)$profile->id, [
            'quizid' => $quizid,
            'designid' => (int)$config->designid,
            'scoredelta' => $scoredelta,
            'xpdelta' => $xpdelta,
            'softcurrencydelta' => $softcurrencydelta,
            'hardcurrencydelta' => $hardcurrencydelta,
            'progress' => json_decode($progressjson, true) ?: [],
            'flags' => json_decode($flagsjson, true) ?: [],
            'stats' => json_decode($statsjson, true) ?: [],
        ]);
        api::log_event($updated, $quizid, (int)$config->designid, $eventtype, 'external.save_progress', [
            'progress' => json_decode($progressjson, true) ?: [],
            'flags' => json_decode($flagsjson, true) ?: [],
            'stats' => json_decode($statsjson, true) ?: [],
        ], $scoredelta + $xpdelta);

        return [
            'quizid' => $quizid,
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'profile' => api::export_profile($updated),
            'design' => api::export_design($design),
            'eventtype' => $eventtype,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'labelid' => new \external_value(PARAM_INT, 'Label id'),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
            'profile' => get_quiz_config::profile_structure(),
            'design' => get_quiz_config::design_structure(),
            'eventtype' => new \external_value(PARAM_ALPHANUMEXT, 'Logged event type'),
        ]);
    }
}
