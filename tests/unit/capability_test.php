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

/**
 * Capability smoke tests for local_stackmathgame.
 *
 * Verifies that the three navigation access tiers work as expected:
 *   - viewstudio: manager only (system context)
 *   - configurequiz: editingteacher+ (module context)
 *   - play: student+ (module context)
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capability_test extends advanced_testcase {
    /**
     * @group local_stackmathgame_db
     */
    public function test_viewstudio_granted_to_manager(): void {
        $this->resetAfterTest();
        $manager = $this->getDataGenerator()->create_user();
        $syscontext = \context_system::instance();
        $this->getDataGenerator()->role_assign(
            (int)$this->get_roleid('manager'),
            $manager->id,
            $syscontext->id
        );
        $this->assertTrue(
            has_capability('local/stackmathgame:viewstudio', $syscontext, $manager),
            'Manager must have viewstudio'
        );
    }

    /**
     * @group local_stackmathgame_db
     */
    public function test_viewstudio_denied_to_editingteacher(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $syscontext = \context_system::instance();
        $this->assertFalse(
            has_capability('local/stackmathgame:viewstudio', $syscontext, $teacher),
            'EditingTeacher must not have viewstudio at system level'
        );
    }

    /**
     * @group local_stackmathgame_db
     */
    public function test_configurequiz_granted_to_editingteacher(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $modcontext = \context_module::instance((int)$quiz->cmid);
        $this->assertTrue(
            has_capability('local/stackmathgame:configurequiz', $modcontext, $teacher),
            'EditingTeacher must have configurequiz'
        );
    }

    /**
     * @group local_stackmathgame_db
     */
    public function test_configurequiz_denied_to_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $modcontext = \context_module::instance((int)$quiz->cmid);
        $this->assertFalse(
            has_capability('local/stackmathgame:configurequiz', $modcontext, $student),
            'Student must not have configurequiz'
        );
    }

    /**
     * @group local_stackmathgame_db
     */
    public function test_play_granted_to_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $modcontext = \context_module::instance((int)$quiz->cmid);
        $this->assertTrue(
            has_capability('local/stackmathgame:play', $modcontext, $student),
            'Student must have play capability'
        );
    }

    /**
     * @group local_stackmathgame_db
     */
    public function test_managenarratives_denied_to_teacher(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $syscontext = \context_system::instance();
        $this->assertFalse(
            has_capability('local/stackmathgame:managenarratives', $syscontext, $teacher),
            'Teachers must not manage narratives (designer-only)'
        );
    }

    /**
     * Helper: return the role ID by shortname.
     *
     * @param string $shortname
     * @return int
     */
    private function get_roleid(string $shortname): int {
        global $DB;
        return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
