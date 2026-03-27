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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Return the current label-bound profile state for a quiz.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_profile_state extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $quizid The quiz instance ID.
     * @return array The profile state array.
     */
    public static function execute(int $quizid): array {
        [, , $config, $profile, $design] = api::validate_quiz_access($quizid);
        return [
            'quizid'   => $quizid,
            'labelid'  => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'profile'  => api::export_profile($profile),
            'design'   => api::export_design($design),
        ];
    }

    /**
     * Describe return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid'   => new \external_value(PARAM_INT, 'Quiz id'),
            'labelid'  => new \external_value(PARAM_INT, 'Label id'),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
            'profile'  => get_quiz_config::profile_structure(),
            'design'   => get_quiz_config::design_structure(),
        ]);
    }
}
