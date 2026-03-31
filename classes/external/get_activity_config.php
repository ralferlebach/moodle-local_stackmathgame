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
 * External function: get_activity_config.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Return the active STACK Math Game configuration for an activity.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_activity_config extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course-module id'),
            'modname' => new \external_value(PARAM_PLUGIN, 'Activity module name', VALUE_DEFAULT, 'quiz'),
            'instanceid' => new \external_value(PARAM_INT, 'Activity instance id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid The course-module ID.
     * @param string $modname The activity module name.
     * @param int $instanceid The activity instance ID.
     * @return array The configuration export.
     */
    public static function execute(int $cmid, string $modname = 'quiz', int $instanceid = 0): array {
        [$cm, , $config, $profile, $design, $activity] = api::validate_activity_access($cmid, $modname, $instanceid);

        $stashmappings = api::export_activity_stash_mappings($activity, (int)$cm->course);
        $questionmap = [];
        if (api::activity_supports_question_flow($activity)) {
            $questionmap = api::get_question_map((int)$cm->id, (int)$activity['quizid']);
        }

        return array_merge(api::export_activity($activity), [
            'enabled' => !empty($config->enabled),
            'requiresbehaviour' => !empty($config->requiresbehaviour),
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'teacherdisplayname' => (string)($config->teacherdisplayname ?? ''),
            'configjson' => (string)($config->configjson ?? '{}'),
            'design' => api::export_design($design),
            'profile' => api::export_profile($profile),
            'questionmap' => $questionmap,
            'stashmappings' => $stashmappings,
        ]);
    }

    /**
     * Describe return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'cmid' => new \external_value(PARAM_INT, 'Course-module id'),
            'modname' => new \external_value(PARAM_PLUGIN, 'Activity module name'),
            'instanceid' => new \external_value(PARAM_INT, 'Activity instance id'),
            'quizid' => new \external_value(PARAM_INT, 'Legacy quiz id when applicable'),
            'enabled' => new \external_value(PARAM_BOOL, 'Enabled'),
            'requiresbehaviour' => new \external_value(PARAM_BOOL, 'Requires custom behaviour'),
            'labelid' => new \external_value(PARAM_INT, 'Label id'),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
            'teacherdisplayname' => new \external_value(PARAM_TEXT, 'Teacher-facing display name'),
            'configjson' => new \external_value(PARAM_RAW, 'Raw config JSON'),
            'design' => get_quiz_config::design_structure(),
            'profile' => get_quiz_config::profile_structure(),
            'questionmap' => new \external_multiple_structure(get_quiz_config::questionmap_structure()),
            'stashmappings' => new \external_multiple_structure(api::stash_mapping_structure()),
        ]);
    }
}
