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
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../fixtures/seedlib.php');

$slotcount = isset($argv[1]) ? max(1, (int)$argv[1]) : 8;
$now = time();

// The seed prints "export KEY='value'" lines that the caller evaluates, so anything else on
// stdout is noise at best. Welcome messages to @example.invalid addresses produce a debugging
// backtrace; the question importer prints a progress report.
$CFG->noemailever = true;
$CFG->debugdisplay = 0;
$CFG->debug = 0;
// Creating a module checks moodle/course:manageactivities in the course context, so this
// script needs an identity. A CLI script has none by default, and the failure surfaces as
// "Sorry, but you do not currently have permissions to do that" from four calls deep.
\core\session\manager::set_user(get_admin());


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
$cm = smg_seed_create_quiz($course, 'Load quiz ' . $now);
$cmid = (int)($cm->coursemodule ?? $cm->cmid ?? 0);
$quizid = (int)$cm->instance;
$quizrecord = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

// Questions. Real STACK questions need a Maxima installation, which a load environment may
// not have. The endpoints measured here read configuration and profile state; they do not
// evaluate answers, so plain questions are sufficient. See README.md for the STACK variant.
$qcategory = question_make_default_categories([\context_module::instance($cmid)]);
$added = smg_seed_import_questions(
    __DIR__ . '/../fixtures/fallback_questions.xml',
    $qcategory,
    $course,
    $quizrecord,
    \context_module::instance($cmid)
);
if ($added === 0) {
    cli_error('No questions could be imported - check tests/fixtures/.');
}
\mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->recompute_quiz_sumgrades();

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
// The plugin's own service, not the mobile one: a token issued against a service that does not
// contain these functions is refused on every call with an access exception, which in a load run
// looks like the endpoints failing rather than the token being wrong.
$service = $DB->get_record('external_services', ['shortname' => 'local_stackmathgame'], '*', MUST_EXIST);
if (empty($service->enabled)) {
    $DB->set_field('external_services', 'enabled', 1, ['id' => $service->id]);
    $service->enabled = 1;
}
// Enabling the protocol is not enough: the user also needs webservice/rest:use, and without it
// every call is refused with "Access control exception ... missing capability: webservice/rest:use"
// - which in a load report looks like the endpoints failing rather than the fixture being
// incomplete. Granted on the authenticated user role, as a site would for a service account.
$authenticateduser = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
role_change_permission(
    $authenticateduser,
    \context_system::instance(),
    'webservice/rest:use',
    CAP_ALLOW
);

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
