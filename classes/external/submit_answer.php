<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Processes a game-side answer submit and returns updated attempt metadata.
 *
 * Fixed issues:
 * 1. previousstate was declared in execute_returns() but never included in the
 *    execute() return array → external API validation threw an exception.
 * 2. mod_quiz_external::process_attempt expects $data as an array of
 *    ['name'=>..., 'value'=>...] maps, not a flat associative array.
 * 3. require_once for the quiz external file is guarded so it doesn't
 *    fail on Moodle installs where the file has moved.
 */
class submit_answer extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'attemptid' => new \external_value(PARAM_INT, 'Quiz attempt id'),
            'slot'      => new \external_value(PARAM_INT, 'Question slot'),
            'answers'   => new \external_multiple_structure(
                new \external_single_structure([
                    'name'  => new \external_value(PARAM_RAW_TRIMMED, 'Input name'),
                    'value' => new \external_value(PARAM_RAW, 'Input value'),
                ])
            ),
        ]);
    }

    public static function execute(int $attemptid, int $slot, array $answers): array {
        global $CFG, $USER;

        require_sesskey();

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $cm         = $attemptobj->get_cm();
        $context    = \context_module::instance((int)$cm->id);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        $quizid  = (int)$attemptobj->get_quizid();
        $config  = \local_stackmathgame\game\quiz_configurator::ensure_default($quizid);
        $profile = \local_stackmathgame\local\service\profile_service::get_or_create_for_quiz(
            (int)$USER->id,
            $quizid
        );
        $design  = \local_stackmathgame\game\theme_manager::get_theme((int)$config->designid);

        // ----------------------------------------------------------------
        // Build flat payload (key => value) for internal use.
        // ----------------------------------------------------------------
        $flatpayload = [];
        foreach ($answers as $answer) {
            $name = (string)($answer['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $flatpayload[$name] = (string)($answer['value'] ?? '');
        }
        // Ensure quiz mechanism fields are present.
        if (!isset($flatpayload['slots'])) {
            $flatpayload['slots'] = (string)$slot;
        }
        if (!isset($flatpayload['attempt'])) {
            $flatpayload['attempt'] = (string)$attemptid;
        }
        if (!isset($flatpayload['sesskey'])) {
            $flatpayload['sesskey'] = sesskey();
        }
        $flatpayload['-submit'] = '1';

        // ----------------------------------------------------------------
        // Convert to the format expected by mod_quiz_external::process_attempt:
        // an array of ['name' => ..., 'value' => ...] objects.
        // ----------------------------------------------------------------
        $externaldata = [];
        foreach ($flatpayload as $name => $value) {
            $externaldata[] = ['name' => (string)$name, 'value' => (string)$value];
        }

        // ----------------------------------------------------------------
        // Try to process via the quiz external API (Moodle 4.x compatible).
        // We load the external file only when it exists to avoid fatal errors.
        // ----------------------------------------------------------------
        $processed = false;
        $message   = get_string('submitansweraccepted', 'local_stackmathgame');

        try {
            // Moodle 4.x: quiz external is typically in mod/quiz/classes/external.php
            $quizexternalfile = $CFG->dirroot . '/mod/quiz/classes/external.php';
            if (is_readable($quizexternalfile)) {
                require_once($quizexternalfile);
            }

            if (class_exists('mod_quiz_external') &&
                    method_exists('mod_quiz_external', 'process_attempt')) {
                // Pass data in the correct [{name,value}] format.
                \mod_quiz_external::process_attempt(
                    $attemptid,
                    $externaldata,
                    false,   // finishattempt
                    false,   // timeup
                    []       // preflightdata
                );
                $processed = true;
                $message   = get_string('submitanswerprocessed', 'local_stackmathgame');
            }
        } catch (\Throwable $e) {
            $processed = false;
            $message   = get_string('submitanswerfallback', 'local_stackmathgame')
                         . ' ' . $e->getMessage();
        }

        // Reload attempt after processing so state reflects the new submission.
        $attemptobj    = \mod_quiz\quiz_attempt::create($attemptid);
        $qa            = $attemptobj->get_question_attempt($slot);
        $state         = (string)$qa->get_state()->get_name();
        $feedbackhtml  = '';
        $previousstate = \local_stackmathgame\local\service\profile_service::get_slot_state(
            $profile,
            $slot
        );
        $scoredelta = 0;
        $xpdelta    = 0;
        $cannext    = false;

        if ($processed) {
            $deltas     = \local_stackmathgame\local\service\profile_service::calculate_submit_deltas(
                $previousstate,
                $state
            );
            $scoredelta = (int)$deltas['score'];
            $xpdelta    = (int)$deltas['xp'];
            $cannext    = (bool)$deltas['solved'];

            $progress     = \local_stackmathgame\local\service\profile_service::decode_json_field(
                $profile->progressjson ?? '{}'
            );
            $slots        = (array)($progress['slots'] ?? []);
            $slotkey      = (string)$slot;
            $previousattempts = 0;
            if (isset($slots[$slotkey]) && is_array($slots[$slotkey])) {
                $previousattempts = (int)($slots[$slotkey]['attempts'] ?? 0);
            }
            $slotpayload = [
                'state'         => $state,
                'attempts'      => $previousattempts + 1,
                'solved'        => $cannext ? 1 : 0,
                'lastsubmitted' => time(),
            ];
            $profile = \local_stackmathgame\local\service\profile_service::apply_progress(
                (int)$profile->id,
                [
                    'quizid'        => $quizid,
                    'designid'      => (int)$config->designid,
                    'scoredelta'    => $scoredelta,
                    'xpdelta'       => $xpdelta,
                    'progress'      => ['slots' => [$slotkey => $slotpayload]],
                    'stats'         => [
                        'lastsubmit' => time(),
                        'laststate'  => $state,
                        'lastslot'   => $slot,
                    ],
                ]
            );
        }

        api::log_event(
            $profile,
            $quizid,
            (int)$config->designid,
            'answer_submitted',
            'external.submit_answer',
            [
                'attemptid'     => $attemptid,
                'slot'          => $slot,
                'answers'       => array_values($flatpayload),
                'questionid'    => (int)$qa->get_question()->id,
                'processed'     => $processed,
                'previousstate' => $previousstate,
                'state'         => $state,
            ],
            count($answers),
            $state
        );

        return [
            'status'        => $processed ? 'processed' : 'accepted',
            'processed'     => $processed,
            'attemptid'     => $attemptid,
            'quizid'        => $quizid,
            'slot'          => $slot,
            'questionid'    => (int)$qa->get_question()->id,
            'state'         => $state,
            'sequencecheck' => (int)$qa->get_sequence_check_count(),
            'answers'       => array_map(static function(array $answer): array {
                return [
                    'name'  => (string)$answer['name'],
                    'value' => (string)$answer['value'],
                ];
            }, $answers),
            'inputnames'    => array_keys($qa->get_qt_data()),
            // *** BUG FIX: previousstate was declared in execute_returns() but
            // was never included in the return array, causing external API
            // validation to throw "Missing required attribute" exceptions. ***
            'previousstate' => $previousstate,
            'message'       => $message,
            'profile'       => api::export_profile($profile),
            'design'        => api::export_design($design),
            'feedbackhtml'  => $feedbackhtml,
            'scoredelta'    => $scoredelta,
            'xpdelta'       => $xpdelta,
            'canretry'      => true,
            'cannext'       => $cannext,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status'        => new \external_value(PARAM_TEXT, 'Execution status'),
            'processed'     => new \external_value(PARAM_BOOL,
                                    'Whether quiz processing was attempted successfully'),
            'attemptid'     => new \external_value(PARAM_INT, 'Quiz attempt id'),
            'quizid'        => new \external_value(PARAM_INT, 'Quiz id'),
            'slot'          => new \external_value(PARAM_INT, 'Question slot'),
            'questionid'    => new \external_value(PARAM_INT, 'Question id'),
            'state'         => new \external_value(PARAM_TEXT, 'Current question state'),
            'sequencecheck' => new \external_value(PARAM_INT, 'Sequence check count'),
            'answers'       => new \external_multiple_structure(
                new \external_single_structure([
                    'name'  => new \external_value(PARAM_RAW_TRIMMED, 'Input name'),
                    'value' => new \external_value(PARAM_RAW, 'Input value'),
                ])
            ),
            'inputnames'    => new \external_multiple_structure(
                new \external_value(PARAM_RAW_TRIMMED, 'Known question input name')
            ),
            'previousstate' => new \external_value(PARAM_TEXT,
                                    'Previous profile-tracked question state'),
            'message'       => new \external_value(PARAM_TEXT, 'Human-readable message'),
            'profile'       => get_quiz_config::profile_structure(),
            'design'        => get_quiz_config::design_structure(),
            'feedbackhtml'  => new \external_value(PARAM_RAW, 'Reserved feedback html channel'),
            'scoredelta'    => new \external_value(PARAM_INT, 'Score delta'),
            'xpdelta'       => new \external_value(PARAM_INT, 'XP delta'),
            'canretry'      => new \external_value(PARAM_BOOL, 'Whether retry remains possible'),
            'cannext'       => new \external_value(PARAM_BOOL,
                                    'Whether the frontend may advance immediately'),
        ]);
    }
}
