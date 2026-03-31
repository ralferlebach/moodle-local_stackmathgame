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
use local_stackmathgame\local\integration\availability;
use local_stackmathgame\local\service\stash_mapping_service;

/**
 * Tests for stash_mapping_service.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\stash_mapping_service
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stash_mapping_service_test extends advanced_testcase {

    /**
     * Create a quiz and return [quizid, cmid, courseid].
     *
     * @return int[]
     */
    private function create_quiz_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', (int)$quiz->id, (int)$course->id, false, MUST_EXIST);
        return [(int)$quiz->id, (int)$cm->id, (int)$course->id];
    }

    /**
     * get_for_quiz returns empty array when no mappings exist.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_for_quiz_empty(): void {
        $this->resetAfterTest();
        $result = stash_mapping_service::get_for_quiz(99999);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * save_for_quiz inserts a new mapping row.
     *
     * @group local_stackmathgame_db
     */
    public function test_save_for_quiz_insert(): void {
        global $DB;
        $this->resetAfterTest();
        [$quizid, $cmid, $courseid] = $this->create_quiz_activity();

        stash_mapping_service::save_for_quiz($quizid, $courseid, [[
            'slotnumber' => 3,
            'stashitemid' => 42,
            'grantquantity' => 2,
            'enabled' => 1,
        ]]);

        $record = $DB->get_record(
            'local_stackmathgame_stashmap',
            ['cmid' => $cmid, 'slotnumber' => 3]
        );
        $this->assertNotFalse($record);
        $this->assertSame(42, (int)$record->stashitemid);
        $this->assertSame(2, (int)$record->grantquantity);
        $this->assertSame(1, (int)$record->enabled);
        $this->assertSame($courseid, (int)$record->stashcourseid);
        $this->assertSame($cmid, (int)$record->cmid);
        $this->assertSame($quizid, (int)$record->quizid);
    }

    /**
     * save_for_quiz updates an existing mapping row.
     *
     * @group local_stackmathgame_db
     */
    public function test_save_for_quiz_update(): void {
        global $DB;
        $this->resetAfterTest();
        [$quizid, $cmid, $courseid] = $this->create_quiz_activity();

        stash_mapping_service::save_for_quiz($quizid, $courseid, [[
            'slotnumber' => 3,
            'stashitemid' => 42,
            'grantquantity' => 1,
            'enabled' => 1,
        ]]);
        stash_mapping_service::save_for_quiz($quizid, $courseid, [[
            'slotnumber' => 3,
            'stashitemid' => 99,
            'grantquantity' => 3,
            'enabled' => 0,
        ]]);

        $records = $DB->get_records('local_stackmathgame_stashmap', ['cmid' => $cmid, 'slotnumber' => 3]);
        $this->assertCount(1, $records, 'Must not create duplicate rows');
        $record = reset($records);
        $this->assertSame(99, (int)$record->stashitemid);
        $this->assertSame(3, (int)$record->grantquantity);
        $this->assertSame(0, (int)$record->enabled);
    }

    /**
     * save_for_quiz deletes a mapping when stashitemid=0.
     *
     * @group local_stackmathgame_db
     */
    public function test_save_for_quiz_delete_when_item_zero(): void {
        global $DB;
        $this->resetAfterTest();
        [$quizid, $cmid, $courseid] = $this->create_quiz_activity();

        stash_mapping_service::save_for_quiz($quizid, $courseid, [[
            'slotnumber' => 3,
            'stashitemid' => 42,
            'grantquantity' => 1,
            'enabled' => 1,
        ]]);
        stash_mapping_service::save_for_quiz($quizid, $courseid, [[
            'slotnumber' => 3,
            'stashitemid' => 0,
            'grantquantity' => 1,
            'enabled' => 1,
        ]]);

        $count = $DB->count_records('local_stackmathgame_stashmap', ['cmid' => $cmid, 'slotnumber' => 3]);
        $this->assertSame(0, $count, 'Row must be deleted when stashitemid=0');
    }

    /**
     * get_for_quiz returns mappings keyed by slot number.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_for_quiz_returns_keyed_by_slot(): void {
        $this->resetAfterTest();
        [$quizid, , $courseid] = $this->create_quiz_activity();

        stash_mapping_service::save_for_quiz($quizid, $courseid, [
            ['slotnumber' => 1, 'stashitemid' => 10, 'grantquantity' => 1, 'enabled' => 1],
            ['slotnumber' => 3, 'stashitemid' => 20, 'grantquantity' => 2, 'enabled' => 1],
        ]);

        $result = stash_mapping_service::get_for_quiz($quizid);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(3, $result);
        $this->assertSame(10, (int)$result[1]->stashitemid);
        $this->assertSame(20, (int)$result[3]->stashitemid);
    }

    /**
     * grantquantity is clamped to 1 minimum.
     *
     * @group local_stackmathgame_db
     */
    public function test_grant_quantity_clamped_to_minimum_one(): void {
        global $DB;
        $this->resetAfterTest();
        [$quizid, $cmid, $courseid] = $this->create_quiz_activity();

        stash_mapping_service::save_for_quiz($quizid, $courseid, [[
            'slotnumber' => 3,
            'stashitemid' => 42,
            'grantquantity' => 0,
            'enabled' => 1,
        ]]);

        $record = $DB->get_record('local_stackmathgame_stashmap', ['cmid' => $cmid]);
        $this->assertSame(1, (int)$record->grantquantity, 'Quantity must be at least 1');
    }


    /**
     * save_for_activity stores and reads mappings by cmid.
     *
     * @group local_stackmathgame_db
     */
    public function test_save_for_activity_and_get_for_activity_use_cmid_as_truth(): void {
        global $DB;
        $this->resetAfterTest();
        [$quizid, $cmid, $courseid] = $this->create_quiz_activity();

        stash_mapping_service::save_for_activity($cmid, $courseid, [[
            'slotnumber' => 2,
            'stashitemid' => 77,
            'grantquantity' => 4,
            'enabled' => 1,
        ]], 'quiz', $quizid);

        $record = $DB->get_record('local_stackmathgame_stashmap', ['cmid' => $cmid, 'slotnumber' => 2]);
        $this->assertNotFalse($record);
        $this->assertSame($quizid, (int)$record->quizid);

        $result = stash_mapping_service::get_for_activity($cmid, $quizid, 'quiz');
        $this->assertArrayHasKey(2, $result);
        $this->assertSame(77, (int)$result[2]->stashitemid);
    }

    /**
     * get_for_activity backfills cmid for legacy quiz rows.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_for_activity_backfills_legacy_quiz_rows(): void {
        global $DB;
        $this->resetAfterTest();
        [$quizid, $cmid, $courseid] = $this->create_quiz_activity();

        $DB->insert_record('local_stackmathgame_stashmap', (object)[
            'quizid' => $quizid,
            'slotnumber' => 3,
            'stashcourseid' => $courseid,
            'stashitemid' => 99,
            'grantquantity' => 1,
            'mode' => 'firstsolve',
            'enabled' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = stash_mapping_service::get_for_activity($cmid, $quizid, 'quiz');
        $this->assertArrayHasKey(3, $result);

        $record = $DB->get_record('local_stackmathgame_stashmap', ['quizid' => $quizid, 'slotnumber' => 3], '*', MUST_EXIST);
        $this->assertSame($cmid, (int)$record->cmid);
    }

    /**
     * get_stash_items_for_course returns empty array without block_stash.
     */
    public function test_get_stash_items_empty_without_block_stash(): void {
        if (availability::has_block_stash()) {
            $this->markTestSkipped('block_stash installed; this test requires it absent.');
        }
        $result = stash_mapping_service::get_stash_items_for_course(1);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * get_stash_items_for_course returns item list when block_stash is installed.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_stash_items_returns_list_with_block_stash(): void {
        global $DB;
        if (!availability::has_block_stash()) {
            $this->markTestSkipped('block_stash not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        // Enable stash by inserting a block_instances record.
        $coursecontext = \context_course::instance((int)$course->id);
        $DB->insert_record('block_instances', (object)[
            'blockname' => 'stash',
            'parentcontextid' => $coursecontext->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'subpagepattern' => null,
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $stashid = $DB->insert_record('block_stash', (object)[
            'courseid' => (int)$course->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('block_stash_items', (object)[
            'stashid' => $stashid,
            'name' => 'Magic Sword',
            'detail' => '',
            'detailformat' => FORMAT_HTML,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $items = stash_mapping_service::get_stash_items_for_course((int)$course->id);
        $this->assertNotEmpty($items);
        $this->assertContains('Magic Sword', $items);
        $this->assertArrayHasKey(0, $items, 'Must have a no-item option at key 0');
    }
}
