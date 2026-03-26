<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Return the current label-bound profile state for a quiz.
 */
class get_profile_state extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
        ]);
    }

    public static function execute(int $quizid): array {
        [, , $config, $profile, $design] = api::validate_quiz_access($quizid);
        return [
            'quizid' => $quizid,
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'profile' => api::export_profile($profile),
            'design' => api::export_design($design),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'labelid' => new \external_value(PARAM_INT, 'Label id'),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
            'profile' => get_quiz_config::profile_structure(),
            'design' => get_quiz_config::design_structure(),
        ]);
    }
}
