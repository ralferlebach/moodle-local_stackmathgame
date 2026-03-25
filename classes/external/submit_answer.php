<?php
// This file is part of Moodle - http://moodle.org/

namespace local_stackmathgame\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use local_stackmathgame\game\state_machine;
use local_stackmathgame\game\mechanic_registry;
use local_stackmathgame\game\quiz_configurator;

/**
 * External function: submit_answer
 *
 * This function is called by the browser AJAX layer after the browser-side AJAX
 * chain has already submitted the answer to Moodle/STACK and received feedback.
 *
 * The browser sends the parsed feedback result, and this function:
 *  1. Validates the request
 *  2. Persists the game state change (mark solved, apply score deltas)
 *  3. Returns new score values and any mechanic events for the client
 *
 * The STACK answer submission itself remains browser-side (conservative approach),
 * but game-state persistence is fully server-side and atomic.
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_answer extends external_api {

    /**
     * Parameter specification.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([

            // The Moodle quiz attempt ID.
            'attemptid'   => new external_value(PARAM_INT,  'Quiz attempt ID'),

            // The quiz CM ID (for capability check).
            'cmid'        => new external_value(PARAM_INT,  'Course module ID'),

            // The site-wide label ID this quiz is assigned to.
            'labelid'     => new external_value(PARAM_INT,  'Label ID'),

            // The question identifier (string key from game config).
            'questionid'  => new external_value(PARAM_TEXT, 'Question ID string key'),

            // The variant page index that was answered (0-based within this question).
            'variantpage' => new external_value(PARAM_INT,  'Variant page index', VALUE_DEFAULT, -1),

            // The outcome as determined by the browser-side AJAX chain.
            // 'correct' | 'partial' | 'incorrect'
            'outcome'     => new external_value(PARAM_ALPHA, 'Outcome: correct|partial|incorrect'),

            // The Moodle session key for CSRF protection.
            'sesskey'     => new external_value(PARAM_RAW,  'Moodle sesskey'),
        ]);
    }

    /**
     * Return value specification.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'     => new external_value(PARAM_BOOL, 'Whether state was persisted'),
            'new_scores'  => new external_single_structure([
                'fairies' => new external_value(PARAM_INT, 'Current fairy count'),
                'mana'    => new external_value(PARAM_INT, 'Current mana'),
            ]),
            'score_delta' => new external_single_structure([
                'fairies' => new external_value(PARAM_INT, 'Change in fairies'),
                'mana'    => new external_value(PARAM_INT, 'Change in mana'),
            ]),
            // Client-side animation/event triggers.
            'events'      => new external_multiple_structure(
                new external_single_structure([
                    'type'  => new external_value(PARAM_ALPHA, 'Event type'),
                    'data'  => new external_value(PARAM_RAW,   'Event payload JSON', VALUE_DEFAULT, '{}'),
                ])
            ),
        ]);
    }

    /**
     * Execute: persist game state after browser-side STACK evaluation.
     */
    public static function execute(
        int    $attemptid,
        int    $cmid,
        int    $labelid,
        string $questionid,
        int    $variantpage,
        string $outcome,
        string $sesskey
    ): array {
        global $USER;

        // --- Validate parameters ---
        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid'   => $attemptid,
            'cmid'        => $cmid,
            'labelid'     => $labelid,
            'questionid'  => $questionid,
            'variantpage' => $variantpage,
            'outcome'     => $outcome,
            'sesskey'     => $sesskey,
        ]);

        // CSRF check.
        if (!confirm_sesskey($params['sesskey'])) {
            throw new \moodle_exception('invalidsesskey');
        }

        // Capability check.
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        // Verify the attempt belongs to the current user.
        $attempt = self::require_own_attempt($params['attemptid'], (int) $USER->id);

        // --- Retrieve mechanic config for this quiz ---
        $plugincfg   = quiz_configurator::get_plugin_config($attempt->quiz);
        $overrides   = $plugincfg ? json_decode($plugincfg->configjson ?? '{}', true) : [];
        $mechanicscfg = $overrides['mechanics'] ?? [];

        // --- Apply game state changes ---
        $userid  = (int) $USER->id;
        $labelid = $params['labelid'];
        $qid     = $params['questionid'];
        $vpage   = $params['variantpage'];

        $context_data = [
            'quizid'       => $attempt->quiz,
            'attemptid'    => $params['attemptid'],
            'questionid'   => $qid,
            'variantpage'  => $vpage,
            'mechanicscfg' => $mechanicscfg,
        ];

        if ($params['outcome'] === 'correct') {
            // Persist solved state.
            state_machine::mark_solved($userid, $labelid, $qid, $vpage);

            // Trigger mechanics.
            $mechresult = mechanic_registry::trigger(
                'on_question_solved', $userid, $labelid, $context_data
            );
        } else {
            // Wrong / partial: only apply penalty mechanics, don't mark solved.
            $mechresult = mechanic_registry::trigger(
                'on_question_failed', $userid, $labelid, $context_data
            );
        }

        // --- Build response ---
        $scores = state_machine::load_scores($userid, $labelid);

        $events = [];
        foreach (($mechresult['events'] ?? []) as $ev) {
            $events[] = [
                'type' => $ev['type'] ?? 'unknown',
                'data' => json_encode($ev['data'] ?? []),
            ];
        }

        return [
            'success'     => true,
            'new_scores'  => [
                'fairies' => (int) ($scores['fairies'] ?? 0),
                'mana'    => (int) ($scores['mana']    ?? 0),
            ],
            'score_delta' => [
                'fairies' => (int) ($mechresult['score_delta']['fairies'] ?? 0),
                'mana'    => (int) ($mechresult['score_delta']['mana']    ?? 0),
            ],
            'events'      => $events,
        ];
    }

    /**
     * Verify the attempt belongs to the user and return the attempt record.
     * Throws moodle_exception on failure.
     */
    private static function require_own_attempt(int $attemptid, int $userid): \stdClass {
        global $DB;

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'userid' => $userid]);
        if (!$attempt) {
            throw new \moodle_exception('invalidattempt', 'local_stackmathgame');
        }
        if ($attempt->state !== \quiz_attempt::IN_PROGRESS) {
            throw new \moodle_exception('attemptnotinprogress', 'local_stackmathgame');
        }

        return $attempt;
    }
}
