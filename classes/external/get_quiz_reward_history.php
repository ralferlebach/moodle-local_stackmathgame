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
 * External function: get_quiz_reward_history.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Legacy quiz wrapper for activity-based reward history.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_quiz_reward_history extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'limit' => new \external_value(PARAM_INT, 'Maximum number of history rows', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $quizid The quiz instance ID.
     * @param int $limit Maximum number of rows.
     * @return array The reward-history export.
     */
    public static function execute(int $quizid, int $limit = 25): array {
        $activity = api::resolve_activity_identity(0, 'quiz', $quizid, $quizid);
        $result = get_activity_reward_history::execute(
            (int)$activity['cmid'],
            (string)$activity['modname'],
            (int)$activity['instanceid'],
            $limit
        );

        return [
            'quizid' => (int)$result['quizid'],
            'labelid' => (int)$result['labelid'],
            'designid' => (int)$result['designid'],
            'history' => (array)$result['history'],
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
            'labelid' => new \external_value(PARAM_INT, 'Label id'),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
            'history' => new \external_multiple_structure(api::reward_history_structure()),
        ]);
    }
}
