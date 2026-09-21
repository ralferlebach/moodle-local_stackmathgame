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
 * CI helper: create the participant, the course and the enrolment for the E2E journey.
 *
 * These three used to be driven through the interface. They are site administration, not game
 * building, and they were the most fragile part of the journey: every run created another
 * "Pat Player", the participant search then matched an older one, and the enrolment dialog
 * reported success for the wrong person. Doing it here removes that whole class of failure and
 * lets the browser spend its time on what the test is for - the quiz, the game settings and the
 * play-through.
 *
 * Everything is unique per run, so the script can be run repeatedly against the same site.
 *
 * Prints "export KEY='value'" lines for the caller to evaluate. Nothing else goes to stdout.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/stack-cas-lib.php');
$moodleroot = local_stackmathgame_moodle_root();
require_once($moodleroot . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

global $CFG, $DB;

$CFG->noemailever = true;
\core\session\manager::set_user(get_admin());

$stamp = (string)time() . random_int(100, 999);
$username = 'smgplayer' . $stamp;
$password = getenv('SMG_PLAYER_PASS') ?: 'Smg-Play-Pass!1';

// A fixed test password is worth more here than policy compliance on a throwaway site.
set_config('passwordpolicy', 0);

$userid = user_create_user((object) [
    'username'   => $username,
    'password'   => $password,
    'firstname'  => 'Pat',
    'lastname'   => 'Player',
    'email'      => $username . '@example.invalid',
    'confirmed'  => 1,
    'mnethostid' => $CFG->mnet_localhost_id,
], true, false);

$course = create_course((object) [
    'fullname'  => 'Math adventure ' . $stamp,
    'shortname' => 'SMGRPG' . $stamp,
    'category'  => \core_course_category::get_default()->id,
    'visible'   => 1,
]);

$studentrole = (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
if (!enrol_try_internal_enrol((int)$course->id, (int)$userid, $studentrole)) {
    fwrite(STDERR, "ERROR: could not enrol $username into course {$course->id}.\n");
    exit(1);
}

// Verified rather than assumed: a silent enrolment failure was the most expensive bug this
// journey ever had, because it surfaced two steps later as "no way to start an attempt".
if (!is_enrolled(\context_course::instance((int)$course->id), (int)$userid)) {
    fwrite(STDERR, "ERROR: $username is not enrolled after enrolment.\n");
    exit(1);
}

echo "export SMG_COURSE_ID='" . (int)$course->id . "'\n";
echo "export SMG_PLAYER_USER='" . $username . "'\n";
echo "export SMG_PLAYER_PASS='" . $password . "'\n";
