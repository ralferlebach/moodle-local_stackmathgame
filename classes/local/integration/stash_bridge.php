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
 * Grants stash items when a question slot is solved.
 *
 * Uses direct DB writes to block_stash_user_items rather than the block_stash
 * persistent-class API (which varies between plugin versions). No capability
 * switching via cron_setup_user() is required.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stash_bridge {
    /**
     * Dispatch a stash item grant after a question slot is solved.
     *
     * @param \stdClass $profile  The user game profile.
     * @param int       $quizid   The quiz ID.
     * @param int       $designid The active design ID.
     * @param int       $slot     The question slot number.
     * @param array     $slotdata Slot state data.
     * @param array     $deltas   Score/XP deltas from the answer.
     * @param array     $activity Optional activity identity payload.
     * @return array Result with keys 'available', 'dispatched', 'stash'.
     */
    public static function dispatch(
        \stdClass $profile,
        int $quizid,
        int $designid,
        int $slot,
        array $slotdata,
        array $deltas,
        array $activity = []
    ): array {
        $stashavailable = availability::has_block_stash();

        if (empty($deltas['solved'])) {
            return ['available' => $stashavailable, 'dispatched' => false, 'stash' => false];
        }

        if ($stashavailable) {
            $stashresult = self::dispatch_to_block_stash($profile, $quizid, $slot, $activity);
            if ($stashresult !== null) {
                self::fire_event($profile, $quizid, $designid, $slot, $stashresult['itemkey']);
                return [
                    'available' => true,
                    'dispatched' => true,
                    'stash' => true,
                    'itemkey' => $stashresult['itemkey'],
                    'stashitemid' => $stashresult['stashitemid'],
                ];
            }
        }

        $itemkey = self::dispatch_to_local_inventory($profile, $slot, $slotdata);
        self::fire_event($profile, $quizid, $designid, $slot, $itemkey);

        return [
            'available' => $stashavailable,
            'dispatched' => true,
            'stash' => false,
            'itemkey' => $itemkey,
        ];
    }

    /**
     * Award a block_stash item via direct DB writes.
     *
     * Looks up the stashmap for this quiz/slot. Increments block_stash_user_items
     * directly, bypassing the persistent-class API to avoid version incompatibilities.
     *
     * @param \stdClass $profile The game profile.
     * @param int       $quizid  The quiz ID.
     * @param int       $slot    The question slot number.
     * @return array|null Mapping info on success, null on failure or no mapping.
     */
    private static function dispatch_to_block_stash(
        \stdClass $profile,
        int $quizid,
        int $slot
    ): ?array {
        global $DB;

        $mapping = $DB->get_record('local_stackmathgame_stashmap', [
            'quizid' => $quizid,
            'slotnumber' => $slot,
            'enabled' => 1,
        ]);
        if (!$mapping) {
            return null;
        }

        $coursectx = \context_course::instance((int)$mapping->stashcourseid, IGNORE_MISSING);
        if (!$coursectx) {
            return null;
        }
        if (
            !$DB->record_exists('block_instances', [
                'blockname' => 'stash',
                'parentcontextid' => $coursectx->id,
            ])
        ) {
            return null;
        }

        try {
            self::award_user_item_direct(
                (int)$profile->userid,
                (int)$mapping->stashitemid,
                (int)$mapping->grantquantity
            );
            return [
                'itemkey' => 'stash_item_' . (int)$mapping->stashitemid,
                'stashitemid' => (int)$mapping->stashitemid,
            ];
        } catch (\Throwable $e) {
            debugging(
                'local_stackmathgame stash_bridge block_stash award failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Write or update a block_stash_user_items row directly.
     *
     * @param int $userid   The recipient user ID.
     * @param int $itemid   The block_stash item ID.
     * @param int $quantity The quantity to add.
     * @return void
     */
    private static function award_user_item_direct(int $userid, int $itemid, int $quantity): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record('block_stash_user_items', [
            'userid' => $userid,
            'itemid' => $itemid,
        ]);

        if ($existing) {
            $DB->update_record('block_stash_user_items', (object)[
                'id' => $existing->id,
                'quantity' => (int)$existing->quantity + $quantity,
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('block_stash_user_items', (object)[
                'itemid' => $itemid,
                'userid' => $userid,
                'quantity' => $quantity,
                'timecreated' => $now,
                'timemodified' => $now,
                'version' => '0',
            ]);
        }
    }

    /**
     * Write a local inventory record as fallback.
     *
     * @param \stdClass $profile  The game profile.
     * @param int       $slot     The question slot.
     * @param array     $slotdata Slot state data.
     * @return string The itemkey used.
     */
    private static function dispatch_to_local_inventory(
        \stdClass $profile,
        int $slot,
        array $slotdata
    ): string {
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
            $record->quantity = (int)$record->quantity + 1;
            $record->timemodified = $now;
            $DB->update_record('local_stackmathgame_inventory', $record);
        } else {
            $DB->insert_record('local_stackmathgame_inventory', (object)[
                'profileid' => (int)$profile->id,
                'itemkey' => $itemkey,
                'quantity' => 1,
                'statejson' => json_encode(['source' => 'stackmathgame'], JSON_UNESCAPED_UNICODE),
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        return $itemkey;
    }

    /**
     * Fire the stash_item_granted Moodle event.
     *
     * @param \stdClass $profile  The game profile.
     * @param int       $quizid   The quiz ID.
     * @param int       $designid The active design ID.
     * @param int       $slot     The question slot.
     * @param string    $itemkey  The item key that was granted.
     * @return void
     */
    private static function fire_event(
        \stdClass $profile,
        int $quizid,
        int $designid,
        int $slot,
        string $itemkey
    ): void {
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
            'context' => $context,
            'userid' => (int)$profile->userid,
            'relateduserid' => (int)$profile->userid,
            'objectid' => (int)$profile->id,
            'other' => [
                'labelid' => (int)$profile->labelid,
                'quizid' => $quizid,
                'designid' => $designid,
                'slot' => $slot,
                'itemkey' => $itemkey,
            ],
        ])->trigger();
    }
}
