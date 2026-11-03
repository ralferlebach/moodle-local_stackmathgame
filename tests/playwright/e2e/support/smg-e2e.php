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

/**
 * CLI for the verified browser journeys: set a game quiz up, and read back what was stored.
 *
 *     php smg-e2e.php seed                   create a student, a course and a game quiz
 *     php smg-e2e.php editor on|off          switch the STACK Math Editor on or off site-wide
 *     php smg-e2e.php state <username> <cmid> what the question engine stored for the latest attempt
 *
 * Prints one JSON object on stdout and nothing else. The Moodle root is SMG_MOODLE_ROOT, or the
 * directory four levels above the plugin when the plugin is installed in place.
 *
 * The seed builds the course; it does not play. Everything the student does is done in the
 * browser, and "state" only reads - so the journey's assertions compare what the student saw with
 * what the question engine stored (re-audit N09). For disposable test sites only.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// The site is SMG_MOODLE_ROOT, or the Moodle root six levels up when the plugin is installed in
// place; a tree whose web root is public/ (Moodle 5.1+) keeps config.php there. Resolved inside
// the require itself, because nothing may change global state before config.php is included.
require(
    (getenv('SMG_MOODLE_ROOT') ?: dirname(__DIR__, 6))
    . (file_exists((getenv('SMG_MOODLE_ROOT') ?: dirname(__DIR__, 6)) . '/config.php') ? '' : '/public')
    . '/config.php'
);
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/local/stackmathgame/tests/fixtures/seedlib.php');

global $DB;

$CFG->noemailever = true;
\core\session\manager::set_user(get_admin());
$command = $argv[1] ?? '';

/**
 * Print a result and stop.
 *
 * @param array $result The result.
 * @return void
 */
function smg_e2e_out(array $result): void {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if ($command === 'editor') {
    if (!\core_component::get_component_directory('local_stackmatheditor')) {
        smg_e2e_out(['editor' => 'absent']);
    }
    // Mode 1 is "enabled everywhere", mode 0 "disabled everywhere": no per-quiz override applies.
    set_config('enabled', ($argv[2] ?? '') === 'on' ? 1 : 0, 'local_stackmatheditor');
    purge_caches(['muc' => true]);
    smg_e2e_out(['editor' => ($argv[2] ?? '') === 'on' ? 'on' : 'off']);
}

if ($command === 'seed') {
    $stamp = (string)time() . random_int(100, 999);
    set_config('passwordpolicy', 0);
    $password = 'Smg-Verify-Pass!1';
    $username = 'smgverify' . $stamp;
    $userid = user_create_user((object)[
        'username' => $username,
        'password' => $password,
        'firstname' => 'Vera',
        'lastname' => 'Verify',
        'email' => $username . '@example.invalid',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ], true, false);
    $course = create_course((object)[
        'fullname' => 'Verified play ' . $stamp,
        'shortname' => 'SMGV' . $stamp,
        'category' => \core_course_category::get_default()->id,
    ]);
    $studentrole = (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
    enrol_try_internal_enrol((int)$course->id, (int)$userid, $studentrole);

    $cm = smg_seed_create_quiz($course, 'Verified quest ' . $stamp);
    // The review options as the quiz form would default them. create_module() leaves them at 0,
    // which hides every feedback during the attempt - the student would never see what STACK
    // said about an answer, and neither would this journey.
    $reviewfields = [
        'reviewattempt', 'reviewcorrectness', 'reviewmaxmarks', 'reviewmarks', 'reviewspecificfeedback',
        'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback',
    ];
    foreach ($reviewfields as $field) {
        $default = get_config('quiz', $field);
        $DB->set_field('quiz', $field, $default === false ? 0x11110 : (int)$default, ['id' => $cm->instance]);
    }
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = \context_module::instance((int)$cm->coursemodule);
    $category = question_make_default_categories([$context]);
    $added = smg_seed_import_questions(
        $CFG->dirroot . '/local/stackmathgame/tests/fixtures/e2e_verified_questions.xml',
        $category,
        $course,
        $quiz,
        $context
    );
    \mod_quiz\quiz_settings::create((int)$quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

    \local_stackmathgame\game\theme_manager::seed_default_theme();
    $config = \local_stackmathgame\game\quiz_configurator::ensure_default((int)$cm->coursemodule);
    $config->enabled = 1;
    $config->designid = (int)$DB->get_field('local_stackmathgame_design', 'id', ['slug' => 'rpg_default'], MUST_EXIST);
    $DB->update_record('local_stackmathgame', $config);
    \local_stackmathgame\local\service\question_map_service::ensure_for_cmid((int)$cm->coursemodule);
    $blockers = \local_stackmathgame\local\service\prerequisite_checker::get_blockers((int)$cm->coursemodule);

    smg_e2e_out([
        'username' => $username,
        'password' => $password,
        'courseid' => (int)$course->id,
        'cmid' => (int)$cm->coursemodule,
        'quizid' => (int)$quiz->id,
        'quizname' => (string)$quiz->name,
        'questions' => $added,
        'blockers' => array_column($blockers, 'message'),
    ]);
}

if ($command === 'state') {
    $user = $DB->get_record('user', ['username' => $argv[2] ?? ''], '*', MUST_EXIST);
    $cm = get_coursemodule_from_id('quiz', (int)($argv[3] ?? 0), 0, false, MUST_EXIST);
    $attempts = $DB->get_records('quiz_attempts', ['quiz' => $cm->instance, 'userid' => $user->id], 'attempt DESC', '*', 0, 1);
    if (!$attempts) {
        smg_e2e_out(['attempt' => null]);
    }
    $attempt = reset($attempts);
    $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
    $slots = [];
    foreach ($quba->get_slots() as $slot) {
        $qa = $quba->get_question_attempt($slot);
        $steps = [];
        foreach ($qa->get_step_iterator() as $step) {
            $data = [];
            foreach ($step->get_all_data() as $name => $value) {
                // Answers and the behaviour's actions; the engine's own bookkeeping (_order,
                // -_rawfraction ...) is left out to keep the report readable.
                if ($name === '-submit' || $name === 'ans1' || $name === 'ans1_val') {
                    $data[$name] = (string)$value;
                }
            }
            $steps[] = ['state' => (string)$step->get_state(), 'fraction' => $step->get_fraction(), 'data' => $data];
        }
        $vars = [];
        foreach ($qa->get_last_qt_data() as $name => $value) {
            $vars[$name] = (string)$value;
        }
        $slots[$slot] = [
            'name' => $qa->get_question()->name,
            'state' => (string)$qa->get_state(),
            'fraction' => $qa->get_fraction(),
            'mark' => $qa->get_mark(),
            'summary' => $qa->get_response_summary(),
            'vars' => $vars,
            'steps' => $steps,
        ];
    }
    $profile = $DB->get_record('local_stackmathgame_profile', ['userid' => $user->id], 'id, progressjson, score', IGNORE_MULTIPLE);
    smg_e2e_out([
        'attempt' => (int)$attempt->id,
        'state' => $attempt->state,
        'sumgrades' => $attempt->sumgrades === null ? null : (float)$attempt->sumgrades,
        'slots' => $slots,
        'profile' => $profile ? [
            'score' => (int)$profile->score,
            'progress' => json_decode((string)$profile->progressjson, true),
        ] : null,
    ]);
}

fwrite(STDERR, "usage: php smg-e2e.php seed | editor on|off | state <username> <cmid>\n");
exit(1);
