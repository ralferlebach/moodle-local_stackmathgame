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
 * Soft bridge for block_xp integration.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\integration;

/**
 * Fires Moodle events that block_xp can listen to for awarding XP.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class xp_bridge {
    /**
     * Dispatch XP-related events if block_xp is installed.
     *
     * @param \stdClass $profile  The user game profile.
     * @param int       $quizid   The quiz ID.
     * @param int       $designid The active design ID.
     * @param int       $slot     The question slot.
     * @param array     $slotdata Slot state data.
     * @param array     $deltas   Score/XP deltas from the answer.
     * @return array Dispatch result with 'available' and 'dispatched' keys.
     */
    public static function dispatch(
        \stdClass $profile,
        int $quizid,
        int $designid,
        int $slot,
        array $slotdata,
        array $deltas
    ): array {
        if (!availability::has_block_xp()) {
            return ['available' => false, 'dispatched' => false];
        }

        $context = null;
        if ($quizid > 0) {
            $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false);
            if ($cm) {
                $context = \context_module::instance((int)$cm->id);
            }
        }
        if (!$context) {
            $context = \context_system::instance();
        }

        $payload = [
            'slot'       => $slot,
            'state'      => (string)($slotdata['state'] ?? ''),
            'scoredelta' => (int)($deltas['score'] ?? 0),
            'xpdelta'    => (int)($deltas['xp'] ?? 0),
            'questionid' => (int)($slotdata['questionid'] ?? 0),
            'designid'   => $designid,
            'quizid'     => $quizid,
        ];

        \local_stackmathgame\event\progress_updated::create([
            'context'       => $context,
            'userid'        => (int)$profile->userid,
            'relateduserid' => (int)$profile->userid,
            'objectid'      => (int)$profile->id,
            'other'         => $payload + ['labelid' => (int)$profile->labelid],
        ])->trigger();

        if (!empty($deltas['solved'])) {
            \local_stackmathgame\event\question_solved::create([
                'context'       => $context,
                'userid'        => (int)$profile->userid,
                'relateduserid' => (int)$profile->userid,
                'objectid'      => (int)$profile->id,
                'other'         => $payload + ['labelid' => (int)$profile->labelid],
            ])->trigger();
        }

        return ['available' => true, 'dispatched' => true];
    }
}
