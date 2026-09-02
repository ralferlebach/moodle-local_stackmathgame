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
 * External function: get_profile_state.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

/**
 * Return the current label-bound profile state for a quiz.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_profile_state extends \core_external\external_api {
    /**
     * Describe input parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'quizid' => new \core_external\external_value(PARAM_INT, 'Quiz id'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $quizid The quiz instance ID.
     * @return array The profile state array.
     */
    public static function execute(int $quizid): array {
        $activity = api::resolve_activity_identity(0, 'quiz', $quizid, $quizid);
        $result = get_activity_profile_state::execute(
            (int)$activity['cmid'],
            (string)$activity['modname'],
            (int)$activity['instanceid']
        );

        return [
            'quizid' => (int)$result['quizid'],
            'labelid' => (int)$result['labelid'],
            'designid' => (int)$result['designid'],
            'profile' => (array)$result['profile'],
            'design' => (array)$result['design'],
        ];
    }

    /**
     * Describe return values.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'quizid'   => new \core_external\external_value(PARAM_INT, 'Quiz id'),
            'labelid'  => new \core_external\external_value(PARAM_INT, 'Label id'),
            'designid' => new \core_external\external_value(PARAM_INT, 'Design id'),
            'profile'  => get_quiz_config::profile_structure(),
            'design'   => get_quiz_config::design_structure(),
        ]);
    }
}
