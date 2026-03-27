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
 * Soft bridge for block_stash integration.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\integration;

/**
 * Manages local inventory records as a lightweight stash alternative.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stash_bridge {
    /**
     * Dispatch a stash-like item grant if block_stash is installed and slot was solved.
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
        if (!availability::has_block_stash() || empty($deltas['solved'])) {
            return ['available' => availability::has_block_stash(), 'dispatched' => false];
        }
        global $DB;
        $progresscfg = (array)($slotdata['config'] ?? []);
        $itemkey = (string)($progresscfg['stashitemkey'] ?? ('smg_slot_' . $slot));
        if ($itemkey === '') {
            $itemkey = 'smg_slot_' . $slot;
        }
        $record = $DB->get_record(
            'local_stackmathgame_inventory',
            ['profileid' => $profile->id, 'itemkey' => $itemkey]
        );
        $now = time();
        if ($record) {
            $record->quantity   = (int)$record->quantity + 1;
            $record->timemodified = $now;
            $DB->update_record('local_stackmathgame_inventory', $record);
        } else {
            $DB->insert_record('local_stackmathgame_inventory', (object)[
                'profileid'    => (int)$profile->id,
                'itemkey'      => $itemkey,
                'quantity'     => 1,
                'statejson'    => json_encode(['source' => 'stackmathgame'], JSON_UNESCAPED_UNICODE),
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
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
        \local_stackmathgame\event\stash_item_granted::create([
            'context'       => $context,
            'userid'        => (int)$profile->userid,
            'relateduserid' => (int)$profile->userid,
            'objectid'      => (int)$profile->id,
            'other'         => [
                'labelid'  => (int)$profile->labelid,
                'quizid'   => $quizid,
                'designid' => $designid,
                'slot'     => $slot,
                'itemkey'  => $itemkey,
            ],
        ])->trigger();
        return ['available' => true, 'dispatched' => true, 'itemkey' => $itemkey];
    }
}
