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
 * Shortcode callbacks for filter_shortcodes.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame;

use context;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\service\narrative_resolver;
use local_stackmathgame\local\service\profile_service;

/**
 * Shortcode callback implementations for STACK Math Game.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortcodes {
    /**
     * Resolve the quiz ID from the shortcode filter environment context.
     *
     * @param object $env The shortcode environment object.
     * @return int|null The quiz instance ID, or null if not resolvable.
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
        $sql    = "SELECT cm.instance
                     FROM {course_modules} cm
                     JOIN {modules} m ON m.id = cm.module
                    WHERE cm.id = :cmid AND m.name = :modname";
        $quizid = $DB->get_field_sql($sql, ['cmid' => (int)$context->instanceid, 'modname' => 'quiz']);
        return $quizid ? (int)$quizid : null;
    }

    /**
     * Resolve a label record from shortcode args or quiz context.
     *
     * @param array  $args The shortcode arguments.
     * @param object $env  The shortcode environment object.
     * @return \stdClass|null The label record, or null if not resolvable.
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
        $cmid = quiz_configurator::cmid_from_quizid($quizid);
        if ($cmid <= 0) {
            return null;
        }
        $config = quiz_configurator::ensure_default($cmid);
        return $DB->get_record('local_stackmathgame_label', ['id' => (int)$config->labelid]) ?: null;
    }

    /**
     * Resolve the current user's profile from shortcode args or quiz context.
     *
     * @param array  $args The shortcode arguments.
     * @param object $env  The shortcode environment object.
     * @return \stdClass|null The profile record, or null if not resolvable.
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
     * @param array        $args    The shortcode arguments.
     * @param object       $env     The shortcode environment object.
     * @param \stdClass|null $profile The resolved profile, if any.
     * @return \stdClass|null The design record, or null if not resolvable.
     */
    protected static function resolve_design(array $args, object $env, ?\stdClass $profile): ?\stdClass {
        $quizid = self::resolve_quizid_from_env($env);
        if ($quizid) {
            $cmid = quiz_configurator::cmid_from_quizid($quizid);
            if ($cmid <= 0) {
                return null;
            }
            $config = quiz_configurator::ensure_default($cmid);
            return theme_manager::get_theme((int)$config->designid);
        }
        if (!empty($args['design'])) {
            return theme_manager::get_theme_by_slug(trim((string)$args['design']));
        }
        if ($profile && !empty($profile->lastdesignid)) {
            return theme_manager::get_theme((int)$profile->lastdesignid);
        }
        return null;
    }

    /**
     * Return a field value from the profile summary or raw profile record.
     *
     * @param \stdClass|null $profile The profile record.
     * @param string         $field   The field name to retrieve.
     * @return string The field value as a string, or empty string if not found.
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

    /**
     * Render the current score for a game label.
     *
     * @param string      $shortcode The shortcode name.
     * @param array       $args      The shortcode arguments.
     * @param string|null $content   Inner content (unused).
     * @param object      $env       The shortcode environment.
     * @param callable    $next      The next filter callback.
     * @return string The rendered value.
     */
    public static function score(
        string $shortcode,
        array $args,
        ?string $content,
        object $env,
        callable $next
    ): string {
        $field   = trim((string)($args['field'] ?? 'score'));
        $profile = self::resolve_profile($args, $env);
        if ($field !== 'score') {
            return self::profile_field($profile, $field);
        }
        return $profile ? (string)$profile->score : '0';
    }

    /**
     * Render the current XP for a game label.
     *
     * @param string      $shortcode The shortcode name.
     * @param array       $args      The shortcode arguments.
     * @param string|null $content   Inner content (unused).
     * @param object      $env       The shortcode environment.
     * @param callable    $next      The next filter callback.
     * @return string The rendered value.
     */
    public static function xp(
        string $shortcode,
        array $args,
        ?string $content,
        object $env,
        callable $next
    ): string {
        $field   = trim((string)($args['field'] ?? 'xp'));
        $profile = self::resolve_profile($args, $env);
        if ($field !== 'xp') {
            return self::profile_field($profile, $field);
        }
        return $profile ? (string)$profile->xp : '0';
    }

    /**
     * Render the current level for a game label.
     *
     * @param string      $shortcode The shortcode name.
     * @param array       $args      The shortcode arguments.
     * @param string|null $content   Inner content (unused).
     * @param object      $env       The shortcode environment.
     * @param callable    $next      The next filter callback.
     * @return string The rendered value.
     */
    public static function level(
        string $shortcode,
        array $args,
        ?string $content,
        object $env,
        callable $next
    ): string {
        $field   = trim((string)($args['field'] ?? 'levelno'));
        $profile = self::resolve_profile($args, $env);
        if ($field !== 'levelno' && $field !== 'level') {
            return self::profile_field($profile, $field);
        }
        return $profile ? (string)$profile->levelno : '0';
    }

    /**
     * Render the progress summary for a game label.
     *
     * @param string      $shortcode The shortcode name.
     * @param array       $args      The shortcode arguments.
     * @param string|null $content   Inner content (unused).
     * @param object      $env       The shortcode environment.
     * @param callable    $next      The next filter callback.
     * @return string The rendered value.
     */
    public static function progress(
        string $shortcode,
        array $args,
        ?string $content,
        object $env,
        callable $next
    ): string {
        $profile = self::resolve_profile($args, $env);
        if (!$profile) {
            return '';
        }
        $format  = trim((string)($args['format'] ?? 'summary'));
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
        $solved  = max(0, (int)($summary['solvedcount'] ?? 0));
        if ($tracked === 0) {
            return '0%';
        }
        return (int)floor(($solved / $tracked) * 100) . '%';
    }

    /**
     * Render narrative content for a scene from the active design.
     *
     * @param string      $shortcode The shortcode name.
     * @param array       $args      The shortcode arguments.
     * @param string|null $content   Inner content wrapped in the narrative.
     * @param object      $env       The shortcode environment.
     * @param callable    $next      The next filter callback.
     * @return string The rendered narrative text.
     */
    public static function narrative(
        string $shortcode,
        array $args,
        ?string $content,
        object $env,
        callable $next
    ): string {
        $profile   = self::resolve_profile($args, $env);
        $design    = self::resolve_design($args, $env, $profile);
        $scene     = trim((string)($args['scene'] ?? narrative_resolver::SCENE_WORLD_ENTER));
        $separator = (string)($args['separator'] ?? ' ');
        $lines     = narrative_resolver::resolve($design, $scene);
        if ($content !== null && trim($content) !== '') {
            array_unshift($lines, $next($content));
        }
        if (empty($lines)) {
            return $content !== null ? $next($content) : '';
        }
        return implode($separator, $lines);
    }

    /**
     * Render the avatar configuration for the current profile.
     *
     * @param string      $shortcode The shortcode name.
     * @param array       $args      The shortcode arguments.
     * @param string|null $content   Inner content (unused).
     * @param object      $env       The shortcode environment.
     * @param callable    $next      The next filter callback.
     * @return string The rendered avatar data.
     */
    public static function avatar(
        string $shortcode,
        array $args,
        ?string $content,
        object $env,
        callable $next
    ): string {
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

    /**
     * Render a leaderboard for a game label.
     *
     * @param string      $shortcode The shortcode name.
     * @param array       $args      The shortcode arguments.
     * @param string|null $content   Inner content (unused).
     * @param object      $env       The shortcode environment.
     * @param callable    $next      The next filter callback.
     * @return string HTML ordered list leaderboard.
     */
    public static function leaderboard(
        string $shortcode,
        array $args,
        ?string $content,
        object $env,
        callable $next
    ): string {
        global $DB;
        $label = self::resolve_label($args, $env);
        if (!$label) {
            return '';
        }
        $limit   = min(20, max(1, (int)($args['limit'] ?? 10)));
        $records = $DB->get_records(
            'local_stackmathgame_profile',
            ['labelid' => $label->id],
            'score DESC, xp DESC, id ASC',
            '*',
            0,
            $limit
        );
        if (!$records) {
            return '';
        }
        $items = [];
        foreach ($records as $record) {
            $user  = $DB->get_record('user', ['id' => (int)$record->userid], 'id,firstname,lastname');
            $name  = $user ? fullname($user) : get_string('deleteduser');
            $items[] = '<li>' . s($name) . ': ' . (int)$record->score . ' / ' . (int)$record->xp . ' XP</li>';
        }
        return '<ol class="smg-shortcode-leaderboard">' . implode('', $items) . '</ol>';
    }
}
