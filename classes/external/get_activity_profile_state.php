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
 * External function: get_activity_profile_state.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../lib/externallib.php');

/**
 * Return the current label-bound profile state for an activity.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_activity_profile_state extends \core_external\external_api {
    /**
     * Describe input parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'cmid' => new \core_external\external_value(PARAM_INT, 'Course-module id'),
            'modname' => new \core_external\external_value(PARAM_PLUGIN, 'Activity module name', VALUE_DEFAULT, 'quiz'),
            'instanceid' => new \core_external\external_value(PARAM_INT, 'Activity instance id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid The course-module ID.
     * @param string $modname The activity module name.
     * @param int $instanceid The activity instance ID.
     * @return array The profile state array.
     */
    public static function execute(int $cmid, string $modname = 'quiz', int $instanceid = 0): array {
        [, , $config, $profile, $design, $activity] = api::validate_activity_access($cmid, $modname, $instanceid);

        return array_merge(api::export_activity($activity), [
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'profile' => api::export_profile($profile),
            'design' => api::export_design($design),
        ]);
    }

    /**
     * Describe return values.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'cmid' => new \core_external\external_value(PARAM_INT, 'Course-module id'),
            'modname' => new \core_external\external_value(PARAM_PLUGIN, 'Activity module name'),
            'instanceid' => new \core_external\external_value(PARAM_INT, 'Activity instance id'),
            'quizid' => new \core_external\external_value(PARAM_INT, 'Legacy quiz id when applicable'),
            'labelid' => new \core_external\external_value(PARAM_INT, 'Label id'),
            'designid' => new \core_external\external_value(PARAM_INT, 'Design id'),
            'profile' => get_quiz_config::profile_structure(),
            'design' => get_quiz_config::design_structure(),
        ]);
    }
}
