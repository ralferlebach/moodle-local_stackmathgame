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
 * Backfill local_stackmathgame stash mappings from legacy quiz IDs to cmid.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([], []);
if ($unrecognized) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognized));
}

$summary = \local_stackmathgame\local\service\stash_mapping_service::backfill_activity_rows();
cli_writeln('Stash-map backfill complete.');
cli_writeln('Rows scanned: ' . (int)$summary['rows']);
cli_writeln('Rows updated: ' . (int)$summary['updated']);
