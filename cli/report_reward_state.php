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
 * Report reward-state data for an activity during Phase III migration.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'cmid' => null,
    'quizid' => null,
    'userid' => null,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognized));
}

if (!empty($options['help']) || (empty($options['cmid']) && empty($options['quizid']))) {
    $help = "Report STACK Math Game reward/inventory state for one activity.

"
        . "Options:
"
        . "--cmid=INT      Course-module id (preferred).
"
        . "--quizid=INT    Legacy quiz id fallback.
"
        . "--userid=INT    User id for inventory/profile lookup (defaults to admin).
"
        . "-h, --help      Show this help.
";
    cli_writeln($help);
    exit(0);
}

$userid = !empty($options['userid']) ? (int)$options['userid'] : 2;
$cmid = !empty($options['cmid']) ? (int)$options['cmid'] : 0;
$quizid = !empty($options['quizid']) ? (int)$options['quizid'] : 0;

$activity = \local_stackmathgame\externalpi::resolve_activity_identity($cmid, 'quiz', 0, $quizid);
$cm = get_coursemodule_from_id((string)$activity['modname'], (int)$activity['cmid'], 0, false, MUST_EXIST);
$stashmappings = \local_stackmathgame\local\service\stash_mapping_service::get_for_activity(
    (int)$activity['cmid'],
    (int)$cm->course,
    (string)$activity['modname'],
    (int)$activity['instanceid']
);
$profile = \local_stackmathgame\local\service\profile_service::get_or_create_for_activity(
    $userid,
    (int)$activity['cmid'],
    (string)$activity['modname'],
    (int)$activity['instanceid']
);
$inventory = \local_stackmathgame\local\service\inventory_service::get_for_profile((int)$profile->id);
$summary = \local_stackmathgame\local\service\inventory_service::get_summary_for_profile((int)$profile->id);

cli_writeln('Reward-state report');
cli_writeln('cmid: ' . (int)$activity['cmid']);
cli_writeln('modname: ' . (string)$activity['modname']);
cli_writeln('instanceid: ' . (int)$activity['instanceid']);
cli_writeln('quizid: ' . (int)$activity['quizid']);
cli_writeln('userid: ' . $userid);
cli_writeln('profileid: ' . (int)$profile->id);
cli_writeln('stashmappings: ' . count($stashmappings));
cli_writeln('inventory items: ' . (int)$summary['itemcount']);
cli_writeln('inventory totalquantity: ' . (int)$summary['totalquantity']);

foreach ($inventory as $itemkey => $row) {
    cli_writeln(' - ' . (string)$itemkey . ': ' . (int)$row->quantity);
}
