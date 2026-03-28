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
 * Design record handling for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\game;

use local_stackmathgame\local\packaging\package_registry;

/**
 * Design record handling and theme management.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_manager {
    /** @var array<int,?\stdClass> Runtime cache keyed by design ID. */
    private static array $cache = [];

    /**
     * Get a design record by its slug.
     *
     * @param string $slug The design slug.
     * @return \stdClass|null The design record, or null if not found.
     */
    public static function get_theme_by_slug(string $slug): ?\stdClass {
        global $DB;
        return $DB->get_record('local_stackmathgame_design', ['slug' => $slug]) ?: null;
    }

    /**
     * Purge the in-process design cache.
     *
     * @return void
     */
    public static function purge_cache(): void {
        self::$cache = [];
    }

    /**
     * Retrieve an active design record by its database ID.
     *
     * @param int $designid The design ID.
     * @return \stdClass|null The design record, or null if inactive or not found.
     */
    public static function get_theme(int $designid): ?\stdClass {
        global $DB;
        if (isset(self::$cache[$designid])) {
            return self::$cache[$designid];
        }
        $record = $DB->get_record('local_stackmathgame_design', ['id' => $designid, 'isactive' => 1]);
        self::$cache[$designid] = $record ?: null;
        return self::$cache[$designid];
    }

    /**
     * Build a full runtime configuration array for a design.
     *
     * @param int $designid The design ID (0 to use the first active design).
     * @return array The runtime config array, or empty array if no design exists.
     */
    public static function get_theme_config(int $designid): array {
        global $DB;
        $theme = $designid > 0 ? self::get_theme($designid) : null;
        if (!$theme) {
            $themes = $DB->get_records('local_stackmathgame_design', ['isactive' => 1], 'name ASC', '*', 0, 1);
            $theme  = $themes ? reset($themes) : null;
        }
        if (!$theme) {
            return [];
        }
        $runtimeassets = package_registry::build_runtime_assets(
            (string)$theme->modecomponent,
            (string)$theme->slug
        );
        $modekey     = preg_replace('/^stackmathgamemode_/', '', (string)$theme->modecomponent);
        $slugsafe    = preg_replace('/[^a-z0-9_\-]+/i', '-', (string)$theme->slug);
        $themeclass  = 'smg-mode-' . $modekey . ' smg-design-' . $slugsafe;
        return [
            'design' => [
                'id'            => (int)$theme->id,
                'name'          => (string)$theme->name,
                'slug'          => (string)$theme->slug,
                'modecomponent' => (string)$theme->modecomponent,
                'description'   => (string)($theme->description ?? ''),
                'isbundled'     => (int)$theme->isbundled,
                'isactive'      => (int)$theme->isactive,
            ],
            'narrative'     => json_decode((string)($theme->narrativejson ?? '{}'), true) ?: [],
            'ui'            => json_decode((string)($theme->uijson ?? '{}'), true) ?: [],
            'mechanics'     => json_decode((string)($theme->mechanicsjson ?? '{}'), true) ?: [],
            'assets'        => json_decode((string)($theme->assetmanifestjson ?? '{}'), true) ?: [],
            'runtimeassets' => $runtimeassets,
            'thumbnailurl'  => (string)($runtimeassets['thumbnail'] ?? ''),
            'modekey'       => $modekey,
            'themeclass'    => $themeclass,
        ];
    }

    /**
     * Return all active design records, ordered by name.
     *
     * @return \stdClass[] Array of design records.
     */
    public static function get_all_enabled(): array {
        global $DB;
        return array_values($DB->get_records('local_stackmathgame_design', ['isactive' => 1], 'name ASC'));
    }

    /**
     * Ensure the rpg_default design exists and return its ID.
     *
     * @return int The design ID.
     */
    public static function ensure_default_design(): int {
        global $DB;
        $record = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default'], 'id');
        if ($record) {
            return (int)$record->id;
        }
        self::seed_default_theme();
        $record = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default'], 'id', MUST_EXIST);
        return (int)$record->id;
    }

    /**
     * Return the base URL for shared plugin assets.
     *
     * @param string $slug Unused; kept for API compatibility.
     * @return string The asset base URL.
     */
    public static function asset_base_url(string $slug): string {
        return (string)new \moodle_url('/local/stackmathgame/pix/packages/shared/');
    }

    /**
     * Seed the default RPG design record if it does not already exist.
     *
     * @return void
     */
    public static function seed_default_theme(): void {
        global $DB;
        $now = time();
        $designs = [
            [
                'name'          => 'RPG Default',
                'slug'          => 'rpg_default',
                'modecomponent' => 'stackmathgamemode_rpg',
                'description'   => 'Fantasy-RPG: Mana, Feenbefreiung und Teleportation.',
                'narrativejson' => json_encode([
                    'world_enter' => ['Willkommen, mutiger Held! Das Abenteuer beginnt.'],
                    'victory'     => ['Sehr gut! Du hast das Monster verwandelt.'],
                    'defeat'      => ['Versuche es noch einmal!'],
                    'boss_intro'  => ['Ein mächtiger Endgegner erscheint.'],
                    'boss_clear'  => ['Du hast den Endgegner besiegt!'],
                ], JSON_UNESCAPED_UNICODE),
                'uijson'        => json_encode(['theme' => 'rpg_default'], JSON_UNESCAPED_UNICODE),
                'mechanicsjson' => json_encode(['mode' => 'rpg', 'version' => 1], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'          => 'ExitGame Default',
                'slug'          => 'exitgames_default',
                'modecomponent' => 'stackmathgamemode_exitgames',
                'description'   => 'ExitGame: adaptiv verzweigendes Quiz mit Sprechblasen-Feedback.',
                'narrativejson' => json_encode([
                    'world_enter' => ['Willkommen im Rätselraum. Löse die Aufgaben, um zu entkommen!'],
                    'victory'     => ['Du hast das letzte Schloss geöffnet!'],
                    'defeat'      => ['Schau dir den nächsten Hinweis an.'],
                    'boss_intro'  => ['Die finale Herausforderung wartet.'],
                ], JSON_UNESCAPED_UNICODE),
                'uijson'        => json_encode(['theme' => 'exitgames_default'], JSON_UNESCAPED_UNICODE),
                'mechanicsjson' => json_encode(['mode' => 'exitgames', 'version' => 1], JSON_UNESCAPED_UNICODE),
            ],
            [
                'name'          => 'WiseWizzard Default',
                'slug'          => 'wisewizzard_default',
                'modecomponent' => 'stackmathgamemode_wisewizzard',
                'description'   => 'WiseGuy Tutor: freundlicher Tutor begleitet durchs Tutorial.',
                'narrativejson' => json_encode([
                    'world_enter' => ['Hallo! Ich bin dein Lernbegleiter. Lass uns zusammen üben.'],
                    'victory'     => ['Hervorragend! Du hast es verstanden.'],
                    'defeat'      => ['Nicht ganz – schau dir den Tipp an. Du schaffst das!'],
                ], JSON_UNESCAPED_UNICODE),
                'uijson'        => json_encode(['theme' => 'wisewizzard_default'], JSON_UNESCAPED_UNICODE),
                'mechanicsjson' => json_encode(['mode' => 'wisewizzard', 'version' => 1], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($designs as $design) {
            if ($DB->record_exists('local_stackmathgame_design', ['slug' => $design['slug']])) {
                continue;
            }
            $DB->insert_record('local_stackmathgame_design', (object)array_merge($design, [
                'thumbnailfilename'   => null,
                'thumbnailfileitemid' => null,
                'isbundled'           => 1,
                'isactive'            => 1,
                'versioncode'         => 1,
                'assetmanifestjson'   => json_encode(['source' => 'bundled'], JSON_UNESCAPED_UNICODE),
                'importmetajson'      => json_encode(['origin' => 'seed'], JSON_UNESCAPED_UNICODE),
                'timecreated'         => $now,
                'timemodified'        => $now,
                'createdby'           => null,
                'modifiedby'          => null,
            ]));
        }
        self::purge_cache();
    }
}
