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

use local_stackmathgame\local\service\branch_resolver;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Process a game-side answer submission and return updated attempt metadata.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_answer extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
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

        require_sesskey();

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $cm         = $attemptobj->get_cm();
        $context    = \context_module::instance((int)$cm->id);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        $quizid  = (int)$attemptobj->get_quizid();
        // Use cmid as source of truth for config lookup (patch 2026032827).
        $config  = \local_stackmathgame\game\quiz_configurator::ensure_default((int)$cm->id);
        $activity = [
            'cmid' => (int)$cm->id,
            'modname' => 'quiz',
            'instanceid' => $quizid,
            'quizid' => $quizid,
        ];
        $profile = \local_stackmathgame\local\service\profile_service::get_or_create_for_activity(
            (int)$USER->id,
            (int)$cm->id,
            'quiz',
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
        $flatpayload['-submit'] = '1';

        $externaldata = [];
        foreach ($flatpayload as $name => $value) {
            $externaldata[] = ['name' => (string)$name, 'value' => (string)$value];
        }

        $processed = false;
        $message   = get_string('submitansweraccepted', 'local_stackmathgame');

        try {
            $quizexternalfile = $CFG->dirroot . '/mod/quiz/classes/external.php';
            if (is_readable($quizexternalfile)) {
                require_once($quizexternalfile);
            }
            if (
                class_exists('mod_quiz_external')
                && method_exists('mod_quiz_external', 'process_attempt')
            ) {
                \mod_quiz_external::process_attempt(
                    $attemptid,
                    $externaldata,
                    false, // Finish attempt flag.
                    false, // Time up flag.
                    []     // Preflight data.
                );
                $processed = true;
                $message   = get_string('submitanswerprocessed', 'local_stackmathgame');
            }
        } catch (\Throwable $e) {
            $processed = false;
            $message   = get_string('submitanswerfallback', 'local_stackmathgame')
                         . ' ' . $e->getMessage();
        }

        $attemptobj    = \mod_quiz\quiz_attempt::create($attemptid);
        $qa            = $attemptobj->get_question_attempt($slot);
        // States including question_state_todo which has no get_name().
        $state = (string)$qa->get_state();
        $feedbackhtml  = '';
        $previousstate = \local_stackmathgame\local\service\profile_service::get_slot_state(
            $profile,
            $slot
        );
        $scoredelta = 0;
        $xpdelta    = 0;
        $cannext    = false;
        $bridges    = self::default_bridge_results();

        if ($processed) {
            $deltas     = \local_stackmathgame\local\service\profile_service::calculate_submit_deltas(
                $previousstate,
                $state
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
                $bridges = self::normalise_bridge_results(
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
                        ],
                        $activity
                    )
                );
            } catch (\Throwable $bridgeerr) {
                debugging(
                    'local_stackmathgame bridge dispatch failed: ' . $bridgeerr->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
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
            $state,
            $activity
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
            'answers'       => array_map(
                static function (array $answer): array {
                    return [
                        'name'  => (string)$answer['name'],
                        'value' => (string)$answer['value'],
                    ];
                },
                $answers
            ),
            'inputnames'    => (function () use ($qa): array {
                try {
                    return array_keys($qa->get_qt_data());
                } catch (\Throwable $qterr) {
                    return [];
                }
            })(),
            'previousstate' => $previousstate,
            'message'       => $message,
            'activity'      => api::export_activity($activity),
            'profile'       => api::export_profile($profile),
            'design'        => api::export_design($design),
            'bridges'       => $bridges,
            'feedbackhtml'  => $feedbackhtml,
            'scoredelta'    => $scoredelta,
            'xpdelta'       => $xpdelta,
            'canretry'      => true,
            'cannext'       => $cannext,
            'nextslot'      => $cannext
                ? branch_resolver::resolve_next_slot(
                    (int)$cm->id,
                    $quizid,
                    $slot,
                    $state,
                    $profile
                )
                : 0,
        ];
    }

    /**
     * Return the default bridge result payload.
     *
     * @return array<string, array<string, int|string|bool>>
     */
    private static function default_bridge_results(): array {
        return [
            'xp' => [
                'available' => \local_stackmathgame\local\integration\availability::has_block_xp(),
                'dispatched' => false,
            ],
            'stash' => [
                'available' => \local_stackmathgame\local\integration\availability::has_block_stash(),
                'dispatched' => false,
                'stash' => false,
                'itemkey' => '',
                'stashitemid' => 0,
            ],
        ];
    }

    /**
     * Normalise bridge results to a stable external payload.
     *
     * @param array $bridges Raw bridge dispatcher result.
     * @return array<string, array<string, int|string|bool>>
     */
    private static function normalise_bridge_results(array $bridges): array {
        $defaults = self::default_bridge_results();
        $xp = array_merge($defaults['xp'], (array)($bridges['xp'] ?? []));
        $stash = array_merge($defaults['stash'], (array)($bridges['stash'] ?? []));

        return [
            'xp' => [
                'available' => !empty($xp['available']),
                'dispatched' => !empty($xp['dispatched']),
            ],
            'stash' => [
                'available' => !empty($stash['available']),
                'dispatched' => !empty($stash['dispatched']),
                'stash' => !empty($stash['stash']),
                'itemkey' => (string)($stash['itemkey'] ?? ''),
                'stashitemid' => (int)($stash['stashitemid'] ?? 0),
            ],
        ];
    }

    /**
     * Describe the bridge result payload.
     *
     * @return \external_single_structure
     */
    private static function bridge_results_structure(): \external_single_structure {
        return new \external_single_structure([
            'xp' => new \external_single_structure([
                'available' => new \external_value(PARAM_BOOL, 'Whether block_xp is available'),
                'dispatched' => new \external_value(PARAM_BOOL, 'Whether XP dispatch occurred'),
            ]),
            'stash' => new \external_single_structure([
                'available' => new \external_value(PARAM_BOOL, 'Whether block_stash is available'),
                'dispatched' => new \external_value(PARAM_BOOL, 'Whether stash dispatch occurred'),
                'stash' => new \external_value(PARAM_BOOL, 'Whether a real block_stash grant was made'),
                'itemkey' => new \external_value(PARAM_TEXT, 'Granted item key, if any'),
                'stashitemid' => new \external_value(PARAM_INT, 'Granted block_stash item id, if any'),
            ]),
        ]);
    }

    /**
     * Describe return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status'        => new \external_value(PARAM_TEXT, 'Execution status'),
            'processed'     => new \external_value(PARAM_BOOL, 'Whether processing succeeded'),
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
            'previousstate' => new \external_value(PARAM_TEXT, 'Previous profile-tracked question state'),
            'message'       => new \external_value(PARAM_TEXT, 'Human-readable message'),
            'activity'      => new \external_single_structure([
                'cmid' => new \external_value(PARAM_INT, 'Course-module id'),
                'modname' => new \external_value(PARAM_PLUGIN, 'Activity module name'),
                'instanceid' => new \external_value(PARAM_INT, 'Activity instance id'),
                'quizid' => new \external_value(PARAM_INT, 'Legacy quiz id when applicable'),
            ]),
            'profile'       => get_quiz_config::profile_structure(),
            'design'        => get_quiz_config::design_structure(),
            'bridges'       => self::bridge_results_structure(),
            'feedbackhtml'  => new \external_value(PARAM_RAW, 'Reserved feedback html channel'),
            'scoredelta'    => new \external_value(PARAM_INT, 'Score delta'),
            'xpdelta'       => new \external_value(PARAM_INT, 'XP delta'),
            'canretry'      => new \external_value(PARAM_BOOL, 'Whether retry remains possible'),
            'cannext'       => new \external_value(PARAM_BOOL, 'Whether frontend may advance immediately'),
            'nextslot'      => new \external_value(PARAM_INT, 'Next slot number from branch_resolver (0 = linear/finish)'),
        ]);
    }
}
