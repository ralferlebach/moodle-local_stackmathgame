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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Return a refreshed HTML fragment for the current question where possible.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_question_fragment extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'attemptid' => new \external_value(PARAM_INT, 'Quiz attempt id'),
            'slot'      => new \external_value(PARAM_INT, 'Question slot'),
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
        global $PAGE;

        require_sesskey();

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $cm         = $attemptobj->get_cm();
        $context    = \context_module::instance((int)$cm->id);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        $qa           = $attemptobj->get_question_attempt($slot);
        $url          = new \moodle_url('/mod/quiz/attempt.php', [
            'attempt' => $attemptid,
            'cmid'    => (int)$cm->id,
        ]);
        $renderer     = $PAGE->get_renderer('mod_quiz');
        $questionhtml = '';
        $status       = 'fallback';

        try {
            if (method_exists($attemptobj, 'render_question')) {
                try {
                    $result = $attemptobj->render_question($slot, false, $url, $renderer);
                    if (is_string($result) && $result !== '') {
                        $questionhtml = $result;
                    } else if (is_object($result) && method_exists($renderer, 'render')) {
                        $questionhtml = $renderer->render($result);
                    }
                } catch (\Throwable $inner) {
                    debugging('SMG render_question failed: ' . $inner->getMessage(), DEBUG_DEVELOPER);
                }
            }
            if ($questionhtml === '') {
                try {
                    $usage        = $attemptobj->get_question_usage();
                    $options      = $attemptobj->get_display_options(true);
                    $questionhtml = $usage->render_question(
                        $slot,
                        $renderer,
                        $options,
                        (string)$slot
                    );
                } catch (\Throwable $inner) {
                    debugging('SMG render via usage failed: ' . $inner->getMessage(), DEBUG_DEVELOPER);
                }
            }
            if ($questionhtml !== '') {
                $status = 'ok';
            }
        } catch (\Throwable $e) {
            $status       = 'fallback';
            $questionhtml = '';
            debugging('SMG get_question_fragment error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $inputnames = [];
        foreach ($qa->get_qt_data() as $name => $value) {
            if ($name !== '-submit' && $name !== ':sequencecheck' && strpos($name, '_') !== 0) {
                $inputnames[] = (string)$name;
            }
        }

        return [
            'status'        => $status,
            'attemptid'     => $attemptid,
            'slot'          => $slot,
            'questionid'    => (int)$qa->get_question()->id,
            'state'         => (string)$qa->get_state()->get_name(),
            'sequencecheck' => (int)$qa->get_sequence_check_count(),
            'questionhtml'  => (string)$questionhtml,
            'inputnames'    => array_values($inputnames),
        ];
    }

    /**
     * Describe return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status'        => new \external_value(PARAM_TEXT, 'Render status'),
            'attemptid'     => new \external_value(PARAM_INT, 'Quiz attempt id'),
            'slot'          => new \external_value(PARAM_INT, 'Question slot'),
            'questionid'    => new \external_value(PARAM_INT, 'Question id'),
            'state'         => new \external_value(PARAM_TEXT, 'Question state'),
            'sequencecheck' => new \external_value(PARAM_INT, 'Sequence check count'),
            'questionhtml'  => new \external_value(PARAM_RAW, 'Rendered question html if available'),
            'inputnames'    => new \external_multiple_structure(
                new \external_value(PARAM_RAW_TRIMMED, 'Known question input name')
            ),
        ]);
    }
}
