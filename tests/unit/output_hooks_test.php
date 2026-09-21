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

use local_stackmathgame\hook\output_hooks;
use local_stackmathgame\tests\game_quiz_testcase;

/**
 * Resolving the quiz behind an attempt page that carries no cmid.
 *
 * Found by the end-to-end journey, not by any unit test: every attempt page works when opened
 * with a cmid in the URL, and the game's own "next scene" link - like Moodle's page navigation -
 * does not put one there. The game therefore ran on the first page of an attempt and vanished on
 * every page after it.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\hook\output_hooks
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class output_hooks_test extends game_quiz_testcase {
    /**
     * An attempt resolves to its quiz's course module.
     */
    public function test_an_attempt_resolves_to_its_course_module(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_game_quiz(2);
        $student = $this->create_enrolled_student();

        // The quiz total is only computed once questions are in it; without this Moodle refuses to
        // start an attempt at a quiz graded out of 100 whose questions add up to nothing.
        \mod_quiz\quiz_settings::create($this->fixturequizid)->get_grade_calculator()->recompute_quiz_sumgrades();

        $quizobj = \mod_quiz\quiz_settings::create($this->fixturequizid, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour('deferredfeedback');
        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $this->assertSame($this->fixturecmid, output_hooks::cmid_from_attempt((int)$attempt->id));
        $this->assertTrue($DB->record_exists('quiz_attempts', ['id' => $attempt->id]));
    }

    /**
     * An unknown or absent attempt resolves to nothing rather than to a guess.
     */
    public function test_an_unknown_attempt_resolves_to_nothing(): void {
        $this->resetAfterTest();

        $this->assertSame(0, output_hooks::cmid_from_attempt(0));
        $this->assertSame(0, output_hooks::cmid_from_attempt(999999));
    }
}
