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
use local_stackmathgame\local\service\flow_service;
use local_stackmathgame\local\service\question_map_service;
use local_stackmathgame\local\service\slot_config_schema;

/**
 * Unit tests for the game flow authoring service (issue #1).
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\flow_service
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_service_test extends advanced_testcase {
    /** @var \stdClass The course record. */
    private \stdClass $course;
    /** @var \stdClass The quiz record. */
    private \stdClass $quiz;
    /** @var int The course-module ID. */
    private int $cmid;

    /**
     * Build a three-slot quiz with the game enabled.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->quiz = $generator->create_module('quiz', [
            'course' => $this->course->id,
            'preferredbehaviour' => 'stackmathgame',
            'questionsperpage' => 1,
        ]);
        $this->cmid = (int)get_coursemodule_from_instance(
            'quiz',
            $this->quiz->id,
            $this->course->id,
            false,
            MUST_EXIST
        )->id;

        $this->add_questions(3);
        quiz_configurator::ensure_default($this->cmid);
        question_map_service::ensure_for_cmid($this->cmid);
    }

    /**
     * Add short-answer questions to the quiz.
     *
     * @param int $count How many to add.
     * @return void
     */
    private function add_questions(int $count): void {
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        for ($i = 0; $i < $count; $i++) {
            $question = $questiongenerator->create_question('shortanswer', null, [
                'category' => $category->id,
                'name' => 'Question ' . ($i + 1),
            ]);
            quiz_add_quiz_question($question->id, $this->quiz);
        }
    }

    /**
     * A freshly mapped quiz produces one usable direction card per slot.
     */
    public function test_slots_start_with_a_default_card(): void {
        $slots = flow_service::get_slots($this->cmid);

        $this->assertCount(3, $slots);
        foreach ($slots as $slotnumber => $slot) {
            $this->assertSame($slotnumber, $slot['slotnumber']);
            $this->assertNotEmpty($slot['questionname']);
            $this->assertSame('shortanswer', $slot['qtype']);
            $this->assertFalse($slot['isstack']);
            $this->assertSame(
                slot_config_schema::SCENE_TYPE_CHALLENGE,
                $slot['config']['scene']['type']
            );
            $this->assertSame(
                slot_config_schema::BRANCH_MODE_LINEAR,
                $slot['config']['branching']['gradedright']['mode']
            );
        }
    }

    /**
     * A valid card is stored and reads back unchanged.
     */
    public function test_saving_a_card_round_trips(): void {
        $config = slot_config_schema::defaults(slot_config_schema::SCENE_TYPE_BOSS);
        $config['narrative']['intro'] = 'The dragon stirs.';
        $config['rewards']['xp'] = 50;
        $config['branching']['gradedright'] = ['mode' => slot_config_schema::BRANCH_MODE_SLOT, 'target' => 3];

        $this->assertSame([], flow_service::save_slot_config($this->cmid, 1, $config));

        $stored = flow_service::get_slot_config($this->cmid, 1);
        $this->assertSame(slot_config_schema::SCENE_TYPE_BOSS, $stored['scene']['type']);
        $this->assertSame('The dragon stirs.', $stored['narrative']['intro']);
        $this->assertSame(50, $stored['rewards']['xp']);
        $this->assertSame(3, $stored['branching']['gradedright']['target']);
    }

    /**
     * A jump to a slot that does not exist is refused, and nothing is written.
     *
     * Refusing without writing matters more than refusing: a half-applied card would leave the
     * quiz in a state the teacher never chose.
     */
    public function test_invalid_branch_target_is_refused(): void {
        $config = slot_config_schema::defaults();
        $config['branching']['gradedright'] = ['mode' => slot_config_schema::BRANCH_MODE_SLOT, 'target' => 99];

        $errors = flow_service::save_slot_config($this->cmid, 1, $config);
        $this->assertNotEmpty($errors);

        $stored = flow_service::get_slot_config($this->cmid, 1);
        $this->assertSame(
            slot_config_schema::BRANCH_MODE_LINEAR,
            $stored['branching']['gradedright']['mode']
        );
    }

    /**
     * An invalid scene type is refused.
     */
    public function test_invalid_scene_type_is_refused(): void {
        $config = slot_config_schema::defaults();
        $config['scene']['type'] = 'dragonfight';

        $this->assertNotEmpty(flow_service::save_slot_config($this->cmid, 1, $config));
    }

    /**
     * Writing to a slot the quiz does not have is refused.
     */
    public function test_unknown_slot_is_refused(): void {
        $this->assertNotEmpty(
            flow_service::save_slot_config($this->cmid, 99, slot_config_schema::defaults())
        );
    }

    /**
     * Rebuilding the question map keeps existing cards.
     *
     * This is the criterion that protects a teacher's work: a rebuild runs whenever the flow
     * page is opened, so if it reset configuration, authoring would be impossible.
     */
    public function test_rebuild_preserves_existing_cards(): void {
        $config = slot_config_schema::defaults(slot_config_schema::SCENE_TYPE_MINIBOSS);
        $config['narrative']['success'] = 'Well fought.';
        $config['rewards']['score'] = 7;
        flow_service::save_slot_config($this->cmid, 2, $config);

        question_map_service::ensure_for_cmid($this->cmid);
        question_map_service::ensure_for_cmid($this->cmid);

        $stored = flow_service::get_slot_config($this->cmid, 2);
        $this->assertSame(slot_config_schema::SCENE_TYPE_MINIBOSS, $stored['scene']['type']);
        $this->assertSame('Well fought.', $stored['narrative']['success']);
        $this->assertSame(7, $stored['rewards']['score']);
    }

    /**
     * Bulk apply changes only the sections it was given.
     *
     * Setting a scene type across twenty slots must not wipe twenty narratives, which is what
     * a whole-config overwrite would do.
     */
    public function test_bulk_apply_is_partial(): void {
        $config = slot_config_schema::defaults();
        $config['narrative']['intro'] = 'Keep me.';
        flow_service::save_slot_config($this->cmid, 1, $config);

        $results = flow_service::apply_to_slots($this->cmid, [1, 2], [
            'scene' => ['type' => slot_config_schema::SCENE_TYPE_BOSS],
        ]);
        $this->assertSame([], $results[1]);
        $this->assertSame([], $results[2]);

        $stored = flow_service::get_slot_config($this->cmid, 1);
        $this->assertSame(slot_config_schema::SCENE_TYPE_BOSS, $stored['scene']['type']);
        $this->assertSame('Keep me.', $stored['narrative']['intro']);
    }

    /**
     * A default linear quiz has no unreachable slots and no dead ends.
     */
    public function test_default_flow_is_clean(): void {
        $analysis = flow_service::analyse_reachability($this->cmid);

        $this->assertSame([], $analysis['unreachable']);
        $this->assertSame([], $analysis['deadends']);
    }

    /**
     * A slot skipped by every branch is reported as unreachable.
     *
     * No single card is wrong here - each is valid on its own. Only the graph shows it, which is
     * exactly why the analysis exists.
     */
    public function test_skipped_slot_is_reported_unreachable(): void {
        foreach (
            [slot_config_schema::OUTCOME_GRADEDRIGHT, slot_config_schema::OUTCOME_COMPLETE,
            slot_config_schema::OUTCOME_DEFAULT] as $outcome
        ) {
            $config = flow_service::get_slot_config($this->cmid, 1);
            $config['branching'][$outcome] = ['mode' => slot_config_schema::BRANCH_MODE_SLOT, 'target' => 3];
            flow_service::save_slot_config($this->cmid, 1, $config);
        }

        $analysis = flow_service::analyse_reachability($this->cmid);
        $this->assertSame([2], $analysis['unreachable']);
    }

    /**
     * The last slot under linear branching is an ending, not a dead end.
     *
     * Reporting it would make the warning fire on every correctly configured quiz, and a warning
     * that always fires is one nobody reads.
     */
    public function test_last_slot_is_not_a_dead_end(): void {
        $this->assertNotContains(3, flow_service::analyse_reachability($this->cmid)['deadends']);
    }

    /**
     * Removing a question from the quiz removes its card.
     */
    public function test_removed_slot_leaves_no_orphan(): void {
        global $DB;

        flow_service::get_slots($this->cmid);
        $this->assertSame(3, $DB->count_records('local_stackmathgame_questionmap', ['cmid' => $this->cmid]));

        $slots = $DB->get_records('quiz_slots', ['quizid' => $this->quiz->id], 'slot DESC', '*', 0, 1);
        $last = reset($slots);
        $DB->delete_records('quiz_slots', ['id' => $last->id]);

        $remaining = flow_service::get_slots($this->cmid);
        $this->assertCount(2, $remaining);
        $this->assertSame(2, $DB->count_records('local_stackmathgame_questionmap', ['cmid' => $this->cmid]));
    }
    /**
     * A stash reward round-trips through the direction card.
     *
     * It used to live in a second per-slot list on the game settings form - which never rendered,
     * because the page did not supply the item list, and whose save method had no caller outside
     * its own tests. It is per-slot game configuration, so it belongs in configjson with the
     * other rewards.
     */
    public function test_stash_reward_round_trips(): void {
        $config = flow_service::get_slot_config($this->cmid, 1);
        $config['rewards']['stash'] = ['itemid' => 7, 'quantity' => 3];

        $this->assertSame([], flow_service::save_slot_config($this->cmid, 1, $config));

        $stored = flow_service::get_slot_config($this->cmid, 1);
        $this->assertSame(7, $stored['rewards']['stash']['itemid']);
        $this->assertSame(3, $stored['rewards']['stash']['quantity']);
    }

    /**
     * A slot with no stash reward reads back as "no item" rather than as a missing key.
     *
     * The bridge treats itemid 0 as "award nothing", so the default has to be present and
     * numeric - an absent key would make every unconfigured slot a special case.
     */
    public function test_stash_defaults_are_present(): void {
        $stash = flow_service::get_slot_config($this->cmid, 2)['rewards']['stash'];

        $this->assertSame(0, $stash['itemid']);
        $this->assertSame(1, $stash['quantity']);
    }

    /**
     * A quantity below one is corrected rather than stored.
     *
     * Awarding zero of an item is not a thing block_stash can do, and a negative quantity would
     * take items away from a player who just solved a scene.
     */
    public function test_stash_quantity_is_clamped(): void {
        $config = flow_service::get_slot_config($this->cmid, 1);
        $config['rewards']['stash'] = ['itemid' => 4, 'quantity' => -5];
        flow_service::save_slot_config($this->cmid, 1, $config);

        $this->assertSame(1, flow_service::get_slot_config($this->cmid, 1)['rewards']['stash']['quantity']);
    }
}
