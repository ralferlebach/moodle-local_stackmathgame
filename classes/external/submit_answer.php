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
 * External function: submit_answer.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

use local_stackmathgame\local\service\navigation_resolver;
use local_stackmathgame\local\service\slot_config_schema;

/**
 * Process a game-side answer submission and return updated attempt metadata.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_answer extends \core_external\external_api {
    /**
     * Describe input parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'attemptid' => new \core_external\external_value(PARAM_INT, 'Quiz attempt id'),
            'slot'      => new \core_external\external_value(PARAM_INT, 'Question slot'),
            'answers'   => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'name'  => new \core_external\external_value(PARAM_RAW_TRIMMED, 'Input name'),
                    'value' => new \core_external\external_value(PARAM_RAW, 'Input value'),
                ])
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int   $attemptid The quiz attempt ID.
     * @param int   $slot      The question slot.
     * @param array $answers   Array of name/value pairs.
     * @return array The submission result.
     */
    public static function execute(int $attemptid, int $slot, array $answers): array {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        require_sesskey();

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $cm         = $attemptobj->get_cm();
        $context    = \context_module::instance((int)$cm->id);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        // The capability says the user may play here; it does not say this attempt is theirs.
        // Without the owner check any player could post answers into a classmate's attempt, and
        // the reward would land in that classmate's profile.
        if ((int)$attemptobj->get_userid() !== (int)$USER->id) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }
        if ($attemptobj->is_finished()) {
            throw new \moodle_exception('attemptalreadyclosed', 'quiz');
        }
        if (!$attemptobj->is_own_attempt()) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }
        // A slot the attempt does not have would otherwise surface as an undefined-index notice
        // several calls deeper, inside the question engine.
        // get_slots() can hand back the numbers as strings depending on the driver, so compare
        // on value rather than identity - a strict comparison rejected every valid slot.
        if (!in_array((int)$slot, array_map('intval', $attemptobj->get_slots()), true)) {
            throw new \moodle_exception('err_unknownslot', 'local_stackmathgame', '', $slot);
        }

        $quizid  = (int)$attemptobj->get_quizid();
        // Use cmid as source of truth for config lookup (patch 2026032827).
        $config  = \local_stackmathgame\game\quiz_configurator::ensure_default((int)$cm->id);
        $profile = \local_stackmathgame\local\service\profile_service::get_or_create_for_quiz(
            (int)$USER->id,
            $quizid
        );
        $design  = \local_stackmathgame\game\theme_manager::get_theme((int)$config->designid);

        $flatpayload = [];
        foreach ($answers as $answer) {
            $name = (string)($answer['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $flatpayload[$name] = (string)($answer['value'] ?? '');
        }
        if (!isset($flatpayload['slots'])) {
            $flatpayload['slots'] = (string)$slot;
        }
        if (!isset($flatpayload['attempt'])) {
            $flatpayload['attempt'] = (string)$attemptid;
        }
        if (!isset($flatpayload['sesskey'])) {
            $flatpayload['sesskey'] = sesskey();
        }
        // A bare "-submit" is the quiz-wide button, not the per-question one. The question engine
        // looks for the behaviour field of THIS question attempt - q{usageid}:{slot}_-submit -
        // and without it the answer is saved but never graded: the attempt stays in "todo", no
        // mark is produced, and the game therefore never rewards anything or opens the next
        // scene. The response still said "processed", which is why it looked like a scoring bug
        // rather than a missing field.
        $qasubmit = $attemptobj->get_question_attempt($slot);
        $submitfield = $qasubmit->get_behaviour_field_name('submit');
        $flatpayload[$submitfield] = '1';
        $flatpayload['-submit'] = '1';

        $externaldata = [];
        foreach ($flatpayload as $name => $value) {
            $externaldata[] = ['name' => (string)$name, 'value' => (string)$value];
        }

        $processed = false;
        $failurereason = '';
        $message   = get_string('submitansweraccepted', 'local_stackmathgame');

        try {
            // Processed directly rather than through mod_quiz_external::process_attempt().
            // That route rebuilds $_POST and hands the question engine a request it never
            // received: the answer was stored, but no behaviour action ever fired, so the
            // attempt stayed in "todo" with no mark - while the endpoint still reported
            // "processed". Nothing was graded, nothing was rewarded, and no scene unlocked.
            //
            // process_submitted_actions() takes the data per slot with unprefixed field names,
            // which is also what makes the intent explicit: this call submits THIS slot.
            $prefix = $qasubmit->get_field_prefix();
            $slotdata = [];
            foreach ($flatpayload as $name => $value) {
                if (strpos((string)$name, $prefix) === 0) {
                    $slotdata[substr((string)$name, strlen($prefix))] = $value;
                }
            }
            $slotdata['-submit'] = '1';

            $attemptobj->process_submitted_actions(time(), false, [$slot => $slotdata]);
            $processed = true;
            $message   = get_string('submitanswerprocessed', 'local_stackmathgame');
        } catch (\Throwable $e) {
            // The player gets a stable, translatable sentence; the detail goes to the event log
            // and the developer debug channel. Concatenating an exception message into a
            // user-facing string leaked internals and produced a different text every time,
            // which no test could assert against.
            $processed = false;
            $failurereason = $e->getMessage();
            $message   = get_string('submitanswerfallback', 'local_stackmathgame');
            debugging(
                'local_stackmathgame submit failed: ' . $failurereason,
                DEBUG_DEVELOPER
            );
        }

        $attemptobj    = \mod_quiz\quiz_attempt::create($attemptid);
        $qa            = $attemptobj->get_question_attempt($slot);
        // States including question_state_todo which has no get_name().
        $state = (string)$qa->get_state();
        // The mark, not the state name, says whether the scene was solved: under
        // adaptivemultipart a correct answer during an attempt sits in "complete", never in
        // "gradedright". And it must be the UNPENALISED mark - see raw_fraction().
        $fraction = self::raw_fraction($qa);
        $feedbackhtml  = '';
        $previousstate = \local_stackmathgame\local\service\profile_service::get_slot_state(
            $profile,
            $slot
        );
        $scoredelta = 0;
        $xpdelta    = 0;
        $cannext    = false;

        // The reward is granted at most once per solved scene, and that has to hold for
        // genuinely parallel requests. A read of the previous state followed by a write of the
        // new one is a read-modify-write: two simultaneous submissions of the same correct
        // answer both read "not yet solved" and both pay out. A single PHP process can never
        // show this - it reads and writes in one database session and always sees its own
        // earlier write - so the guard is a cross-process lock, and the proof lives in
        // tests/load/stackmathgame-capacity-race.js.
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_stackmathgame_reward');
        $lock = $lockfactory->get_lock('profile' . (int)$profile->id . '_slot' . $slot, 10);
        if (!$lock) {
            // Losing the race is a refusal, not an error: another request is already awarding
            // this scene, and awarding it twice is exactly what must not happen.
            $processed = false;
            $message = get_string('submitanswerbusy', 'local_stackmathgame');
        }

        if ($processed) {
            try {
                // Re-read inside the lock. The value fetched before it was taken may already be
                // stale, and acting on it is the whole bug.
                $profile = \local_stackmathgame\local\service\profile_service::get_or_create_for_quiz(
                    (int)$USER->id,
                    $quizid
                );
                $previousstate = \local_stackmathgame\local\service\profile_service::get_slot_state(
                    $profile,
                    $slot
                );
                $slotconfig = \local_stackmathgame\local\service\flow_service::get_slot_config(
                    (int)$cm->id,
                    $slot
                );
                $previousfraction = \local_stackmathgame\local\service\profile_service::get_slot_fraction(
                    $profile,
                    $slot
                );
                $deltas     = \local_stackmathgame\local\service\profile_service::calculate_submit_deltas(
                    $previousstate,
                    $state,
                    $slotconfig,
                    $fraction,
                    $previousfraction
                );
                $scoredelta = (int)$deltas['score'];
                $xpdelta    = (int)$deltas['xp'];
                $cannext    = (bool)$deltas['solved'];

                $progress         = \local_stackmathgame\local\service\profile_service::decode_json_field(
                    $profile->progressjson ?? '{}'
                );
                $slots            = (array)($progress['slots'] ?? []);
                $slotkey          = (string)$slot;
                $previousattempts = 0;
                if (isset($slots[$slotkey]) && is_array($slots[$slotkey])) {
                    $previousattempts = (int)($slots[$slotkey]['attempts'] ?? 0);
                }
                $slotpayload = [
                    'state'         => $state,
                    // The best mark reached so far, which is what get_slot_fraction() reads back
                    // on the next submission. Without it every repeat of a solved question looked
                    // like a first solve and paid out again - the exact reward farming the lock
                    // above is meant to prevent.
                    'fraction'      => max(
                        (float)($slots[$slotkey]['fraction'] ?? 0.0),
                        $fraction === null ? 0.0 : (float)$fraction
                    ),
                    'attempts'      => $previousattempts + 1,
                    'solved'        => $cannext ? 1 : 0,
                    'lastsubmitted' => time(),
                ];
                $profile = \local_stackmathgame\local\service\profile_service::apply_progress(
                    (int)$profile->id,
                    [
                        'quizid'     => $quizid,
                        'designid'   => (int)$config->designid,
                        'scoredelta' => $scoredelta,
                        'xpdelta'    => $xpdelta,
                        'progress'   => ['slots' => [$slotkey => $slotpayload]],
                        'stats'      => [
                            'lastsubmit' => time(),
                            'laststate'  => $state,
                            'lastslot'   => $slot,
                        ],
                    ]
                );

                try {
                    \local_stackmathgame\local\integration\bridge_dispatcher::on_answer_result(
                        $profile,
                        $quizid,
                        (int)$config->designid,
                        $slot,
                        [
                        'state' => $state,
                        'questionid' => (int)$qa->get_question()->id,
                        'config' => [],
                        ],
                        [
                        'score' => $scoredelta,
                        'xp' => $xpdelta,
                        'solved' => $cannext,
                        ]
                    );
                } catch (\Throwable $bridgeerr) {
                    debugging(
                        'local_stackmathgame bridge dispatch failed: ' . $bridgeerr->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }
            } finally {
                // Released in a finally block: an exception between taking the lock and here
                // would otherwise hold it until it times out, and every further submission on
                // this scene would be refused for no visible reason.
                $lock->release();
            }
        } else if ($lock) {
            $lock->release();
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
                'failurereason' => $failurereason,
                'scoredelta'    => $scoredelta,
                'xpdelta'       => $xpdelta,
            ],
            count($answers),
            $state
        );

        return [
            // The old "accepted" was returned for a failed submit too, which reads like success.
            // A submission that was not processed is a refusal, and the client has to be able to
            // tell the difference without parsing a message.
            'status'        => $processed ? 'processed' : 'notprocessed',
            'processed'     => $processed,
            // An explicit instruction rather than something for the client to infer: when the
            // pageless path could not process the answer, the browser must fall back to the
            // ordinary quiz page instead of pretending the answer landed.
            'requiresnativefallback' => !$processed,
            'attemptid'     => $attemptid,
            'quizid'        => $quizid,
            'slot'          => $slot,
            'questionid'    => (int)$qa->get_question()->id,
            'state'         => $state,
            'sequencecheck' => (int)$qa->get_sequence_check_count(),
            'answers'       => array_map(
                static function (array $answer): array {
                    return [
                        'name'  => (string)$answer['name'],
                        'value' => (string)$answer['value'],
                    ];
                },
                $answers
            ),
            // Deliberately get_last_qt_data(): get_qt_data() does not exist on question_attempt,
            // and the catch that used to wrap this swallowed the resulting TypeError, so the client
            // silently received an empty list on every submission - it could never rebind the
            // inputs after a fragment refresh.
            'inputnames'    => array_keys($qa->get_last_qt_data()),
            'previousstate' => $previousstate,
            'message'       => $message,
            'profile'       => api::export_profile($profile),
            'design'        => api::export_design($design),
            'feedbackhtml'  => $feedbackhtml,
            'scoredelta'    => $scoredelta,
            'xpdelta'       => $xpdelta,
            'canretry'      => true,
            'cannext'       => $cannext,
            // Resolved here, once, from the same branch_resolver the rest of the server uses.
            // The client used to re-read configjson and reach its own conclusion, which is how
            // `linear` - the default every auto-created slot gets - ended up with no way forward.
            'navigation'    => navigation_resolver::resolve(
                (int)$cm->id,
                $quizid,
                $slot,
                $cannext
                    ? slot_config_schema::OUTCOME_GRADEDRIGHT
                    : slot_config_schema::OUTCOME_GRADEDWRONG,
                $profile,
                $attemptid
            ),
        ];
    }

    /**
     * Return the unpenalised mark of a question attempt, 0..1.
     *
     * get_fraction() is the mark after penalties. adaptivemultipart records the unpenalised
     * result of each potential response tree as -_rawfraction_<prtname>, and that is the honest
     * answer to "was this correct": STACK makes a student validate their input before it grades,
     * that validation carries the question's penalty, and a first-time correct answer therefore
     * never reaches the full mark. Judging correctness by the penalised figure would keep the
     * next scene shut for a right answer.
     *
     * @param \question_attempt $qa The question attempt.
     * @return float|null The mark, or null when the question has not been graded.
     */
    private static function raw_fraction(\question_attempt $qa): ?float {
        $raw = null;
        foreach ($qa->get_last_step()->get_all_data() as $name => $value) {
            if (strpos((string)$name, '-_rawfraction_') === 0 && $value !== null && $value !== '') {
                $raw = ($raw ?? 0.0) + (float)$value;
            }
        }
        if ($raw !== null) {
            // Several trees add up to the whole question, so the sum is already the 0..1 mark;
            // clamping guards against a design whose trees total more than one.
            return min(1.0, max(0.0, $raw));
        }

        $fraction = $qa->get_fraction();
        return $fraction === null ? null : (float)$fraction;
    }
    /**
     * Describe return values.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'status'        => new \core_external\external_value(PARAM_TEXT, 'Execution status'),
            'processed'     => new \core_external\external_value(PARAM_BOOL, 'Whether processing succeeded'),
            'requiresnativefallback' => new \core_external\external_value(
                PARAM_BOOL,
                'True when the client must reload the ordinary quiz page instead of continuing'
            ),
            'attemptid'     => new \core_external\external_value(PARAM_INT, 'Quiz attempt id'),
            'quizid'        => new \core_external\external_value(PARAM_INT, 'Quiz id'),
            'slot'          => new \core_external\external_value(PARAM_INT, 'Question slot'),
            'questionid'    => new \core_external\external_value(PARAM_INT, 'Question id'),
            'state'         => new \core_external\external_value(PARAM_TEXT, 'Current question state'),
            'sequencecheck' => new \core_external\external_value(PARAM_INT, 'Sequence check count'),
            'answers'       => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'name'  => new \core_external\external_value(PARAM_RAW_TRIMMED, 'Input name'),
                    'value' => new \core_external\external_value(PARAM_RAW, 'Input value'),
                ])
            ),
            'inputnames'    => new \core_external\external_multiple_structure(
                new \core_external\external_value(PARAM_RAW_TRIMMED, 'Known question input name')
            ),
            'previousstate' => new \core_external\external_value(PARAM_TEXT, 'Previous profile-tracked question state'),
            'message'       => new \core_external\external_value(PARAM_TEXT, 'Human-readable message'),
            'profile'       => get_quiz_config::profile_structure(),
            'design'        => get_quiz_config::design_structure(),
            'feedbackhtml'  => new \core_external\external_value(PARAM_RAW, 'Reserved feedback html channel'),
            'scoredelta'    => new \core_external\external_value(PARAM_INT, 'Score delta'),
            'xpdelta'       => new \core_external\external_value(PARAM_INT, 'XP delta'),
            'canretry'      => new \core_external\external_value(PARAM_BOOL, 'Whether retry remains possible'),
            'cannext'       => new \core_external\external_value(PARAM_BOOL, 'Whether frontend may advance immediately'),
            'navigation'    => navigation_resolver::external_structure(),
        ]);
    }
}
