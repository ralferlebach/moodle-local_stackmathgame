<?php
namespace local_stackmathgame\game;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\local\packaging\package_registry;

/**
 * Design record handling.
 *
 * Legacy class name retained while schema uses local_stackmathgame_design.
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

        $record = $DB->get_record('local_stackmathgame_design', ['id' => $themeid, 'isactive' => 1]);
        self::$cache[$themeid] = $record ?: null;
        return self::$cache[$themeid];
    }

    public static function get_theme_config(int $themeid): array {
        global $DB;

        $theme = $themeid > 0 ? self::get_theme($themeid) : null;
        if (!$theme) {
            $theme = $DB->get_records('local_stackmathgame_design', ['isactive' => 1], 'name ASC', '*', 0, 1);
            $theme = $theme ? reset($theme) : null;
        }

        if (!$theme || empty($theme->uijson)) {
            return [];
        }

        return json_decode((string)$theme->uijson, true) ?: [];
    }

    /**
     * @return \stdClass[]
     */
    public static function get_all_enabled(): array {
        global $DB;
        return array_values($DB->get_records('local_stackmathgame_design', ['isactive' => 1], 'name ASC'));
    }

    public static function asset_base_url(string $slug): string {
        global $CFG;
        $package = package_registry::get_bundled_design_package_by_slug($slug);
        if ($package) {
            $relative = str_replace($CFG->dirroot . '/', '', $package['packagedir']);
            return (string)new \moodle_url('/' . $relative . '/assets/');
        }

        return (string)new \moodle_url('/local/stackmathgame/pix/packages/shared/');
    }

    public static function seed_default_themes(): void {
        global $DB;

        $now = time();
        foreach (package_registry::get_bundled_design_packages() as $package) {
            if ($DB->record_exists('local_stackmathgame_design', ['slug' => $package['slug']])) {
                continue;
            }

            $DB->insert_record('local_stackmathgame_design', (object)[
                'name' => $package['name'],
                'slug' => $package['slug'],
                'modecomponent' => $package['modecomponent'],
                'description' => $package['description'],
                'thumbnailfilename' => $package['manifest']['thumbnail'] ?? null,
                'thumbnailfileitemid' => null,
                'isbundled' => 1,
                'isactive' => 1,
                'versioncode' => $package['versioncode'],
                'narrativejson' => $package['narrativejson'],
                'uijson' => $package['uijson'],
                'mechanicsjson' => $package['mechanicsjson'],
                'assetmanifestjson' => $package['assetmanifestjson'],
                'importmetajson' => $package['importmetajson'],
                'timecreated' => $now,
                'timemodified' => $now,
                'createdby' => null,
                'modifiedby' => null,
            ]);
        }

        self::purge_cache();
    }
}
