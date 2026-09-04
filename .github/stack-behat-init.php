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
 * CI helper: initialise the STACK CAS for the Behat test site.
 *
 * Run from the Moodle root after moodle-plugin-ci install:
 *   working-directory: moodle
 *   run: php "$GITHUB_WORKSPACE/plugin/.github/stack-behat-init.php"
 *
 * Modelled on the equivalent helper in local_stackmatheditor, which solved the
 * same problem there.
 *
 * Why a script and not `admin/cli/cfg.php`: moodle-plugin-ci installs the test
 * databases (bht_ and phpu_ prefixes) but never the main site, so cfg.php dies
 * with "Table config does not exist". BEHAT_UTIL switches the whole request to
 * the Behat database, which is where the Behat run will look for these values.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('BEHAT_UTIL', true);
define('CLI_SCRIPT', true);

require_once(__DIR__ . '/stack-cas-lib.php');
require_once(local_stackmathgame_moodle_root() . '/config.php');

global $CFG, $DB;

if (!$DB->get_manager()->table_exists('config')) {
    fwrite(STDERR, "ERROR: {config} not found in the Behat database.\n");
    fwrite(STDERR, "Run this after moodle-plugin-ci install.\n");
    exit(1);
}

exit(local_stackmathgame_init_stack_cas($CFG, 'Behat'));
