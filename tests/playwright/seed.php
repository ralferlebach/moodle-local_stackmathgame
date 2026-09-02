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
 * CLI seed for the local_stackmathgame Playwright tests.
 *
 * Creates a course with a game-enabled quiz, a teacher who can reach the game settings and a
 * student who can play it, then prints the shell "export" lines the specs consume.
 *
 * Questions come from tests/fixtures/stack_playwright.xml when that file exists, so the
 * browser journeys run against real STACK questions. Without it the seed falls back to plain
 * short-answer questions: the navigation, settings and asset journeys are still meaningful,
 * only the STACK-specific feedback assertions skip themselves.
 *
 * Disposable dev/staging sites only.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

$now = time();

// A presentable name: the screenshots of a green run are used as illustrations.
$coursename = 'Algebra adventure';
$coursecategory = \core_course_category::get_default();
$course = create_course((object) [
    'fullname' => $coursename,
    // Unique by construction: two seeds started within the same second would otherwise
    // collide on the shortname and the course could not be created.
    'shortname' => uniqid('SMGPW', true),
    'category' => $coursecategory->id,
    'visible' => 1,
]);

// One question per page: the branch resolver navigates between pages, so several slots on one
// page would make the journey assert against a navigation the plugin does not drive.
$module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
$cm = create_module((object) [
    'course'             => $course->id,
    'module'             => $module->id,
    'modulename'         => 'quiz',
    'section'            => 0,
    'visible'            => 1,
    'cmidnumber'         => '',
    'name'               => 'The forest of equations',
    'intro'              => '',
    'introformat'        => FORMAT_HTML,
    'preferredbehaviour' => 'stackmathgame',
    'questionsperpage'   => 1,
    'attempts'           => 0,
    'grade'              => 100,
    'sumgrades'          => 0,
]);
$cmid = (int)$cm->cmid;
$quizid = (int)$cm->instance;
$quizrecord = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

$qcategory = question_make_default_categories([\context_module::instance($cmid)]);
$fixture = __DIR__ . '/../fixtures/stack_playwright.xml';
$usedstack = 0;

if (is_readable($fixture)) {
    // Real STACK questions. The import needs qtype_stack installed and a reachable Maxima;
    // when either is missing the import throws, and falling back is better than a seed that
    // aborts the whole browser run.
    try {
        $qformat = new qformat_xml();
        $qformat->setCategory($qcategory);
        $qformat->setContexts([\context_module::instance($cmid)]);
        $qformat->setCourse($course);
        $qformat->setFilename($fixture);
        $qformat->setRealfilename('stack_playwright.xml');
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);
        if ($qformat->importpreprocess() && $qformat->importprocess() && $qformat->importpostprocess()) {
            $usedstack = 1;
        }
    } catch (\Throwable $e) {
        echo "# STACK-Fixture konnte nicht importiert werden: " . $e->getMessage() . "\n";
        $usedstack = 0;
    }
}

if (!$usedstack) {
    for ($i = 1; $i <= 3; $i++) {
        $questiondata = (object) [
            'category'              => $qcategory->id,
            'parent'                => 0,
            'name'                  => 'Scene ' . $i,
            'questiontext'          => 'What is ' . $i . ' + ' . $i . '?',
            'questiontextformat'    => FORMAT_HTML,
            'generalfeedback'       => '',
            'generalfeedbackformat' => FORMAT_HTML,
            'defaultmark'           => 1,
            'penalty'               => 0.1,
            'qtype'                 => 'shortanswer',
            'length'                => 1,
            'stamp'                 => make_unique_id_code(),
            'timecreated'           => $now,
            'timemodified'          => $now,
            'createdby'             => 2,
            'modifiedby'            => 2,
        ];
        $questiondata->id = $DB->insert_record('question', $questiondata);
        quiz_add_quiz_question($questiondata->id, $quizrecord);
    }
} else {
    foreach ($DB->get_records('question', ['category' => $qcategory->id], 'id ASC') as $question) {
        quiz_add_quiz_question($question->id, $quizrecord);
    }
}

// Game configuration. The specs assert against the RPG design because it is the one with the
// richest asset manifest, so a missing asset shows up as a visible failure rather than a
// silently absent decoration.
\local_stackmathgame\game\theme_manager::seed_default_theme();
$config = \local_stackmathgame\game\quiz_configurator::ensure_default($cmid);
$config->enabled = 1;
$rpg = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default'], '*', IGNORE_MISSING);
if ($rpg) {
    $config->designid = (int)$rpg->id;
}
$DB->update_record('local_stackmathgame', $config);
\local_stackmathgame\local\service\question_map_service::ensure_for_cmid($cmid);

/**
 * Create a confirmed user and enrol them into the seeded course.
 *
 * @param string $username The login name.
 * @param string $password The password.
 * @param string $rolename The course role shortname.
 * @return \stdClass The created user record.
 */
function smg_seed_user(string $username, string $password, string $rolename): \stdClass {
    global $CFG, $DB, $course;
    $existing = $DB->get_record('user', ['username' => $username], '*', IGNORE_MISSING);
    if (!$existing) {
        $existing = \core_user::get_user(user_create_user((object) [
            'username'   => $username,
            'password'   => $password,
            'firstname'  => ucfirst($rolename),
            'lastname'   => 'Tester',
            'email'      => $username . '@example.invalid',
            'confirmed'  => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
        ], true, false));
    }
    enrol_try_internal_enrol(
        $course->id,
        $existing->id,
        $DB->get_field('role', 'id', ['shortname' => $rolename], MUST_EXIST)
    );
    return $existing;
}

smg_seed_user('smgteacher', 'Smg-Teach-Pass!1', 'editingteacher');
smg_seed_user('smgstudent', 'Smg-Play-Pass!1', 'student');

// A manager account for the authenticated accessibility checks of the plugin's own pages.
// Created on a throwaway CI site only; the specs skip when the variables are absent.
$manager = $DB->get_record('user', ['username' => 'smga11y'], '*', IGNORE_MISSING);
if (!$manager) {
    $manager = \core_user::get_user(user_create_user((object) [
        'username'   => 'smga11y',
        'password'   => 'Smg-A11y-Pass!1',
        'firstname'  => 'Access',
        'lastname'   => 'Checker',
        'email'      => 'smga11y@example.invalid',
        'confirmed'  => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ], true, false));
}
role_assign(
    $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST),
    $manager->id,
    \context_system::instance()->id
);

echo "export SMG_BASE_URL='" . $CFG->wwwroot . "'\n";
echo "export SMG_COURSE_ID='" . $course->id . "'\n";
echo "export SMG_COURSE_NAME='" . $coursename . "'\n";
echo "export SMG_CMID='" . $cmid . "'\n";
echo "export SMG_QUIZID='" . $quizid . "'\n";
echo "export SMG_HAS_STACK='" . $usedstack . "'\n";
echo "export SMG_TEACHER_USER='smgteacher'\n";
echo "export SMG_TEACHER_PASS='Smg-Teach-Pass!1'\n";
echo "export SMG_STUDENT_USER='smgstudent'\n";
echo "export SMG_STUDENT_PASS='Smg-Play-Pass!1'\n";
// Deliberately NOT called SMG_ADMIN_*: this is a manager, not the site administrator.
// Overriding the admin credentials with it denied the specs access to /admin pages that
// require moodle/site:config.
echo "export SMG_MANAGER_USER='smga11y'\n";
echo "export SMG_MANAGER_PASS='Smg-A11y-Pass!1'\n";
