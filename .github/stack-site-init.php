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
 * CI helper: initialise the STACK CAS for an installed site.
 *
 * The counterpart of stack-behat-init.php and stack-phpunit-init.php for the load and browser
 * workflows, which install a real site rather than a test environment.
 *
 * Run from the workspace root, above the Moodle directory:
 *   php "$GITHUB_WORKSPACE/plugin/.github/stack-site-init.php"
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/stack-cas-lib.php');
$moodleroot = local_stackmathgame_moodle_root();
require_once($moodleroot . '/config.php');

global $CFG, $DB;

if (!$DB->get_manager()->table_exists('config')) {
    fwrite(STDERR, "ERROR: {config} not found - run this after the site has been installed.\n");
    exit(1);
}

exit(local_stackmathgame_init_stack_cas($CFG, 'site'));
