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
 * Bridge dispatcher for optional integrations.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\integration;

/**
 * Dispatch optional integration bridges after a processed answer result.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bridge_dispatcher {
    /**
     * Return a stable bridge availability summary for runtime/export callers.
     *
     * @return array<string, bool> Availability map.
     */
    public static function availability_summary(): array {
        return [
            'xp' => availability::has_block_xp(),
            'stash' => availability::has_block_stash(),
            'localinventory' => true,
        ];
    }

    /**
     * Trigger optional bridges (XP, stash) after a processed answer result.
     *
     * @param \stdClass $profile  The user game profile.
     * @param int       $quizid   The quiz ID.
     * @param int       $designid The active design ID.
     * @param int       $slot     The question slot.
     * @param array     $slotdata Slot state data.
     * @param array     $deltas   Score/XP deltas from the answer.
     * @param array     $activity Optional activity identity payload.
     * @return array Results from each bridge.
     */
    public static function on_answer_result(
        \stdClass $profile,
        int $quizid,
        int $designid,
        int $slot,
        array $slotdata,
        array $deltas,
        array $activity = []
    ): array {
        return [
            'xp' => xp_bridge::dispatch($profile, $quizid, $designid, $slot, $slotdata, $deltas),
            'stash' => stash_bridge::dispatch($profile, $quizid, $designid, $slot, $slotdata, $deltas, $activity),
        ];
    }
}
