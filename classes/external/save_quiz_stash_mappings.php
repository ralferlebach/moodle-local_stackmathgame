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
 * External function: save_quiz_stash_mappings.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Legacy quiz wrapper for activity-based stash mapping writes.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_quiz_stash_mappings extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'mappings' => new \external_multiple_structure(api::stash_mapping_input_structure()),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $quizid The quiz instance ID.
     * @param array $mappings Submitted mapping rows.
     * @return array The stash mapping export.
     */
    public static function execute(int $quizid, array $mappings = []): array {
        $activity = api::resolve_activity_identity(0, 'quiz', $quizid, $quizid);
        $result = save_activity_stash_mappings::execute(
            (int)$activity['cmid'],
            (string)$activity['modname'],
            (int)$activity['instanceid'],
            $mappings
        );

        return [
            'quizid' => (int)$result['quizid'],
            'stashmappings' => (array)$result['stashmappings'],
        ];
    }

    /**
     * Describe return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'stashmappings' => new \external_multiple_structure(api::stash_mapping_structure()),
        ]);
    }
}
