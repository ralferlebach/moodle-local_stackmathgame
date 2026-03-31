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

namespace local_stackmathgame\tests\unit;

use advanced_testcase;
use local_stackmathgame\external\api;
use local_stackmathgame\external\get_activity_config;
use local_stackmathgame\external\get_activity_reward_state;
use local_stackmathgame\external\get_activity_reward_history;
use local_stackmathgame\external\get_quiz_reward_history;
use local_stackmathgame\external\get_activity_stash_mappings;
use local_stackmathgame\external\get_quiz_reward_state;
use local_stackmathgame\external\save_activity_stash_mappings;
use local_stackmathgame\external\prefetch_next_activity_node;
use local_stackmathgame\local\service\profile_service;
use local_stackmathgame\local\service\stash_mapping_service;

/**
 * Unit tests for helper methods in local_stackmathgame\external\api.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\external\api
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_helper_test extends advanced_testcase {
    /**
     * Full payload passes through with correct types.
     */
    public function test_normalise_full_payload(): void {
        $input = ['questionid' => 7, 'slot' => 3, 'answers' => ['a', 'b']];
        $result = api::normalise_question_payload($input);
        $this->assertSame(7, $result['questionid']);
        $this->assertSame(3, $result['slot']);
        $this->assertSame(['a', 'b'], $result['answers']);
    }

    /**
     * Empty payload returns safe zero/empty defaults.
     */
    public function test_normalise_empty_payload(): void {
        $result = api::normalise_question_payload([]);
        $this->assertSame(0, $result['questionid']);
        $this->assertSame(0, $result['slot']);
        $this->assertSame([], $result['answers']);
    }

    /**
     * Numeric strings are cast to int by the normaliser.
     */
    public function test_normalise_string_values(): void {
        $result = api::normalise_question_payload(['questionid' => '42', 'slot' => '5']);
        $this->assertSame(42, $result['questionid']);
        $this->assertSame(5, $result['slot']);
    }


    /**
     * resolve_activity_identity() derives the canonical quiz activity from cmid.
     */
    public function test_resolve_activity_identity_from_cmid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $activity = api::resolve_activity_identity((int)$quiz->cmid);

        $this->assertSame((int)$quiz->cmid, $activity['cmid']);
        $this->assertSame('quiz', $activity['modname']);
        $this->assertSame((int)$quiz->id, $activity['instanceid']);
        $this->assertSame((int)$quiz->id, $activity['quizid']);
    }

    /**
     * resolve_activity_identity() backfills the cmid for quiz-based callers.
     */
    public function test_resolve_activity_identity_from_legacy_quizid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $activity = api::resolve_activity_identity(0, 'quiz', 0, (int)$quiz->id);

        $this->assertSame((int)$quiz->cmid, $activity['cmid']);
        $this->assertSame('quiz', $activity['modname']);
        $this->assertSame((int)$quiz->id, $activity['instanceid']);
        $this->assertSame((int)$quiz->id, $activity['quizid']);
    }

    /**
     * resolve_activity_identity() uses cmid as the source of truth for modname.
     */
    public function test_resolve_activity_identity_uses_cmid_source_of_truth(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $activity = api::resolve_activity_identity((int)$page->cmid, 'quiz');

        $this->assertSame((int)$page->cmid, $activity['cmid']);
        $this->assertSame('page', $activity['modname']);
        $this->assertSame((int)$page->id, $activity['instanceid']);
        $this->assertSame(0, $activity['quizid']);
    }

    /**
     * activity_supports_question_flow() is currently limited to quiz activities.
     */
    public function test_activity_supports_question_flow_only_for_quiz(): void {
        $this->assertTrue(api::activity_supports_question_flow(['modname' => 'quiz']));
        $this->assertFalse(api::activity_supports_question_flow(['modname' => 'page']));
    }

    /**
     * export_activity() returns all required canonical identity keys.
     */
    public function test_export_activity_has_required_keys(): void {
        $export = api::export_activity([
            'cmid' => 7,
            'modname' => 'quiz',
            'instanceid' => 11,
            'quizid' => 11,
        ]);

        $this->assertSame(7, $export['cmid']);
        $this->assertSame('quiz', $export['modname']);
        $this->assertSame(11, $export['instanceid']);
        $this->assertSame(11, $export['quizid']);
    }


    /**
     * validate_activity_access() uses cmid as the source of truth.
     *
     * @group local_stackmathgame_db
     */
    public function test_validate_activity_access_uses_cmid_as_source_of_truth(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        [, , $config, $profile, $design, $activity] = api::validate_activity_access(
            (int)$page->cmid,
            'quiz',
            0
        );

        $this->assertSame((int)$page->cmid, (int)$activity['cmid']);
        $this->assertSame('page', (string)$activity['modname']);
        $this->assertSame((int)$page->id, (int)$activity['instanceid']);
        $this->assertSame(0, (int)$activity['quizid']);
        $this->assertGreaterThan(0, (int)$config->labelid);
        $this->assertGreaterThan(0, (int)$profile->id);
        $this->assertIsObject($design);
    }

    /**
     * get_activity_config() returns an empty question map for non-quiz activities.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_get_activity_config_for_page_returns_empty_question_map(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $result = get_activity_config::execute((int)$page->cmid, 'page', (int)$page->id);

        $this->assertSame((int)$page->cmid, (int)$result['cmid']);
        $this->assertSame('page', (string)$result['modname']);
        $this->assertSame((int)$page->id, (int)$result['instanceid']);
        $this->assertSame(0, (int)$result['quizid']);
        $this->assertSame([], $result['questionmap']);
        $this->assertSame([], $result['stashmappings']);
    }

    /**
     * prefetch_next_activity_node() returns an end payload for non-quiz activities.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_prefetch_next_activity_node_for_page_returns_end_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $result = prefetch_next_activity_node::execute((int)$page->cmid, 'page', (int)$page->id, 0);

        $this->assertSame((int)$page->cmid, (int)$result['cmid']);
        $this->assertSame('page', (string)$result['modname']);
        $this->assertSame('end', (string)$result['nextnode']['nodetype']);
        $this->assertSame(0, (int)$result['nextnode']['slotnumber']);
    }


    /**
     * get_activity_config() includes stash mappings for quiz activities.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_get_activity_config_for_quiz_includes_stash_mappings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        stash_mapping_service::save_for_activity((int)$quiz->cmid, (int)$course->id, [[
            'slotnumber' => 2,
            'stashitemid' => 99,
            'grantquantity' => 3,
            'enabled' => 1,
        ]], 'quiz', (int)$quiz->id);

        $result = get_activity_config::execute((int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $this->assertSame((int)$quiz->cmid, (int)$result['cmid']);
        $this->assertCount(1, $result['stashmappings']);
        $this->assertSame(2, (int)$result['stashmappings'][0]['slotnumber']);
        $this->assertSame(99, (int)$result['stashmappings'][0]['stashitemid']);
        $this->assertSame(3, (int)$result['stashmappings'][0]['grantquantity']);
        $this->assertTrue((bool)$result['stashmappings'][0]['enabled']);
    }

    /**
     * export_design(null) returns all required keys.
     */


    /**
     * get_activity_stash_mappings() returns an empty list for non-quiz activities.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_get_activity_stash_mappings_for_page_returns_empty_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $result = get_activity_stash_mappings::execute((int)$page->cmid, 'page', (int)$page->id);

        $this->assertSame((int)$page->cmid, (int)$result['cmid']);
        $this->assertSame('page', (string)$result['modname']);
        $this->assertSame([], $result['stashmappings']);
    }

    /**
     * save_activity_stash_mappings() persists mappings and get_activity_stash_mappings() returns them.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_save_activity_stash_mappings_round_trips(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $saved = save_activity_stash_mappings::execute((int)$quiz->cmid, 'quiz', (int)$quiz->id, [[
            'slotnumber' => 3,
            'stashitemid' => 123,
            'grantquantity' => 2,
            'enabled' => true,
        ]]);

        $this->assertCount(1, $saved['stashmappings']);
        $this->assertSame(3, (int)$saved['stashmappings'][0]['slotnumber']);
        $this->assertSame(123, (int)$saved['stashmappings'][0]['stashitemid']);

        $loaded = get_activity_stash_mappings::execute((int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $this->assertCount(1, $loaded['stashmappings']);
        $this->assertSame(3, (int)$loaded['stashmappings'][0]['slotnumber']);
        $this->assertSame(123, (int)$loaded['stashmappings'][0]['stashitemid']);
        $this->assertSame(2, (int)$loaded['stashmappings'][0]['grantquantity']);
        $this->assertTrue((bool)$loaded['stashmappings'][0]['enabled']);
    }

    /**
     * get_activity_reward_state() returns empty inventory and stash mappings for non-quiz activities.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_get_activity_reward_state_for_page_returns_empty_lists(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $result = get_activity_reward_state::execute((int)$page->cmid, 'page', (int)$page->id);

        $this->assertSame((int)$page->cmid, (int)$result['cmid']);
        $this->assertSame('page', (string)$result['modname']);
        $this->assertSame([], $result['inventory']);
        $this->assertSame(['itemcount' => 0, 'totalquantity' => 0], $result['inventorysummary']);
        $this->assertSame([], $result['stashmappings']);
        $this->assertArrayHasKey('stash', $result['bridges']);
        $this->assertArrayHasKey('localinventory', $result['bridges']);
    }

    /**
     * Reward state exports stash mappings and local inventory for quiz activities.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_get_activity_reward_state_for_quiz_includes_inventory_and_stashmappings(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $profile = profile_service::get_or_create_for_activity((int)$USER->id, (int)$quiz->cmid, 'quiz', (int)$quiz->id);

        stash_mapping_service::save_for_activity((int)$quiz->cmid, (int)$course->id, [[
            'slotnumber' => 4,
            'stashitemid' => 321,
            'grantquantity' => 1,
            'enabled' => 1,
        ]], 'quiz', (int)$quiz->id);

        $DB->insert_record('local_stackmathgame_inventory', (object)[
            'profileid' => (int)$profile->id,
            'itemkey' => 'smg_slot_4',
            'quantity' => 2,
            'statejson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = get_activity_reward_state::execute((int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $this->assertSame((int)$quiz->cmid, (int)$result['cmid']);
        $this->assertCount(1, $result['inventory']);
        $this->assertSame('smg_slot_4', (string)$result['inventory'][0]['itemkey']);
        $this->assertSame(2, (int)$result['inventory'][0]['quantity']);
        $this->assertSame(1, (int)$result['inventorysummary']['itemcount']);
        $this->assertSame(2, (int)$result['inventorysummary']['totalquantity']);
        $this->assertCount(1, $result['stashmappings']);
        $this->assertSame(4, (int)$result['stashmappings'][0]['slotnumber']);
        $this->assertSame(321, (int)$result['stashmappings'][0]['stashitemid']);
        $this->assertTrue((bool)$result['bridges']['localinventory']);
    }

    /**
     * Legacy quiz reward wrapper delegates to the activity reward state endpoint.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_get_quiz_reward_state_returns_quiz_wrapper_payload(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $profile = profile_service::get_or_create_for_activity((int)$USER->id, (int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $DB->insert_record('local_stackmathgame_inventory', (object)[
            'profileid' => (int)$profile->id,
            'itemkey' => 'reward_key',
            'quantity' => 1,
            'statejson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = get_quiz_reward_state::execute((int)$quiz->id);

        $this->assertSame((int)$quiz->id, (int)$result['quizid']);
        $this->assertCount(1, $result['inventory']);
        $this->assertSame('reward_key', (string)$result['inventory'][0]['itemkey']);
        $this->assertSame(1, (int)$result['inventorysummary']['itemcount']);
        $this->assertArrayHasKey('xp', $result['bridges']);
    }

    public function test_export_design_null_has_required_keys(): void {
        $export = api::export_design(null);
        $required = [
            'id', 'name', 'slug', 'modecomponent', 'description',
            'isbundled', 'isactive', 'narrativejson', 'uijson',
            'mechanicsjson', 'assetmanifestjson', 'runtimejson',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $export, "export_design(null) must have key '$key'");
        }
    }

    /**
     * export_design(null) returns zeros and empty strings.
     */
    public function test_export_design_null_default_values(): void {
        $export = api::export_design(null);
        $this->assertSame(0, $export['id']);
        $this->assertSame('', $export['name']);
        $this->assertSame('{}', $export['runtimejson']);
        $this->assertSame(0, $export['isbundled']);
    }

    /**
     * export_profile returns required keys with correct types.
     */
    public function test_export_profile_has_required_keys(): void {
        $profile = (object)[
            'id' => 1,
            'userid' => 2,
            'labelid' => 3,
            'score' => 100,
            'xp' => 250,
            'levelno' => 3,
            'softcurrency' => 10,
            'hardcurrency' => 5,
            'avatarconfigjson' => '{}',
            'progressjson' => '{}',
            'statsjson' => '{}',
            'flagsjson' => '{}',
            'lastquizid' => 7,
            'lastdesignid' => 4,
            'lastaccess' => 1700000000,
        ];
        $export = api::export_profile($profile);
        $this->assertSame(1, $export['id']);
        $this->assertSame(100, $export['score']);
        $this->assertSame(250, $export['xp']);
        $this->assertSame(3, $export['levelno']);
        $this->assertArrayHasKey('summaryjson', $export);
        $summary = json_decode($export['summaryjson'], true);
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('solvedcount', $summary);
    }


    /**
     * get_activity_reward_history() returns an empty history list for non-quiz activities.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_get_activity_reward_history_for_page_returns_empty_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $result = get_activity_reward_history::execute((int)$page->cmid, 'page', (int)$page->id, 5);

        $this->assertSame((int)$page->cmid, (int)$result['cmid']);
        $this->assertSame('page', (string)$result['modname']);
        $this->assertSame([], $result['history']);
    }

    /**
     * Activity and legacy quiz reward-history endpoints export logged activity rows.
     *
     * @group local_stackmathgame_db
     * @runInSeparateProcess
     */
    public function test_reward_history_endpoints_export_activity_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        [, , $config, $profile, , $activity] = api::validate_activity_access((int)$quiz->cmid, 'quiz', (int)$quiz->id);
        api::log_event(
            $profile,
            (int)$quiz->id,
            (int)$config->designid,
            'reward_state_checked',
            'unit.api_helper_test',
            ['questionid' => 0, 'check' => 'activity-history'],
            7,
            'ok',
            $activity
        );

        $activityresult = get_activity_reward_history::execute((int)$quiz->cmid, 'quiz', (int)$quiz->id, 5);
        $legacyresult = get_quiz_reward_history::execute((int)$quiz->id, 5);

        $this->assertNotEmpty($activityresult['history']);
        $this->assertSame((int)$quiz->cmid, (int)$activityresult['history'][0]['cmid']);
        $this->assertSame('reward_state_checked', (string)$activityresult['history'][0]['eventtype']);
        $this->assertSame((int)$quiz->id, (int)$legacyresult['history'][0]['quizid']);
        $this->assertSame('unit.api_helper_test', (string)$legacyresult['history'][0]['source']);
    }

}
