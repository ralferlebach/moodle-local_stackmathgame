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

use local_stackmathgame\external\get_question_fragment;
use local_stackmathgame\external\submit_answer;
use local_stackmathgame\local\service\flow_service;
use local_stackmathgame\local\service\slot_config_schema;
use local_stackmathgame\tests\game_quiz_testcase;

/**
 * The runtime endpoints against the attempts and slots they must refuse (re-audit N01, N06, N08).
 *
 * Built on shortanswer questions, which need no CAS, so these run on every runner. The STACK
 * specific half of the submit path lives in stack_submit_test.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\external\get_question_fragment
 * @covers     \local_stackmathgame\external\submit_answer
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class runtime_endpoints_security_test extends game_quiz_testcase {
    /** A value with the exact shape of a Moodle session key, sent where an answer field could be. */
    const SENTINEL_SESSKEY = 'Zq7Sentnl9';

    /** A value sent in a field the log has no business keeping. */
    const SENTINEL_OTHER = 'not-a-diagnostic-field-4711';

    /** @var \stdClass The student who owns the attempt. */
    private \stdClass $student;

    /**
     * Build a three-question quiz and a student.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_game_quiz(3);
        \mod_quiz\quiz_settings::create($this->fixturequizid)->get_grade_calculator()->recompute_quiz_sumgrades();
        $this->student = $this->create_enrolled_student();
        $_POST['sesskey'] = sesskey();
    }

    /**
     * Clean up the request superglobals the tests set.
     */
    protected function tearDown(): void {
        unset($_POST['sesskey'], $_GET['page'], $_GET['smgslot']);
        parent::tearDown();
    }

    /**
     * Start an attempt for the current user.
     *
     * @return \mod_quiz\quiz_attempt The attempt.
     */
    private function start_attempt(): \mod_quiz\quiz_attempt {
        global $USER;

        $quizobj = \mod_quiz\quiz_settings::create($this->fixturequizid, (int)$USER->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour('immediatefeedback');
        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, (int)$USER->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return \mod_quiz\quiz_attempt::create($attempt->id);
    }

    /**
     * Put all three slots on one page and give the page a group mode.
     *
     * @param string $mode The group mode.
     * @param int $pick How many alternatives are played.
     */
    private function group_page(string $mode, int $pick = 1): void {
        global $DB;

        $DB->set_field('quiz_slots', 'page', 1, ['quizid' => $this->fixturequizid]);
        $config = flow_service::get_slot_config($this->fixturecmid, 1);
        $config['group'] = ['mode' => $mode, 'pick' => $pick, 'strategy' => slot_config_schema::PICK_FIRST];
        $this->assertSame([], flow_service::save_slot_config($this->fixturecmid, 1, $config));
    }

    /**
     * Build the answers a browser would send for a slot.
     *
     * @param \mod_quiz\quiz_attempt $attemptobj The attempt.
     * @param int $slot The slot.
     * @param string $answer The answer.
     * @return array Name/value pairs.
     */
    private function answers(\mod_quiz\quiz_attempt $attemptobj, int $slot, string $answer): array {
        $qa = $attemptobj->get_question_attempt($slot);
        return [
            ['name' => $qa->get_qt_field_name('answer'), 'value' => $answer],
            ['name' => $qa->get_control_field_name('sequencecheck'), 'value' => (string)$qa->get_sequence_check_count()],
        ];
    }

    // Fragment endpoint (re-audit N08).

    /**
     * The player's own running attempt renders.
     */
    public function test_fragment_of_an_own_running_attempt_renders(): void {
        $attemptobj = $this->start_attempt();

        $result = get_question_fragment::execute((int)$attemptobj->get_attemptid(), 1);

        $this->assertSame('ok', $result['status']);
        $this->assertNotSame('', $result['questionhtml']);
    }

    /**
     * The player's own finished attempt is refused: review is Moodle's review page.
     */
    public function test_fragment_of_an_own_finished_attempt_is_refused(): void {
        $attemptobj = $this->start_attempt();
        $attemptobj->process_finish(time(), false);
        $attemptobj = \mod_quiz\quiz_attempt::create((int)$attemptobj->get_attemptid());
        $this->assertTrue($attemptobj->is_finished());

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('attemptalreadyclosed', 'quiz'));
        get_question_fragment::execute((int)$attemptobj->get_attemptid(), 1);
    }

    /**
     * Another player's attempt returns nothing.
     */
    public function test_fragment_of_a_foreign_attempt_is_refused(): void {
        $attemptobj = $this->start_attempt();
        $this->create_enrolled_student();
        $_POST['sesskey'] = sesskey();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('notyourattempt', 'quiz'));
        get_question_fragment::execute((int)$attemptobj->get_attemptid(), 1);
    }

    /**
     * A slot the attempt does not have returns nothing.
     */
    public function test_fragment_of_an_unknown_slot_is_refused(): void {
        $attemptobj = $this->start_attempt();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_unknownslot', 'local_stackmathgame', 99));
        get_question_fragment::execute((int)$attemptobj->get_attemptid(), 99);
    }

    /**
     * Without the play capability nothing is returned, even for an own attempt.
     */
    public function test_fragment_without_the_play_capability_is_refused(): void {
        global $DB;

        $attemptobj = $this->start_attempt();
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability(
            'local/stackmathgame:play',
            CAP_PROHIBIT,
            $roleid,
            \context_module::instance($this->fixturecmid)->id,
            true
        );

        $this->expectException(\required_capability_exception::class);
        get_question_fragment::execute((int)$attemptobj->get_attemptid(), 1);
    }

    /**
     * An alternative the player was not given cannot be read ahead by its number.
     */
    public function test_fragment_of_an_unoffered_alternative_is_refused(): void {
        $this->group_page(slot_config_schema::GROUP_MODE_ALTERNATIVES, 1);
        $attemptobj = $this->start_attempt();
        // PICK_FIRST: slot 1 is offered, 2 and 3 are not.
        $this->assertSame('ok', get_question_fragment::execute((int)$attemptobj->get_attemptid(), 1)['status']);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_slotnotoffered', 'local_stackmathgame', 2));
        get_question_fragment::execute((int)$attemptobj->get_attemptid(), 2);
    }

    /**
     * The fragment needs the session key like every browser request that reads attempt data.
     */
    public function test_fragment_needs_the_session_key(): void {
        $attemptobj = $this->start_attempt();
        unset($_POST['sesskey']);

        $this->expectException(\moodle_exception::class);
        get_question_fragment::execute((int)$attemptobj->get_attemptid(), 1);
    }

    // Quest stages (re-audit N06).

    /**
     * A quest stage two steps ahead cannot be answered by posting its slot.
     */
    public function test_a_future_quest_stage_cannot_be_submitted(): void {
        global $DB;

        $this->group_page(slot_config_schema::GROUP_MODE_QUEST);
        $attemptobj = $this->start_attempt();
        $before = $DB->count_records('question_attempt_steps');

        try {
            submit_answer::execute((int)$attemptobj->get_attemptid(), 3, $this->answers($attemptobj, 3, 'frog'));
            $this->fail('A future quest stage accepted an answer.');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_slotnotoffered', $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('question_attempt_steps'), 'A step was written for a future stage.');
        $this->expectException(\moodle_exception::class);
        get_question_fragment::execute((int)$attemptobj->get_attemptid(), 3);
    }

    /**
     * Once stage one is solved, stage two opens - and stage three still does not.
     */
    public function test_quest_stages_open_one_after_another(): void {
        $this->group_page(slot_config_schema::GROUP_MODE_QUEST);
        $attemptobj = $this->start_attempt();

        $first = submit_answer::execute((int)$attemptobj->get_attemptid(), 1, $this->answers($attemptobj, 1, 'frog'));
        $this->assertTrue($first['cannext']);
        $this->assertSame('substep', $first['navigation']['action']);
        $this->assertSame(2, $first['navigation']['nextslot']);
        $this->assertStringContainsString('smgslot=2', $first['navigation']['url']);

        $attemptobj = \mod_quiz\quiz_attempt::create((int)$attemptobj->get_attemptid());
        $second = submit_answer::execute((int)$attemptobj->get_attemptid(), 2, $this->answers($attemptobj, 2, 'frog'));
        $this->assertTrue($second['processed']);
        $this->assertSame(3, $second['navigation']['nextslot']);

        // After the last stage the quest is left by the ordinary branch: nothing follows the page,
        // so the run finishes rather than offering another stage.
        $attemptobj = \mod_quiz\quiz_attempt::create((int)$attemptobj->get_attemptid());
        $third = submit_answer::execute((int)$attemptobj->get_attemptid(), 3, $this->answers($attemptobj, 3, 'frog'));
        $this->assertSame('finish', $third['navigation']['action']);
    }

    // Session keys in the event log (re-audit N01).

    /**
     * The logged payload keeps the answer and nothing that looks like a credential.
     */
    public function test_the_event_log_keeps_the_answer_and_never_the_session_key(): void {
        global $DB;

        $attemptobj = $this->start_attempt();
        $answers = $this->answers($attemptobj, 1, 'frog');
        // Everything a browser - or a tampering client - might add.
        $answers[] = ['name' => 'sesskey', 'value' => self::SENTINEL_SESSKEY];
        $answers[] = ['name' => 'sesskey', 'value' => sesskey()];
        $answers[] = ['name' => 'thispage', 'value' => '0'];
        $answers[] = ['name' => 'q999:1_answer', 'value' => self::SENTINEL_OTHER];
        $answers[] = ['name' => 'someotherfield', 'value' => self::SENTINEL_OTHER];

        $result = submit_answer::execute((int)$attemptobj->get_attemptid(), 1, $answers);
        $this->assertTrue($result['processed']);

        $rows = $DB->get_records('local_stackmathgame_eventlog', ['eventtype' => 'answer_submitted']);
        $this->assertCount(1, $rows);
        $json = (string)reset($rows)->payloadjson;
        $this->assertStringNotContainsString(sesskey(), $json, 'The real session key was logged.');
        $this->assertStringNotContainsString(self::SENTINEL_SESSKEY, $json, 'A sent session key was logged.');
        $this->assertStringNotContainsString(self::SENTINEL_OTHER, $json, 'A field outside the positive list was logged.');
        $payload = json_decode($json, true);
        // Keyed by field, so the log is readable again - the old list of bare values was not.
        $this->assertSame('frog', $payload['answers']['answer']);
        $this->assertArrayHasKey(':sequencecheck', $payload['answers']);
        $this->assertSame([':sequencecheck', 'answer'], array_values(array_intersect(
            [':sequencecheck', 'answer'],
            array_keys($payload['answers'])
        )));
        $this->assertEmpty(array_diff(array_keys($payload['answers']), [':sequencecheck', 'answer']));
        // And the response does not echo a session key back either.
        $this->assertNotContains('sesskey', array_column($result['answers'], 'name'));
    }

    /**
     * Grading and the reward still follow the answer once the credential is gone from it.
     */
    public function test_grading_and_reward_still_work(): void {
        $config = flow_service::get_slot_config($this->fixturecmid, 2);
        $config['rewards']['score'] = 25;
        flow_service::save_slot_config($this->fixturecmid, 2, $config);
        $attemptobj = $this->start_attempt();

        $wrong = submit_answer::execute((int)$attemptobj->get_attemptid(), 1, $this->answers($attemptobj, 1, 'cat'));
        $this->assertTrue($wrong['processed']);
        $this->assertFalse($wrong['cannext']);
        $this->assertSame(0, $wrong['scoredelta']);
        $this->assertSame('gradedwrong', $wrong['state']);

        $attemptobj = \mod_quiz\quiz_attempt::create((int)$attemptobj->get_attemptid());
        $right = submit_answer::execute((int)$attemptobj->get_attemptid(), 2, $this->answers($attemptobj, 2, 'frog'));
        $this->assertTrue($right['cannext']);
        $this->assertTrue($right['firstsolve']);
        $this->assertSame(25, $right['scoredelta']);
        $this->assertSame('gradedright', $right['state']);
        $attemptobj = \mod_quiz\quiz_attempt::create((int)$attemptobj->get_attemptid());
        $this->assertSame('frog', $attemptobj->get_question_attempt(2)->get_last_qt_var('answer'));
    }

    /**
     * The request itself stays protected against cross-site forgery.
     */
    public function test_a_submit_without_the_session_key_is_refused(): void {
        global $DB;

        $attemptobj = $this->start_attempt();
        $steps = $DB->count_records('question_attempt_steps');
        unset($_POST['sesskey']);

        try {
            submit_answer::execute((int)$attemptobj->get_attemptid(), 1, $this->answers($attemptobj, 1, 'frog'));
            $this->fail('A submission without a session key was accepted.');
        } catch (\moodle_exception $e) {
            // The function require_sesskey() reports a missing key as a missing parameter and a wrong one as
            // invalid; either is the refusal.
            $this->assertContains($e->errorcode, ['invalidsesskey', 'missingparam']);
        }
        $this->assertSame($steps, $DB->count_records('question_attempt_steps'));
        $this->assertSame(0, $DB->count_records('local_stackmathgame_eventlog', ['eventtype' => 'answer_submitted']));
    }

    /**
     * A wrong session key - one sent among the answers does not count - is refused as well.
     */
    public function test_a_session_key_among_the_answers_does_not_authorise(): void {
        $attemptobj = $this->start_attempt();
        $_POST['sesskey'] = 'wrongwrong';
        $answers = $this->answers($attemptobj, 1, 'frog');
        $answers[] = ['name' => 'sesskey', 'value' => sesskey()];

        $this->expectException(\moodle_exception::class);
        submit_answer::execute((int)$attemptobj->get_attemptid(), 1, $answers);
    }
}
