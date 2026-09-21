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
use local_stackmathgame\local\service\profile_service;
use local_stackmathgame\local\service\slot_config_schema;
use local_stackmathgame\tests\game_quiz_testcase;

/**
 * An instruction scene never holds the player back.
 *
 * The schema described the type as "no answer required" from the start, and nothing honoured it:
 * a welcome or briefing page was navigated like a challenge, so a page that hides its own input -
 * which such pages do - had no way forward and stopped the run on its first scene. Found by the
 * end-to-end journey once it played all five fixture questions instead of three.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\navigation_resolver
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class instruction_scene_test extends game_quiz_testcase {
    /**
     * Build a three-slot quiz whose first scene is an instruction.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_game_quiz(3);

        $config = flow_service::get_slot_config($this->fixturecmid, 1);
        $config['scene']['type'] = slot_config_schema::SCENE_TYPE_INSTRUCTION;
        $this->assertSame([], flow_service::save_slot_config($this->fixturecmid, 1, $config));
    }

    /**
     * Resolve the navigation from slot 1 for a given outcome.
     *
     * @param string $outcome The outcome to resolve for.
     * @return array The navigation decision.
     */
    private function resolve_from_first(string $outcome): array {
        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );
        return navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            1,
            $outcome,
            $profile,
            0
        );
    }

    /**
     * An unanswered instruction scene offers the next scene.
     */
    public function test_unanswered_instruction_moves_on(): void {
        $decision = $this->resolve_from_first(slot_config_schema::OUTCOME_DEFAULT);

        $this->assertSame('continue', $decision['action']);
        $this->assertSame(2, $decision['nextslot']);
    }

    /**
     * Even a wrong answer does not keep the player on an instruction scene.
     *
     * There is nothing to get wrong on a briefing page; holding the player there would be the
     * same dead end the unanswered case was.
     */
    public function test_wrong_answer_on_instruction_still_moves_on(): void {
        $decision = $this->resolve_from_first(slot_config_schema::OUTCOME_GRADEDWRONG);

        $this->assertSame('continue', $decision['action']);
    }

    /**
     * A challenge still keeps the player on a wrong answer.
     *
     * The counterpart: the exception must not leak into ordinary scenes, where the retry is the
     * point.
     */
    public function test_a_challenge_still_holds_on_a_wrong_answer(): void {
        $profile = profile_service::get_or_create_for_quiz(
            (int)$this->getDataGenerator()->create_user()->id,
            $this->fixturequizid
        );
        $decision = navigation_resolver::resolve(
            $this->fixturecmid,
            $this->fixturequizid,
            2,
            slot_config_schema::OUTCOME_GRADEDWRONG,
            $profile,
            0
        );

        $this->assertSame('stay', $decision['action']);
    }
}
