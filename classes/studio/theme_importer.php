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
 * ZIP design bundle importer for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\studio;

use local_stackmathgame\game\theme_manager;
use local_stackmathgame\studio\design_validator;

/**
 * Imports zipped design bundles into the local_stackmathgame_design table.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_importer {
    /**
     * Process an uploaded ZIP draft file and create or update a design record.
     *
     * The ZIP should contain a manifest.json at its root declaring at minimum
     * the modecomponent field. Without it, the import is rejected.
     *
     * @return array{success: bool, designid: int|null, error: string|null}
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
        $slug     = clean_param(
            str_replace([' ', '-'], '_', strtolower(pathinfo($filename, PATHINFO_FILENAME))),
            PARAM_ALPHANUMEXT
        );
        if ($slug === '') {
            $slug = 'imported_' . time();
        }

        $manifest   = [];
        $tmpdir     = make_request_directory();
        $tmpzippath = $tmpdir . '/' . $filename;
        $file->copy_content_to($tmpzippath);

        $narrativejson  = '{}';
        $uijson         = '{}';
        $mechanicsjson  = '{}';

        if (is_readable($tmpzippath) && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($tmpzippath) === true) {
                $manifestraw = $zip->getFromName('manifest.json');
                if ($manifestraw !== false) {
                    $manifest = json_decode($manifestraw, true) ?: [];
                }
                // Validate the ZIP before proceeding.
                $zip->close();

                $errors = design_validator::validate_zip($tmpzippath);
                if (!empty($errors)) {
                    return [
                        'success'  => false,
                        'designid' => null,
                        'error'    => implode(' ', $errors),
                    ];
                }

                // Re-open to read payload files.
                if ($zip->open($tmpzippath) === true) {
                    $payloadmap = [
                        'narrative.json' => 'narrativejson',
                        'ui.json'        => 'uijson',
                        'mechanics.json' => 'mechanicsjson',
                    ];
                    foreach ($payloadmap as $file => $field) {
                        $raw = $zip->getFromName($file);
                        if ($raw !== false) {
                            $decoded = json_decode($raw, true);
                            if (is_array($decoded)) {
                                $$field = json_encode(
                                    $decoded,
                                    JSON_UNESCAPED_UNICODE
                                );
                            }
                        }
                    }
                    $zip->close();
                }
            }
        }

        $name          = clean_param(
            (string)($manifest['displayname'] ?? ucwords(str_replace('_', ' ', $slug))),
            PARAM_TEXT
        );
        $modecomponent = clean_param((string)($manifest['modecomponent'] ?? ''), PARAM_COMPONENT);
        $description   = clean_param((string)($manifest['description'] ?? ''), PARAM_TEXT);
        $version       = (int)($manifest['versioncode'] ?? 1);

        if ($modecomponent === '') {
            return [
                'success'  => false,
                'designid' => null,
                'error'    => 'manifest.json is missing or does not declare a modecomponent.',
            ];
        }

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
            $DB->update_record('local_stackmathgame_design', (object)[
                'id'            => (int)$existing->id,
                'name'          => $name,
                'modecomponent' => $modecomponent,
                'description'   => $description,
                'versioncode'   => $version,
                'isactive'      => 1,
                'importmetajson' => json_encode([
                    'origin'   => 'upload',
                    'filename' => $filename,
                    'imported' => $now,
                    'manifest' => $manifest,
                ], JSON_UNESCAPED_UNICODE),
                'narrativejson' => $narrativejson,
                'uijson'        => $uijson,
                'mechanicsjson' => $mechanicsjson,
                'timemodified'  => $now,
                'modifiedby'    => (int)$USER->id,
            ]);
            $designid = (int)$existing->id;
        } else {
            $assetslots = (array)($manifest['assetslots'] ?? []);
            $designid   = (int)$DB->insert_record('local_stackmathgame_design', (object)[
                'name'             => $name,
                'slug'             => $slug,
                'modecomponent'    => $modecomponent,
                'description'      => $description,
                'isbundled'        => 0,
                'isactive'         => 1,
                'versioncode'      => $version,
                'narrativejson'    => $narrativejson,
                'uijson'           => $uijson,
                'mechanicsjson'    => $mechanicsjson,
                'assetmanifestjson' => json_encode($assetslots, JSON_UNESCAPED_UNICODE),
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
