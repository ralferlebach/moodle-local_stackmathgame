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
 * Renderable for the prerequisite panel on the game settings page.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\output;

use local_stackmathgame\local\service\prerequisite_checker;
use renderer_base;

/**
 * Shows, per activity, which prerequisites for running a game are met and which are not.
 *
 * The panel exists because the failure it reports is silent. A quiz with the wrong question
 * behaviour renders perfectly, starts an attempt, and simply is not a game - so the teacher
 * looks for a fault in the game settings, which are complete and correct.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prerequisite_panel implements \renderable, \templatable {
    /** @var array[] The check results from prerequisite_checker::check(). */
    private array $checks;

    /**
     * Constructor.
     *
     * @param array[] $checks The check results.
     */
    public function __construct(array $checks) {
        $this->checks = $checks;
    }

    /**
     * Build the panel for an activity.
     *
     * @param int $cmid The course-module ID.
     * @return self The renderable.
     */
    public static function for_cmid(int $cmid): self {
        return new self(prerequisite_checker::check($cmid));
    }

    /**
     * Export data for the template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        $errors = 0;
        $warnings = 0;

        foreach ($this->checks as $check) {
            $status = (string)$check['status'];
            if ($status === prerequisite_checker::STATUS_ERROR) {
                $errors++;
            } else if ($status === prerequisite_checker::STATUS_WARNING) {
                $warnings++;
            }
            $rows[] = [
                'key' => $check['key'],
                'label' => $check['label'],
                'message' => $check['message'],
                'fixurl' => $check['fixurl'],
                'hasfixurl' => !empty($check['fixurl']),
                'iserror' => $status === prerequisite_checker::STATUS_ERROR,
                'iswarning' => $status === prerequisite_checker::STATUS_WARNING,
                'isok' => $status === prerequisite_checker::STATUS_OK,
                // Colour alone must not carry the outcome, so each row also gets a text label.
                'statuslabel' => get_string('prereq_status_' . $status, 'local_stackmathgame'),
            ];
        }

        return [
            'rows' => $rows,
            'haserrors' => $errors > 0,
            'haswarnings' => $warnings > 0,
            'summary' => $errors > 0
                ? get_string('prereq_summary_blocked', 'local_stackmathgame', $errors)
                : ($warnings > 0
                    ? get_string('prereq_summary_warnings', 'local_stackmathgame', $warnings)
                    : get_string('prereq_summary_ok', 'local_stackmathgame')),
        ];
    }
}
