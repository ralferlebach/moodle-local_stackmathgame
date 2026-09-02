<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_stackmathgame\unit;

use advanced_testcase;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\local\service\prerequisite_checker;

/**
 * Unit tests for the prerequisite checker.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\prerequisite_checker
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class prerequisite_checker_test extends advanced_testcase {
    /**
     * Create a course and quiz for testing.
     *
     * @param string $behaviour The quiz preferredbehaviour.
     * @param int $questioncount How many shortanswer questions to add.
     * @return \stdClass The course-module record.
     */
    private function make_quiz(string $behaviour, int $questioncount = 2): \stdClass {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', [
            'course' => $course->id,
            'preferredbehaviour' => $behaviour,
            'questionsperpage' => 1,
        ]);

        if ($questioncount > 0) {
            /** @var \core_question_generator $questiongenerator */
            $questiongenerator = $generator->get_plugin_generator('core_question');
            $category = $questiongenerator->create_question_category();
            for ($i = 0; $i < $questioncount; $i++) {
                $question = $questiongenerator->create_question('shortanswer', null, [
                    'category' => $category->id,
                ]);
                quiz_add_quiz_question($question->id, $quiz);
            }
        }

        return get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
    }

    /**
     * Return the single check with the given key.
     *
     * @param array[] $checks The check results.
     * @param string $key The check key.
     * @return array The matching check.
     */
    private function check_for(array $checks, string $key): array {
        foreach ($checks as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }
        $this->fail('No check with key ' . $key);
    }

    /**
     * The wrong question behaviour is a blocking error, not a warning.
     *
     * This is the whole point of issue #3: such a quiz renders and starts an attempt perfectly,
     * so nothing else in the system would ever notice.
     */
    public function test_wrong_behaviour_blocks(): void {
        $this->resetAfterTest();
        $cm = $this->make_quiz('deferredfeedback');
        quiz_configurator::ensure_default((int)$cm->id);

        $check = $this->check_for(prerequisite_checker::check((int)$cm->id), 'behaviour');
        $this->assertSame(prerequisite_checker::STATUS_ERROR, $check['status']);
        $this->assertStringContainsString('deferredfeedback', $check['message']);
        $this->assertNotEmpty($check['fixurl'], 'A blocking check should say where to fix it.');
        $this->assertFalse(prerequisite_checker::is_playable((int)$cm->id));
    }

    /**
     * The correct behaviour satisfies that check.
     */
    public function test_correct_behaviour_passes(): void {
        $this->resetAfterTest();
        $cm = $this->make_quiz('stackmathgame');
        quiz_configurator::ensure_default((int)$cm->id);

        $check = $this->check_for(prerequisite_checker::check((int)$cm->id), 'behaviour');
        $this->assertSame(prerequisite_checker::STATUS_OK, $check['status']);
    }

    /**
     * requiresbehaviour = 0 downgrades the behaviour check to a warning rather than removing it.
     *
     * Removing it entirely would make an administrator's override indistinguishable from a
     * correctly configured quiz, which is how the original bug stayed invisible.
     */
    public function test_behaviour_check_downgrades_when_not_enforced(): void {
        global $DB;
        $this->resetAfterTest();
        $cm = $this->make_quiz('deferredfeedback');
        $config = quiz_configurator::ensure_default((int)$cm->id);
        $DB->set_field('local_stackmathgame', 'requiresbehaviour', 0, ['id' => $config->id]);

        $check = $this->check_for(prerequisite_checker::check((int)$cm->id), 'behaviour');
        $this->assertSame(prerequisite_checker::STATUS_WARNING, $check['status']);
    }

    /**
     * A quiz without STACK questions cannot run a game.
     */
    public function test_no_stack_questions_blocks(): void {
        $this->resetAfterTest();
        $cm = $this->make_quiz('stackmathgame', 2);
        quiz_configurator::ensure_default((int)$cm->id);

        $check = $this->check_for(prerequisite_checker::check((int)$cm->id), 'questions');
        $this->assertSame(prerequisite_checker::STATUS_ERROR, $check['status']);
    }

    /**
     * An empty quiz reports the emptiness rather than the absence of STACK questions.
     *
     * The distinction matters to the teacher: "add STACK questions" is useless advice for a quiz
     * that has no questions at all.
     */
    public function test_empty_quiz_reports_emptiness(): void {
        $this->resetAfterTest();
        $cm = $this->make_quiz('stackmathgame', 0);
        quiz_configurator::ensure_default((int)$cm->id);

        $check = $this->check_for(prerequisite_checker::check((int)$cm->id), 'questions');
        $this->assertSame(prerequisite_checker::STATUS_ERROR, $check['status']);
        $this->assertSame(
            get_string('prereq_questions_none', 'local_stackmathgame'),
            $check['message']
        );
    }

    /**
     * get_blockers() returns only the blocking checks.
     */
    public function test_get_blockers_returns_only_errors(): void {
        $this->resetAfterTest();
        $cm = $this->make_quiz('deferredfeedback');
        quiz_configurator::ensure_default((int)$cm->id);

        $blockers = prerequisite_checker::get_blockers((int)$cm->id);
        $this->assertNotEmpty($blockers);
        foreach ($blockers as $blocker) {
            $this->assertSame(prerequisite_checker::STATUS_ERROR, $blocker['status']);
        }
    }

    /**
     * A fully configured quiz is playable.
     *
     * The negative cases were always testable; this one needs qtype_stack, qbehaviour_stackmathgame
     * and filter_shortcodes actually installed, which is why it was missing until the CI grew a
     * Maxima installation. Without it, every assertion in this class was about a refusal, and a
     * checker that refuses everything would have passed them all.
     */
    public function test_fully_configured_quiz_is_playable(): void {
        if (!\core_component::get_component_directory('qtype_stack')) {
            $this->markTestSkipped('qtype_stack is not installed in this tree.');
        }

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', [
            'course' => $course->id,
            'preferredbehaviour' => 'stackmathgame',
            'questionsperpage' => 1,
        ]);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('stack', 'test1', ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
        quiz_configurator::ensure_default((int)$cm->id);
        \local_stackmathgame\local\service\question_map_service::ensure_for_cmid((int)$cm->id);

        $blockers = prerequisite_checker::get_blockers((int)$cm->id);
        $this->assertSame(
            [],
            $blockers,
            'Unexpected blockers: ' . implode(' | ', array_column($blockers, 'message'))
        );
        $this->assertTrue(prerequisite_checker::is_playable((int)$cm->id));
    }

    /**
     * The question behaviour must be selectable as a quiz's preferred behaviour.
     *
     * This is the defect the first Behat run surfaced, four layers from its cause: a behaviour
     * type that does not declare itself archetypal is left out of the quiz behaviour menu, and
     * question_engine::make_archetypal_behaviour() throws when an attempt starts. Asserting it
     * here turns a mid-attempt coding exception into a named failure.
     */
    public function test_behaviour_is_archetypal(): void {
        if (!\core_component::get_component_directory('qbehaviour_stackmathgame')) {
            $this->markTestSkipped('qbehaviour_stackmathgame is not installed in this tree.');
        }

        $this->assertArrayHasKey(
            prerequisite_checker::REQUIRED_BEHAVIOUR,
            \question_engine::get_archetypal_behaviours(),
            'qbehaviour_stackmathgame is not archetypal, so no quiz can be set to it. '
                . 'Its behaviourtype.php needs is_archetypal() returning true.'
        );
    }

    /**
     * Every check carries a label and a message, so the panel never renders an empty cell.
     */
    public function test_every_check_is_fully_populated(): void {
        $this->resetAfterTest();
        $cm = $this->make_quiz('stackmathgame');
        quiz_configurator::ensure_default((int)$cm->id);

        $checks = prerequisite_checker::check((int)$cm->id);
        $this->assertNotEmpty($checks);
        foreach ($checks as $check) {
            $this->assertNotEmpty($check['key']);
            $this->assertNotEmpty($check['label'], 'Check ' . $check['key'] . ' has no label.');
            $this->assertNotEmpty($check['message'], 'Check ' . $check['key'] . ' has no message.');
            $this->assertContains($check['status'], [
                prerequisite_checker::STATUS_OK,
                prerequisite_checker::STATUS_WARNING,
                prerequisite_checker::STATUS_ERROR,
            ]);
        }
    }
}
