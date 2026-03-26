<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/classes/external.php');

/**
 * Processes a game-side answer submit and returns updated attempt metadata.
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

        $payload = [];
        foreach ($answers as $answer) {
            $name = (string)($answer['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $payload[$name] = (string)($answer['value'] ?? '');
        }

        $processed = false;
        $message = get_string('submitansweraccepted', 'local_stackmathgame');
        try {
            if (!isset($payload['slots'])) {
                $payload['slots'] = (string)$slot;
            }
            if (!isset($payload['attempt'])) {
                $payload['attempt'] = (string)$attemptid;
            }
            if (!isset($payload['sesskey'])) {
                $payload['sesskey'] = sesskey();
            }
            $payload['-submit'] = 1;

            if (class_exists('mod_quiz_external') && method_exists('mod_quiz_external', 'process_attempt')) {
                \mod_quiz_external::process_attempt($attemptid, $payload, false, false, []);
                $processed = true;
                $message = get_string('submitanswerprocessed', 'local_stackmathgame');
            }
        } catch (\Throwable $e) {
            $processed = false;
            $message = get_string('submitanswerfallback', 'local_stackmathgame') . ' ' . $e->getMessage();
        }

        // Recreate the attempt object after processing.
        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $qa = $attemptobj->get_question_attempt($slot);
        $state = (string)$qa->get_state()->get_name();
        $feedbackhtml = '';
        $scoredelta = 0;
        $xpdelta = 0;
        $cannext = false;

        if ($processed) {
            if (in_array($state, ['gradedright', 'complete'], true)) {
                $scoredelta = 10;
                $xpdelta = 5;
                $cannext = true;
            } else if ($state === 'gradedpartial') {
                $scoredelta = 5;
                $xpdelta = 2;
            }
            $profile = \local_stackmathgame\local\service\profile_service::apply_progress((int)$profile->id, [
                'quizid' => $quizid,
                'designid' => (int)$config->designid,
                'scoredelta' => $scoredelta,
                'xpdelta' => $xpdelta,
                'progress' => ['slots' => [(string)$slot => $state]],
                'stats' => ['lastsubmit' => time(), 'laststate' => $state],
            ]);
        }

        api::log_event($profile, $quizid, (int)$config->designid, 'answer_submitted', 'external.submit_answer', [
            'attemptid' => $attemptid,
            'slot' => $slot,
            'answers' => array_values($payload),
            'questionid' => (int)$qa->get_question()->id,
            'processed' => $processed,
            'state' => $state,
        ], count($answers), $state);

        return [
            'status' => $processed ? 'processed' : 'accepted',
            'processed' => $processed,
            'attemptid' => $attemptid,
            'quizid' => $quizid,
            'slot' => $slot,
            'questionid' => (int)$qa->get_question()->id,
            'state' => $state,
            'sequencecheck' => (int)$qa->get_sequence_check_count(),
            'answers' => array_map(static function(array $answer): array {
                return [
                    'name' => (string)$answer['name'],
                    'value' => (string)$answer['value'],
                ];
            }, $answers),
            'inputnames' => array_keys($qa->get_qt_data()),
            'message' => $message,
            'profile' => api::export_profile($profile),
            'design' => api::export_design($design),
            'feedbackhtml' => $feedbackhtml,
            'scoredelta' => $scoredelta,
            'xpdelta' => $xpdelta,
            'canretry' => true,
            'cannext' => $cannext,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_TEXT, 'Execution status'),
            'processed' => new \external_value(PARAM_BOOL, 'Whether quiz processing was attempted successfully'),
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
            'scoredelta' => new \external_value(PARAM_INT, 'Score delta'),
            'xpdelta' => new \external_value(PARAM_INT, 'XP delta'),
            'canretry' => new \external_value(PARAM_BOOL, 'Whether retry remains possible'),
            'cannext' => new \external_value(PARAM_BOOL, 'Whether the frontend may advance immediately'),
        ]);
    }
}
