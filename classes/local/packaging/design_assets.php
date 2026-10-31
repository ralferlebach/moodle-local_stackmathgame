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

namespace local_stackmathgame\local\packaging;

/**
 * The artwork of design packages uploaded through the Game Design Studio.
 *
 * A bundled design keeps its artwork in the plugin directory. An uploaded one keeps it in Moodle's
 * file storage, one file area per design, and Moodle serves it through pluginfile.php - so a design
 * can bring artwork that may be used on a site but not shipped with the plugin, it survives plugin
 * upgrades, and nothing is ever fetched from anywhere else at runtime.
 *
 * Files are stored under their asset key, not their original name: names inside a package are
 * whatever the artist chose, and two keys pointing at the same file, or a name with a path in it,
 * must not decide what ends up on disk.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class design_assets {
    /** File area holding the artwork of uploaded designs; the item id is the design id. */
    const FILEAREA = 'designasset';

    /** Extensions an asset may have. */
    const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    /** Largest single asset, in bytes. */
    const MAX_FILE_BYTES = 20 * 1024 * 1024;

    /** Largest total of all assets in one package, in bytes. */
    const MAX_TOTAL_BYTES = 100 * 1024 * 1024;

    /**
     * Check the asset part of a package: every declared file present, allowed, safe and in bounds.
     *
     * @param \ZipArchive $zip The open package.
     * @param array $manifest The decoded manifest.
     * @return string[] Human-readable problems; empty when the assets are acceptable.
     */
    public static function validate(\ZipArchive $zip, array $manifest): array {
        $errors = [];
        $total = 0;
        $slots = (array)($manifest['assetslots'] ?? []);

        foreach ($slots as $key => $relative) {
            $key = (string)$key;
            $relative = (string)$relative;
            if (clean_param($key, PARAM_ALPHANUMEXT) !== $key || $key === '') {
                $errors[] = "Asset key '$key' may only contain letters, digits, '_' and '-'.";
                continue;
            }
            if ($relative === '' || strpos($relative, '..') !== false || $relative[0] === '/') {
                $errors[] = "Asset '$key' points outside the package: '$relative'.";
                continue;
            }
            $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $errors[] = "Asset '$key' has the unsupported type '.$ext'.";
                continue;
            }
            $stat = $zip->statName($relative);
            if ($stat === false) {
                $errors[] = "Asset '$key' is declared in manifest.json but '$relative' is not in the package.";
                continue;
            }
            if ($stat['size'] > self::MAX_FILE_BYTES) {
                $errors[] = "Asset '$key' is larger than " . display_size(self::MAX_FILE_BYTES) . '.';
            }
            $total += $stat['size'];
            if ($ext === 'svg' && !self::svg_is_safe((string)$zip->getFromName($relative))) {
                $errors[] = "Asset '$key' is an SVG with script or event handlers, which is not allowed.";
            }
        }
        if ($total > self::MAX_TOTAL_BYTES) {
            $errors[] = 'The package assets are larger than ' . display_size(self::MAX_TOTAL_BYTES) . ' in total.';
        }

        foreach ((array)($manifest['sprites'] ?? []) as $key => $meta) {
            if (!array_key_exists((string)$key, $slots)) {
                $errors[] = "Sprite '$key' has no matching entry in assetslots.";
                continue;
            }
            $meta = (array)$meta;
            foreach (['frames', 'framewidth', 'frameheight', 'frameduration'] as $field) {
                if (!isset($meta[$field]) || (int)$meta[$field] < 1) {
                    $errors[] = "Sprite '$key' needs a positive '$field'.";
                }
            }
        }
        return $errors;
    }

    /**
     * Report whether an SVG is free of anything that could run.
     *
     * The file is served from the site's own domain, where script inside an image is script on
     * the site. Artwork has no need for any of it, so anything that looks like it is refused
     * rather than cleaned: a cleaner that misses one form is worse than a refusal the author can
     * act on.
     *
     * @param string $content The SVG source.
     * @return bool True when no script, handler or script URL is present.
     */
    public static function svg_is_safe(string $content): bool {
        $patterns = [
            '/<\s*script\b/i',
            '/\son[a-z]+\s*=/i',
            '/javascript\s*:/i',
            '/<\s*foreignObject\b/i',
            '/<!ENTITY/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Replace a design's stored artwork with the files of a package.
     *
     * @param \ZipArchive $zip The open, already validated package.
     * @param array $manifest The decoded manifest.
     * @param int $designid The design the artwork belongs to.
     * @return array What was stored: ['assetslots' => key => filename, 'sprites' => key => geometry].
     */
    public static function store(\ZipArchive $zip, array $manifest, int $designid): array {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $fs->delete_area_files($context->id, 'local_stackmathgame', self::FILEAREA, $designid);

        $stored = [];
        foreach ((array)($manifest['assetslots'] ?? []) as $key => $relative) {
            $content = $zip->getFromName((string)$relative);
            if ($content === false) {
                continue;
            }
            $filename = $key . '.' . strtolower(pathinfo((string)$relative, PATHINFO_EXTENSION));
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'local_stackmathgame',
                'filearea' => self::FILEAREA,
                'itemid' => $designid,
                'filepath' => '/',
                'filename' => $filename,
            ], $content);
            $stored[(string)$key] = $filename;
        }

        $sprites = [];
        foreach ((array)($manifest['sprites'] ?? []) as $key => $meta) {
            if (!isset($stored[(string)$key])) {
                continue;
            }
            $meta = (array)$meta;
            $sprites[(string)$key] = [
                'frames' => max(1, (int)$meta['frames']),
                'framewidth' => max(1, (int)$meta['framewidth']),
                'frameheight' => max(1, (int)$meta['frameheight']),
                'frameduration' => max(1, (int)$meta['frameduration']),
            ];
        }
        return ['source' => 'upload', 'assetslots' => $stored, 'sprites' => $sprites];
    }

    /**
     * Resolve an uploaded design's artwork to URLs.
     *
     * @param \stdClass $design The design record.
     * @return array Map of asset key to URL.
     */
    public static function runtime_assets(\stdClass $design): array {
        $record = json_decode((string)($design->assetmanifestjson ?? '{}'), true) ?: [];
        $urls = [];
        foreach ((array)($record['assetslots'] ?? []) as $key => $filename) {
            $urls[(string)$key] = \moodle_url::make_pluginfile_url(
                \context_system::instance()->id,
                'local_stackmathgame',
                self::FILEAREA,
                (int)$design->id,
                '/',
                (string)$filename
            )->out(false);
        }
        return $urls;
    }

    /**
     * Return an uploaded design's sprite geometry.
     *
     * @param \stdClass $design The design record.
     * @return array Map of asset key to geometry.
     */
    public static function sprite_meta(\stdClass $design): array {
        $record = json_decode((string)($design->assetmanifestjson ?? '{}'), true) ?: [];
        return (array)($record['sprites'] ?? []);
    }
}
