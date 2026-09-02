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
 * Renderable for the game flow slot list.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\output;

use local_stackmathgame\local\service\flow_service;
use local_stackmathgame\local\service\slot_config_schema;
use renderer_base;

/**
 * One row per quiz slot, showing the question and the direction card side by side.
 *
 * Showing both together is the point of the page. The question title alone does not say what
 * the slot does in the game, and the scene configuration alone does not say which question it
 * belongs to - and a teacher authoring twenty slots needs to see the pairing at a glance.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow_list implements \renderable, \templatable {
    /** @var int The course-module ID. */
    private int $cmid;

    /**
     * Constructor.
     *
     * @param int $cmid The course-module ID.
     */
    public function __construct(int $cmid) {
        $this->cmid = $cmid;
    }

    /**
     * Export data for the template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output): array {
        $slots = flow_service::get_slots($this->cmid);
        $analysis = flow_service::analyse_reachability($this->cmid);

        $rows = [];
        foreach ($slots as $slotnumber => $slot) {
            $config = $slot['config'];
            $rows[] = [
                'slotnumber' => $slotnumber,
                'questionname' => $slot['questionname'],
                'questionid' => $slot['questionid'],
                'qtype' => $slot['qtype'],
                'isstack' => $slot['isstack'],
                'scenetype' => get_string(
                    'flow_scenetype_' . (string)($config['scene']['type'] ?? 'challenge'),
                    'local_stackmathgame'
                ),
                'branchsummary' => $this->branch_summary($config),
                'score' => (int)($config['rewards']['score'] ?? 0),
                'xp' => (int)($config['rewards']['xp'] ?? 0),
                'hasnarrative' => trim((string)($config['narrative']['intro'] ?? '')) !== ''
                    || trim((string)($config['narrative']['success'] ?? '')) !== ''
                    || trim((string)($config['narrative']['fail'] ?? '')) !== '',
                'unreachable' => in_array($slotnumber, $analysis['unreachable'], true),
                'deadend' => in_array($slotnumber, $analysis['deadends'], true),
                'editurl' => (new \moodle_url('/local/stackmathgame/flow.php', [
                    'cmid' => $this->cmid,
                    'slot' => $slotnumber,
                    'action' => 'editslot',
                ]))->out(false),
            ];
        }

        return [
            'cmid' => $this->cmid,
            'sesskey' => sesskey(),
            'rows' => $rows,
            'hasrows' => !empty($rows),
            'actionurl' => (new \moodle_url('/local/stackmathgame/flow.php'))->out(false),
            'hasproblems' => !empty($analysis['unreachable']) || !empty($analysis['deadends']),
            'unreachable' => implode(', ', $analysis['unreachable']),
            'hasunreachable' => !empty($analysis['unreachable']),
            'deadends' => implode(', ', $analysis['deadends']),
            'hasdeadends' => !empty($analysis['deadends']),
            'scenetypes' => $this->scene_type_options(),
        ];
    }

    /**
     * Summarise where a slot sends the player after a correct answer.
     *
     * Only the correct-answer branch is summarised: it is the one that defines the path through
     * the quiz, and four outcomes per row would make a twenty-slot table unreadable.
     *
     * @param array $config The slot config.
     * @return string A short human-readable summary.
     */
    private function branch_summary(array $config): string {
        $rule = (array)($config['branching'][slot_config_schema::OUTCOME_GRADEDRIGHT] ?? []);
        $mode = (string)($rule['mode'] ?? slot_config_schema::BRANCH_MODE_LINEAR);
        if ($mode === slot_config_schema::BRANCH_MODE_SLOT) {
            return get_string('flow_branchto', 'local_stackmathgame', (int)($rule['target'] ?? 0));
        }
        return get_string('flow_branchmode_' . $mode, 'local_stackmathgame');
    }

    /**
     * Build the scene type options for the bulk-apply control.
     *
     * @return array[] List of value/label pairs.
     */
    private function scene_type_options(): array {
        $options = [];
        foreach (slot_config_schema::SCENE_TYPES as $type) {
            $options[] = [
                'value' => $type,
                'label' => get_string('flow_scenetype_' . $type, 'local_stackmathgame'),
            ];
        }
        return $options;
    }
}
