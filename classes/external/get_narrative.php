<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Returns narrative lines for a named scene from the active design.
 */
class get_narrative extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'scene' => new \external_value(PARAM_ALPHANUMEXT, 'Narrative scene key'),
        ]);
    }

    public static function execute(int $quizid, string $scene): array {
        [, , $config, $profile, $design] = api::validate_quiz_access($quizid);
        $narrative = json_decode((string)($design->narrativejson ?? '{}'), true) ?: [];
        $lines = $narrative[$scene] ?? [];
        if (!is_array($lines)) {
            $lines = [$lines];
        }
        api::log_event($profile, $quizid, (int)$config->designid, 'narrative_requested', 'external.get_narrative', ['scene' => $scene]);
        return [
            'quizid' => $quizid,
            'scene' => $scene,
            'lines' => array_values(array_map('strval', $lines)),
            'designid' => (int)$config->designid,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'scene' => new \external_value(PARAM_ALPHANUMEXT, 'Narrative scene key'),
            'lines' => new \external_multiple_structure(new \external_value(PARAM_RAW, 'Narrative line')),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
        ]);
    }
}
