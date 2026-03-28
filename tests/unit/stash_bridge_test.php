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
use local_stackmathgame\local\integration\stash_bridge;

/**
 * Tests for stash_bridge.
 *
 * Three scenarios:
 *   A) block_stash NOT installed: falls back to local inventory silently.
 *   B) block_stash installed, no mapping: falls back to local inventory.
 *   C) block_stash installed, mapping present: awards real stash item.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\integration\stash_bridge
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stash_bridge_test extends advanced_testcase {
    /**
     * Build a minimal profile object for testing.
     *
     * @param int $userid
     * @return \stdClass
     */
    private function make_profile(int $userid): \stdClass {
        return (object)[
            'id' => 1,
            'userid' => $userid,
            'labelid' => 1,
            'score' => 0,
            'xp' => 0,
            'progressjson' => '{}',
        ];
    }

    /**
     * Without block_stash, dispatch skips when not solved.
     */
    public function test_dispatch_skips_when_not_solved_no_stash(): void {
        if (availability::has_block_stash()) {
            $this->markTestSkipped('block_stash installed; this test requires it absent.');
        }
        $profile = $this->make_profile(2);
        $result = stash_bridge::dispatch(
            $profile, 10, 1, 3, [], ['solved' => false, 'score' => 0, 'xp' => 0]
        );
        $this->assertFalse($result['dispatched']);
        $this->assertFalse($result['stash']);
    }

    /**
     * Without block_stash, a solved slot writes to local inventory.
     *
     * @group local_stackmathgame_db
     */
    public function test_dispatch_writes_local_inventory_without_block_stash(): void {
        global $DB;
        if (availability::has_block_stash()) {
            $this->markTestSkipped('block_stash installed; this test requires it absent.');
        }
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $profile = $this->make_profile((int)$user->id);

        $result = stash_bridge::dispatch(
            $profile, 10, 1, 3, [], ['solved' => true, 'score' => 10, 'xp' => 5]
        );

        $this->assertTrue($result['dispatched']);
        $this->assertFalse($result['stash']);
        $this->assertStringStartsWith('smg_slot_', $result['itemkey']);

        $record = $DB->get_record(
            'local_stackmathgame_inventory',
            ['profileid' => $profile->id, 'itemkey' => $result['itemkey']]
        );
        $this->assertNotFalse($record);
        $this->assertSame(1, (int)$record->quantity);
    }

    /**
     * Without block_stash, dispatching twice increments quantity.
     *
     * @group local_stackmathgame_db
     */
    public function test_dispatch_increments_local_inventory_on_repeat(): void {
        global $DB;
        if (availability::has_block_stash()) {
            $this->markTestSkipped('block_stash installed; this test requires it absent.');
        }
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $profile = $this->make_profile((int)$user->id);
        $deltas = ['solved' => true, 'score' => 10, 'xp' => 5];

        stash_bridge::dispatch($profile, 10, 1, 3, [], $deltas);
        stash_bridge::dispatch($profile, 10, 1, 3, [], $deltas);

        $record = $DB->get_record(
            'local_stackmathgame_inventory',
            ['profileid' => $profile->id, 'itemkey' => 'smg_slot_3']
        );
        $this->assertSame(2, (int)$record->quantity);
    }

    /**
     * The stash_item_granted event fires even on local inventory fallback.
     *
     * @group local_stackmathgame_db
     */
    public function test_event_fires_on_local_inventory_dispatch(): void {
        if (availability::has_block_stash()) {
            $this->markTestSkipped('block_stash installed; this test requires it absent.');
        }
        $this->resetAfterTest();
        $sink = $this->redirectEvents();
        $user = $this->getDataGenerator()->create_user();
        $profile = $this->make_profile((int)$user->id);

        stash_bridge::dispatch(
            $profile, 0, 1, 5, [], ['solved' => true, 'score' => 10, 'xp' => 5]
        );

        $events = $sink->get_events();
        $sink->close();
        $eventnames = array_map(fn($e) => $e->eventname, $events);
        $this->assertContains(
            '\\local_stackmathgame\\event\\stash_item_granted',
            $eventnames
        );
    }

    /**
     * With block_stash but no stashmap row, falls back to local inventory.
     *
     * @group local_stackmathgame_db
     */
    public function test_dispatch_fallback_when_no_mapping(): void {
        global $DB;
        if (!availability::has_block_stash()) {
            $this->markTestSkipped('block_stash not installed.');
        }
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $profile = $this->make_profile((int)$user->id);

        $result = stash_bridge::dispatch(
            $profile, 99999, 1, 3, [], ['solved' => true, 'score' => 10, 'xp' => 5]
        );

        $this->assertTrue($result['dispatched']);
        $this->assertFalse($result['stash']);
        $record = $DB->get_record(
            'local_stackmathgame_inventory',
            ['profileid' => $profile->id, 'itemkey' => 'smg_slot_3']
        );
        $this->assertNotFalse($record);
    }

    /**
     * With block_stash and a stashmap row, awards the real stash item.
     *
     * @group local_stackmathgame_db
     */
    public function test_dispatch_awards_real_stash_item_when_mapped(): void {
        global $DB;
        if (!availability::has_block_stash()) {
            $this->markTestSkipped('block_stash not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $stashgen = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $stash = $stashgen->create_stash(['courseid' => $course->id]);
        $item = $stashgen->create_item(['stashid' => $stash->get_id(), 'name' => 'Test Gem']);

        $DB->insert_record('local_stackmathgame_stashmap', (object)[
            'quizid' => 77,
            'slotnumber' => 3,
            'stashcourseid' => (int)$course->id,
            'stashitemid' => (int)$item->get_id(),
            'grantquantity' => 1,
            'mode' => 'firstsolve',
            'enabled' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $profile = $this->make_profile((int)$user->id);
        $result = stash_bridge::dispatch(
            $profile, 77, 1, 3, [], ['solved' => true, 'score' => 10, 'xp' => 5]
        );

        $this->assertTrue($result['dispatched']);
        $this->assertTrue($result['stash']);
        $this->assertSame((int)$item->get_id(), $result['stashitemid']);

        $manager = \block_stash\manager::get((int)$course->id);
        $useritem = $manager->get_user_item((int)$user->id, (int)$item->get_id());
        $this->assertGreaterThanOrEqual(1, (int)$useritem->get_quantity());
    }

    /**
     * Verify stash_bridge uses cron_setup_user for the admin context switch.
     */
    public function test_stash_bridge_uses_cron_setup_user(): void {
        $file = __DIR__ . '/../../classes/local/integration/stash_bridge.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('cron_setup_user()', $content);
    }

    /**
     * Verify stash_bridge restores the original user in a finally block.
     */
    public function test_stash_bridge_restores_user_in_finally(): void {
        $content = file_get_contents(
            __DIR__ . '/../../classes/local/integration/stash_bridge.php'
        );
        $this->assertStringContainsString('finally', $content);
        $this->assertStringContainsString('set_user($originaluser)', $content);
    }
}
