<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shared question import used by the Playwright and load seeds.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Import a Moodle XML question file into a category and add every question to a quiz.
 *
 * Importing rather than inserting into {question} directly: since Moodle 4.0 a question also
 * needs a question_bank_entry and a question_version, and a hand-built insert produces a row
 * that quiz_add_quiz_question() rejects with "get_question_bank_entry(): Return value must be
 * of type object, false returned".
 *
 * @param string $file Absolute path of the XML file.
 * @param stdClass $category The question category to import into.
 * @param stdClass $course The course.
 * @param stdClass $quiz The quiz record to add the questions to.
 * @param context $context The module context.
 * @return int Number of questions added.
 */
function smg_seed_import_questions(
    string $file,
    stdClass $category,
    stdClass $course,
    stdClass $quiz,
    context $context
): int {
    global $DB;

    $before = $DB->get_fieldset_select('question', 'id', '1=1');

    $qformat = new qformat_xml();
    $qformat->setCategory($category);
    $qformat->setContexts([$context]);
    $qformat->setCourse($course);
    $qformat->setFilename($file);
    $qformat->setRealfilename(basename($file));
    $qformat->setMatchgrades('error');
    $qformat->setCatfromfile(false);
    $qformat->setContextfromfile(false);
    $qformat->setStoponerror(true);

    // The XML importer echoes a progress report. A seed's stdout is parsed by its caller, so
    // it is captured and discarded rather than allowed to mix into the export lines.
    ob_start();
    $ok = $qformat->importpreprocess() && $qformat->importprocess() && $qformat->importpostprocess();
    ob_end_clean();
    if (!$ok) {
        return 0;
    }

    $added = 0;
    $select = $before ? 'id NOT IN (' . implode(',', $before) . ')' : 'id IS NOT NULL';
    $after = $DB->get_records_select('question', $select, [], 'id ASC');
    foreach ($after as $question) {
        quiz_add_quiz_question($question->id, $quiz);
        $added++;
    }
    return $added;
}

/**
 * Create a course-module for a game-enabled quiz.
 *
 * Shared by both seeds. The field list is longer than it looks because create_module() expects
 * what the module form would have supplied: several {quiz} columns are NOT NULL without a
 * database default, and quiz_process_options() renames quizpassword to password. Missing any of
 * them fails with a constraint violation naming only the first one.
 *
 * @param stdClass $course The course.
 * @param string $name The activity name.
 * @return stdClass The course-module record returned by create_module().
 */
function smg_seed_create_quiz(stdClass $course, string $name): stdClass {
    global $DB;

    $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);

    return create_module((object) [
        'course'             => $course->id,
        'module'             => $module->id,
        'modulename'         => 'quiz',
        'section'            => 0,
        'visible'            => 1,
        'cmidnumber'         => '',
        'name'               => $name,
        'introeditor'        => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
        // The runtime refuses to start a game on any other behaviour, so a seed that used one
        // would measure the refusal path rather than the game.
        'preferredbehaviour' => 'stackmathgame',
        // One question per page: the branch resolver navigates between pages.
        'questionsperpage'   => 1,
        'attempts'           => 0,
        'grade'              => 100,
        'sumgrades'          => 0,
        'overduehandling'    => 'autoabandon',
        'navmethod'          => 'free',
        'quizpassword'       => '',
        'subnet'             => '',
        'browsersecurity'    => '-',
        'timeopen'           => 0,
        'timeclose'          => 0,
        'timelimit'          => 0,
        'questiondecimalpoints' => -1,
        'decimalpoints'      => 2,
        'showuserpicture'    => 0,
        'showblocks'         => 0,
        'delay1'             => 0,
        'delay2'             => 0,
        'graceperiod'        => 0,
        'canredoquestions'   => 0,
        'shuffleanswers'     => 1,
        'completionattemptsexhausted' => 0,
        'completionminattempts' => 0,
    ]);
}
