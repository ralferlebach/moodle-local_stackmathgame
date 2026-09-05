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
}
