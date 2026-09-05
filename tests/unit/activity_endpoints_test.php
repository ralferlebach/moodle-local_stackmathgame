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
use local_stackmathgame\external\get_activity_config;
use local_stackmathgame\external\get_activity_narrative;
use local_stackmathgame\external\get_activity_profile_state;
use local_stackmathgame\external\prefetch_next_activity_node;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\service\question_map_service;

/**
 * The activity-addressed endpoints and the design layer beneath them.
 *
 * The quiz-addressed endpoints are thin wrappers around these, so this is where the behaviour
 * actually lives. Addressing by cmid rather than quizid is the plugin's stated direction - it
 * encodes the full context and leaves room for module types other than quiz - and these are the
 * functions that implement it.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\external\get_activity_config
 * @covers     \local_stackmathgame\external\get_activity_profile_state
 * @covers     \local_stackmathgame\external\get_activity_narrative
 * @covers     \local_stackmathgame\external\prefetch_next_activity_node
 * @covers     \local_stackmathgame\game\theme_manager
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_endpoints_test extends game_quiz_testcase {
    /** @var int The course-module ID. */
    private int $cmid;
    /** @var int The quiz instance ID. */
    private int $quizid;

    /**
     * Build a game-enabled quiz with three slots.
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
     * The configuration can be fetched by course-module id.
     */
    public function test_config_by_cmid(): void {
        $result = get_activity_config::execute($this->cmid);

        $this->assertSame($this->cmid, (int)$result['cmid']);
        $this->assertSame('quiz', (string)$result['modname']);
        $this->assertCount(3, $result['questionmap']);
    }

    /**
     * The profile can be fetched by course-module id.
     */
    public function test_profile_by_cmid(): void {
        $result = get_activity_profile_state::execute($this->cmid);

        $this->assertArrayHasKey('profile', $result);
        $this->assertSame(0, (int)$result['profile']['xp']);
    }

    /**
     * The narrative can be fetched by course-module id.
     */
    public function test_narrative_by_cmid(): void {
        $result = get_activity_narrative::execute($this->cmid, 'quiz', 0, 'world_enter');

        $this->assertIsArray($result['lines']);
    }

    /**
     * The prefetch resolves a navigation for each outcome it is asked about.
     *
     * A wrong answer keeps the player where they are; the retry is the point of the scene.
     */
    public function test_prefetch_by_cmid_per_outcome(): void {
        $right = prefetch_next_activity_node::execute($this->cmid, 'quiz', 0, 1, 'gradedright');
        $this->assertSame('continue', $right['navigation']['action']);
        $this->assertSame(2, (int)$right['navigation']['nextslot']);

        $wrong = prefetch_next_activity_node::execute($this->cmid, 'quiz', 0, 1, 'gradedwrong');
        $this->assertSame('stay', $wrong['navigation']['action']);
    }

    /**
     * The last slot finishes the run rather than pointing nowhere.
     */
    public function test_prefetch_finishes_on_the_last_slot(): void {
        $result = prefetch_next_activity_node::execute($this->cmid, 'quiz', 0, 3, 'gradedright');

        $this->assertSame('finish', $result['navigation']['action']);
    }

    /**
     * Every activity endpoint's payload matches its declared return structure.
     */
    public function test_payloads_match_their_structures(): void {
        $checks = [
            [get_activity_config::execute_returns(), get_activity_config::execute($this->cmid)],
            [get_activity_profile_state::execute_returns(), get_activity_profile_state::execute($this->cmid)],
            [
                get_activity_narrative::execute_returns(),
                get_activity_narrative::execute($this->cmid, 'quiz', 0, 'victory'),
            ],
            [
                prefetch_next_activity_node::execute_returns(),
                prefetch_next_activity_node::execute($this->cmid, 'quiz', 0, 1),
            ],
        ];

        foreach ($checks as $index => [$structure, $payload]) {
            $this->assertIsArray(
                \core_external\external_api::clean_returnvalue($structure, $payload),
                "Activity endpoint $index returned a payload its structure rejects."
            );
        }
    }

    /**
     * Every bundled design resolves a theme configuration with its own assets.
     */
    public function test_every_design_resolves_its_configuration(): void {
        global $DB;

        $designs = $DB->get_records('local_stackmathgame_design');
        $this->assertNotEmpty($designs);

        foreach ($designs as $design) {
            $config = theme_manager::get_theme_config((int)$design->id);
            $this->assertNotEmpty($config, $design->slug . ' resolved no configuration.');
            $this->assertNotEmpty(
                $config['runtimeassets'] ?? [],
                $design->slug . ' resolved no assets.'
            );
        }
    }

    /**
     * An unknown design resolves to nothing rather than to a half-built configuration.
     */
    public function test_unknown_design_resolves_to_nothing(): void {
        $this->assertNull(theme_manager::get_theme(999999));
    }

    /**
     * Seeding the bundled designs twice does not duplicate them.
     *
     * The seed runs on every install and upgrade, so it has to be idempotent.
     */
    public function test_seeding_is_idempotent(): void {
        global $DB;

        $before = $DB->count_records('local_stackmathgame_design');
        theme_manager::seed_default_theme();
        theme_manager::seed_default_theme();

        $this->assertSame($before, $DB->count_records('local_stackmathgame_design'));
    }
}
