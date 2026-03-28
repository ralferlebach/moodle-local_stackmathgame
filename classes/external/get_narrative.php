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
 * External function: get_narrative.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

use local_stackmathgame\local\service\narrative_resolver;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Return narrative lines for a named scene from the active design.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_narrative extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'scene'  => new \external_value(PARAM_ALPHANUMEXT, 'Narrative scene key'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int    $quizid The quiz instance ID.
     * @param string $scene  The narrative scene key.
     * @return array The narrative lines array.
     */
    public static function execute(int $quizid, string $scene): array {
        [, , $config, $profile, $design] = api::validate_quiz_access($quizid);
        $lines = narrative_resolver::resolve($design, $scene);
        if (!is_array($lines)) {
            $lines = [$lines];
        }
        api::log_event(
            $profile,
            $quizid,
            (int)$config->designid,
            'narrative_requested',
            'external.get_narrative',
            ['scene' => $scene]
        );
        return [
            'quizid'   => $quizid,
            'scene'    => $scene,
            'lines'    => array_values(array_map('strval', $lines)),
            'designid' => (int)$config->designid,
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
            'scene'    => new \external_value(PARAM_ALPHANUMEXT, 'Narrative scene key'),
            'lines'    => new \external_multiple_structure(
                new \external_value(PARAM_RAW, 'Narrative line')
            ),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
        ]);
    }
}
