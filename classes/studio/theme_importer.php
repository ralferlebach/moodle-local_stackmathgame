<?php
namespace local_stackmathgame\studio;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\theme_manager;

/**
 * Import zipped design bundles into the local_stackmathgame_design table.
 *
 * Fixed issues:
 * 1. Referenced table 'local_stackmathgame_theme' which does not exist.
 *    The correct table is 'local_stackmathgame_design'.
 * 2. Called theme_manager::default_fantasy_config() which is not defined
 *    anywhere – replaced with a safe default design record structure.
 * 3. Used old field names (shortname → slug, configjson, isbuiltin → isbundled,
 *    sortorder) that don't match the actual install.xml schema.
 */
class theme_importer {

    /**
     * Process an uploaded ZIP draft file and create/update a design record.
     *
     * Expects the uploaded ZIP to contain at least a manifest.json at the root.
     * If manifest.json is absent the importer creates a minimal stub record.
     *
     * @return array{success:bool, designid:int|null, error:string|null}
     */
    public static function process_upload(): array {
        global $DB, $USER;

        $draftid = optional_param('importzip', 0, PARAM_INT);
        if (!$draftid) {
            return ['success' => false, 'designid' => null, 'error' => 'No file uploaded.'];
        }

        $fs          = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files       = $fs->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $draftid,
            'id',
            false
        );
        if (!$files) {
            return ['success' => false, 'designid' => null, 'error' => 'No file uploaded.'];
        }

        $file     = reset($files);
        $filename = $file->get_filename();
        // Derive a safe slug from the filename (without extension).
        $slug = clean_param(
            str_replace([' ', '-'], '_', strtolower(pathinfo($filename, PATHINFO_FILENAME))),
            PARAM_ALPHANUMEXT
        );
        if ($slug === '') {
            $slug = 'imported_' . time();
        }

        // Try to extract manifest.json from the ZIP.
        $manifest     = [];
        $tmpdir       = make_request_directory();
        $tmpzippath   = $tmpdir . '/' . $filename;
        $file->copy_content_to($tmpzippath);

        if (is_readable($tmpzippath) && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($tmpzippath) === true) {
                $manifestraw = $zip->getFromName('manifest.json');
                if ($manifestraw !== false) {
                    $manifest = json_decode($manifestraw, true) ?: [];
                }
                $zip->close();
            }
        }

        // Extract relevant fields from the manifest (with safe fallbacks).
        $name          = clean_param((string)($manifest['displayname'] ?? ucwords(str_replace('_', ' ', $slug))), PARAM_TEXT);
        $modecomponent = clean_param((string)($manifest['modecomponent'] ?? ''), PARAM_COMPONENT);
        $description   = clean_param((string)($manifest['description']   ?? ''), PARAM_TEXT);
        $version       = (int)($manifest['versioncode'] ?? 1);

        if ($modecomponent === '') {
            // Cannot import without knowing which mode this design belongs to.
            return [
                'success'  => false,
                'designid' => null,
                'error'    => 'manifest.json is missing or does not declare a modecomponent.',
            ];
        }

        // *** BUG FIX: was using non-existent table 'local_stackmathgame_theme'.
        // Correct table is 'local_stackmathgame_design' with field 'slug'. ***
        $existing = $DB->get_record('local_stackmathgame_design', ['slug' => $slug]);
        if ($existing && !empty($existing->isbundled)) {
            return [
                'success'  => false,
                'designid' => null,
                'error'    => 'Built-in (bundled) designs cannot be overwritten via import.',
            ];
        }

        $now = time();

        if ($existing) {
            // Update the existing imported design.
            $DB->update_record('local_stackmathgame_design', (object)[
                'id'            => (int)$existing->id,
                'name'          => $name,
                'modecomponent' => $modecomponent,
                'description'   => $description,
                'versioncode'   => $version,
                'isactive'      => 1,
                'importmetajson' => json_encode([
                    'origin'    => 'upload',
                    'filename'  => $filename,
                    'imported'  => $now,
                    'manifest'  => $manifest,
                ], JSON_UNESCAPED_UNICODE),
                'timemodified'  => $now,
                'modifiedby'    => (int)$USER->id,
            ]);
            $designid = (int)$existing->id;
        } else {
            // *** BUG FIX: was inserting into non-existent table with wrong fields.
            // Now uses correct table and schema from install.xml. ***
            $designid = (int)$DB->insert_record('local_stackmathgame_design', (object)[
                'name'             => $name,
                'slug'             => $slug,
                'modecomponent'    => $modecomponent,
                'description'      => $description,
                'isbundled'        => 0,
                'isactive'         => 1,
                'versioncode'      => $version,
                'narrativejson'    => '{}',
                'uijson'           => '{}',
                'mechanicsjson'    => '{}',
                'assetmanifestjson' => json_encode(
                    (array)($manifest['assetslots'] ?? []),
                    JSON_UNESCAPED_UNICODE
                ),
                'importmetajson'   => json_encode([
                    'origin'   => 'upload',
                    'filename' => $filename,
                    'imported' => $now,
                    'manifest' => $manifest,
                ], JSON_UNESCAPED_UNICODE),
                'timecreated'      => $now,
                'timemodified'     => $now,
                'createdby'        => (int)$USER->id,
                'modifiedby'       => (int)$USER->id,
            ]);
        }

        theme_manager::purge_cache();

        return ['success' => true, 'designid' => $designid, 'error' => null];
    }
}
