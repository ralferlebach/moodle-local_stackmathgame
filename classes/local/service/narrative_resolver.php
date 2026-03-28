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
 * Narrative resolver service for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

/**
 * Resolves narrative text from a design's narrativejson for a given scene key.
 *
 * Canonical scene keys (defined as class constants) are the only slots that
 * GameDesigners should populate. Lehrende cannot edit narrativejson directly;
 * the design_edit_form guards the field behind the managenarratives capability.
 *
 * Usage:
 *   $lines = narrative_resolver::resolve($design, 'world_enter');
 *   $text  = narrative_resolver::resolve_as_string($design, 'victory');
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class narrative_resolver {
    // ── Canonical scene keys ────────────────────────────────────────────────
    // These are the standardised slot names that GameDesigners populate in
    // The Design Studio. Any additional keys are silently ignored by the UI
    // But still accessible via the resolver.

    /** Student first enters the world / campaign. */
    const SCENE_WORLD_ENTER = 'world_enter';

    /** Student successfully answers a question. */
    const SCENE_VICTORY = 'victory';

    /** Student answers incorrectly. */
    const SCENE_DEFEAT = 'defeat';

    /** A boss encounter begins. */
    const SCENE_BOSS_INTRO = 'boss_intro';

    /** A boss is defeated. */
    const SCENE_BOSS_CLEAR = 'boss_clear';

    /** An item or reward is granted. */
    const SCENE_REWARD = 'reward';

    /** Transition between sections / chapters. */
    const SCENE_TRANSITION = 'transition';

    /** Final scene; campaign or quiz completed. */
    const SCENE_OUTRO = 'outro';

    /** Shown at the start of a new chapter or section. */
    const SCENE_CHAPTER_START = 'chapter_start';

    /**
     * All canonical scene keys in recommended display order.
     *
     * @return string[]
     */
    public static function canonical_scenes(): array {
        return [
            self::SCENE_WORLD_ENTER,
            self::SCENE_CHAPTER_START,
            self::SCENE_VICTORY,
            self::SCENE_DEFEAT,
            self::SCENE_BOSS_INTRO,
            self::SCENE_BOSS_CLEAR,
            self::SCENE_REWARD,
            self::SCENE_TRANSITION,
            self::SCENE_OUTRO,
        ];
    }

    /**
     * Validate that a scene key is canonical.
     *
     * Non-canonical keys are accepted by the resolver (designs may extend
     * the schema) but are not guaranteed to be displayed in the Studio UI.
     *
     * @param string $scene The scene key to validate.
     * @return bool True if the key is one of the canonical constants.
     */
    public static function is_canonical(string $scene): bool {
        return in_array($scene, self::canonical_scenes(), true);
    }

    /**
     * Resolve narrative lines for a scene from a design record.
     *
     * Returns an array of non-empty strings. The value stored in narrativejson
     * may be a string (returned as a single-element array) or an array of
     * strings (filtered for empty values). Returns an empty array when the
     * scene is not defined in the design.
     *
     * @param \stdClass|null $design The design record (must have narrativejson).
     * @param string         $scene  The scene key to resolve.
     * @return string[] Array of narrative lines (may be empty).
     */
    public static function resolve(?\stdClass $design, string $scene): array {
        if (!$design) {
            return [];
        }
        $narrative = json_decode((string)($design->narrativejson ?? '{}'), true);
        if (!is_array($narrative)) {
            return [];
        }
        $raw = $narrative[$scene] ?? null;
        if ($raw === null) {
            return [];
        }
        $lines = is_array($raw) ? $raw : [$raw];
        return array_values(array_filter(
            array_map('strval', $lines),
            static function (string $line): bool {
                return trim($line) !== '';
            }
        ));
    }

    /**
     * Resolve narrative lines and join them into a single string.
     *
     * @param \stdClass|null $design    The design record.
     * @param string         $scene     The scene key to resolve.
     * @param string         $separator Separator placed between lines (default: space).
     * @return string The joined narrative text, or empty string if not defined.
     */
    public static function resolve_as_string(
        ?\stdClass $design,
        string $scene,
        string $separator = ' '
    ): string {
        return implode($separator, self::resolve($design, $scene));
    }

    /**
     * Return the full narrative map for a design, keyed by scene.
     *
     * Only canonical scenes are included. Missing scenes return an empty array.
     *
     * @param \stdClass|null $design The design record.
     * @return array<string, string[]> Map of scene key to lines array.
     */
    public static function resolve_all(?\stdClass $design): array {
        $result = [];
        foreach (self::canonical_scenes() as $scene) {
            $result[$scene] = self::resolve($design, $scene);
        }
        return $result;
    }
}
