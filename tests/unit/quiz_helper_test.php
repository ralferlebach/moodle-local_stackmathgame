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

namespace local_stackmatheditor\tests\unit;

use advanced_testcase;
use local_stackmatheditor\quiz_helper;

/**
 * Unit tests for local_stackmatheditor\quiz_helper.
 *
 * DB-dependent tests require @group local_stackmatheditor_db.
 *
 * @package    local_stackmatheditor
 * @covers     \local_stackmatheditor\quiz_helper
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_helper_test extends advanced_testcase {
    /**
     * get_cmid() returns 0 when no PAGE context or URL param is available.
     */
    public function test_get_cmid_no_context(): void {
        // In a unit test there is no PAGE->cm, and no URL params.
        $cmid = quiz_helper::get_cmid();
        $this->assertIsInt($cmid);
        $this->assertGreaterThanOrEqual(0, $cmid);
    }

    /**
     * can_manage_quiz() returns false for an invalid/nonexistent cmid.
     */
    public function test_can_manage_quiz_invalid_cmid(): void {
        $result = quiz_helper::can_manage_quiz(0);
        $this->assertFalse(
            $result,
            'can_manage_quiz(0) must return false — cmid 0 does not exist'
        );
    }

    /**
     * can_manage_quiz() returns false for a very large nonexistent cmid.
     */
    public function test_can_manage_quiz_nonexistent_cmid(): void {
        $result = quiz_helper::can_manage_quiz(PHP_INT_MAX);
        $this->assertFalse(
            $result,
            'can_manage_quiz with nonexistent cmid must return false'
        );
    }

    /**
     * get_return_url() returns a non-empty string.
     *
     * $PAGE->set_url() must be called before accessing $PAGE->url;
     * omitting it causes debugging() in Moodle 4.5 which advanced_testcase
     * treats as a test error.
     */
    public function test_get_return_url_fallback(): void {
        global $PAGE;
        // Initialise $PAGE->url so get_return_url() can access it without
        // triggering the 'This page did not call $PAGE->set_url()' notice.
        $PAGE->set_url(new \moodle_url('/'));

        $url = quiz_helper::get_return_url(42);
        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }

    /**
     * quiz_has_stack_questions() returns false for a nonexistent cmid.
     */
    public function test_quiz_has_stack_questions_invalid(): void {
        $result = quiz_helper::quiz_has_stack_questions(0);
        $this->assertFalse(
            $result,
            'Nonexistent quiz must have no STACK questions'
        );
    }

    /**
     * load_quiz_stack_questions() returns empty array for nonexistent quiz.
     *
     * @group local_stackmatheditor_db
     */
    public function test_load_stack_questions_nonexistent_quiz(): void {
        $this->resetAfterTest();
        $questions = quiz_helper::load_quiz_stack_questions(99999999);
        $this->assertIsArray($questions);
        $this->assertEmpty(
            $questions,
            'Nonexistent quiz must return empty question list'
        );
    }

    /**
     * load_attempt_stack_slots() returns expected structure for nonexistent attempt.
     *
     * @group local_stackmatheditor_db
     */
    public function test_load_attempt_slots_nonexistent(): void {
        $this->resetAfterTest();
        $result = quiz_helper::load_attempt_stack_slots(99999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('slotmap', $result);
        $this->assertArrayHasKey('qbeids', $result);
        $this->assertArrayHasKey('qbeidmap', $result);
        $this->assertEmpty($result['slotmap']);
    }
}
