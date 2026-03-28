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
 * Design ZIP exporter for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\studio;

/**
 * Exports a game design as a self-contained ZIP bundle.
 *
 * ZIP structure:
 *   manifest.json   Required: name, slug, modecomponent, version, description, exported.
 *   narrative.json  Narrative text blocks keyed by scene name.
 *   ui.json         UI configuration for the design.
 *   mechanics.json  Mechanics/rules configuration.
 *   assets/         Asset manifest entries (metadata only, not binary files).
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class design_exporter {
    /**
     * Build and return the raw ZIP content for a design.
     *
     * Returns null when the design does not exist or ZipArchive is unavailable.
     * The ZIP is created in a temporary directory and read into a string so the
     * caller can stream it without leaving temp files behind.
     *
     * @param int $designid The design record ID.
     * @return string|null Raw ZIP content, or null on failure.
     */
    public static function build_zip(int $designid): ?string {
        global $DB;

        if (!class_exists(\ZipArchive::class)) {
            return null;
        }

        $design = $DB->get_record('local_stackmathgame_design', ['id' => $designid]);
        if (!$design) {
            return null;
        }

        $tmpdir  = make_request_directory();
        $zippath = $tmpdir . '/smg_design_' .
            clean_param((string)$design->slug, PARAM_ALPHANUMEXT) . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zippath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        // Build manifest.
        $manifest = [
            'version'       => 1,
            'name'          => (string)$design->name,
            'slug'          => (string)$design->slug,
            'modecomponent' => (string)$design->modecomponent,
            'description'   => (string)($design->description ?? ''),
            'isbundled'     => !empty($design->isbundled),
            'exported'      => date('Y-m-d\TH:i:s\Z'),
            'exportedby'    => 'local_stackmathgame',
        ];
        $zip->addFromString(
            'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // Add narrative, UI, and mechanics JSON payloads.
        $payloads = [
            'narrative.json' => (string)($design->narrativejson ?? '{}'),
            'ui.json'        => (string)($design->uijson ?? '{}'),
            'mechanics.json' => (string)($design->mechanicsjson ?? '{}'),
        ];
        foreach ($payloads as $filename => $raw) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $decoded = [];
            }
            $zip->addFromString(
                $filename,
                json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        // Add asset manifest (metadata only; binary files not included).
        $assetmanifest = json_decode((string)($design->assetmanifestjson ?? '{}'), true);
        if (!is_array($assetmanifest)) {
            $assetmanifest = [];
        }
        $zip->addFromString(
            'assets/manifest.json',
            json_encode($assetmanifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $zip->close();

        if (!is_readable($zippath)) {
            return null;
        }

        $content = file_get_contents($zippath);
        @unlink($zippath);

        return $content !== false ? $content : null;
    }

    /**
     * Return the filename to use when downloading an exported design ZIP.
     *
     * @param int    $designid The design record ID.
     * @param string $slug     The design slug (used in filename).
     * @return string Suggested download filename.
     */
    public static function get_filename(int $designid, string $slug): string {
        $safeslug = clean_param($slug, PARAM_ALPHANUMEXT);
        if ($safeslug === '') {
            $safeslug = 'design_' . $designid;
        }
        return 'smg_design_' . $safeslug . '.zip';
    }
}
