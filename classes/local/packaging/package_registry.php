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
 * Package registry for bundled mode packages.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\packaging;

/**
 * Reads bundled mode package manifests and resolves local asset URLs.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_registry {
    /** @var array<string, array> Runtime manifest cache. */
    private static array $manifestcache = [];

    /**
     * Load and return the manifest for a bundled mode package.
     *
     * @param string $modecomponent The mode component name (e.g. 'stackmathgamemode_rpg').
     * @param string $slug          The design slug (e.g. 'rpg_default').
     * @return array|null The manifest array, or null if not found.
     */
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
        $pkgpath      = $modepath . '/packages/' . $slug;
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
        $manifest['_modekey']     = $modekey;
        $manifest['_modepath']    = $modepath;
        $manifest['_packagepath'] = $pkgpath;
        self::$manifestcache[$key] = $manifest;
        return $manifest;
    }

    /**
     * Build an asset URL map from a package manifest.
     *
     * @param string $modecomponent The mode component name.
     * @param string $slug          The design slug.
     * @return array<string, string> Map of asset key => URL.
     */
    public static function build_runtime_assets(string $modecomponent, string $slug): array {
        $manifest = self::get_bundled_package($modecomponent, $slug);
        if (!$manifest) {
            return [];
        }
        $modekey = (string)($manifest['_modekey'] ?? '');
        $assets  = [];
        foreach ((array)($manifest['assetslots'] ?? []) as $assetkey => $relativepath) {
            $assets[(string)$assetkey] = self::build_asset_url($modekey, $slug, (string)$relativepath);
        }
        if (!empty($manifest['thumbnail'])) {
            $assets['thumbnail'] = self::build_asset_url($modekey, $slug, (string)$manifest['thumbnail']);
        }
        return $assets;
    }

    /**
     * Build the public URL for a package asset file.
     *
     * @param string $modekey      The mode key (without stackmathgamemode_ prefix).
     * @param string $slug         The design slug.
     * @param string $relativepath Path relative to the package root.
     * @return string The absolute URL.
     */
    private static function build_asset_url(string $modekey, string $slug, string $relativepath): string {
        $relativepath = ltrim($relativepath, '/');
        return (new \moodle_url(
            '/local/stackmathgame/mode/' . $modekey . '/packages/' . $slug . '/' . $relativepath
        ))->out(false);
    }

    /**
     * Resolve the filesystem path and mode key for a mode component.
     *
     * @param string $modecomponent The full component name (e.g. 'stackmathgamemode_rpg').
     * @return array Two-element array: [absolute path, mode key string].
     */
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
