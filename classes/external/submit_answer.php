<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Placeholder webservice endpoint for game-side answer submission.
 */
class submit_answer extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'attemptid' => new \external_value(PARAM_INT, 'Question attempt id'),
            'slot' => new \external_value(PARAM_INT, 'Question slot'),
            'answers' => new \external_multiple_structure(
                new \external_single_structure([
                    'name' => new \external_value(PARAM_ALPHANUMEXT, 'Input name'),
                    'value' => new \external_value(PARAM_RAW, 'Input value'),
                ])
            ),
        ]);
    }

    public static function execute(int $attemptid, int $slot, array $answers): array {
        self::validate_context(\context_system::instance());
        require_sesskey();

        return [
            'status' => 'not_implemented',
            'attemptid' => $attemptid,
            'slot' => $slot,
            'answers' => $answers,
            'message' => get_string('submitanswerplaceholder', 'local_stackmathgame'),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_TEXT, 'Execution status'),
            'attemptid' => new \external_value(PARAM_INT, 'Question attempt id'),
            'slot' => new \external_value(PARAM_INT, 'Question slot'),
            'answers' => new \external_multiple_structure(
                new \external_single_structure([
                    'name' => new \external_value(PARAM_ALPHANUMEXT, 'Input name'),
                    'value' => new \external_value(PARAM_RAW, 'Input value'),
                ])
            ),
            'message' => new \external_value(PARAM_TEXT, 'Human-readable message'),
        ]);
    }
}
