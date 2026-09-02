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
 * CLI seed for the local_stackmathgame load tests (k6 and JMeter).
 *
 * Creates a course, a quiz using the stackmathgame question behaviour with one question per
 * page, enables the game on it, builds the question map and mints a REST web-service token
 * for an enrolled student. Prints shell "export" lines for the load plans.
 *
 * The endpoints under test are web services rather than pages, so a token is unavoidable:
 * the plans must exercise the same calls game_engine.js makes, and those all go through
 * /webservice/rest/server.php.
 *
 * Disposable dev/staging sites only - this enables web services and mints a token.
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
require_once($CFG->dirroot . '/question/engine/bank.php');

$slotcount = isset($argv[1]) ? max(1, (int)$argv[1]) : 8;
$now = time();

// Course.
$coursecategory = \core_course_category::get_default();
$course = create_course((object) [
    'fullname'  => 'STACK Math Game load ' . $now,
    'shortname' => uniqid('SMGLOAD', true),
    'category'  => $coursecategory->id,
    'visible'   => 1,
]);

// Quiz. preferredbehaviour must be stackmathgame: the runtime refuses to start with any
// other behaviour (issue #3), so a load run against the wrong one would measure the refusal
// path instead of the game.
$module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
$cm = create_module((object) [
    'course'             => $course->id,
    'module'             => $module->id,
    'modulename'         => 'quiz',
    'section'            => 0,
    'visible'            => 1,
    'cmidnumber'         => '',
    'name'               => 'Load quiz ' . $now,
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

// Questions. Real STACK questions need a Maxima installation, which a load environment may
// not have. The endpoints measured here read configuration and profile state; they do not
// evaluate answers, so plain questions are sufficient. See README.md for the STACK variant.
$qcategory = question_make_default_categories([\context_module::instance($cmid)]);
for ($i = 1; $i <= $slotcount; $i++) {
    $questiondata = (object) [
        'category'              => $qcategory->id,
        'parent'                => 0,
        'name'                  => 'Load question ' . $i,
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

// Game configuration and question map.
\local_stackmathgame\game\theme_manager::seed_default_theme();
$config = \local_stackmathgame\game\quiz_configurator::ensure_default($cmid);
$config->enabled = 1;
$DB->update_record('local_stackmathgame', $config);
$mapsummary = \local_stackmathgame\local\service\question_map_service::ensure_for_cmid($cmid);

// Student plus REST token: the load plans call the same web services the browser runtime
// calls, so they need a real, enrolled user rather than an anonymous session.
$username = 'smgload' . $now;
$password = 'Smg-Load-Pass!1';
$student = \core_user::get_user(user_create_user((object) [
    'username'   => $username,
    'password'   => $password,
    'firstname'  => 'Load',
    'lastname'   => 'Player',
    'email'      => $username . '@example.invalid',
    'confirmed'  => 1,
    'mnethostid' => $CFG->mnet_localhost_id,
], true, false));

$studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
enrol_try_internal_enrol($course->id, $student->id, $studentroleid);

set_config('enablewebservices', 1);
$protocols = (string)get_config('core', 'webserviceprotocols');
if (strpos($protocols, 'rest') === false) {
    set_config('webserviceprotocols', trim($protocols . ',rest', ','));
}
$service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
if (!$service) {
    $service = $DB->get_record('external_services', ['enabled' => 1], '*', IGNORE_MULTIPLE);
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
echo "export SLOTS='" . (int)$mapsummary['slots'] . "'\n";
echo "export TOKEN='" . $token . "'\n";
echo "export SMG_LOAD_USER='" . $username . "'\n";
echo "export SMG_LOAD_PASS='" . $password . "'\n";
