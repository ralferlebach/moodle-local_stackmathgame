<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Returns a refreshed HTML fragment for the current question where possible.
 */
class get_question_fragment extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'attemptid' => new \external_value(PARAM_INT, 'Quiz attempt id'),
            'slot' => new \external_value(PARAM_INT, 'Question slot'),
        ]);
    }

    public static function execute(int $attemptid, int $slot): array {
        global $PAGE;

        require_sesskey();

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
        $cm = $attemptobj->get_cm();
        $context = \context_module::instance((int)$cm->id);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        $qa = $attemptobj->get_question_attempt($slot);
        $url = new \moodle_url('/mod/quiz/attempt.php', ['attempt' => $attemptid, 'cmid' => (int)$cm->id]);
        $renderer = $PAGE->get_renderer('mod_quiz');
        $questionhtml = '';
        $status = 'fallback';

        try {
            if (method_exists($attemptobj, 'render_question')) {
                try {
                    $result = $attemptobj->render_question($slot, false, $url, $renderer);
                    if (is_string($result) && $result !== '') {
                        $questionhtml = $result;
                    } else if (is_object($result) && method_exists($renderer, 'render')) {
                        $questionhtml = $renderer->render($result);
                    }
                } catch (\Throwable $e) {
                    // Fall back to alternative strategies below.
                }
            }

            if ($questionhtml === '') {
                try {
                    $usage = $attemptobj->get_question_usage();
                    $options = $attemptobj->get_display_options(true);
                    $questionhtml = $usage->render_question($slot, $renderer, $options, (string)$slot);
                } catch (\Throwable $e) {
                    // Leave empty and let the client use full-page fallback.
                }
            }

            if ($questionhtml !== '') {
                $status = 'ok';
            }
        } catch (\Throwable $e) {
            $status = 'fallback';
            $questionhtml = '';
        }

        $inputnames = [];
        foreach ($qa->get_qt_data() as $name => $value) {
            if ($name !== '-submit' && $name !== ':sequencecheck' && strpos($name, '_') !== 0) {
                $inputnames[] = (string)$name;
            }
        }

        return [
            'status' => $status,
            'attemptid' => $attemptid,
            'slot' => $slot,
            'questionid' => (int)$qa->get_question()->id,
            'state' => (string)$qa->get_state()->get_name(),
            'sequencecheck' => (int)$qa->get_sequence_check_count(),
            'questionhtml' => (string)$questionhtml,
            'inputnames' => array_values($inputnames),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_TEXT, 'Render status'),
            'attemptid' => new \external_value(PARAM_INT, 'Quiz attempt id'),
            'slot' => new \external_value(PARAM_INT, 'Question slot'),
            'questionid' => new \external_value(PARAM_INT, 'Question id'),
            'state' => new \external_value(PARAM_TEXT, 'Question state'),
            'sequencecheck' => new \external_value(PARAM_INT, 'Sequence check count'),
            'questionhtml' => new \external_value(PARAM_RAW, 'Rendered question html if available'),
            'inputnames' => new \external_multiple_structure(new \external_value(PARAM_RAW_TRIMMED, 'Known question input name')),
        ]);
    }
}
