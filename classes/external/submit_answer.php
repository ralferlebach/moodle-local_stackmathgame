<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * First external answer-submit layer for the game frontend.
 *
 * This does not yet perform a full Question Engine writeback. It validates
 * access, captures the client payload, exposes current attempt metadata, and
 * logs the interaction for the game runtime.
 */
class submit_answer extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'attemptid' => new \external_value(PARAM_INT, 'Quiz attempt id'),
            'slot' => new \external_value(PARAM_INT, 'Question slot'),
            'answers' => new \external_multiple_structure(
                new \external_single_structure([
                    'name' => new \external_value(PARAM_RAW_TRIMMED, 'Input name'),
                    'value' => new \external_value(PARAM_RAW, 'Input value'),
                ])
            ),
        ]);
    }

    public static function execute(int $attemptid, int $slot, array $answers): array {
        global $USER;
        require_sesskey();

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $cm = $attemptobj->get_cm();
        $context = \context_module::instance((int)$cm->id);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        $quizid = (int)$attemptobj->get_quizid();
        $config = \local_stackmathgame\game\quiz_configurator::ensure_default($quizid);
        $profile = \local_stackmathgame\local\service\profile_service::get_or_create_for_quiz((int)$USER->id, $quizid);
        $design = \local_stackmathgame\game\theme_manager::get_theme((int)$config->designid);

        $qa = $attemptobj->get_question_attempt($slot);
        $inputnames = [];
        foreach ($qa->get_qt_data() as $name => $value) {
            if ($name !== '-submit' && $name !== ':sequencecheck' && strpos($name, '_') !== 0) {
                $inputnames[] = (string)$name;
            }
        }

        api::log_event($profile, $quizid, (int)$config->designid, 'answer_submitted', 'external.submit_answer', [
            'attemptid' => $attemptid,
            'slot' => $slot,
            'answers' => $answers,
            'questionid' => (int)$qa->get_question()->id,
        ], count($answers));

        return [
            'status' => 'accepted',
            'attemptid' => $attemptid,
            'quizid' => $quizid,
            'slot' => $slot,
            'questionid' => (int)$qa->get_question()->id,
            'state' => (string)$qa->get_state()->get_name(),
            'sequencecheck' => (int)$qa->get_sequence_check_count(),
            'answers' => array_map(static function(array $answer): array {
                return [
                    'name' => (string)$answer['name'],
                    'value' => (string)$answer['value'],
                ];
            }, $answers),
            'inputnames' => array_values($inputnames),
            'message' => get_string('submitansweraccepted', 'local_stackmathgame'),
            'profile' => api::export_profile($profile),
            'design' => api::export_design($design),
            'feedbackhtml' => '',
            'canretry' => true,
            'cannext' => false,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_TEXT, 'Execution status'),
            'attemptid' => new \external_value(PARAM_INT, 'Quiz attempt id'),
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'slot' => new \external_value(PARAM_INT, 'Question slot'),
            'questionid' => new \external_value(PARAM_INT, 'Question id'),
            'state' => new \external_value(PARAM_TEXT, 'Current question state'),
            'sequencecheck' => new \external_value(PARAM_INT, 'Sequence check count'),
            'answers' => new \external_multiple_structure(
                new \external_single_structure([
                    'name' => new \external_value(PARAM_RAW_TRIMMED, 'Input name'),
                    'value' => new \external_value(PARAM_RAW, 'Input value'),
                ])
            ),
            'inputnames' => new \external_multiple_structure(new \external_value(PARAM_RAW_TRIMMED, 'Known question input name')),
            'message' => new \external_value(PARAM_TEXT, 'Human-readable message'),
            'profile' => get_quiz_config::profile_structure(),
            'design' => get_quiz_config::design_structure(),
            'feedbackhtml' => new \external_value(PARAM_RAW, 'Reserved feedback html channel'),
            'canretry' => new \external_value(PARAM_BOOL, 'Whether retry remains possible'),
            'cannext' => new \external_value(PARAM_BOOL, 'Whether the frontend may advance immediately'),
        ]);
    }
}
