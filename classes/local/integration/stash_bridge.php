<?php
namespace local_stackmathgame\local\integration;

defined('MOODLE_INTERNAL') || die();

/**
 * Soft bridge for block_stash.
 */
final class stash_bridge {
    public static function dispatch(\stdClass $profile, int $quizid, int $designid, int $slot, array $slotdata, array $deltas): array {
        if (!availability::has_block_stash() || empty($deltas['solved'])) {
            return ['available' => availability::has_block_stash(), 'dispatched' => false];
        }
        global $DB;
        $progresscfg = (array)($slotdata['config'] ?? []);
        $itemkey = (string)($progresscfg['stashitemkey'] ?? ('smg_slot_' . $slot));
        if ($itemkey === '') {
            $itemkey = 'smg_slot_' . $slot;
        }
        $record = $DB->get_record('local_stackmathgame_inventory', ['profileid' => $profile->id, 'itemkey' => $itemkey]);
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
        $context = null;
        if ($quizid > 0 && ($cm = get_coursemodule_from_instance('quiz', $quizid, 0, false))) {
            $context = \context_module::instance((int)$cm->id);
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
        return ['available' => true, 'dispatched' => true, 'itemkey' => $itemkey];
    }
}
