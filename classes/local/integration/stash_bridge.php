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
 * When block_stash is installed and a mapping exists in local_stackmathgame_stashmap,
 * this bridge awards the configured item quantity via the block_stash manager API.
 * The manager requires the CAN_MANAGE capability, which is obtained by temporarily
 * switching to the site admin user (cron pattern) and restoring afterwards.
 *
 * When block_stash is absent or no mapping is configured, the bridge falls back to
 * writing a local inventory record in local_stackmathgame_inventory, preserving the
 * pre-existing behaviour.
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
     * @return array Result with keys 'available', 'dispatched', 'itemkey', 'stash'.
     */
    public static function dispatch(
        \stdClass $profile,
        int $quizid,
        int $designid,
        int $slot,
        array $slotdata,
        array $deltas
    ): array {
        $stashavailable = availability::has_block_stash();

        if (empty($deltas['solved'])) {
            return ['available' => $stashavailable, 'dispatched' => false, 'stash' => false];
        }

        // Try the real block_stash path if a mapping is configured.
        if ($stashavailable) {
            $stashresult = self::dispatch_to_block_stash($profile, $quizid, $slot);
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

        // Fallback: write to the local inventory table.
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
     * Look up a stash mapping and award the item via block_stash manager.
     *
     * Temporarily switches to the site admin user to obtain the CAN_MANAGE
     * capability required by block_stash\manager::update_user_item_amount().
     * This follows the same pattern used by Moodle cron tasks that need to
     * perform privileged operations on behalf of enrolled users.
     *
     * @param \stdClass $profile The game profile (userid used for award target).
     * @param int       $quizid  The quiz ID (used for mapping lookup).
     * @param int       $slot    The question slot number.
     * @return array|null Mapping data array on success, null if no mapping found or error.
     */
    private static function dispatch_to_block_stash(
        \stdClass $profile,
        int $quizid,
        int $slot
    ): ?array {
        global $DB, $USER;

        $mapping = $DB->get_record('local_stackmathgame_stashmap', [
            'quizid' => $quizid,
            'slotnumber' => $slot,
            'enabled' => 1,
        ]);
        if (!$mapping) {
            return null;
        }

        // Temporarily switch to admin to satisfy block_stash::require_manage().
        $originaluser = $USER;
        cron_setup_user();

        try {
            $manager = \block_stash\manager::get((int)$mapping->stashcourseid);

            if (!$manager->is_enabled()) {
                return null;
            }

            // Determine new quantity: get current amount and add the grant quantity.
            $useritem = $manager->get_user_item((int)$profile->userid, (int)$mapping->stashitemid);
            $newqty = (int)$useritem->get_quantity() + (int)$mapping->grantquantity;
            $manager->update_user_item_amount(
                (int)$mapping->stashitemid,
                (int)$profile->userid,
                $newqty
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
        } finally {
            // Always restore the original user, even if an exception occurred.
            \core\session\manager::set_user($originaluser);
        }
    }

    /**
     * Write a local inventory record as a fallback when block_stash is absent
     * or no mapping is configured for the quiz/slot.
     *
     * @param \stdClass $profile  The game profile.
     * @param int       $slot     The question slot.
     * @param array     $slotdata Slot state data (may contain stashitemkey).
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
                'statejson' => json_encode(
                    ['source' => 'stackmathgame'],
                    JSON_UNESCAPED_UNICODE
                ),
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
