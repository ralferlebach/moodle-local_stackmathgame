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
 * CLI seed for the reward exclusivity race (tests/load/stackmathgame-capacity-race.js).
 *
 * Builds exactly the fixture that race needs and nothing else: a playable quiz, a slot whose
 * direction card awards a non-zero reward, and an attempt that is already open so the plan's
 * virtual users can all post into the same one.
 *
 * The reward matters. With the schema default of 0 the race passes trivially - a gate that can
 * only succeed proves nothing, and this is the one gate for a guarantee PHPUnit cannot express:
 * a single PHP process reads and writes the profile in one database session and always sees its
 * own earlier write, so only genuinely parallel requests can interleave between "already solved?"
 * and the XP increment.
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
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../fixtures/seedlib.php');

$rewardxp = (int)(getenv('REWARD_XP') ?: 10);
$rewardscore = (int)(getenv('REWARD_SCORE') ?: 25);
$now = time();

\core\session\manager::set_user(get_admin());
$CFG->noemailever = true;
$CFG->debugdisplay = 0;
$CFG->debug = 0;

$coursecategory = \core_course_category::get_default();
$course = create_course((object) [
    'fullname'  => 'STACK Math Game race ' . $now,
    'shortname' => uniqid('SMGRACE', true),
    'category'  => $coursecategory->id,
    'visible'   => 1,
]);

$cm = smg_seed_create_quiz($course, 'Race quiz ' . $now);
$cmid = (int)($cm->coursemodule ?? $cm->cmid ?? 0);
$quizid = (int)$cm->instance;
$quizrecord = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$context = \context_module::instance($cmid);

// STACK questions, because the game behaviour only accepts multi-part gradable ones.
$qcategory = question_make_default_categories([$context]);
$added = smg_seed_import_questions(
    __DIR__ . '/../fixtures/stack_playwright.xml',
    $qcategory,
    $course,
    $quizrecord,
    $context
);
if ($added === 0) {
    cli_error('No questions could be imported - check tests/fixtures/.');
}
\mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->recompute_quiz_sumgrades();

\local_stackmathgame\game\theme_manager::seed_default_theme();
$config = \local_stackmathgame\game\quiz_configurator::ensure_default($cmid);
$config->enabled = 1;
$DB->update_record('local_stackmathgame', $config);
\local_stackmathgame\local\service\question_map_service::ensure_for_cmid($cmid);

// The reward the race asserts against. Written through flow_service so it goes into configjson
// the same way the flow editor writes it - a hand-built row would test a shape nothing else uses.
$slot = 1;
$slotconfig = \local_stackmathgame\local\service\flow_service::get_slot_config($cmid, $slot);
$slotconfig['rewards']['xp'] = $rewardxp;
$slotconfig['rewards']['score'] = $rewardscore;
$errors = \local_stackmathgame\local\service\flow_service::save_slot_config($cmid, $slot, $slotconfig);
if ($errors) {
    cli_error('Could not configure the reward: ' . implode(' ', $errors));
}

$username = 'smgrace' . $now;
$password = 'Smg-Race-Pass!1';
$student = \core_user::get_user(user_create_user((object) [
    'username'   => $username,
    'password'   => $password,
    'firstname'  => 'Race',
    'lastname'   => 'Player',
    'email'      => $username . '@example.invalid',
    'confirmed'  => 1,
    'mnethostid' => $CFG->mnet_localhost_id,
], true, false));
enrol_try_internal_enrol(
    $course->id,
    $student->id,
    $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST)
);

// An attempt has to exist and be open: the race posts into one attempt from many connections at
// once, which is the whole point.
$quizobj = \mod_quiz\quiz_settings::create($quizid, $student->id);
$quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
$quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
$attempt = quiz_create_attempt($quizobj, 1, false, $now, false, $student->id);
quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $now);
quiz_attempt_save_started($quizobj, $quba, $attempt);
$attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
$qa = $attemptobj->get_question_attempt($slot);

set_config('enablewebservices', 1);
$protocols = (string)get_config('core', 'webserviceprotocols');
if (strpos($protocols, 'rest') === false) {
    set_config('webserviceprotocols', trim($protocols . ',rest', ','));
}
role_change_permission(
    $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST),
    \context_system::instance(),
    'webservice/rest:use',
    CAP_ALLOW
);
$service = $DB->get_record('external_services', ['shortname' => 'local_stackmathgame'], '*', MUST_EXIST);
if (empty($service->enabled)) {
    $DB->set_field('external_services', 'enabled', 1, ['id' => $service->id]);
}
$token = external_generate_token(
    EXTERNAL_TOKEN_PERMANENT,
    $service,
    $student->id,
    \context_system::instance()
);

echo "export BASE_URL='" . $CFG->wwwroot . "'\n";
echo "export COURSEID='" . $course->id . "'\n";
echo "export CMID='" . $cmid . "'\n";
echo "export QUIZID='" . $quizid . "'\n";
echo "export ATTEMPTID='" . (int)$attempt->id . "'\n";
echo "export SLOT='" . $slot . "'\n";
// The field names the race has to post, so the plan does not have to guess the prefix.
echo "export ANSWERFIELD='" . $qa->get_qt_field_name('ans1') . "'\n";
echo "export VALFIELD='" . $qa->get_qt_field_name('ans1_val') . "'\n";
echo "export SUBMITFIELD='" . $qa->get_behaviour_field_name('submit') . "'\n";
echo "export SEQFIELD='" . $qa->get_control_field_name('sequencecheck') . "'\n";
echo "export EXPECTED_XP='" . $rewardxp . "'\n";
echo "export TOKEN='" . $token . "'\n";
