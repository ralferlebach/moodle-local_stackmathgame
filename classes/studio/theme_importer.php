<?php
namespace local_stackmathgame\studio;

/**
 * Theme ZIP importer and exporter.
 *
 * ZIP format specification:
 * ─────────────────────────
 * my_theme_package.zip
 * ├── theme.json          ← Required. Full theme configjson + name/shortname/description.
 * ├── thumbnail.png       ← Recommended. 400×240 preview image.
 * ├── sprites/            ← All sprite sheet PNGs referenced in theme.json
 * │   ├── Player_IDLE_spritesheet.png
 * │   └── …
 * ├── backgrounds/        ← Background images referenced in theme.json
 * │   ├── bg-battle.png
 * │   └── …
 * └── ui/                 ← UI SVG/PNG assets
 *     ├── fairy.svg
 *     └── …
 *
 * On import:
 *  1. theme.json is validated (required keys, shortname format, no path traversal).
 *  2. Assets are copied into {dataroot}/local_stackmathgame/themes/{shortname}/.
 *     (Moodle file API filearea, not plugin pix/ — pix/ is for bundled-only themes.)
 *  3. A DB record is created in local_stackmathgame_theme.
 *  4. Asset URLs in configjson are rewritten to point to the new filearea.
 *
 * On export:
 *  1. The theme DB record and configjson are read.
 *  2. All referenced asset files are gathered from the filearea.
 *  3. A ZIP is assembled and streamed to the browser.
 *
 * @package    local_stackmathgame
 */
class theme_importer {

    private const MAX_ZIP_SIZE_MB  = 50;
    private const ALLOWED_IMG_EXTS = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'];
    private const REQUIRED_KEYS    = ['shortname', 'name', 'player', 'enemies', 'backgrounds', 'ui'];
    private const FILEAREA         = 'theme_assets';
    private const COMPONENT        = 'local_stackmathgame';

    // -------------------------------------------------------------------------
    // IMPORT
    // -------------------------------------------------------------------------

    /**
     * Process a ZIP file upload from the studio import form.
     * Returns ['success' => bool, 'themeid' => int|null, 'error' => string|null].
     */
    public static function process_upload(): array {
        global $DB, $USER;

        $draftid = optional_param('importzip', 0, PARAM_INT);
        if (!$draftid) {
            return ['success' => false, 'error' => 'No file uploaded.'];
        }

        // Get uploaded file from user draft area.
        $fs      = get_file_storage();
        $context = \context_user::instance($USER->id);
        $files   = $fs->get_area_files($context->id, 'user', 'draft', $draftid,
                                        'filename', false);

        if (empty($files)) {
            return ['success' => false, 'error' => 'No file found in upload.'];
        }

        $zipfile = reset($files);
        if (!str_ends_with(strtolower($zipfile->get_filename()), '.zip')) {
            return ['success' => false, 'error' => 'File must be a .zip archive.'];
        }

        if ($zipfile->get_filesize() > self::MAX_ZIP_SIZE_MB * 1024 * 1024) {
            return ['success' => false, 'error' => 'ZIP exceeds ' . self::MAX_ZIP_SIZE_MB . ' MB limit.'];
        }

        // Extract to a temp directory.
        $tmpdir = make_temp_directory('smg_import_' . uniqid());
        $zipfile->copy_content_to($tmpdir . '/upload.zip');

        $zip = new \ZipArchive();
        if ($zip->open($tmpdir . '/upload.zip') !== true) {
            fulldelete($tmpdir);
            return ['success' => false, 'error' => 'Could not open ZIP file.'];
        }

        $zip->extractTo($tmpdir . '/extracted/');
        $zip->close();

        // Validate theme.json.
        $jsonpath = $tmpdir . '/extracted/theme.json';
        if (!file_exists($jsonpath)) {
            fulldelete($tmpdir);
            return ['success' => false, 'error' => 'ZIP must contain a theme.json file.'];
        }

        $config    = json_decode(file_get_contents($jsonpath), true);
        $validated = self::validate_config($config);
        if ($validated !== true) {
            fulldelete($tmpdir);
            return ['success' => false, 'error' => $validated];
        }

        $shortname = clean_param($config['shortname'], PARAM_SAFEDIR);

        // Check for duplicate shortname.
        if ($DB->record_exists('local_stackmathgame_theme', ['shortname' => $shortname])) {
            fulldelete($tmpdir);
            return ['success' => false, 'error' => "A theme with shortname '{$shortname}' already exists."];
        }

        // Copy assets to Moodle file API (system context, our filearea).
        $syscontext = \context_system::instance();
        $assetmap   = self::store_assets(
            $tmpdir . '/extracted/',
            $shortname,
            $syscontext,
            $fs
        );

        // Rewrite asset references in configjson to use file serve URLs.
        $config = self::rewrite_asset_urls($config, $assetmap, $shortname);

        // Insert DB record.
        $now     = time();
        $themeid = $DB->insert_record('local_stackmathgame_theme', (object) [
            'name'        => clean_param($config['name'], PARAM_TEXT),
            'shortname'   => $shortname,
            'configjson'  => json_encode($config, JSON_UNESCAPED_UNICODE),
            'enabled'     => 1,
            'sortorder'   => $DB->count_records('local_stackmathgame_theme'),
            'timecreated' => $now,
        ]);

        fulldelete($tmpdir);

        return ['success' => true, 'themeid' => (int) $themeid];
    }

    // -------------------------------------------------------------------------
    // EXPORT
    // -------------------------------------------------------------------------

    /**
     * Stream a theme as a ZIP download to the browser.
     *
     * @param int $themeid
     */
    public static function export_theme(int $themeid): void {
        global $DB;

        $theme = $DB->get_record('local_stackmathgame_theme', ['id' => $themeid]);
        if (!$theme) {
            throw new \moodle_exception('invalidthemeid', 'local_stackmathgame');
        }

        $config   = json_decode($theme->configjson, true) ?? [];
        $tmpdir   = make_temp_directory('smg_export_' . $themeid . '_' . uniqid());
        $zippath  = $tmpdir . '/' . $theme->shortname . '.zip';

        // Determine whether assets are in filearea (imported) or pix/ (bundled).
        $isBuiltin = self::is_builtin_theme($theme->shortname);

        $zip = new \ZipArchive();
        $zip->open($zippath, \ZipArchive::CREATE);

        // Write theme.json (strip internal filearea URL rewrites → restore original paths).
        $exportconfig = self::restore_asset_paths($config);
        $zip->addFromString('theme.json', json_encode($exportconfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Gather assets.
        if ($isBuiltin) {
            self::add_builtin_assets($zip, $theme->shortname);
        } else {
            self::add_filearea_assets($zip, $theme->shortname, $themeid);
        }

        $zip->close();

        // Stream to browser.
        $filename = clean_filename($theme->shortname . '_theme.zip');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zippath));
        readfile($zippath);
        fulldelete($tmpdir);
        exit;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function validate_config(?array $config): true|string {
        if (!is_array($config)) {
            return 'theme.json is not valid JSON.';
        }
        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($config[$key])) {
                return "theme.json missing required key: '{$key}'.";
            }
        }
        $shortname = $config['shortname'] ?? '';
        if (!preg_match('/^[a-z][a-z0-9_]{2,49}$/', $shortname)) {
            return "shortname must be lowercase alphanumeric/underscore, 3–50 chars, starting with a letter.";
        }
        return true;
    }

    private static function store_assets(
        string   $extractdir,
        string   $shortname,
        \context $ctx,
        \file_storage $fs
    ): array {
        $assetmap = [];
        $subdirs  = ['sprites', 'backgrounds', 'ui'];

        foreach ($subdirs as $subdir) {
            $srcdir = $extractdir . $subdir . '/';
            if (!is_dir($srcdir)) {
                continue;
            }
            foreach (glob($srcdir . '*') as $filepath) {
                $filename = basename($filepath);
                $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, self::ALLOWED_IMG_EXTS, true)) {
                    continue;
                }
                // Store in Moodle filearea: component/filearea/itemid(=themeid-to-be)/filepath
                $filerecord = [
                    'contextid' => $ctx->id,
                    'component' => self::COMPONENT,
                    'filearea'  => self::FILEAREA,
                    'itemid'    => 0, // will be updated after theme insert
                    'filepath'  => '/' . $shortname . '/' . $subdir . '/',
                    'filename'  => $filename,
                ];
                $file = $fs->create_file_from_pathname($filerecord, $filepath);
                $assetmap[$subdir . '/' . $filename] = (string) \moodle_url::make_pluginfile_url(
                    $ctx->id, self::COMPONENT, self::FILEAREA, 0,
                    '/' . $shortname . '/' . $subdir . '/', $filename
                );
            }
        }

        // Also store thumbnail.
        $thumbpath = $extractdir . 'thumbnail.png';
        if (file_exists($thumbpath)) {
            $filerecord = [
                'contextid' => $ctx->id,
                'component' => self::COMPONENT,
                'filearea'  => self::FILEAREA,
                'itemid'    => 0,
                'filepath'  => '/' . $shortname . '/',
                'filename'  => 'thumbnail.png',
            ];
            $fs->create_file_from_pathname($filerecord, $thumbpath);
            $assetmap['thumbnail.png'] = (string) \moodle_url::make_pluginfile_url(
                $ctx->id, self::COMPONENT, self::FILEAREA, 0,
                '/' . $shortname . '/', 'thumbnail.png'
            );
        }

        return $assetmap;
    }

    private static function rewrite_asset_urls(array $config, array $assetmap, string $shortname): array {
        // Deep-walk and rewrite any string that matches a key in assetmap.
        array_walk_recursive($config, function(&$value) use ($assetmap) {
            if (is_string($value) && isset($assetmap[$value])) {
                $value = $assetmap[$value];
            }
        });
        return $config;
    }

    private static function restore_asset_paths(array $config): array {
        // Inverse: strip full URLs back to relative paths for portability.
        array_walk_recursive($config, function(&$value) {
            if (is_string($value) && str_contains($value, 'pluginfile.php')) {
                // Extract the last two path segments (subdir/filename).
                if (preg_match('|/([^/]+/[^/]+\.\w+)$|', $value, $m)) {
                    $value = $m[1];
                }
            }
        });
        return $config;
    }

    private static function add_builtin_assets(\ZipArchive $zip, string $shortname): void {
        global $CFG;
        $basedir = $CFG->dirroot . '/local/stackmathgame/pix/themes/' . $shortname . '/';
        if (!is_dir($basedir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basedir));
        foreach ($it as $file) {
            if ($file->isDir()) continue;
            $relative = substr($file->getPathname(), strlen($basedir));
            $zip->addFile($file->getPathname(), $relative);
        }
    }

    private static function add_filearea_assets(\ZipArchive $zip, string $shortname, int $themeid): void {
        $syscontext = \context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $syscontext->id, self::COMPONENT, self::FILEAREA,
            false, 'filepath,filename', false
        );
        foreach ($files as $file) {
            // Only include files for this shortname.
            if (!str_starts_with($file->get_filepath(), '/' . $shortname . '/')) {
                continue;
            }
            $relative = ltrim($file->get_filepath(), '/') . $file->get_filename();
            $zip->addFromString($relative, $file->get_content());
        }
    }

    private static function is_builtin_theme(string $shortname): bool {
        global $CFG;
        return is_dir($CFG->dirroot . '/local/stackmathgame/pix/themes/' . $shortname . '/');
    }
}
