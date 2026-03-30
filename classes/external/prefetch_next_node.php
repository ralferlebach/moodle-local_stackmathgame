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
 * External function: prefetch_next_node.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

use xmldb_field;
use xmldb_table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Return the next mapped node or next quiz slot as prefetch data.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prefetch_next_node extends \external_api {
    /**
     * Describe input parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'currentslot' => new \external_value(
                PARAM_INT,
                'Current slot number',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $quizid The quiz instance ID.
     * @param int $currentslot The currently active slot.
     * @return array The next-node payload.
     */
    public static function execute(int $quizid, int $currentslot = 0): array {
        $activity = api::resolve_activity_identity(0, 'quiz', $quizid, $quizid);
        $result = prefetch_next_activity_node::execute(
            (int)$activity['cmid'],
            (string)$activity['modname'],
            (int)$activity['instanceid'],
            $currentslot
        );

        return [
            'quizid' => (int)$result['quizid'],
            'currentslot' => (int)$result['currentslot'],
            'nextnode' => (array)$result['nextnode'],
        ];
    }

    /**
     * Resolve the quiz slot field that stores the question identifier.
     *
     * Moodle versions differ here. Older installations expose quiz_slots.questionid,
     * newer ones expose quiz_slots.question.
     *
     * @return string SQL-safe field name or 0 fallback.
     */
    private static function get_quiz_slot_question_field(): string {
        global $DB;

        $manager = $DB->get_manager();
        $table = new xmldb_table('quiz_slots');

        if ($manager->field_exists($table, new xmldb_field('question'))) {
            return 'question';
        }

        if ($manager->field_exists($table, new xmldb_field('questionid'))) {
            return 'questionid';
        }

        return '0';
    }

    /**
     * Describe return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'currentslot' => new \external_value(PARAM_INT, 'Current slot number'),
            'nextnode' => get_quiz_config::questionmap_structure(),
        ]);
    }
}
