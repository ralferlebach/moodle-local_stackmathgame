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

        stash_mapping_service::save_for_quiz(77, 5, [[
            'slotnumber' => 3,
            'stashitemid' => 42,
            'grantquantity' => 2,
            'enabled' => 1,
        ]]);

        $record = $DB->get_record(
            'local_stackmathgame_stashmap',
            ['quizid' => 77, 'slotnumber' => 3]
        );
        $this->assertNotFalse($record);
        $this->assertSame(42, (int)$record->stashitemid);
        $this->assertSame(2, (int)$record->grantquantity);
        $this->assertSame(1, (int)$record->enabled);
        $this->assertSame(5, (int)$record->stashcourseid);
    }

    /**
     * save_for_quiz updates an existing mapping row.
     *
     * @group local_stackmathgame_db
     */
    public function test_save_for_quiz_update(): void {
        global $DB;
        $this->resetAfterTest();

        stash_mapping_service::save_for_quiz(77, 5, [[
            'slotnumber' => 3,
            'stashitemid' => 42,
            'grantquantity' => 1,
            'enabled' => 1,
        ]]);
        stash_mapping_service::save_for_quiz(77, 5, [[
            'slotnumber' => 3,
            'stashitemid' => 99,
            'grantquantity' => 3,
            'enabled' => 0,
        ]]);

        $records = $DB->get_records('local_stackmathgame_stashmap', ['quizid' => 77, 'slotnumber' => 3]);
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

        stash_mapping_service::save_for_quiz(77, 5, [[
            'slotnumber' => 3,
            'stashitemid' => 42,
            'grantquantity' => 1,
            'enabled' => 1,
        ]]);
        stash_mapping_service::save_for_quiz(77, 5, [[
            'slotnumber' => 3,
            'stashitemid' => 0,
            'grantquantity' => 1,
            'enabled' => 1,
        ]]);

        $count = $DB->count_records('local_stackmathgame_stashmap', ['quizid' => 77, 'slotnumber' => 3]);
        $this->assertSame(0, $count, 'Row must be deleted when stashitemid=0');
    }

    /**
     * get_for_quiz returns mappings keyed by slot number.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_for_quiz_returns_keyed_by_slot(): void {
        $this->resetAfterTest();

        stash_mapping_service::save_for_quiz(77, 5, [
            ['slotnumber' => 1, 'stashitemid' => 10, 'grantquantity' => 1, 'enabled' => 1],
            ['slotnumber' => 3, 'stashitemid' => 20, 'grantquantity' => 2, 'enabled' => 1],
        ]);

        $result = stash_mapping_service::get_for_quiz(77);
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

        stash_mapping_service::save_for_quiz(77, 5, [[
            'slotnumber' => 3,
            'stashitemid' => 42,
            'grantquantity' => 0,
            'enabled' => 1,
        ]]);

        $record = $DB->get_record('local_stackmathgame_stashmap', ['quizid' => 77]);
        $this->assertSame(1, (int)$record->grantquantity, 'Quantity must be at least 1');
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
        // Enable stash and create item directly in DB to avoid generator version issues.
        $manager = \block_stash\manager::get((int)$course->id);
        $manager->set_enabled();

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

        // Force reload of manager to pick up new stash record.
        $manager = \block_stash\manager::get((int)$course->id, true);
        $manager->set_enabled();

        $items = stash_mapping_service::get_stash_items_for_course((int)$course->id);
        $this->assertNotEmpty($items);
        $this->assertContains('Magic Sword', $items);
        $this->assertArrayHasKey(0, $items, 'Must have a no-item option at key 0');
    }
}
