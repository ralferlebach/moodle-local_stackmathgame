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

use local_stackmathgame\tests\game_quiz_testcase;
use local_stackmathgame\external\get_narrative;
use local_stackmathgame\external\get_profile_state;
use local_stackmathgame\external\get_quiz_config;
use local_stackmathgame\external\prefetch_next_node;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\service\question_map_service;

/**
 * The four read endpoints the game runtime calls on every attempt page.
 *
 * They are exercised together because that is how they are used: game_engine.js fires all four
 * inside one Promise.all, and a payload that is individually valid but collectively inconsistent
 * is exactly the failure the browser would show and a per-endpoint test would miss.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\external\get_quiz_config
 * @covers     \local_stackmathgame\external\get_profile_state
 * @covers     \local_stackmathgame\external\get_narrative
 * @covers     \local_stackmathgame\external\prefetch_next_node
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class read_endpoints_test extends game_quiz_testcase {
    /** @var int The quiz instance ID. */
    private int $quizid;
    /** @var int The course-module ID. */
    private int $cmid;

    /**
     * Build a game-enabled quiz with three slots and a logged-in student.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->create_game_quiz(3, true);
        $this->cmid = $this->fixturecmid;
        $this->quizid = $this->fixturequizid;

        $this->create_enrolled_student();
    }

    /**
     * The configuration carries the design and one map entry per slot.
     */
    public function test_quiz_config_carries_design_and_map(): void {
        $result = get_quiz_config::execute($this->quizid);

        $this->assertSame($this->quizid, (int)$result['quizid']);
        $this->assertTrue((bool)$result['enabled']);
        $this->assertNotNull($result['design']);
        $this->assertCount(3, $result['questionmap']);
    }

    /**
     * Assets arrive as a structured list, not buried in a JSON string.
     *
     * The regression from issue #4: the client read them at the wrong level and silently got an
     * empty map, so every mode rendered nothing where a sprite belonged.
     */
    public function test_quiz_config_exposes_assets_structurally(): void {
        $design = get_quiz_config::execute($this->quizid)['design'];

        $this->assertArrayHasKey('runtimeassets', $design);
        $this->assertNotEmpty($design['runtimeassets']);
        foreach ($design['runtimeassets'] as $asset) {
            $this->assertArrayHasKey('key', $asset);
            $this->assertNotEmpty($asset['url']);
        }
    }

    /**
     * A first call creates the profile rather than failing on its absence.
     */
    public function test_profile_state_creates_a_profile(): void {
        $result = get_profile_state::execute($this->quizid);

        $this->assertArrayHasKey('profile', $result);
        $this->assertSame(0, (int)$result['profile']['xp']);
        $this->assertSame(0, (int)$result['profile']['score']);
    }

    /**
     * The narrative resolves for every canonical scene.
     */
    public function test_narrative_resolves_for_each_scene(): void {
        foreach (['world_enter', 'victory', 'defeat'] as $scene) {
            $result = get_narrative::execute($this->quizid, $scene);
            $this->assertIsArray($result['lines'], "Scene $scene returned no lines array.");
        }
    }

    /**
     * An unknown scene returns empty rather than throwing.
     *
     * Narrative is authored content; a typo in a scene key must not take down the attempt page.
     */
    public function test_unknown_scene_is_empty(): void {
        $result = get_narrative::execute($this->quizid, 'nosuchscene');

        $this->assertSame([], $result['lines']);
    }

    /**
     * The prefetch resolves both the next node and the navigation.
     */
    public function test_prefetch_resolves_navigation(): void {
        $result = prefetch_next_node::execute($this->quizid, 1);

        $this->assertSame(1, (int)$result['currentslot']);
        $this->assertArrayHasKey('navigation', $result);
        $this->assertContains($result['navigation']['action'], ['continue', 'finish', 'stay']);
    }

    /**
     * Every endpoint's payload matches its declared return structure.
     *
     * A mismatch is reported as invalid_parameter_exception with no detail, which is a miserable
     * thing to debug from a browser - so it is caught here instead.
     */
    public function test_payloads_match_their_declared_structures(): void {
        $checks = [
            [get_quiz_config::execute_returns(), get_quiz_config::execute($this->quizid)],
            [get_profile_state::execute_returns(), get_profile_state::execute($this->quizid)],
            [get_narrative::execute_returns(), get_narrative::execute($this->quizid, 'victory')],
            [prefetch_next_node::execute_returns(), prefetch_next_node::execute($this->quizid, 1)],
        ];

        foreach ($checks as $index => [$structure, $payload]) {
            $clean = \core_external\external_api::clean_returnvalue($structure, $payload);
            $this->assertIsArray($clean, "Endpoint $index returned a payload its structure rejects.");
        }
    }
}
