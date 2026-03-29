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
 * Label-bound profile state management.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;

/**
 * Handles label-bound profile state and progress tracking.
 *
 * The apply_progress() method wraps its read-modify-write cycle in a
 * delegated Moodle database transaction to prevent duplicate XP awards
 * when a student submits rapidly or from multiple tabs simultaneously.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_service {
    /**
     * Calculate the level number for a given XP total.
     *
     * @param int $xp Total XP.
     * @return int Level number (minimum 1).
     */
    public static function calculate_level_from_xp(int $xp): int {
        return max(1, (int)floor($xp / 100) + 1);
    }

    /**
     * Decode a nullable JSON string field to an array.
     *
     * @param string|null $value The JSON string or null.
     * @return array The decoded array, or empty array on failure.
     */
    public static function decode_json_field(?string $value): array {
        return json_decode((string)$value, true) ?: [];
    }

    /**
     * Return the state string for a specific question slot from a profile.
     *
     * @param \stdClass $profile The profile record.
     * @param int       $slot    The slot number.
     * @return string The state string, or empty string if not tracked.
     */
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

    /**
     * Calculate score/XP deltas for a state transition.
     *
     * @param string $previousstate Previous question state string.
     * @param string $newstate      New question state string.
     * @return array Array with keys 'score', 'xp', 'solved'.
     */
    public static function calculate_submit_deltas(string $previousstate, string $newstate): array {
        $rightstates   = ['gradedright', 'complete'];
        $partialstates = ['gradedpartial'];
        $wasright   = in_array($previousstate, $rightstates, true);
        $isright    = in_array($newstate, $rightstates, true);
        $waspartial = in_array($previousstate, $partialstates, true);
        $ispartial  = in_array($newstate, $partialstates, true);

        if ($isright && !$wasright) {
            return ['score' => 10, 'xp' => 5, 'solved' => true];
        }
        if ($ispartial && !$wasright && !$waspartial) {
            return ['score' => 5, 'xp' => 2, 'solved' => false];
        }
        return ['score' => 0, 'xp' => 0, 'solved' => $wasright || $isright];
    }

    /**
     * Build a summary statistics array from a profile record.
     *
     * @param \stdClass $profile The profile record.
     * @return array Summary with solvedcount, partialcount, trackedslots, levelprogress.
     */
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
            'solvedcount'   => $solved,
            'partialcount'  => $partial,
            'trackedslots'  => count($slots),
            'levelprogress' => (int)$profile->xp % 100,
        ];
    }

    /**
     * Get or create the profile for a user in the context of a specific quiz.
     *
     * @param int $userid  The user ID.
     * @param int $quizid  The quiz ID (used to look up labelid).
     * @return \stdClass The profile record.
     */
    public static function get_or_create_for_quiz(int $userid, int $quizid): \stdClass {
        $cmid = quiz_configurator::cmid_from_quizid($quizid);
        if ($cmid <= 0) {
            throw new \moodle_exception('quiznotfound', 'local_stackmathgame', '', $quizid);
        }
        $config = quiz_configurator::ensure_default($cmid);
        return self::get_or_create(
            (int)$userid,
            (int)$config->labelid,
            $quizid,
            (int)$config->designid
        );
    }

    /**
     * Get or create a profile record for a user and label combination.
     *
     * @param int      $userid   The user ID.
     * @param int      $labelid  The label ID.
     * @param int|null $quizid   Optional last-accessed quiz ID.
     * @param int|null $designid Optional last-used design ID.
     * @return \stdClass The profile record.
     */
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

    /**
     * Apply a set of delta changes to a profile record inside a DB transaction.
     *
     * The read-modify-write cycle runs inside a delegated Moodle database
     * transaction to prevent duplicate XP awards from concurrent submissions.
     *
     * @param int   $profileid Profile record ID.
     * @param array $changes   Changes with optional keys: scoredelta, xpdelta,
     *                         softcurrencydelta, hardcurrencydelta, levelno,
     *                         quizid, designid, progress, flags, stats.
     * @return \stdClass Updated profile record (re-read after commit).
     * @throws \Throwable Re-thrown after transaction rollback.
     */
    public static function apply_progress(int $profileid, array $changes): \stdClass {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $profile  = $DB->get_record(
                'local_stackmathgame_profile',
                ['id' => $profileid],
                '*',
                MUST_EXIST
            );
            $progress = json_decode((string)$profile->progressjson, true) ?: [];
            $flags    = json_decode((string)$profile->flagsjson, true) ?: [];
            $stats    = json_decode((string)$profile->statsjson, true) ?: [];

            $profile->score        += (int)($changes['scoredelta'] ?? 0);
            $profile->xp           += (int)($changes['xpdelta'] ?? 0);
            $profile->softcurrency  += (int)($changes['softcurrencydelta'] ?? 0);
            $profile->hardcurrency  += (int)($changes['hardcurrencydelta'] ?? 0);

            $profile->score        = max(0, (int)$profile->score);
            $profile->xp           = max(0, (int)$profile->xp);
            $profile->softcurrency = max(0, (int)$profile->softcurrency);
            $profile->hardcurrency = max(0, (int)$profile->hardcurrency);

            $profile->levelno = max(
                1,
                (int)($changes['levelno'] ?? self::calculate_level_from_xp((int)$profile->xp))
            );
            $profile->lastquizid = isset($changes['quizid'])
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
            $profile->flagsjson    = json_encode($flags, JSON_UNESCAPED_UNICODE);
            $profile->statsjson    = json_encode($stats, JSON_UNESCAPED_UNICODE);

            $DB->update_record('local_stackmathgame_profile', $profile);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }

        return $DB->get_record(
            'local_stackmathgame_profile',
            ['id' => $profileid],
            '*',
            MUST_EXIST
        );
    }
}
