<?php
namespace local_stackmathgame;

defined('MOODLE_INTERNAL') || die();

use context;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\service\profile_service;

/**
 * Shortcode callbacks for filter_shortcodes.
 */
class shortcodes {
    /**
     * Resolve quiz id from filter context when possible.
     *
     * @param object $env
     * @return int|null
     */
    protected static function resolve_quizid_from_env(object $env): ?int {
        global $DB;

        if (empty($env->context) || !($env->context instanceof context)) {
            return null;
        }
        $context = $env->context;
        if ((int)$context->contextlevel !== CONTEXT_MODULE) {
            return null;
        }
        $sql = "SELECT cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid AND m.name = :modname";
        $quizid = $DB->get_field_sql($sql, ['cmid' => (int)$context->instanceid, 'modname' => 'quiz']);
        return $quizid ? (int)$quizid : null;
    }

    /**
     * Resolve a label record from shortcode args or quiz context.
     *
     * @param array $args
     * @param object $env
     * @return \stdClass|null
     */
    protected static function resolve_label(array $args, object $env): ?\stdClass {
        global $DB;

        $labelname = trim((string)($args['label'] ?? ''));
        if ($labelname !== '') {
            $label = $DB->get_record('local_stackmathgame_label', ['name' => $labelname]);
            if ($label) {
                return $label;
            }
            return $DB->get_record('local_stackmathgame_label', ['idnumber' => $labelname]) ?: null;
        }

        $quizid = self::resolve_quizid_from_env($env);
        if (!$quizid) {
            return null;
        }

        $config = quiz_configurator::ensure_default($quizid);
        return $DB->get_record('local_stackmathgame_label', ['id' => (int)$config->labelid]) ?: null;
    }

    /**
     * Resolve the current user's profile from shortcode args or quiz context.
     *
     * @param array $args
     * @param object $env
     * @return \stdClass|null
     */
    protected static function resolve_profile(array $args, object $env): ?\stdClass {
        global $USER;
        if (empty($USER->id)) {
            return null;
        }
        $label = self::resolve_label($args, $env);
        if (!$label) {
            return null;
        }

        $quizid = self::resolve_quizid_from_env($env);
        if ($quizid) {
            return profile_service::get_or_create((int)$USER->id, (int)$label->id, $quizid);
        }
        return profile_service::get_or_create((int)$USER->id, (int)$label->id);
    }

    /**
     * Resolve the active design for the shortcode context.
     *
     * @param array $args
     * @param object $env
     * @param \stdClass|null $profile
     * @return \stdClass|null
     */
    protected static function resolve_design(array $args, object $env, ?\stdClass $profile): ?\stdClass {
        $quizid = self::resolve_quizid_from_env($env);
        if ($quizid) {
            $config = quiz_configurator::ensure_default($quizid);
            return theme_manager::get_theme((int)$config->designid);
        }
        if (!empty($args['design'])) {
            $slug = trim((string)$args['design']);
            return theme_manager::get_theme_by_slug($slug);
        }
        if ($profile && !empty($profile->lastdesignid)) {
            return theme_manager::get_theme((int)$profile->lastdesignid);
        }
        return null;
    }

    /**
     * Return a field from profile summary or raw profile.
     *
     * @param \stdClass|null $profile
     * @param string $field
     * @return string
     */
    protected static function profile_field(?\stdClass $profile, string $field): string {
        if (!$profile) {
            return '';
        }
        $summary = profile_service::build_summary($profile);
        if (array_key_exists($field, $summary)) {
            return (string)$summary[$field];
        }
        if (property_exists($profile, $field)) {
            return (string)$profile->{$field};
        }
        return '';
    }

    public static function score(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $field = trim((string)($args['field'] ?? 'score'));
        $profile = self::resolve_profile($args, $env);
        if ($field !== 'score') {
            return self::profile_field($profile, $field);
        }
        return $profile ? (string)$profile->score : '0';
    }

    public static function xp(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $field = trim((string)($args['field'] ?? 'xp'));
        $profile = self::resolve_profile($args, $env);
        if ($field !== 'xp') {
            return self::profile_field($profile, $field);
        }
        return $profile ? (string)$profile->xp : '0';
    }

    public static function level(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $field = trim((string)($args['field'] ?? 'levelno'));
        $profile = self::resolve_profile($args, $env);
        if ($field !== 'levelno' && $field !== 'level') {
            return self::profile_field($profile, $field);
        }
        return $profile ? (string)$profile->levelno : '0';
    }

    public static function progress(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $profile = self::resolve_profile($args, $env);
        if (!$profile) {
            return '';
        }
        $format = trim((string)($args['format'] ?? 'summary'));
        $summary = profile_service::build_summary($profile);
        if (!empty($args['field'])) {
            return self::profile_field($profile, trim((string)$args['field']));
        }
        if ($format === 'json') {
            return json_encode($summary, JSON_UNESCAPED_UNICODE);
        }
        if ($format === 'raw') {
            return (string)($profile->progressjson ?? '{}');
        }
        $tracked = max(0, (int)($summary['trackedslots'] ?? 0));
        $solved = max(0, (int)($summary['solvedcount'] ?? 0));
        if ($tracked === 0) {
            return '0%';
        }
        $percent = (int)floor(($solved / $tracked) * 100);
        return $percent . '%';
    }

    public static function narrative(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $profile = self::resolve_profile($args, $env);
        $design = self::resolve_design($args, $env, $profile);
        if (!$design) {
            return $content !== null ? $next($content) : '';
        }
        $narrative = json_decode((string)($design->narrativejson ?? '{}'), true) ?: [];
        $scene = trim((string)($args['scene'] ?? 'world_enter'));
        $lines = $narrative[$scene] ?? [];
        if (!is_array($lines)) {
            $lines = [$lines];
        }
        $lines = array_values(array_filter(array_map('strval', $lines), static function(string $line): bool {
            return trim($line) !== '';
        }));
        if ($content !== null && trim($content) !== '') {
            array_unshift($lines, $next($content));
        }
        if (empty($lines)) {
            return '';
        }
        $separator = (string)($args['separator'] ?? ' ');
        return implode($separator, $lines);
    }

    public static function avatar(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        $profile = self::resolve_profile($args, $env);
        if (!$profile || empty($profile->avatarconfigjson)) {
            return '';
        }
        $field = trim((string)($args['field'] ?? ''));
        if ($field === '') {
            return (string)$profile->avatarconfigjson;
        }
        $data = json_decode((string)$profile->avatarconfigjson, true) ?: [];
        return isset($data[$field]) ? (string)$data[$field] : '';
    }

    public static function leaderboard(string $shortcode, array $args, ?string $content, object $env, callable $next): string {
        global $DB;

        $label = self::resolve_label($args, $env);
        if (!$label) {
            return '';
        }
        $limit = min(20, max(1, (int)($args['limit'] ?? 10)));
        $records = $DB->get_records('local_stackmathgame_profile', ['labelid' => $label->id], 'score DESC, xp DESC, id ASC', '*', 0, $limit);
        if (!$records) {
            return '';
        }
        $items = [];
        foreach ($records as $record) {
            $user = $DB->get_record('user', ['id' => (int)$record->userid], 'id,firstname,lastname');
            $name = $user ? fullname($user) : get_string('deleteduser');
            $items[] = '<li>' . s($name) . ': ' . (int)$record->score . ' / ' . (int)$record->xp . ' XP</li>';
        }
        return '<ol class="smg-shortcode-leaderboard">' . implode('', $items) . '</ol>';
    }
}
