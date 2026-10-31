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
 * External function: get_question_fragment.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

/**
 * Return a refreshed HTML fragment for the current question where possible.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_question_fragment extends \core_external\external_api {
    /**
     * Describe input parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'attemptid' => new \core_external\external_value(PARAM_INT, 'Quiz attempt id'),
            'slot'      => new \core_external\external_value(PARAM_INT, 'Question slot'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $attemptid The quiz attempt ID.
     * @param int $slot      The question slot number.
     * @return array The question fragment array.
     */
    public static function execute(int $attemptid, int $slot): array {
        global $CFG, $PAGE, $USER;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        // The session key protects browser-originated POSTs against cross-site request forgery.
        // A web-service call authenticates with a token and carries no cookie session, so there
        // is nothing to forge against - and requiring it there makes the endpoint unusable over
        // REST, which is how the load plans found this.
        if (!defined('WS_SERVER') || !WS_SERVER) {
            require_sesskey();
        }

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $cm         = $attemptobj->get_cm();
        $context    = \context_module::instance((int)$cm->id);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        // The same ownership checks the write endpoint makes. Reading is not harmless here: the
        // fragment is the rendered question attempt, including the responses given and any
        // feedback already earned. Without this, anyone who may play in the activity could read
        // a classmate's attempt by guessing an id - and ids are sequential.
        if ((int)$attemptobj->get_userid() !== (int)$USER->id || !$attemptobj->is_own_attempt()) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }
        if (!in_array((int)$slot, array_map('intval', $attemptobj->get_slots()), true)) {
            throw new \moodle_exception('err_unknownslot', 'local_stackmathgame', '', $slot);
        }

        $qa           = $attemptobj->get_question_attempt($slot);
        $url          = new \moodle_url('/mod/quiz/attempt.php', [
            'attempt' => $attemptid,
            'cmid'    => (int)$cm->id,
        ]);
        $renderer     = $PAGE->get_renderer('mod_quiz');
        $questionhtml = '';
        $status       = 'fallback';

        try {
            // The renderer before the page URL - quiz_attempt::render_question($slot, $reviewing,
            // $renderer, $thispageurl). The arguments used to be passed the other way round, which
            // threw a TypeError on every call; a second path through get_question_usage(), which
            // Moodle reserves for unit tests, then failed as well. The endpoint therefore never
            // returned a fragment, and the engine quietly reloaded the whole page after every check.
            $questionhtml = (string)$attemptobj->render_question($slot, false, $renderer, $url);
            if ($questionhtml !== '') {
                $status = 'ok';
            }
        } catch (\Throwable $e) {
            $status       = 'fallback';
            $questionhtml = '';
            debugging('SMG get_question_fragment error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $inputnames = api::answer_input_names($qa);

        return [
            'status'        => $status,
            'attemptid'     => $attemptid,
            'slot'          => $slot,
            'questionid'    => (int)$qa->get_question()->id,
            // A string cast, not get_name(): question_state_todo has no get_name(), so the
            // previous form threw for exactly the state a freshly opened scene is in.
            'state'         => (string)$qa->get_state(),
            'sequencecheck' => (int)$qa->get_sequence_check_count(),
            'questionhtml'  => (string)$questionhtml,
            'inputnames'    => array_values($inputnames),
        ];
    }

    /**
     * Describe return values.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'status'        => new \core_external\external_value(PARAM_TEXT, 'Render status'),
            'attemptid'     => new \core_external\external_value(PARAM_INT, 'Quiz attempt id'),
            'slot'          => new \core_external\external_value(PARAM_INT, 'Question slot'),
            'questionid'    => new \core_external\external_value(PARAM_INT, 'Question id'),
            'state'         => new \core_external\external_value(PARAM_TEXT, 'Question state'),
            'sequencecheck' => new \core_external\external_value(PARAM_INT, 'Sequence check count'),
            'questionhtml'  => new \core_external\external_value(PARAM_RAW, 'Rendered question html if available'),
            'inputnames'    => new \core_external\external_multiple_structure(
                new \core_external\external_value(PARAM_RAW_TRIMMED, 'Known question input name')
            ),
        ]);
    }
}
