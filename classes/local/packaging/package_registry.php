<?php
namespace local_stackmathgame\local\packaging;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads bundled mode package manifests and resolves local asset URLs.
 */
class package_registry {
    /** @var array<string,array> */
    private static array $manifestcache = [];

    public static function get_bundled_package(string $modecomponent, string $slug): ?array {
        $key = $modecomponent . '::' . $slug;
        if (array_key_exists($key, self::$manifestcache)) {
            return self::$manifestcache[$key] ?: null;
        }
        [$modepath, $modekey] = self::resolve_mode_path($modecomponent);
        if ($modepath === '' || !is_dir($modepath)) {
            self::$manifestcache[$key] = [];
            return null;
        }
        $pkgpath = $modepath . '/packages/' . $slug;
        $manifestfile = $pkgpath . '/manifest.json';
        if (!is_readable($manifestfile)) {
            self::$manifestcache[$key] = [];
            return null;
        }
        $manifest = json_decode((string)file_get_contents($manifestfile), true) ?: [];
        if (!$manifest) {
            self::$manifestcache[$key] = [];
            return null;
        }
        $manifest['_modekey'] = $modekey;
        $manifest['_modepath'] = $modepath;
        $manifest['_packagepath'] = $pkgpath;
        self::$manifestcache[$key] = $manifest;
        return $manifest;
    }

    public static function build_runtime_assets(string $modecomponent, string $slug): array {
        $manifest = self::get_bundled_package($modecomponent, $slug);
        if (!$manifest) {
            return [];
        }
        $modekey = (string)($manifest['_modekey'] ?? '');
        $assets = [];
        $slots = (array)($manifest['assetslots'] ?? []);
        foreach ($slots as $assetkey => $relativepath) {
            $assets[(string)$assetkey] = self::build_asset_url($modekey, $slug, (string)$relativepath);
        }
        if (!empty($manifest['thumbnail'])) {
            $assets['thumbnail'] = self::build_asset_url($modekey, $slug, (string)$manifest['thumbnail']);
        }
        return $assets;
    }

    private static function build_asset_url(string $modekey, string $slug, string $relativepath): string {
        $relativepath = ltrim($relativepath, '/');
        return (new \moodle_url('/local/stackmathgame/mode/' . $modekey . '/packages/' . $slug . '/' . $relativepath))->out(false);
    }

    private static function resolve_mode_path(string $modecomponent): array {
        global $CFG;
        $modekey = preg_replace('/^stackmathgamemode_/', '', $modecomponent);
        if (!$modekey) {
            return ['', ''];
        }
        $path = $CFG->dirroot . '/local/stackmathgame/mode/' . $modekey;
        return [$path, $modekey];
    }
}
