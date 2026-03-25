<?php
namespace local_stackmathgame\game;

defined('MOODLE_INTERNAL') || die();

/**
 * Theme record handling.
 */
class theme_manager {
    /** @var array<int,?\stdClass> */
    private static array $cache = [];

    public static function purge_cache(): void {
        self::$cache = [];
    }

    public static function get_theme(int $themeid): ?\stdClass {
        global $DB;

        if (isset(self::$cache[$themeid])) {
            return self::$cache[$themeid];
        }

        $record = $DB->get_record('local_stackmathgame_theme', ['id' => $themeid, 'enabled' => 1]);
        self::$cache[$themeid] = $record ?: null;
        return self::$cache[$themeid];
    }

    public static function get_theme_config(int $themeid): array {
        global $DB;

        $theme = $themeid > 0 ? self::get_theme($themeid) : null;
        if (!$theme) {
            $theme = $DB->get_records('local_stackmathgame_theme', ['enabled' => 1], 'sortorder ASC', '*', 0, 1);
            $theme = $theme ? reset($theme) : null;
        }

        if (!$theme || empty($theme->configjson)) {
            return [];
        }

        return json_decode($theme->configjson, true) ?: [];
    }

    /**
     * @return \stdClass[]
     */
    public static function get_all_enabled(): array {
        global $DB;
        return array_values($DB->get_records('local_stackmathgame_theme', ['enabled' => 1], 'sortorder ASC'));
    }

    public static function asset_base_url(string $shortname): string {
        return (string)new \moodle_url('/local/stackmathgame/pix/themes/' . clean_param($shortname, PARAM_SAFEDIR) . '/');
    }

    public static function seed_default_theme(): void {
        global $DB;

        if ($DB->record_exists('local_stackmathgame_theme', ['shortname' => 'fantasy'])) {
            return;
        }

        $now = time();
        $DB->insert_record('local_stackmathgame_theme', (object)[
            'name' => 'Fantasy Adventure',
            'shortname' => 'fantasy',
            'configjson' => json_encode(self::default_fantasy_config(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'isbuiltin' => 1,
            'enabled' => 1,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        self::purge_cache();
    }

    public static function default_fantasy_config(): array {
        return [
            'description' => 'Default fantasy-themed wrapper for STACK Math Game.',
            'backgrounds' => [
                'city' => 'bg-elven_land4.png',
                'battle' => 'bg-forest1.png',
                'clearing' => 'bg-forest4.png',
            ],
            'ui' => [
                'flag' => 'flag.svg',
                'skull' => 'skull.svg',
                'house' => 'house-solid.svg',
                'fairy' => 'fairy.svg',
            ],
        ];
    }
}
