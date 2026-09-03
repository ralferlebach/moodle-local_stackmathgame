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
 * CI helper: initialise the STACK CAS for the PHPUnit test environment.
 *
 * Run from the Moodle root after moodle-plugin-ci install:
 *   working-directory: moodle
 *   run: php "$GITHUB_WORKSPACE/plugin/.github/stack-phpunit-init.php"
 *
 * The PHPUnit counterpart of stack-behat-init.php. It matters for a reason
 * beyond configuration: STACK writes maximalocal.mac into $CFG->dataroot, and
 * the PHPUnit dataroot never receives one from the installer. Without this the
 * STACK end-to-end tests skip themselves, correctly but permanently.
 *
 * PHPUNIT_UTIL plus lib/phpunit/bootstrap.php is how Moodle's own
 * admin/tool/phpunit/cli/util.php enters the test database; reproducing that is
 * what makes set_config() land in the phpu_ prefix rather than the main site.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (isset($_SERVER['REMOTE_ADDR'])) {
    die; // No access from web.
}

define('IGNORE_COMPONENT_CACHE', true);
putenv('BEHAT_CLI=0');

define('PHPUNIT_UTIL', true);

// The bootstrap uses PHPUnit's own classes, so Composer's autoloader has to be in place first -
// exactly the order admin/tool/phpunit/cli/util.php uses.
require(getcwd() . '/vendor/autoload.php');
require(getcwd() . '/lib/phpunit/bootstrap.php');
require_once(__DIR__ . '/stack-cas-lib.php');

global $CFG, $DB;

if (!$DB->get_manager()->table_exists('config')) {
    fwrite(STDERR, "ERROR: {config} not found in the PHPUnit database.\n");
    fwrite(STDERR, "Run this after the PHPUnit environment has been initialised.\n");
    exit(1);
}

exit(local_stackmathgame_init_stack_cas($CFG, 'PHPUnit'));
