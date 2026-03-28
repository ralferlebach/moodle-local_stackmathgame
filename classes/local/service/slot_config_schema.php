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
 * JSON schema constants for the slot-based Regiekarte (configjson).
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

/**
 * Constants and validator for the configjson stored per questionmap slot.
 *
 * Schema: version, enabled, scene.type, branching (per outcome), rewards, narrative, display.
 * See slot_config_schema::defaults() for the canonical empty config.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class slot_config_schema {
    /** Scene type: instruction slide, no answer required. */
    const SCENE_TYPE_INSTRUCTION = 'instruction';
    /** Scene type: standard challenge question. */
    const SCENE_TYPE_CHALLENGE = 'challenge';
    /** Scene type: harder challenge (mini-boss). */
    const SCENE_TYPE_MINIBOSS = 'miniboss';
    /** Scene type: final challenge in a chapter. */
    const SCENE_TYPE_BOSS = 'boss';
    /** Scene type: reward scene, no answer required. */
    const SCENE_TYPE_REWARD = 'reward';
    /** Scene type: transition between chapters. */
    const SCENE_TYPE_TRANSITION = 'transition';
    /** Scene type: final scene of the quiz campaign. */
    const SCENE_TYPE_OUTRO = 'outro';

    /** All valid scene type values. */
    const SCENE_TYPES = [
        self::SCENE_TYPE_INSTRUCTION,
        self::SCENE_TYPE_CHALLENGE,
        self::SCENE_TYPE_MINIBOSS,
        self::SCENE_TYPE_BOSS,
        self::SCENE_TYPE_REWARD,
        self::SCENE_TYPE_TRANSITION,
        self::SCENE_TYPE_OUTRO,
    ];

    /** Branching mode: go to the next slot in linear order. */
    const BRANCH_MODE_LINEAR = 'linear';
    /** Branching mode: jump to a specific slot number. */
    const BRANCH_MODE_SLOT = 'slot';
    /** Branching mode: end the quiz. */
    const BRANCH_MODE_END = 'end';

    /** All valid branching mode values. */
    const BRANCH_MODES = [
        self::BRANCH_MODE_LINEAR,
        self::BRANCH_MODE_SLOT,
        self::BRANCH_MODE_END,
    ];

    /** Outcome: question answered correctly. */
    const OUTCOME_GRADEDRIGHT = 'gradedright';
    /** Outcome: question answered incorrectly. */
    const OUTCOME_GRADEDWRONG = 'gradedwrong';
    /** Outcome: attempt marked complete. */
    const OUTCOME_COMPLETE = 'complete';
    /** Outcome: fallback when no specific outcome applies. */
    const OUTCOME_DEFAULT = 'default';

    /**
     * Parse and normalise a configjson string.
     *
     * Returns null when the JSON is unparseable.
     *
     * @param string $json The raw configjson.
     * @return array|null Normalised config, or null on parse failure.
     */
    public static function parse(string $json): ?array {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        return self::normalise($data);
    }

    /**
     * Build a default configjson array for a new slot.
     *
     * @param string $scenetype One of the SCENE_TYPE_* constants.
     * @return array The default config.
     */
    public static function defaults(string $scenetype = self::SCENE_TYPE_CHALLENGE): array {
        return [
            'version'   => 1,
            'enabled'   => true,
            'scene'     => ['type' => $scenetype],
            'branching' => [
                'gradedright' => ['mode' => self::BRANCH_MODE_LINEAR],
                'gradedwrong' => ['mode' => self::BRANCH_MODE_LINEAR],
                'complete'    => ['mode' => self::BRANCH_MODE_LINEAR],
                'default'     => ['mode' => self::BRANCH_MODE_LINEAR],
            ],
            'rewards'   => [
                'score'           => 0,
                'xp'              => 0,
                'achievementkeys' => [],
                'badgeids'        => [],
                'stash'           => [],
            ],
            'narrative' => ['intro' => '', 'success' => '', 'fail' => ''],
            'display'   => ['showxp' => true, 'showinventory' => false, 'showavatar' => false],
        ];
    }

    /**
     * Validate a parsed config array and return a list of error strings.
     *
     * @param array $config     The normalised config.
     * @param int[] $validslots Slot numbers that exist in the quiz.
     * @return string[] Validation errors (empty = valid).
     */
    public static function validate(array $config, array $validslots = []): array {
        $errors = [];

        $scenetype = (string)($config['scene']['type'] ?? '');
        if (!in_array($scenetype, self::SCENE_TYPES, true)) {
            $errors[] = 'scene.type is invalid: ' . $scenetype;
        }

        $defaultmode = (string)($config['branching']['default']['mode'] ?? '');
        if (!in_array($defaultmode, self::BRANCH_MODES, true)) {
            $errors[] = 'branching.default.mode is missing or invalid.';
        }

        if (!empty($validslots)) {
            foreach ([self::OUTCOME_GRADEDRIGHT, self::OUTCOME_GRADEDWRONG, self::OUTCOME_COMPLETE] as $key) {
                $rule = $config['branching'][$key] ?? [];
                if (($rule['mode'] ?? '') === self::BRANCH_MODE_SLOT) {
                    $target = (int)($rule['target'] ?? 0);
                    if (!in_array($target, $validslots, true)) {
                        $errors[] = "branching.$key.target=$target is not a valid slot.";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Fill missing keys with safe defaults.
     *
     * @param array $data Raw decoded config.
     * @return array Normalised config.
     */
    private static function normalise(array $data): array {
        $defaults = self::defaults();
        $data['version'] = (int)($data['version'] ?? 1);
        $data['enabled'] = isset($data['enabled']) ? (bool)$data['enabled'] : true;
        $data['scene'] = array_merge($defaults['scene'], (array)($data['scene'] ?? []));
        $data['branching'] = array_merge($defaults['branching'], (array)($data['branching'] ?? []));
        $data['rewards'] = array_merge($defaults['rewards'], (array)($data['rewards'] ?? []));
        $data['narrative'] = array_merge($defaults['narrative'], (array)($data['narrative'] ?? []));
        $data['display'] = array_merge($defaults['display'], (array)($data['display'] ?? []));
        return $data;
    }
}
