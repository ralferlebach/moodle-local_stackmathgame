<?php
namespace local_stackmathgame\local\packaging;

defined('MOODLE_INTERNAL') || die();

/**
 * Registry for bundled design packages shipped by mode subplugins.
 */
class package_registry {
    /**
     * Return bundled design packages discovered under mode subplugins.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_bundled_design_packages(): array {
        global $CFG;

        $packages = [];
        $basedir = $CFG->dirroot . '/local/stackmathgame/mode';
        if (!is_dir($basedir)) {
            return $packages;
        }

        foreach (glob($basedir . '/*/packages/*/manifest.json') as $manifestpath) {
            $manifest = json_decode(file_get_contents($manifestpath), true);
            if (!is_array($manifest)) {
                continue;
            }

            $designslug = (string)($manifest['designslug'] ?? '');
            $modecomponent = (string)($manifest['modecomponent'] ?? '');
            if ($designslug === '' || $modecomponent === '') {
                continue;
            }

            $packagedir = dirname($manifestpath);
            $packages[] = [
                'slug' => $designslug,
                'name' => (string)($manifest['displayname'] ?? $designslug),
                'modecomponent' => $modecomponent,
                'description' => (string)($manifest['description'] ?? ''),
                'versioncode' => (int)($manifest['versioncode'] ?? 1),
                'manifest' => $manifest,
                'packagedir' => $packagedir,
                'thumbnailpath' => isset($manifest['thumbnail']) ? $packagedir . '/' . ltrim((string)$manifest['thumbnail'], '/') : null,
                'narrativejson' => self::read_optional_json($packagedir, $manifest['narrativefile'] ?? null),
                'uijson' => self::read_optional_json($packagedir, $manifest['uifile'] ?? null),
                'mechanicsjson' => self::read_optional_json($packagedir, $manifest['mechanicsfile'] ?? null),
                'assetmanifestjson' => json_encode($manifest['assetslots'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'importmetajson' => json_encode([
                    'origin' => 'bundled',
                    'manifestpath' => str_replace($CFG->dirroot . '/', '', $manifestpath),
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ];
        }

        usort($packages, static function(array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        return $packages;
    }

    /**
     * @param string $slug
     * @return array<string, mixed>|null
     */
    public static function get_bundled_design_package_by_slug(string $slug): ?array {
        foreach (self::get_bundled_design_packages() as $package) {
            if ($package['slug'] === $slug) {
                return $package;
            }
        }
        return null;
    }

    /**
     * Resolve a bundled asset URL based on a design slug and asset key.
     */
    public static function resolve_bundled_asset_url(string $slug, string $assetkey): ?\moodle_url {
        global $CFG;

        $package = self::get_bundled_design_package_by_slug($slug);
        if (!$package) {
            return null;
        }
        $assetslots = $package['manifest']['assetslots'] ?? [];
        $relativepath = $assetslots[$assetkey] ?? null;
        if (!$relativepath || !is_string($relativepath)) {
            return null;
        }

        $normalized = ltrim(str_replace('..', '', $relativepath), '/');
        $fullpath = $package['packagedir'] . '/' . $normalized;
        if (!is_file($fullpath)) {
            return null;
        }

        $relative = str_replace($CFG->dirroot . '/', '', $fullpath);
        return new \moodle_url('/' . $relative);
    }

    /**
     * @param string $packagedir
     * @param string|null $relativefile
     * @return string
     */
    private static function read_optional_json(string $packagedir, ?string $relativefile): string {
        if (!$relativefile) {
            return json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $fullpath = $packagedir . '/' . ltrim($relativefile, '/');
        if (!is_file($fullpath)) {
            return json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $content = file_get_contents($fullpath);
        $decoded = json_decode($content, true);
        return json_encode(is_array($decoded) ? $decoded : [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
