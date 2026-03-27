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
        global $USER, $DB;
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
            $payload['slots'] = $payload['slots'] ?? (string)$slot;
            $payload['attempt'] = $payload['attempt'] ?? (string)$attemptid;
            $payload['sesskey'] = $payload['sesskey'] ?? sesskey();
            $payload['-submit'] = 1;
            if (class_exists('mod_quiz_external') && method_exists('mod_quiz_external', 'process_attempt')) {
                \mod_quiz_external::process_attempt($attemptid, $payload, false, false, []);
                $processed = true;
                $message = get_string('submitanswerprocessed', 'local_stackmathgame');
            }
        } catch (\Throwable $e) {
            $message = get_string('submitanswerfallback', 'local_stackmathgame') . ' ' . $e->getMessage();
        }

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $qa = $attemptobj->get_question_attempt($slot);
        $state = (string)$qa->get_state()->get_name();
        $sequencecheck = (int)$qa->get_sequence_check_count();
        $previousstate = \local_stackmathgame\local\service\profile_service::get_slot_state($profile, $slot);
        $deltas = \local_stackmathgame\local\service\profile_service::calculate_submit_deltas($previousstate, $state);
        $scoredelta = (int)$deltas['score'];
        $xpdelta = (int)$deltas['xp'];
        $cannext = (bool)$deltas['solved'];
        $canretry = !$cannext;

        $progress = \local_stackmathgame\local\service\profile_service::decode_json_field($profile->progressjson ?? '{}');
        $slots = (array)($progress['slots'] ?? []);
        $slotkey = (string)$slot;
        $slotprogress = (array)($slots[$slotkey] ?? []);
        $slotprogress['questionid'] = (int)$qa->get_question_id();
        $slotprogress['state'] = $state;
        $slotprogress['attempts'] = (int)($slotprogress['attempts'] ?? 0) + 1;
        $slotprogress['solved'] = !empty($deltas['solved']);
        $slotprogress['partial'] = in_array($state, ['gradedpartial'], true);
        $slotprogress['lastsubmitted'] = time();
        $slotprogress['lastsequencecheck'] = $sequencecheck;
        $slotprogress['attemptid'] = $attemptid;
        $slots[$slotkey] = $slotprogress;
        $progress['slots'] = $slots;

        $profile = \local_stackmathgame\local\service\profile_service::apply_progress((int)$profile->id, [
            'quizid' => $quizid,
            'designid' => (int)$config->designid,
            'scoredelta' => $scoredelta,
            'xpdelta' => $xpdelta,
            'progress' => $progress,
        ]);

        api::log_event($profile, $quizid, (int)$config->designid, 'answer_submit', 'external_submit', [
            'questionid' => (int)$qa->get_question_id(),
            'slot' => $slot,
            'state' => $state,
            'processed' => $processed,
            'sequencecheck' => $sequencecheck,
        ], $scoredelta, $state);

        $bridges = \local_stackmathgame\local\integration\bridge_dispatcher::on_answer_result(
            $profile,
            $quizid,
            (int)$config->designid,
            $slot,
            $slotprogress,
            $deltas
        );

        $feedbacksummary = trim(strip_tags((string)$qa->get_current_summary()));

        return [
            'processed' => $processed ? 1 : 0,
            'message' => $message,
            'state' => $state,
            'previousstate' => $previousstate,
            'attemptstate' => (string)$attemptobj->get_attempt()->state,
            'feedbacksummary' => $feedbacksummary,
            'scoredelta' => $scoredelta,
            'xpdelta' => $xpdelta,
            'canretry' => $canretry ? 1 : 0,
            'cannext' => $cannext ? 1 : 0,
            'profile' => api::export_profile($profile),
            'design' => api::export_design($design),
            'bridgesjson' => json_encode($bridges, JSON_UNESCAPED_UNICODE),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'processed' => new \external_value(PARAM_INT, 'Whether attempt processing succeeded'),
            'message' => new \external_value(PARAM_TEXT, 'Human readable message'),
            'state' => new \external_value(PARAM_RAW, 'Question state'),
            'previousstate' => new \external_value(PARAM_RAW, 'Previous tracked slot state'),
            'attemptstate' => new \external_value(PARAM_RAW, 'Attempt state'),
            'feedbacksummary' => new \external_value(PARAM_RAW, 'Feedback summary'),
            'scoredelta' => new \external_value(PARAM_INT, 'Score delta applied'),
            'xpdelta' => new \external_value(PARAM_INT, 'XP delta applied'),
            'canretry' => new \external_value(PARAM_INT, 'Whether retry is possible'),
            'cannext' => new \external_value(PARAM_INT, 'Whether next step is available'),
            'profile' => get_quiz_config::profile_structure(),
            'design' => get_quiz_config::design_structure(),
            'bridgesjson' => new \external_value(PARAM_RAW, 'Integration bridge status JSON'),
        ]);
    }
}
