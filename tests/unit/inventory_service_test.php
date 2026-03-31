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
use local_stackmathgame\local\service\inventory_service;
use local_stackmathgame\local\service\profile_service;

/**
 * Unit tests for inventory_service.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\inventory_service
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class inventory_service_test extends advanced_testcase {
    /**
     * get_for_profile() returns rows keyed by itemkey.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_for_profile_returns_rows_keyed_by_itemkey(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $profile = profile_service::get_or_create_for_activity((int)$USER->id, (int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $DB->insert_record('local_stackmathgame_inventory', (object)[
            'profileid' => (int)$profile->id,
            'itemkey' => 'alpha',
            'quantity' => 3,
            'statejson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = inventory_service::get_for_profile((int)$profile->id);

        $this->assertArrayHasKey('alpha', $result);
        $this->assertSame(3, (int)$result['alpha']->quantity);
    }

    /**
     * get_for_activity() resolves the activity profile and returns inventory rows.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_for_activity_returns_inventory_rows(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $profile = profile_service::get_or_create_for_activity((int)$USER->id, (int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $DB->insert_record('local_stackmathgame_inventory', (object)[
            'profileid' => (int)$profile->id,
            'itemkey' => 'beta',
            'quantity' => 1,
            'statejson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = inventory_service::get_for_activity((int)$USER->id, (int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $this->assertArrayHasKey('beta', $result);
        $this->assertSame(1, (int)$result['beta']->quantity);
    }


    /**
     * get_summary_for_activity() returns aggregate counts.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_summary_for_activity_returns_counts(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $profile = profile_service::get_or_create_for_activity((int)$USER->id, (int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $DB->insert_record('local_stackmathgame_inventory', (object)[
            'profileid' => (int)$profile->id,
            'itemkey' => 'alpha',
            'quantity' => 2,
            'statejson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('local_stackmathgame_inventory', (object)[
            'profileid' => (int)$profile->id,
            'itemkey' => 'beta',
            'quantity' => 5,
            'statejson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = inventory_service::get_summary_for_activity((int)$USER->id, (int)$quiz->cmid, 'quiz', (int)$quiz->id);

        $this->assertSame(2, (int)$result['itemcount']);
        $this->assertSame(7, (int)$result['totalquantity']);
    }

}
