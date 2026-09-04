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

use local_stackmathgame\local\service\profile_service;

/**
 * Persist game progress deltas for the current activity profile.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_activity_progress extends \core_external\external_api {
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
            'scoredelta' => new \core_external\external_value(PARAM_INT, 'Score delta', VALUE_DEFAULT, 0),
            'xpdelta' => new \core_external\external_value(PARAM_INT, 'XP delta', VALUE_DEFAULT, 0),
            'softcurrencydelta' => new \core_external\external_value(PARAM_INT, 'Soft currency delta', VALUE_DEFAULT, 0),
            'hardcurrencydelta' => new \core_external\external_value(PARAM_INT, 'Hard currency delta', VALUE_DEFAULT, 0),
            'progressjson' => new \core_external\external_value(PARAM_RAW, 'Progress patch as JSON', VALUE_DEFAULT, '{}'),
            'flagsjson' => new \core_external\external_value(PARAM_RAW, 'Flags patch as JSON', VALUE_DEFAULT, '{}'),
            'statsjson' => new \core_external\external_value(PARAM_RAW, 'Stats patch as JSON', VALUE_DEFAULT, '{}'),
            'eventtype' => new \core_external\external_value(
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
        // The session key protects browser-originated POSTs against cross-site request forgery.
        // A web-service call authenticates with a token and carries no cookie session, so there
        // is nothing to forge against - and requiring it there makes the endpoint unusable over
        // REST, which is how the load plans found this.
        if (!defined('WS_SERVER') || !WS_SERVER) {
            require_sesskey();
        }

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
            'eventtype' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'Logged event type'),
        ]);
    }
}
