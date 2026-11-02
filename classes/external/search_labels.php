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
 * External function: search_labels.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Search the active game labels for the label selector of the settings page.
 *
 * Labels are site-wide, so a site can hold far more of them than a selector should load at once.
 * The selector asks for a page of matches as the teacher types instead of receiving every label
 * with the page (issue #14).
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_labels extends external_api {
    /** Most matches returned for one query. */
    const LIMIT = 50;

    /**
     * Describe input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course-module whose settings are edited'),
            'query' => new external_value(PARAM_TEXT, 'Part of the label name', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid The course-module ID.
     * @param string $query Part of the label name.
     * @return array Matching labels and whether there were more.
     */
    public static function execute(int $cmid, string $query = ''): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'query' => $query]);
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/stackmathgame:configurequiz', $context);

        $where = 'status = 1 AND timedeleted IS NULL';
        $sqlparams = [];
        $query = trim($params['query']);
        if ($query !== '') {
            $where .= ' AND ' . $DB->sql_like('name', ':query', false, false);
            $sqlparams['query'] = '%' . $DB->sql_like_escape($query) . '%';
        }
        // One more than shown, to know whether to say that there are more.
        $records = $DB->get_records_select(
            'local_stackmathgame_label',
            $where,
            $sqlparams,
            'name ASC',
            'id, name',
            0,
            self::LIMIT + 1
        );
        $labels = [];
        foreach (array_slice($records, 0, self::LIMIT) as $record) {
            $labels[] = ['id' => (int)$record->id, 'name' => format_string($record->name)];
        }
        return ['labels' => $labels, 'more' => count($records) > self::LIMIT];
    }

    /**
     * Describe return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'labels' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Label id'),
                'name' => new external_value(PARAM_TEXT, 'Label name'),
            ])),
            'more' => new external_value(PARAM_BOOL, 'Whether more labels match than were returned'),
        ]);
    }
}
