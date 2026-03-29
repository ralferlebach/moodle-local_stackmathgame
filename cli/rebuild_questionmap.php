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
 * Rebuild question-map rows from quiz slots.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$help = "Rebuild local_stackmathgame question-map rows from quiz slots.\n\n"
    . "Options:\n"
    . "--all            Rebuild all configured activities (default).\n"
    . "--cmid=ID[,ID]   Rebuild one or more specific course-module IDs.\n"
    . "-h, --help       Print out this help.\n";

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'all' => false,
        'cmid' => null,
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognised));
}

if (!empty($options['help'])) {
    cli_writeln($help);
    exit(0);
}

$cmids = [];
if (!empty($options['cmid'])) {
    $parts = preg_split('/[,;\s]+/', (string)$options['cmid']);
    foreach ($parts as $part) {
        $cmid = (int)$part;
        if ($cmid > 0) {
            $cmids[] = $cmid;
        }
    }
}

if (empty($options['all']) && empty($cmids)) {
    $options['all'] = true;
}

if (!empty($options['all'])) {
    $summary = \local_stackmathgame\local\service\question_map_service::rebuild_all();
} else {
    $summary = \local_stackmathgame\local\service\question_map_service::rebuild_all($cmids);
}

cli_writeln('Question-map rebuild complete.');
cli_writeln('Activities: ' . (int)$summary['activities']);
cli_writeln('Slots: ' . (int)$summary['slots']);
cli_writeln('Created: ' . (int)$summary['created']);
cli_writeln('Updated: ' . (int)$summary['updated']);
cli_writeln('Deleted: ' . (int)$summary['deleted']);
cli_writeln('Backfilled: ' . (int)$summary['backfilled']);

foreach ($summary['results'] as $result) {
    cli_writeln(
        sprintf(
            '  cmid=%d quizid=%d slots=%d created=%d updated=%d deleted=%d backfilled=%d',
            (int)$result['cmid'],
            (int)$result['quizid'],
            (int)$result['slots'],
            (int)$result['created'],
            (int)$result['updated'],
            (int)$result['deleted'],
            (int)$result['backfilled']
        )
    );
}
