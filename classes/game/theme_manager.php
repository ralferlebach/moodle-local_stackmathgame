<?php
namespace local_stackmathgame\game;

defined('MOODLE_INTERNAL') || die();

/**
 * Design record handling.
 */
class theme_manager {
    /** @var array<int,?\stdClass> */
    private static array $cache = [];

    public static function purge_cache(): void {
        self::$cache = [];
    }

    public static function get_theme(int $designid): ?\stdClass {
        global $DB;
        if (isset(self::$cache[$designid])) {
            return self::$cache[$designid];
        }
        $record = $DB->get_record('local_stackmathgame_design', ['id' => $designid, 'isactive' => 1]);
        self::$cache[$designid] = $record ?: null;
        return self::$cache[$designid];
    }

    public static function get_theme_config(int $designid): array {
        global $DB;
        $theme = $designid > 0 ? self::get_theme($designid) : null;
        if (!$theme) {
            $themes = $DB->get_records('local_stackmathgame_design', ['isactive' => 1], 'name ASC', '*', 0, 1);
            $theme = $themes ? reset($themes) : null;
        }
        if (!$theme) {
            return [];
        }
        return [
            'design' => [
                'id' => (int)$theme->id,
                'name' => (string)$theme->name,
                'slug' => (string)$theme->slug,
                'modecomponent' => (string)$theme->modecomponent,
                'description' => (string)($theme->description ?? ''),
                'isbundled' => (int)$theme->isbundled,
                'isactive' => (int)$theme->isactive,
            ],
            'narrative' => json_decode((string)($theme->narrativejson ?? '{}'), true) ?: [],
            'ui' => json_decode((string)($theme->uijson ?? '{}'), true) ?: [],
            'mechanics' => json_decode((string)($theme->mechanicsjson ?? '{}'), true) ?: [],
            'assets' => json_decode((string)($theme->assetmanifestjson ?? '{}'), true) ?: [],
        ];
    }

    /**
     * @return \stdClass[]
     */
    public static function get_all_enabled(): array {
        global $DB;
        return array_values($DB->get_records('local_stackmathgame_design', ['isactive' => 1], 'name ASC'));
    }

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

    public static function asset_base_url(string $slug): string {
        return (string)new \moodle_url('/local/stackmathgame/pix/packages/shared/');
    }

    public static function seed_default_theme(): void {
        global $DB;
        if ($DB->record_exists('local_stackmathgame_design', ['slug' => 'rpg_default'])) {
            return;
        }
        $now = time();
        $DB->insert_record('local_stackmathgame_design', (object)[
            'name' => 'RPG Default',
            'slug' => 'rpg_default',
            'modecomponent' => 'stackmathgamemode_rpg',
            'description' => 'Default RPG design bundled with local_stackmathgame.',
            'thumbnailfilename' => null,
            'thumbnailfileitemid' => null,
            'isbundled' => 1,
            'isactive' => 1,
            'versioncode' => 1,
            'narrativejson' => json_encode([
                'world_enter' => ['Welcome to the adventure.'],
                'victory' => ['Well done.'],
                'defeat' => ['Try again.'],
            ], JSON_UNESCAPED_UNICODE),
            'uijson' => json_encode(['theme' => 'rpg_default'], JSON_UNESCAPED_UNICODE),
            'mechanicsjson' => json_encode(['version' => 1], JSON_UNESCAPED_UNICODE),
            'assetmanifestjson' => json_encode(['source' => 'bundled'], JSON_UNESCAPED_UNICODE),
            'importmetajson' => json_encode(['origin' => 'seed'], JSON_UNESCAPED_UNICODE),
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => null,
            'modifiedby' => null,
        ]);
        self::purge_cache();
    }
}
