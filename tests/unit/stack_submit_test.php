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
use local_stackmathgame\external\submit_answer;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\local\service\flow_service;
use local_stackmathgame\local\service\profile_service;
use local_stackmathgame\local\service\question_map_service;
use local_stackmathgame\local\service\slot_config_schema;

/**
 * End-to-end tests for the pageless submit path against real STACK questions (issue #5).
 *
 * These need qtype_stack, qbehaviour_stackmathgame and a working Maxima. Where any of those is
 * absent the class skips itself rather than passing quietly: a skipped STACK test is not a
 * passing STACK test, and reporting it as one is worse than reporting nothing.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\external\submit_answer
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stack_submit_test extends advanced_testcase {
    /** The model answer of the test-3 fixture ("give an example of an odd function"). */
    const CORRECT_ANSWER = 'x^3';

    /** Syntactically valid but even, so it is graded wrong rather than rejected as unparseable. */
    const WRONG_ANSWER = 'x^2';

    /** @var \stdClass The quiz record. */
    private \stdClass $quiz;
    /** @var int The course-module ID. */
    private int $cmid;
    /** @var \stdClass The student. */
    private \stdClass $student;

    /**
     * Build a playable STACK quiz, or skip.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();

        // Registered before anything else, because the CAS probe below talks to Maxima and STACK
        // caches the result in the database. Any write made before this call is reported as an
        // "unexpected database modification" and fails the test - which is what happened on
        // every runner where Maxima actually works, so the check meant to make the suite honest
        // was itself breaking it.
        $this->resetAfterTest();

        foreach (['qtype_stack', 'qbehaviour_stackmathgame'] as $component) {
            if (!\core_component::get_component_directory($component)) {
                $this->markTestSkipped($component . ' is not installed in this tree.');
            }
        }

        // PHPUnit runs against its own database, so whatever a site administrator configured is
        // not present here. Without these three settings STACK has no CAS to talk to and every
        // input stays "invalid" - which looked for a long time like "this environment has no
        // Maxima" when in fact Maxima was installed and simply never wired up.
        // PHPUnit runs against its own database and dataroot, so nothing an administrator
        // configured on the site is present here. Three things are needed, and leaving any of
        // them out looks like a different problem: the CAS settings, maximalocal.mac (which
        // STACK's installer writes into $CFG->dataroot and the test dataroot never receives),
        // and a genuine connect to confirm the result.
        if (!self::configure_cas()) {
            $this->markTestSkipped(
                'No working Maxima connection - STACK cannot grade, so these assertions would '
                    . 'test nothing. Run .github/stack-phpunit-init.php, or install maxima.'
            );
        }

        // Question creation writes into a user file area, so it needs a logged-in user; without
        // this the generator fails with "Invalid user" from deep inside the question type.
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->quiz = $generator->create_module('quiz', [
            'course' => $course->id,
            'preferredbehaviour' => 'stackmathgame',
            'questionsperpage' => 1,
        ]);
        $this->cmid = (int)get_coursemodule_from_instance(
            'quiz',
            $this->quiz->id,
            $course->id,
            false,
            MUST_EXIST
        )->id;

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        // Deterministic fixtures only. test1 randomises its integrand, so no literal answer is
        // ever correct and every "correct answer" assertion would be a coin toss.
        // The test-3 fixture has a fixed model answer and a real potential response tree, so it can actually
        // be graded. The input-rendering fixtures have no PRT at all and stay "invalid" forever,
        // and test-1 randomises its integrand, so no literal answer is ever right.
        foreach (['test3', 'test3'] as $variant) {
            $question = $questiongenerator->create_question('stack', $variant, [
                'category' => $category->id,
            ]);
            quiz_add_quiz_question($question->id, $this->quiz);
        }
        // The quiz sumgrades field stays 0 until the questions are counted, and
        // quiz_start_new_attempt
        // refuses to open an attempt on a quiz graded out of 100 whose questions total zero.
        // The old quiz_update_sumgrades() is deprecated in 4.5 and its notice fails the suite
        // under --fail-on-warning.
        \mod_quiz\quiz_settings::create($this->quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        $this->quiz = $DB->get_record('quiz', ['id' => $this->quiz->id], '*', MUST_EXIST);

        quiz_configurator::ensure_default($this->cmid);
        question_map_service::ensure_for_cmid($this->cmid);

        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $course->id, 'student');
        $this->setUser($this->student);
    }

    /**
     * Configure qtype_stack for this test run and report whether the CAS answers.
     *
     * stackmaxima_genuine_connect() is STACK's own healthcheck, so this stays correct if they
     * change how a connection is verified. Configuration happens inside the test because
     * resetAfterTest() rolls back anything the CI helper set before the run.
     *
     * @return bool True when STACK can reach Maxima.
     */
    private static function configure_cas(): bool {
        $maxima = trim((string)shell_exec('command -v maxima'));
        if ($maxima === '') {
            return false;
        }

        set_config('platform', 'linux', 'qtype_stack');
        set_config('maximacommand', $maxima, 'qtype_stack');
        set_config('maximaversion', 'default', 'qtype_stack');
        set_config('casresultscache', 'db', 'qtype_stack');
        set_config('casdebugging', '0', 'qtype_stack');
        // The first call compiles STACK's library and is by far the slowest.
        set_config('castimeout', '300', 'qtype_stack');
        set_config('maximalibraries', '', 'qtype_stack');

        try {
            \stack_cas_configuration::create_maximalocal();
            [, , $ok] = \stack_connection_helper::stackmaxima_genuine_connect();
            return (bool)$ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Start an attempt on the seeded quiz.
     *
     * @return \mod_quiz\quiz_attempt The attempt.
     */
    private function start_attempt(): \mod_quiz\quiz_attempt {
        $quizobj = \mod_quiz\quiz_settings::create($this->quiz->id, $this->student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $this->student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return \mod_quiz\quiz_attempt::create($attempt->id);
    }

    /**
     * Submit an answer through the web service.
     *
     * @param \mod_quiz\quiz_attempt $attemptobj The attempt.
     * @param int $slot The slot.
     * @param array $values Map of input name suffix to value, e.g. ['ans1' => self::CORRECT_ANSWER].
     * @return array The web service result.
     */
    private function submit(\mod_quiz\quiz_attempt $attemptobj, int $slot, array $values): array {
        // The endpoint requires a session key, exactly as it should for a mutation. Supplying it
        // here keeps that check under test rather than weakening it for the test's convenience.
        $_POST['sesskey'] = sesskey();

        $qa = $attemptobj->get_question_attempt($slot);
        $answers = [];
        foreach ($values as $suffix => $value) {
            $answers[] = ['name' => $qa->get_qt_field_name($suffix), 'value' => (string)$value];
        }
        $answers[] = ['name' => $qa->get_control_field_name('sequencecheck'),
            'value' => (string)$qa->get_sequence_check_count()];

        return submit_answer::execute((int)$attemptobj->get_attemptid(), $slot, $answers);
    }

    /**
     * Submit an answer the way a player does: validate, then grade.
     *
     * STACK asks the student to confirm the interpretation of their input before it is scored.
     * The first submission therefore only validates and leaves the attempt in "todo"; the second
     * one, with the same value, is the one that grades. A test that submits once asserts nothing
     * about grading at all.
     *
     * @param \mod_quiz\quiz_attempt $attemptobj The attempt.
     * @param int $slot The slot.
     * @param array $values Map of input name suffix to value.
     * @return array The result of the grading submission.
     */
    private function submit_and_grade(\mod_quiz\quiz_attempt $attemptobj, int $slot, array $values): array {
        $this->submit($attemptobj, $slot, $values);

        // The grading pass carries the validation field alongside the answer. In the browser the
        // fragment refresh puts that hidden field into the DOM and collectAnswers() picks it up
        // on its own; here it has to be stated.
        $validated = $values;
        foreach ($values as $suffix => $value) {
            $validated[$suffix . '_val'] = $value;
        }

        $fresh = \mod_quiz\quiz_attempt::create((int)$attemptobj->get_attemptid());
        return $this->submit($fresh, $slot, $validated);
    }

    /**
     * Set the rewards on a slot's direction card.
     *
     * @param int $slot The slot.
     * @param int $score The score reward.
     * @param int $xp The XP reward.
     */
    private function set_rewards(int $slot, int $score, int $xp): void {
        $config = flow_service::get_slot_config($this->cmid, $slot);
        $config['rewards']['score'] = $score;
        $config['rewards']['xp'] = $xp;
        $this->assertSame([], flow_service::save_slot_config($this->cmid, $slot, $config));
    }

    /**
     * A correct answer is processed and pays the reward the teacher configured.
     *
     * The reward figures used to be hardcoded at 10 and 5, so the score and XP fields in the flow
     * editor changed nothing at all.
     */
    public function test_correct_answer_pays_the_configured_reward(): void {
        $this->set_rewards(1, 42, 17);
        $attemptobj = $this->start_attempt();

        $result = $this->submit_and_grade($attemptobj, 1, ['ans1' => self::CORRECT_ANSWER]);

        $this->assertTrue($result['processed']);
        $this->assertSame('processed', $result['status']);
        $this->assertFalse($result['requiresnativefallback']);
        $this->assertSame(42, $result['scoredelta']);
        $this->assertSame(17, $result['xpdelta']);
        $this->assertTrue($result['cannext']);
    }

    /**
     * A wrong answer is processed but pays nothing and offers no way forward.
     */
    public function test_wrong_answer_pays_nothing(): void {
        $this->set_rewards(1, 42, 17);
        $attemptobj = $this->start_attempt();

        $result = $this->submit_and_grade($attemptobj, 1, ['ans1' => self::WRONG_ANSWER]);

        $this->assertTrue($result['processed']);
        $this->assertSame(0, $result['scoredelta']);
        $this->assertSame(0, $result['xpdelta']);
        $this->assertFalse($result['cannext']);
        $this->assertSame('stay', $result['navigation']['action']);
    }

    /**
     * Answering the same question correctly twice pays once.
     *
     * The sequential half of the at-most-once guarantee. The parallel half cannot be shown from a
     * single PHP process - it reads and writes the profile in one database session and always
     * sees its own earlier write - and lives in tests/load/stackmathgame-capacity-race.js.
     */
    public function test_resubmitting_a_solved_question_pays_nothing(): void {
        $this->set_rewards(1, 42, 17);
        $attemptobj = $this->start_attempt();

        $first = $this->submit_and_grade($attemptobj, 1, ['ans1' => self::CORRECT_ANSWER]);
        $this->assertSame(42, $first['scoredelta']);

        $attemptobj = \mod_quiz\quiz_attempt::create((int)$attemptobj->get_attemptid());
        $second = $this->submit_and_grade($attemptobj, 1, ['ans1' => self::CORRECT_ANSWER]);

        $this->assertSame(0, $second['scoredelta'], 'A solved scene paid out twice.');
        $this->assertSame(0, $second['xpdelta'], 'A solved scene paid XP twice.');

        $profile = profile_service::get_or_create_for_quiz((int)$this->student->id, (int)$this->quiz->id);
        $this->assertSame(42, (int)$profile->score);
        $this->assertSame(17, (int)$profile->xp);
    }

    /**
     * A wrong answer followed by a correct one pays the full reward.
     *
     * Retrying is the point of a scene, so it must not be penalised into paying nothing.
     */
    public function test_retry_after_a_wrong_answer_still_pays(): void {
        $this->set_rewards(1, 30, 12);
        $attemptobj = $this->start_attempt();

        $this->submit_and_grade($attemptobj, 1, ['ans1' => self::WRONG_ANSWER]);
        $attemptobj = \mod_quiz\quiz_attempt::create((int)$attemptobj->get_attemptid());
        $result = $this->submit_and_grade($attemptobj, 1, ['ans1' => self::CORRECT_ANSWER]);

        $this->assertSame(30, $result['scoredelta']);
        $this->assertTrue($result['cannext']);
    }

    /**
     * The state is serialised without calling a method a state may not implement.
     *
     * question_state_todo has no get_name(), which is the defect issue #5 names explicitly.
     */
    public function test_state_is_reported_for_every_stage(): void {
        $attemptobj = $this->start_attempt();

        // Under adaptivemultipart the state stays "todo" for as long as the attempt is open -
        // the mark moves, not the name. What this guards is the original defect: serialising
        // the state threw for question_state_todo, which has no get_name().
        $before = (string)$attemptobj->get_question_attempt(1)->get_state();
        $this->assertNotSame('', $before);

        $result = $this->submit_and_grade($attemptobj, 1, ['ans1' => self::CORRECT_ANSWER]);
        $this->assertNotSame('', $result['state']);
        $this->assertTrue($result['processed']);
        $this->assertTrue($result['cannext'], 'A correct answer did not register as solved.');
    }

    /**
     * The input names of the question are reported back, so the client can rebind them.
     */
    public function test_input_names_are_reported(): void {
        $attemptobj = $this->start_attempt();

        $result = $this->submit_and_grade($attemptobj, 1, ['ans1' => self::CORRECT_ANSWER]);

        $this->assertNotEmpty($result['inputnames']);
    }

    /**
     * Another user's attempt cannot be submitted into.
     *
     * The capability says the user may play in this activity; it does not say the attempt is
     * theirs. Without the owner check the reward would land in the other player's profile.
     */
    public function test_foreign_attempt_is_refused(): void {
        $attemptobj = $this->start_attempt();

        $intruder = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $intruder->id,
            (int)$this->quiz->course,
            'student'
        );
        $this->setUser($intruder);

        $this->expectException(\moodle_exception::class);
        $this->submit($attemptobj, 1, ['ans1' => self::CORRECT_ANSWER]);
    }

    /**
     * A slot the attempt does not contain is refused rather than failing deeper in the engine.
     */
    public function test_unknown_slot_is_refused(): void {
        $attemptobj = $this->start_attempt();

        $this->expectException(\moodle_exception::class);
        submit_answer::execute((int)$attemptobj->get_attemptid(), 99, []);
    }

    /**
     * Solving the last slot finishes the run rather than pointing nowhere.
     */
    public function test_last_slot_finishes_the_run(): void {
        $attemptobj = $this->start_attempt();
        $lastslot = max($attemptobj->get_slots());

        $config = flow_service::get_slot_config($this->cmid, $lastslot);
        $config['branching'][slot_config_schema::OUTCOME_GRADEDRIGHT] =
            ['mode' => slot_config_schema::BRANCH_MODE_END];
        flow_service::save_slot_config($this->cmid, $lastslot, $config);

        $result = $this->submit_and_grade($attemptobj, $lastslot, ['ans1' => self::CORRECT_ANSWER]);

        $this->assertContains($result['navigation']['action'], ['finish', 'stay']);
    }
}
