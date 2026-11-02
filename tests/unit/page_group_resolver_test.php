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

use local_stackmathgame\local\service\flow_service;
use local_stackmathgame\local\service\navigation_resolver;
use local_stackmathgame\local\service\page_group_resolver;
use local_stackmathgame\local\service\profile_service;
use local_stackmathgame\local\service\question_map_service;
use local_stackmathgame\local\service\slot_config_schema;
use local_stackmathgame\tests\game_quiz_testcase;

/**
 * The three ways several questions on one quiz page can relate to each other (issue #9).
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\page_group_resolver
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page_group_resolver_test extends game_quiz_testcase {
    /**
     * Build a quiz whose three questions share one page.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_game_quiz(3);
        $this->put_all_slots_on_one_page();
    }

    /**
     * Move every slot onto page 1, so the three questions form a group.
     */
    private function put_all_slots_on_one_page(): void {
        global $DB;

        $DB->set_field('quiz_slots', 'page', 1, ['quizid' => $this->fixturequizid]);
    }

    /**
     * Configure the group mode on the page's first slot.
     *
     * @param string $mode The group mode.
     * @param int $pick How many alternatives to play.
     * @param string $strategy The selection strategy.
     */
    private function set_group(string $mode, int $pick = 1, string $strategy = 'random'): void {
        $config = flow_service::get_slot_config($this->fixturecmid, 1);
        $config['group'] = ['mode' => $mode, 'pick' => $pick, 'strategy' => $strategy];
        $this->assertSame([], flow_service::save_slot_config($this->fixturecmid, 1, $config));
    }

    /**
     * Without configuration a shared page behaves as it always did.
     *
     * The default has to be the old behaviour, or installing this release would change how every
     * existing quiz plays.
     */
    public function test_default_is_separate_scenes(): void {
        $result = page_group_resolver::resolve($this->fixturecmid, 0, 1);

        $this->assertSame(slot_config_schema::GROUP_MODE_SCENES, $result['mode']);
        $this->assertSame([1, 2, 3], $result['playable']);
        $this->assertSame(1, $result['active']);
    }

    /**
     * Alternatives offer only the picked subset.
     */
    public function test_alternatives_offer_a_subset(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_ALTERNATIVES, 1);

        $result = page_group_resolver::resolve($this->fixturecmid, 0, 1);

        $this->assertSame(slot_config_schema::GROUP_MODE_ALTERNATIVES, $result['mode']);
        $this->assertCount(1, $result['playable']);
        $this->assertContains($result['playable'][0], [1, 2, 3]);
        $this->assertSame(1, $result['total']);
    }

    /**
     * The same attempt always gets the same alternative.
     *
     * A reload that produced a different question would discard the work already done on the
     * first, and would let a player reshuffle until they liked what they got.
     */
    public function test_the_pick_is_stable_within_an_attempt(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_ALTERNATIVES, 1);

        $first = page_group_resolver::resolve($this->fixturecmid, 0, 4242);
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(
                $first['playable'],
                page_group_resolver::resolve($this->fixturecmid, 0, 4242)['playable'],
                'The alternative changed between two identical resolutions.'
            );
        }
    }

    /**
     * Different attempts can get different alternatives.
     *
     * Not a strict requirement of any single attempt, but if every attempt got the same question
     * the feature would be pointless - so at least one of a spread of attempts must differ.
     */
    public function test_different_attempts_can_differ(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_ALTERNATIVES, 1);

        $seen = [];
        for ($attempt = 1; $attempt <= 40; $attempt++) {
            $seen[page_group_resolver::resolve($this->fixturecmid, 0, $attempt)['playable'][0]] = true;
        }

        $this->assertGreaterThan(1, count($seen), 'Every attempt received the same alternative.');
    }

    /**
     * A question that was not offered cannot be answered.
     *
     * The security half: the question engine would accept an answer to any slot of the attempt,
     * so without this check a player could answer a hidden alternative by posting its number.
     */
    public function test_an_unoffered_alternative_is_not_playable(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_ALTERNATIVES, 1);

        $offered = page_group_resolver::resolve($this->fixturecmid, 0, 7)['playable'][0];
        $hidden = array_values(array_diff([1, 2, 3], [$offered]));

        $this->assertTrue(page_group_resolver::is_slot_playable($this->fixturecmid, 0, $offered, 7));
        foreach ($hidden as $slot) {
            $this->assertFalse(
                page_group_resolver::is_slot_playable($this->fixturecmid, 0, $slot, 7),
                "Slot $slot was answerable although it was never offered."
            );
        }
    }

    /**
     * A quest presents its stages in order.
     */
    public function test_a_quest_advances_through_its_stages(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_QUEST);

        $fresh = page_group_resolver::resolve($this->fixturecmid, 0, 1);
        $this->assertSame(slot_config_schema::GROUP_MODE_QUEST, $fresh['mode']);
        $this->assertSame(1, $fresh['active']);
        $this->assertSame(3, $fresh['total']);
        $this->assertSame(0, $fresh['done']);

        $middle = page_group_resolver::resolve($this->fixturecmid, 0, 1, [1 => true]);
        $this->assertSame(2, $middle['active']);
        $this->assertSame(1, $middle['done']);
    }

    /**
     * A finished quest reports no active stage.
     *
     * Zero rather than the last stage: "nothing left here" is what the navigation needs to move
     * on, and repeating the final stage would be indistinguishable from being stuck on it.
     */
    public function test_a_finished_quest_has_no_active_stage(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_QUEST);

        $result = page_group_resolver::resolve(
            $this->fixturecmid,
            0,
            1,
            [1 => true, 2 => true, 3 => true]
        );

        $this->assertSame(0, $result['active']);
        $this->assertSame(3, $result['done']);
    }

    /**
     * A solved stage stays reachable.
     *
     * Looking back at a stage already completed is not the same as being made to redo it.
     */
    public function test_solved_quest_stages_stay_reachable(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_QUEST);

        $result = page_group_resolver::resolve($this->fixturecmid, 0, 1, [1 => true]);

        $this->assertContains(1, $result['playable']);
    }

    /**
     * Stages after the active one are not playable (re-audit N06).
     *
     * They used to be, so posting the number of stage three answered - and rewarded - it while
     * stage one was still open.
     */
    public function test_future_quest_stages_are_not_playable(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_QUEST);

        $fresh = page_group_resolver::resolve($this->fixturecmid, 0, 1);
        $this->assertSame([1], $fresh['playable']);
        $this->assertFalse(page_group_resolver::is_slot_playable($this->fixturecmid, 0, 2, 1));
        $this->assertFalse(page_group_resolver::is_slot_playable($this->fixturecmid, 0, 3, 1));

        $middle = page_group_resolver::resolve($this->fixturecmid, 0, 1, [1 => true]);
        $this->assertSame([1, 2], $middle['playable']);
        $this->assertFalse(page_group_resolver::is_slot_playable($this->fixturecmid, 0, 3, 1, [1 => true]));

        $done = page_group_resolver::resolve($this->fixturecmid, 0, 1, [1 => true, 2 => true, 3 => true]);
        $this->assertSame([1, 2, 3], $done['playable']);
    }

    /**
     * The presented slot is the server's choice, at first render, on reload and after a stage.
     */
    public function test_the_presented_slot_follows_the_resolution(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_QUEST);
        $cmid = $this->fixturecmid;

        // First render: stage one. A reload with the same progress gives the same answer.
        $this->assertSame(1, page_group_resolver::presented_slot($cmid, 0, 1));
        $this->assertSame(1, page_group_resolver::presented_slot($cmid, 0, 1));
        // After stage one: stage two, without any slot in the URL.
        $this->assertSame(2, page_group_resolver::presented_slot($cmid, 0, 1, [1 => true]));
        // A solved stage may be asked for explicitly; a future one may not - manipulating the URL
        // falls back to the active stage.
        $this->assertSame(1, page_group_resolver::presented_slot($cmid, 0, 1, [1 => true], 1));
        $this->assertSame(2, page_group_resolver::presented_slot($cmid, 0, 1, [1 => true], 3));
        $this->assertSame(2, page_group_resolver::presented_slot($cmid, 0, 1, [1 => true], 99));
        // A finished quest shows its last stage, not its first.
        $this->assertSame(3, page_group_resolver::presented_slot($cmid, 0, 1, [1 => true, 2 => true, 3 => true]));
    }

    /**
     * Only an offered alternative is presented, whatever the URL asks for.
     */
    public function test_the_presented_alternative_is_an_offered_one(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_ALTERNATIVES, 1, slot_config_schema::PICK_FIRST);

        $this->assertSame(1, page_group_resolver::presented_slot($this->fixturecmid, 0, 5));
        $this->assertSame(1, page_group_resolver::presented_slot($this->fixturecmid, 0, 5, [], 2));
        $this->assertSame(1, page_group_resolver::presented_slot($this->fixturecmid, 0, 5, [1 => true]));
    }

    /**
     * Separate scenes on one page honour a requested slot, and default to the first unsolved.
     */
    public function test_separate_scenes_present_the_requested_or_first_unsolved_slot(): void {
        $this->assertSame(1, page_group_resolver::presented_slot($this->fixturecmid, 0, 1));
        $this->assertSame(2, page_group_resolver::presented_slot($this->fixturecmid, 0, 1, [1 => true]));
        $this->assertSame(3, page_group_resolver::presented_slot($this->fixturecmid, 0, 1, [], 3));
    }

    /**
     * Picking more alternatives than the page holds is clamped, not an error.
     */
    public function test_picking_too_many_is_clamped(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_ALTERNATIVES, 99);

        $result = page_group_resolver::resolve($this->fixturecmid, 0, 1);

        $this->assertCount(3, $result['playable']);
    }

    /**
     * A page with a single question needs no group at all.
     */
    public function test_a_single_question_page_is_unaffected(): void {
        global $DB;

        $DB->set_field('quiz_slots', 'page', 2, ['quizid' => $this->fixturequizid, 'slot' => 2]);
        $DB->set_field('quiz_slots', 'page', 3, ['quizid' => $this->fixturequizid, 'slot' => 3]);

        $result = page_group_resolver::resolve($this->fixturecmid, 0, 1);

        $this->assertSame([1], $result['playable']);
        $this->assertSame(1, $result['active']);
    }
    /**
     * A quest reports a substep, not a move to the next node.
     *
     * The distinction is the whole point of the mode: with one word for both, finishing stage one
     * of three would look exactly like finishing the quest, and the run would move on with two
     * stages unplayed.
     */
    public function test_a_quest_stage_resolves_to_a_substep(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_QUEST);
        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );

        $decision = navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            1,
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            $profile,
            0
        );

        $this->assertSame('substep', $decision['action']);
        $this->assertSame(2, $decision['nextslot']);
    }

    /**
     * The last stage of a quest hands over to the ordinary branching.
     */
    public function test_the_last_quest_stage_leaves_the_group(): void {
        $this->set_group(slot_config_schema::GROUP_MODE_QUEST);
        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );
        profile_service::apply_progress((int)$profile->id, [
            'progress' => ['slots' => ['1' => ['solved' => 1], '2' => ['solved' => 1]]],
        ]);
        $profile = profile_service::get_or_create_for_quiz((int)$profile->userid, $this->fixturequizid);

        $decision = navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            3,
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            $profile,
            0
        );

        $this->assertNotSame('substep', $decision['action']);
    }

    /**
     * Three stages in order, then the quest's external branch: out of the page, not back into it.
     */
    public function test_a_three_stage_quest_leaves_its_page_after_the_last_stage(): void {
        global $DB;

        // A fourth question on its own page after the quest.
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $quiz = $DB->get_record('quiz', ['id' => $this->fixturequizid], '*', MUST_EXIST);
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz, 2);
        question_map_service::ensure_for_cmid($this->fixturecmid);
        $this->set_group(slot_config_schema::GROUP_MODE_QUEST);

        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );
        $steps = [];
        foreach ([1, 2, 3] as $stage) {
            $decision = navigation_resolver::resolve(
                $this->fixturecmid,
                $this->fixturequizid,
                $stage,
                slot_config_schema::OUTCOME_GRADEDRIGHT,
                $profile,
                11
            );
            $steps[] = [$decision['action'], $decision['nextslot']];
            profile_service::apply_progress((int)$profile->id, [
                'progress' => ['slots' => [(string)$stage => ['solved' => 1]]],
            ]);
            $profile = profile_service::get_or_create_for_quiz((int)$profile->userid, $this->fixturequizid);
        }

        $this->assertSame([['substep', 2], ['substep', 3], ['continue', 4]], $steps);
    }

    /**
     * A finished set of alternatives moves past the page, not to an alternative never offered.
     */
    public function test_finished_alternatives_leave_the_page(): void {
        global $DB;

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $quiz = $DB->get_record('quiz', ['id' => $this->fixturequizid], '*', MUST_EXIST);
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz, 2);
        question_map_service::ensure_for_cmid($this->fixturecmid);
        $this->set_group(slot_config_schema::GROUP_MODE_ALTERNATIVES, 1, slot_config_schema::PICK_FIRST);
        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );

        // Slot 1 is the offered alternative. Linear from it would be slot 2 - on the same page and
        // never offered.
        $decision = navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            1,
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            $profile,
            11
        );

        $this->assertSame('continue', $decision['action']);
        $this->assertSame(4, $decision['nextslot']);
    }

    /**
     * Separate scenes never produce a substep.
     *
     * The default must keep behaving exactly as it did, or installing this release would change
     * how every existing quiz plays.
     */
    public function test_separate_scenes_do_not_substep(): void {
        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );

        $decision = navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            1,
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            $profile,
            0
        );

        $this->assertNotSame('substep', $decision['action']);
    }
    /**
     * Entering a named level is announced once, on its first slot.
     *
     * Once, not on every question of the level: an intro that replays at each question stops
     * being an intro.
     */
    public function test_entering_a_level_is_announced(): void {
        global $DB;

        // Put the slots back on separate pages so the branching moves between them.
        foreach ([1, 2, 3] as $slot) {
            $DB->set_field('quiz_slots', 'page', $slot, ['quizid' => $this->fixturequizid, 'slot' => $slot]);
        }
        $DB->insert_record('quiz_sections', (object)[
            'quizid' => $this->fixturequizid,
            'firstslot' => 2,
            'heading' => 'The forest',
            'shufflequestions' => 0,
        ]);

        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );

        $entering = navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            1,
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            $profile,
            0
        );
        $this->assertTrue($entering['enterslevel'], 'Moving into slot 2 did not announce the level.');
        $this->assertSame('The forest', $entering['levelheading']);

        $within = navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            2,
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            $profile,
            0
        );
        $this->assertFalse($within['enterslevel'], 'The level was announced again inside it.');
    }

    /**
     * An unnamed section is not announced.
     *
     * Moodle always creates a section at slot 1, usually with no heading. Announcing it would put
     * an empty chapter title in front of every quiz.
     */
    public function test_an_unnamed_level_is_not_announced(): void {
        global $DB;

        foreach ([1, 2, 3] as $slot) {
            $DB->set_field('quiz_slots', 'page', $slot, ['quizid' => $this->fixturequizid, 'slot' => $slot]);
        }
        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );

        $decision = navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            1,
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            $profile,
            0
        );

        $this->assertFalse($decision['enterslevel']);
        $this->assertSame('', $decision['levelheading']);
    }
}
