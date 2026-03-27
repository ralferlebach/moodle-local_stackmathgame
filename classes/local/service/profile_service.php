<?php
namespace local_stackmathgame\local\service;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;

/**
 * Handles label-bound profile state.
 */
class profile_service {
    public static function calculate_level_from_xp(int $xp): int {
        return max(1, (int)floor($xp / 100) + 1);
    }

    public static function decode_json_field(?string $value): array {
        return json_decode((string)$value, true) ?: [];
    }

    public static function get_slot_state(\stdClass $profile, int $slot): string {
        $progress = self::decode_json_field($profile->progressjson ?? '{}');
        $slots = (array)($progress['slots'] ?? []);
        $slotkey = (string)$slot;
        if (isset($slots[$slotkey]) && is_array($slots[$slotkey])) {
            return (string)($slots[$slotkey]['state'] ?? '');
        }
        if (isset($slots[$slotkey]) && is_scalar($slots[$slotkey])) {
            return (string)$slots[$slotkey];
        }
        return '';
    }

    public static function calculate_submit_deltas(string $previousstate, string $newstate): array {
        $rightstates = ['gradedright', 'complete'];
        $partialstates = ['gradedpartial'];
        $wasright = in_array($previousstate, $rightstates, true);
        $isright = in_array($newstate, $rightstates, true);
        $waspartial = in_array($previousstate, $partialstates, true);
        $ispartial = in_array($newstate, $partialstates, true);

        if ($isright && !$wasright) {
            $score = $waspartial ? 5 : 10;
            $xp = $waspartial ? 3 : 5;
            return ['score' => $score, 'xp' => $xp, 'solved' => true];
        }
        if ($ispartial && !$wasright && !$waspartial) {
            return ['score' => 5, 'xp' => 2, 'solved' => false];
        }
        return ['score' => 0, 'xp' => 0, 'solved' => $wasright || $isright];
    }

    public static function build_summary(\stdClass $profile): array {
        $progress = self::decode_json_field($profile->progressjson ?? '{}');
        $slots = (array)($progress['slots'] ?? []);
        $solved = 0;
        $partial = 0;
        foreach ($slots as $slot) {
            $state = is_array($slot) ? (string)($slot['state'] ?? '') : (string)$slot;
            if (in_array($state, ['gradedright', 'complete'], true)) {
                $solved++;
            } else if ($state === 'gradedpartial') {
                $partial++;
            }
        }
        return [
            'solvedcount' => $solved,
            'partialcount' => $partial,
            'trackedslots' => count($slots),
            'levelprogress' => (int)$profile->xp % 100,
        ];
    }

    public static function get_or_create_for_quiz(int $userid, int $quizid): \stdClass {
        $config = quiz_configurator::ensure_default($quizid);
        return self::get_or_create((int)$userid, (int)$config->labelid, $quizid, (int)$config->designid);
    }

    public static function get_or_create(int $userid, int $labelid, ?int $quizid = null, ?int $designid = null): \stdClass {
        global $DB;
        $record = $DB->get_record('local_stackmathgame_profile', ['userid' => $userid, 'labelid' => $labelid]);
        if ($record) {
            return $record;
        }
        $now = time();
        $id = $DB->insert_record('local_stackmathgame_profile', (object)[
            'userid' => $userid,
            'labelid' => $labelid,
            'score' => 0,
            'xp' => 0,
            'levelno' => 1,
            'softcurrency' => 0,
            'hardcurrency' => 0,
            'avatarconfigjson' => json_encode([], JSON_UNESCAPED_UNICODE),
            'progressjson' => json_encode([], JSON_UNESCAPED_UNICODE),
            'statsjson' => json_encode([], JSON_UNESCAPED_UNICODE),
            'flagsjson' => json_encode([], JSON_UNESCAPED_UNICODE),
            'lastquizid' => $quizid,
            'lastdesignid' => $designid ?? theme_manager::ensure_default_design(),
            'lastaccess' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return $DB->get_record('local_stackmathgame_profile', ['id' => $id], '*', MUST_EXIST);
    }

    public static function apply_progress(int $profileid, array $changes): \stdClass {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $profile = $DB->get_record('local_stackmathgame_profile', ['id' => $profileid], '*', MUST_EXIST);
        $progress = self::decode_json_field($profile->progressjson ?? '{}');
        $flags = self::decode_json_field($profile->flagsjson ?? '{}');
        $stats = self::decode_json_field($profile->statsjson ?? '{}');

        $profile->score += (int)($changes['scoredelta'] ?? 0);
        $profile->xp += (int)($changes['xpdelta'] ?? 0);
        $profile->softcurrency += (int)($changes['softcurrencydelta'] ?? 0);
        $profile->hardcurrency += (int)($changes['hardcurrencydelta'] ?? 0);
        $profile->levelno = max(1, (int)($changes['levelno'] ?? self::calculate_level_from_xp((int)$profile->xp)));
        $profile->lastquizid = isset($changes['quizid']) ? (int)$changes['quizid'] : $profile->lastquizid;
        $profile->lastdesignid = isset($changes['designid']) ? (int)$changes['designid'] : $profile->lastdesignid;
        $profile->lastaccess = time();
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
        $profile->flagsjson = json_encode($flags, JSON_UNESCAPED_UNICODE);
        $profile->statsjson = json_encode($stats, JSON_UNESCAPED_UNICODE);
        $DB->update_record('local_stackmathgame_profile', $profile);
        $transaction->allow_commit();
        return $DB->get_record('local_stackmathgame_profile', ['id' => $profileid], '*', MUST_EXIST);
    }
}
