<?php
namespace local_stackmathgame\local\service;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;

/**
 * Handles label-bound profile state.
 */
class profile_service {
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
        $profile = $DB->get_record('local_stackmathgame_profile', ['id' => $profileid], '*', MUST_EXIST);
        $progress = json_decode((string)$profile->progressjson, true) ?: [];
        $flags = json_decode((string)$profile->flagsjson, true) ?: [];
        $stats = json_decode((string)$profile->statsjson, true) ?: [];

        $profile->score += (int)($changes['scoredelta'] ?? 0);
        $profile->xp += (int)($changes['xpdelta'] ?? 0);
        $profile->softcurrency += (int)($changes['softcurrencydelta'] ?? 0);
        $profile->hardcurrency += (int)($changes['hardcurrencydelta'] ?? 0);
        $profile->levelno = max(1, (int)($changes['levelno'] ?? $profile->levelno));
        $profile->lastquizid = isset($changes['quizid']) ? (int)$changes['quizid'] : $profile->lastquizid;
        $profile->lastdesignid = isset($changes['designid']) ? (int)$changes['designid'] : $profile->lastdesignid;
        $profile->lastaccess = time();
        $profile->timemodified = $profile->lastaccess;

        if (array_key_exists('progress', $changes)) {
            $incoming = (array)$changes['progress'];
            $progress = array_replace_recursive($progress, $incoming);
        }
        if (array_key_exists('flags', $changes)) {
            $incoming = (array)$changes['flags'];
            $flags = array_replace_recursive($flags, $incoming);
        }
        if (array_key_exists('stats', $changes)) {
            $incoming = (array)$changes['stats'];
            $stats = array_replace_recursive($stats, $incoming);
        }

        $profile->progressjson = json_encode($progress, JSON_UNESCAPED_UNICODE);
        $profile->flagsjson = json_encode($flags, JSON_UNESCAPED_UNICODE);
        $profile->statsjson = json_encode($stats, JSON_UNESCAPED_UNICODE);
        $DB->update_record('local_stackmathgame_profile', $profile);
        return $DB->get_record('local_stackmathgame_profile', ['id' => $profileid], '*', MUST_EXIST);
    }
}
