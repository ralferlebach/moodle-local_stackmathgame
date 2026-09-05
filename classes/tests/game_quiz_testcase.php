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

namespace local_stackmathgame\tests;

use advanced_testcase;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\service\question_map_service;

/**
 * Base class for tests that need a game-enabled quiz.
 *
 * It lives in classes/tests/ so the component autoloader finds it: Moodle's test class loader
 * only picks up files named *_test.php, and a trait in tests/fixtures/ would need an include -
 * which at the top of a test file counts as a change in global state, and inside setUp() runs too
 * late for PHP to resolve. classes/tests/ is where Moodle core puts shared test bases.
 *
 * Three test classes had built this fixture identically, which PHPCPD flagged and which drifts:
 * one copy gains a field and the others keep passing for a slightly different setup than the one
 * they describe.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class game_quiz_testcase extends advanced_testcase {
    /** @var int The course-module ID of the seeded quiz. */
    protected int $fixturecmid = 0;

    /** @var int The instance ID of the seeded quiz. */
    protected int $fixturequizid = 0;

    /** @var \stdClass The course the quiz lives in. */
    protected \stdClass $fixturecourse;

    /**
     * Create a course, a game-enabled quiz with $slots questions, and map it.
     *
     * @param int $slots How many questions to add.
     * @param bool $enable Whether to switch the game on.
     * @return void
     */
    protected function create_game_quiz(int $slots = 3, bool $enable = true): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $this->fixturecourse = $generator->create_course();
        $quiz = $generator->create_module('quiz', [
            'course' => $this->fixturecourse->id,
            // Any other behaviour leaves the game inactive, so a fixture using one would exercise
            // the refusal path rather than the game.
            'preferredbehaviour' => 'stackmathgame',
            // One question per page: the branch resolver navigates between pages.
            'questionsperpage' => 1,
        ]);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        for ($i = 0; $i < $slots; $i++) {
            quiz_add_quiz_question(
                $questiongenerator->create_question('shortanswer', null, ['category' => $category->id])->id,
                $quiz
            );
        }

        $this->fixturequizid = (int)$quiz->id;
        $this->fixturecmid = (int)get_coursemodule_from_instance(
            'quiz',
            $quiz->id,
            $this->fixturecourse->id,
            false,
            MUST_EXIST
        )->id;

        theme_manager::seed_default_theme();
        $config = quiz_configurator::ensure_default($this->fixturecmid);
        if ($enable) {
            $config->enabled = 1;
            $DB->update_record('local_stackmathgame', $config);
        }
        question_map_service::ensure_for_cmid($this->fixturecmid);
    }

    /**
     * Create a student enrolled in the fixture course and log them in.
     *
     * @return \stdClass The student.
     */
    protected function create_enrolled_student(): \stdClass {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->fixturecourse->id, 'student');
        $this->setUser($student);
        return $student;
    }
}
