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
 * External function: save_activity_progress.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use local_stackmathgame\local\service\profile_service;

/**
 * Persist game progress deltas for the current activity profile.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_activity_progress extends \external_api {
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
            'scoredelta' => new \external_value(PARAM_INT, 'Score delta', VALUE_DEFAULT, 0),
            'xpdelta' => new \external_value(PARAM_INT, 'XP delta', VALUE_DEFAULT, 0),
            'softcurrencydelta' => new \external_value(PARAM_INT, 'Soft currency delta', VALUE_DEFAULT, 0),
            'hardcurrencydelta' => new \external_value(PARAM_INT, 'Hard currency delta', VALUE_DEFAULT, 0),
            'progressjson' => new \external_value(PARAM_RAW, 'Progress patch as JSON', VALUE_DEFAULT, '{}'),
            'flagsjson' => new \external_value(PARAM_RAW, 'Flags patch as JSON', VALUE_DEFAULT, '{}'),
            'statsjson' => new \external_value(PARAM_RAW, 'Stats patch as JSON', VALUE_DEFAULT, '{}'),
            'eventtype' => new \external_value(
                PARAM_ALPHANUMEXT,
                'Logged event type',
                VALUE_DEFAULT,
                'progress_saved'
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid The course-module ID.
     * @param string $modname The activity module name.
     * @param int $instanceid The activity instance ID.
     * @param int $scoredelta Score delta.
     * @param int $xpdelta XP delta.
     * @param int $softcurrencydelta Soft currency delta.
     * @param int $hardcurrencydelta Hard currency delta.
     * @param string $progressjson Progress patch JSON.
     * @param string $flagsjson Flags patch JSON.
     * @param string $statsjson Stats patch JSON.
     * @param string $eventtype Event type string.
     * @return array The updated profile state.
     */
    public static function execute(
        int $cmid,
        string $modname = 'quiz',
        int $instanceid = 0,
        int $scoredelta = 0,
        int $xpdelta = 0,
        int $softcurrencydelta = 0,
        int $hardcurrencydelta = 0,
        string $progressjson = '{}',
        string $flagsjson = '{}',
        string $statsjson = '{}',
        string $eventtype = 'progress_saved'
    ): array {
        require_sesskey();

        [, , $config, $profile, $design, $activity] = api::validate_activity_access($cmid, $modname, $instanceid);

        $changes = [
            'designid' => (int)$config->designid,
            'scoredelta' => $scoredelta,
            'xpdelta' => $xpdelta,
            'softcurrencydelta' => $softcurrencydelta,
            'hardcurrencydelta' => $hardcurrencydelta,
            'progress' => json_decode($progressjson, true) ?: [],
            'flags' => json_decode($flagsjson, true) ?: [],
            'stats' => json_decode($statsjson, true) ?: [],
        ];
        if (!empty($activity['quizid'])) {
            $changes['quizid'] = (int)$activity['quizid'];
        }

        $updated = profile_service::apply_progress((int)$profile->id, $changes);
        api::log_event(
            $updated,
            (int)$activity['quizid'],
            (int)$config->designid,
            $eventtype,
            'external.save_activity_progress',
            [
                'cmid' => (int)$activity['cmid'],
                'modname' => (string)$activity['modname'],
                'instanceid' => (int)$activity['instanceid'],
                'progress' => json_decode($progressjson, true) ?: [],
                'flags' => json_decode($flagsjson, true) ?: [],
                'stats' => json_decode($statsjson, true) ?: [],
            ],
            $scoredelta + $xpdelta
        );

        return array_merge(api::export_activity($activity), [
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'profile' => api::export_profile($updated),
            'design' => api::export_design($design),
            'eventtype' => $eventtype,
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
            'labelid' => new \external_value(PARAM_INT, 'Label id'),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
            'profile' => get_quiz_config::profile_structure(),
            'design' => get_quiz_config::design_structure(),
            'eventtype' => new \external_value(PARAM_ALPHANUMEXT, 'Logged event type'),
        ]);
    }
}
