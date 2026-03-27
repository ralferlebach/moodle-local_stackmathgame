<?php
namespace local_stackmathgame\local\service;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;

/**
 * Handles label-bound profile state.
 *
 * Fixed issues:
 * 1. apply_progress() previously performed a bare read-then-write without
 *    any transaction or locking. When a student clicked "Check" rapidly, or
 *    when two tabs submitted simultaneously, both requests read the same
 *    baseline XP value, computed deltas independently, and wrote conflicting
 *    updates – resulting in duplicate XP awards.
 *
 *    The fix wraps the entire read-modify-write cycle in a delegated Moodle
 *    database transaction. Moodle's $DB->start_delegated_transaction() is
 *    compatible with nested transactions (the outermost commit wins) and
 *    causes the inner write to fail with a rollback if a concurrent
 *    transaction already modified the same row – which is the desired
 *    serialization behaviour on all supported DB engines (MySQL/MariaDB
 *    InnoDB, PostgreSQL).
 */
class profile_service {

    // ------------------------------------------------------------------
    // Pure helpers (no DB)
    // ------------------------------------------------------------------

    public static function calculate_level_from_xp(int $xp): int {
        return max(1, (int)floor($xp / 100) + 1);
    }

    public static function decode_json_field(?string $value): array {
        return json_decode((string)$value, true) ?: [];
    }

    public static function get_slot_state(\stdClass $profile, int $slot): string {
        $progress = self::decode_json_field($profile->progressjson ?? '{}');
        $slots    = (array)($progress['slots'] ?? []);
        $slotkey  = (string)$slot;
        if (isset($slots[$slotkey]) && is_array($slots[$slotkey])) {
            return (string)($slots[$slotkey]['state'] ?? '');
        }
        if (isset($slots[$slotkey]) && is_scalar($slots[$slotkey])) {
            return (string)$slots[$slotkey];
        }
        return '';
    }

    public static function calculate_submit_deltas(string $previousstate, string $newstate): array {
        $rightstates   = ['gradedright', 'complete'];
        $partialstates = ['gradedpartial'];
        $wasright   = in_array($previousstate, $rightstates,   true);
        $isright    = in_array($newstate,      $rightstates,   true);
        $waspartial = in_array($previousstate, $partialstates, true);
        $ispartial  = in_array($newstate,      $partialstates, true);

        if ($isright && !$wasright) {
            return ['score' => 10, 'xp' => 5, 'solved' => true];
        }
        if ($ispartial && !$wasright && !$waspartial) {
            return ['score' => 5, 'xp' => 2, 'solved' => false];
        }
        return ['score' => 0, 'xp' => 0, 'solved' => $wasright || $isright];
    }

    public static function build_summary(\stdClass $profile): array {
        $progress = self::decode_json_field($profile->progressjson ?? '{}');
        $slots    = (array)($progress['slots'] ?? []);
        $solved   = 0;
        $partial  = 0;
        foreach ($slots as $slot) {
            $state = is_array($slot) ? (string)($slot['state'] ?? '') : (string)$slot;
            if (in_array($state, ['gradedright', 'complete'], true)) {
                $solved++;
            } else if ($state === 'gradedpartial') {
                $partial++;
            }
        }
        return [
            'solvedcount'  => $solved,
            'partialcount' => $partial,
            'trackedslots' => count($slots),
            'levelprogress' => (int)$profile->xp % 100,
        ];
    }

    // ------------------------------------------------------------------
    // Profile retrieval / creation
    // ------------------------------------------------------------------

    public static function get_or_create_for_quiz(int $userid, int $quizid): \stdClass {
        $config = quiz_configurator::ensure_default($quizid);
        return self::get_or_create(
            (int)$userid,
            (int)$config->labelid,
            $quizid,
            (int)$config->designid
        );
    }

    public static function get_or_create(
        int $userid,
        int $labelid,
        ?int $quizid = null,
        ?int $designid = null
    ): \stdClass {
        global $DB;

        $record = $DB->get_record(
            'local_stackmathgame_profile',
            ['userid' => $userid, 'labelid' => $labelid]
        );
        if ($record) {
            return $record;
        }

        $now = time();
        $id  = $DB->insert_record('local_stackmathgame_profile', (object)[
            'userid'           => $userid,
            'labelid'          => $labelid,
            'score'            => 0,
            'xp'               => 0,
            'levelno'          => 1,
            'softcurrency'     => 0,
            'hardcurrency'     => 0,
            'avatarconfigjson' => json_encode([], JSON_UNESCAPED_UNICODE),
            'progressjson'     => json_encode([], JSON_UNESCAPED_UNICODE),
            'statsjson'        => json_encode([], JSON_UNESCAPED_UNICODE),
            'flagsjson'        => json_encode([], JSON_UNESCAPED_UNICODE),
            'lastquizid'       => $quizid,
            'lastdesignid'     => $designid ?? theme_manager::ensure_default_design(),
            'lastaccess'       => $now,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
        return $DB->get_record('local_stackmathgame_profile', ['id' => $id], '*', MUST_EXIST);
    }

    // ------------------------------------------------------------------
    // Progress update – race-condition safe
    // ------------------------------------------------------------------

    /**
     * Apply a set of changes to a profile record.
     *
     * The entire read-modify-write cycle runs inside a delegated Moodle
     * database transaction so that concurrent submissions cannot produce
     * duplicate XP or score awards.
     *
     * @param int   $profileid Profile record id.
     * @param array $changes   Associative array with optional keys:
     *                           scoredelta, xpdelta, softcurrencydelta,
     *                           hardcurrencydelta, levelno, quizid, designid,
     *                           progress (array patch), flags (array patch),
     *                           stats (array patch).
     * @return \stdClass       Updated profile record (re-read after commit).
     * @throws \Throwable      Re-throws after rolling back the transaction.
     */
    public static function apply_progress(int $profileid, array $changes): \stdClass {
        global $DB;

        // *** BUG FIX: wrap in a delegated transaction to serialize concurrent
        // submissions. Without this, two rapid "Check" clicks could both read
        // xp=100, each add 5, and both write 105 instead of the correct 110. ***
        $transaction = $DB->start_delegated_transaction();
        try {
            $profile  = $DB->get_record(
                'local_stackmathgame_profile',
                ['id' => $profileid],
                '*',
                MUST_EXIST
            );
            $progress = json_decode((string)$profile->progressjson, true) ?: [];
            $flags    = json_decode((string)$profile->flagsjson,    true) ?: [];
            $stats    = json_decode((string)$profile->statsjson,    true) ?: [];

            $profile->score        += (int)($changes['scoredelta']        ?? 0);
            $profile->xp           += (int)($changes['xpdelta']           ?? 0);
            $profile->softcurrency  += (int)($changes['softcurrencydelta']  ?? 0);
            $profile->hardcurrency  += (int)($changes['hardcurrencydelta']  ?? 0);

            // Prevent scores going negative.
            $profile->score        = max(0, (int)$profile->score);
            $profile->xp           = max(0, (int)$profile->xp);
            $profile->softcurrency = max(0, (int)$profile->softcurrency);
            $profile->hardcurrency = max(0, (int)$profile->hardcurrency);

            $profile->levelno     = max(
                1,
                (int)($changes['levelno'] ?? self::calculate_level_from_xp((int)$profile->xp))
            );
            $profile->lastquizid  = isset($changes['quizid'])
                ? (int)$changes['quizid']
                : $profile->lastquizid;
            $profile->lastdesignid = isset($changes['designid'])
                ? (int)$changes['designid']
                : $profile->lastdesignid;
            $profile->lastaccess  = time();
            $profile->timemodified = $profile->lastaccess;

            if (array_key_exists('progress', $changes)) {
                $progress = array_replace_recursive($progress, (array)$changes['progress']);
            }
            if (array_key_exists('flags', $changes)) {
                $flags = array_replace_recursive($flags, (array)$changes['flags']);
            }
            if (array_key_exists('stats', $changes)) {
                $stats = array_replace_recursive($stats, (array)$changes['stats']);
            }

            $profile->progressjson = json_encode($progress, JSON_UNESCAPED_UNICODE);
            $profile->flagsjson    = json_encode($flags,    JSON_UNESCAPED_UNICODE);
            $profile->statsjson    = json_encode($stats,    JSON_UNESCAPED_UNICODE);

            $DB->update_record('local_stackmathgame_profile', $profile);
            $transaction->allow_commit();

        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;  // Re-throw so callers can handle.
        }

        return $DB->get_record(
            'local_stackmathgame_profile',
            ['id' => $profileid],
            '*',
            MUST_EXIST
        );
    }
}
