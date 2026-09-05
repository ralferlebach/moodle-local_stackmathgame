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
use local_stackmathgame\form\slot_config_form;
use local_stackmathgame\local\service\slot_config_schema;

/**
 * The direction card's conversion between stored config and form values.
 *
 * The form itself needs a page to render, but the two conversions are plain functions and carry
 * the logic that can silently lose a teacher's work - so they are tested directly.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\form\slot_config_form
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class slot_config_form_test extends advanced_testcase {
    /**
     * A stored card fills the flat form fields.
     */
    public function test_config_fills_the_form(): void {
        $config = slot_config_schema::defaults(slot_config_schema::SCENE_TYPE_BOSS);
        $config['narrative']['intro'] = 'The dragon stirs.';
        $config['rewards']['score'] = 42;
        $config['rewards']['xp'] = 17;
        $config['rewards']['achievementkeys'] = ['first_blood', 'dragon_slayer'];
        $config['branching'][slot_config_schema::OUTCOME_GRADEDRIGHT] =
            ['mode' => slot_config_schema::BRANCH_MODE_SLOT, 'target' => 3];

        $values = slot_config_form::config_to_form($config, 42, 1);

        $this->assertSame(slot_config_schema::SCENE_TYPE_BOSS, $values['scenetype']);
        $this->assertSame('The dragon stirs.', $values['narrative_intro']);
        $this->assertSame(42, $values['reward_score']);
        // The achievement keys are a list on disk and a comma-separated line in the form.
        $this->assertSame('first_blood, dragon_slayer', $values['reward_achievements']);
        $this->assertSame(3, $values['branch_gradedright_target']);
        $this->assertSame(42, $values['cmid']);
    }

    /**
     * Submitted values become a schema-shaped card again.
     */
    public function test_form_becomes_a_card(): void {
        $existing = slot_config_schema::defaults();
        $data = (object)[
            'scenetype' => slot_config_schema::SCENE_TYPE_MINIBOSS,
            'enabled' => 1,
            'narrative_intro' => 'Here we go.',
            'narrative_success' => '',
            'narrative_fail' => '',
            'reward_score' => 5,
            'reward_xp' => 3,
            'reward_achievements' => ' one , two ,, ',
            'branch_gradedright_mode' => slot_config_schema::BRANCH_MODE_SLOT,
            'branch_gradedright_target' => 2,
            'branch_gradedwrong_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_complete_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_default_mode' => slot_config_schema::BRANCH_MODE_END,
            'display_showxp' => 1,
            'display_showinventory' => 0,
            'display_showavatar' => 0,
        ];

        $config = slot_config_form::form_to_config($data, $existing);

        $this->assertSame(slot_config_schema::SCENE_TYPE_MINIBOSS, $config['scene']['type']);
        $this->assertSame('Here we go.', $config['narrative']['intro']);
        // Empty entries and stray whitespace are dropped rather than stored as blank keys.
        $this->assertSame(['one', 'two'], $config['rewards']['achievementkeys']);
        $this->assertSame(2, $config['branching']['gradedright']['target']);
        $this->assertTrue($config['display']['showxp']);
        $this->assertFalse($config['display']['showavatar']);
    }

    /**
     * A target is stored only for a jump.
     *
     * Keeping a stale target beside "linear" would leave a value that looks meaningful and is
     * not - and the next reader cannot tell which of the two applies.
     */
    public function test_target_is_dropped_unless_jumping(): void {
        $data = (object)[
            'scenetype' => slot_config_schema::SCENE_TYPE_CHALLENGE,
            'enabled' => 1,
            'branch_gradedright_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_gradedright_target' => 7,
            'branch_gradedwrong_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_complete_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_default_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'reward_score' => 0,
            'reward_xp' => 0,
            'reward_achievements' => '',
        ];

        $config = slot_config_form::form_to_config($data, slot_config_schema::defaults());

        $this->assertArrayNotHasKey('target', $config['branching']['gradedright']);
    }

    /**
     * Sections the form does not model survive an edit.
     *
     * Stash mappings and badge IDs are managed elsewhere; resetting them because this form has
     * no control for them would destroy configuration the teacher never touched.
     */
    public function test_unmodelled_sections_survive(): void {
        $existing = slot_config_schema::defaults();
        $existing['rewards']['badgeids'] = [11, 12];
        $existing['rewards']['stash'] = ['itemid' => 4, 'quantity' => 3];

        $data = (object)[
            'scenetype' => slot_config_schema::SCENE_TYPE_CHALLENGE,
            'enabled' => 1,
            'reward_score' => 1,
            'reward_xp' => 1,
            'reward_achievements' => '',
            'branch_gradedright_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_gradedwrong_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_complete_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_default_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
        ];

        $config = slot_config_form::form_to_config($data, $existing);

        $this->assertSame([11, 12], $config['rewards']['badgeids']);
        $this->assertSame(4, $config['rewards']['stash']['itemid']);
    }

    /**
     * A negative reward cannot be stored.
     */
    public function test_negative_rewards_are_clamped(): void {
        $data = (object)[
            'scenetype' => slot_config_schema::SCENE_TYPE_CHALLENGE,
            'enabled' => 1,
            'reward_score' => -50,
            'reward_xp' => -50,
            'reward_achievements' => '',
            'branch_gradedright_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_gradedwrong_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_complete_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
            'branch_default_mode' => slot_config_schema::BRANCH_MODE_LINEAR,
        ];

        $config = slot_config_form::form_to_config($data, slot_config_schema::defaults());

        $this->assertSame(0, $config['rewards']['score']);
        $this->assertSame(0, $config['rewards']['xp']);
    }

    /**
     * A card survives a full round trip unchanged.
     */
    public function test_round_trip_is_stable(): void {
        $original = slot_config_schema::defaults(slot_config_schema::SCENE_TYPE_REWARD);
        $original['narrative']['success'] = 'Take this.';
        $original['rewards']['xp'] = 9;

        $values = slot_config_form::config_to_form($original, 1, 1);
        $result = slot_config_form::form_to_config((object)$values, $original);

        $this->assertSame($original['scene']['type'], $result['scene']['type']);
        $this->assertSame($original['narrative']['success'], $result['narrative']['success']);
        $this->assertSame($original['rewards']['xp'], $result['rewards']['xp']);
    }
}
