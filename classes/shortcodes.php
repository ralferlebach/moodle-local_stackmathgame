<?php
namespace local_stackmathgame;

defined('MOODLE_INTERNAL') || die();

/**
 * Shortcode callbacks for filter_shortcodes.
 */
class shortcodes {
    /**
     * Resolve a profile from shortcode args.
     *
     * @param array $args
     * @return \stdClass|null
     */
    protected static function resolve_profile(array $args): ?\stdClass {
        global $DB, $USER;

        $labelname = trim((string)($args['label'] ?? ''));
        if ($labelname === '') {
            return null;
        }

        $label = $DB->get_record('local_stackmathgame_label', ['name' => $labelname]);
        if (!$label) {
            return null;
        }

        return $DB->get_record('local_stackmathgame_profile', [
            'userid' => $USER->id,
            'labelid' => $label->id,
        ]) ?: null;
    }

    public static function score(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $profile = self::resolve_profile($args);
        return $profile ? (string)$profile->score : '0';
    }

    public static function xp(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $profile = self::resolve_profile($args);
        return $profile ? (string)$profile->xp : '0';
    }

    public static function level(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $profile = self::resolve_profile($args);
        return $profile ? (string)$profile->levelno : '0';
    }

    public static function progress(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $profile = self::resolve_profile($args);
        if (!$profile || empty($profile->progressjson)) {
            return '';
        }
        return (string)$profile->progressjson;
    }

    public static function narrative(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        return $content !== null ? $next($content) : '';
    }

    public static function avatar(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $profile = self::resolve_profile($args);
        if (!$profile || empty($profile->avatarconfigjson)) {
            return '';
        }
        return (string)$profile->avatarconfigjson;
    }

    public static function leaderboard(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        global $DB;

        $labelname = trim((string)($args['label'] ?? ''));
        if ($labelname === '') {
            return '';
        }
        $label = $DB->get_record('local_stackmathgame_label', ['name' => $labelname]);
        if (!$label) {
            return '';
        }
        $records = $DB->get_records('local_stackmathgame_profile', ['labelid' => $label->id], 'score DESC', '*', 0, 10);
        if (!$records) {
            return '';
        }
        $lines = [];
        foreach ($records as $record) {
            $lines[] = (string)$record->score;
        }
        return implode(', ', $lines);
    }
}
