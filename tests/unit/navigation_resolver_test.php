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
use local_stackmathgame\local\service\navigation_resolver;
use local_stackmathgame\local\service\profile_service;
use local_stackmathgame\local\service\question_map_service;
use local_stackmathgame\local\service\slot_config_schema;

/**
 * Unit tests for the navigation resolver (issue #2).
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\navigation_resolver
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation_resolver_test extends advanced_testcase {
    /** @var \stdClass The course-module record. */
    private \stdClass $cm;
    /** @var int The quiz instance ID. */
    private int $quizid;
    /** @var \stdClass The player profile. */
    private \stdClass $profile;

    /**
     * Build a three-slot quiz with the game enabled.
     */
    protected function setUp(): void {
        parent::setUp();
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
        for ($i = 0; $i < 3; $i++) {
            $question = $questiongenerator->create_question('shortanswer', null, [
                'category' => $category->id,
            ]);
            quiz_add_quiz_question($question->id, $quiz);
        }

        $this->quizid = (int)$quiz->id;
        $this->cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
        quiz_configurator::ensure_default((int)$this->cm->id);
        question_map_service::ensure_for_cmid((int)$this->cm->id);

        $user = $generator->create_user();
        $this->setUser($user);
        $this->profile = profile_service::get_or_create_for_quiz((int)$user->id, $this->quizid);
    }

    /**
     * Store a branching rule on a slot.
     *
     * @param int $slot The slot number.
     * @param string $outcome The outcome key.
     * @param array $rule The rule array.
     */
    private function set_rule(int $slot, string $outcome, array $rule): void {
        global $DB;
        $config = slot_config_schema::defaults();
        $config['branching'][$outcome] = $rule;
        $DB->set_field(
            'local_stackmathgame_questionmap',
            'configjson',
            json_encode($config),
            ['cmid' => (int)$this->cm->id, 'slotnumber' => $slot]
        );
    }

    /**
     * Resolve for the standard case.
     *
     * @param int $slot The current slot.
     * @param string $outcome The outcome.
     * @return array The navigation payload.
     */
    private function resolve(int $slot, string $outcome): array {
        return navigation_resolver::resolve(
            (int)$this->cm->id,
            $this->quizid,
            $slot,
            $outcome,
            $this->profile,
            77
        );
    }

    /**
     * The default rule is linear, and linear must produce a real next step.
     *
     * This is the core of issue #2: every auto-created slot gets `linear`, and the modes only
     * ever produced a control for an explicit `slot` jump - so the normal case was the one with
     * no way forward.
     */
    public function test_linear_default_advances_to_the_next_slot(): void {
        $navigation = $this->resolve(1, slot_config_schema::OUTCOME_GRADEDRIGHT);

        $this->assertSame(navigation_resolver::ACTION_CONTINUE, $navigation['action']);
        $this->assertSame(2, $navigation['nextslot']);
        $this->assertNotEmpty($navigation['url']);
        $this->assertNotEmpty($navigation['label']);
    }

    /**
     * An explicit slot jump is honoured.
     */
    public function test_explicit_slot_target_is_used(): void {
        $this->set_rule(1, slot_config_schema::OUTCOME_GRADEDRIGHT, ['mode' => 'slot', 'target' => 3]);

        $navigation = $this->resolve(1, slot_config_schema::OUTCOME_GRADEDRIGHT);
        $this->assertSame(navigation_resolver::ACTION_CONTINUE, $navigation['action']);
        $this->assertSame(3, $navigation['nextslot']);
    }

    /**
     * The `end` mode finishes the run rather than silently doing nothing.
     */
    public function test_end_mode_finishes(): void {
        $this->set_rule(1, slot_config_schema::OUTCOME_GRADEDRIGHT, ['mode' => 'end']);

        $navigation = $this->resolve(1, slot_config_schema::OUTCOME_GRADEDRIGHT);
        $this->assertSame(navigation_resolver::ACTION_FINISH, $navigation['action']);
        $this->assertSame(0, $navigation['nextslot']);
        // A finish still needs somewhere to go, or the player is stranded on the last scene.
        $this->assertNotEmpty($navigation['url']);
    }

    /**
     * The last slot finishes even under the linear default.
     */
    public function test_last_slot_finishes(): void {
        $navigation = $this->resolve(3, slot_config_schema::OUTCOME_GRADEDRIGHT);
        $this->assertSame(navigation_resolver::ACTION_FINISH, $navigation['action']);
    }

    /**
     * A wrong answer keeps the player on the scene.
     *
     * Resolving a target here would hand the client a way forward the instant an answer is
     * graded wrong, which defeats the point of a retry.
     */
    public function test_wrong_answer_stays(): void {
        $navigation = $this->resolve(1, slot_config_schema::OUTCOME_GRADEDWRONG);
        $this->assertSame(navigation_resolver::ACTION_STAY, $navigation['action']);
        $this->assertSame('', $navigation['url']);
    }

    /**
     * An unreachable slot target falls back to linear rather than stranding the player.
     */
    public function test_invalid_slot_target_falls_back_to_linear(): void {
        $this->set_rule(1, slot_config_schema::OUTCOME_GRADEDRIGHT, ['mode' => 'slot', 'target' => 99]);

        $navigation = $this->resolve(1, slot_config_schema::OUTCOME_GRADEDRIGHT);
        $this->assertSame(navigation_resolver::ACTION_CONTINUE, $navigation['action']);
        $this->assertSame(2, $navigation['nextslot']);
    }

    /**
     * Without an attempt there is no URL, but the decision is still reported.
     *
     * A caller that only wants to know where the player would go must not be forced to invent
     * an attempt id.
     */
    public function test_missing_attempt_yields_decision_without_url(): void {
        $navigation = navigation_resolver::resolve(
            (int)$this->cm->id,
            $this->quizid,
            1,
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            $this->profile,
            0
        );
        $this->assertSame(2, $navigation['nextslot']);
        $this->assertSame('', $navigation['url']);
    }

    /**
     * quiz_slots.page is one-based; the attempt page parameter is zero-based.
     *
     * Getting this wrong is invisible - the player simply lands on the wrong question.
     */
    public function test_page_for_slot_is_zero_based(): void {
        $this->assertSame(0, navigation_resolver::page_for_slot($this->quizid, 1));
        $this->assertSame(1, navigation_resolver::page_for_slot($this->quizid, 2));
    }
}
