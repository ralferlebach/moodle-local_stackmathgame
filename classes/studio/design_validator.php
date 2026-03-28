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
 * Design ZIP validator for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\studio;

/**
 * Validates the structure and content of a design ZIP bundle before import.
 *
 * A valid design ZIP must contain:
 *   - manifest.json with keys: name, slug, modecomponent, version (int >= 1)
 *
 * Optional but validated if present:
 *   - narrative.json   valid JSON object
 *   - ui.json          valid JSON object
 *   - mechanics.json   valid JSON object
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class design_validator {
    /** Minimum required manifest keys. */
    const MANIFEST_REQUIRED = ['name', 'slug', 'modecomponent', 'version'];

    /** Optional JSON payload files in the ZIP. */
    const OPTIONAL_JSON_FILES = ['narrative.json', 'ui.json', 'mechanics.json'];

    /**
     * Validate a ZIP file path and return a list of errors.
     *
     * Returns an empty array when the ZIP is valid. Each error is a
     * human-readable string suitable for display to the user.
     *
     * @param string $zippath Absolute filesystem path to the ZIP file.
     * @return string[] Array of error messages (empty = valid).
     */
    public static function validate_zip(string $zippath): array {
        $errors = [];

        if (!is_readable($zippath)) {
            $errors[] = 'ZIP file is not readable.';
            return $errors;
        }

        if (!class_exists(\ZipArchive::class)) {
            $errors[] = 'ZipArchive PHP extension is not available.';
            return $errors;
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zippath, \ZipArchive::RDONLY);
        if ($result !== true) {
            $errors[] = 'ZIP file could not be opened (error code: ' . $result . ').';
            return $errors;
        }

        // Validate manifest.json.
        $manifestraw = $zip->getFromName('manifest.json');
        if ($manifestraw === false) {
            $errors[] = 'manifest.json is missing from the ZIP root.';
            $zip->close();
            return $errors;
        }

        $manifest = json_decode($manifestraw, true);
        if (!is_array($manifest)) {
            $errors[] = 'manifest.json is not valid JSON.';
            $zip->close();
            return $errors;
        }

        foreach (self::MANIFEST_REQUIRED as $key) {
            if (!isset($manifest[$key]) || (string)$manifest[$key] === '') {
                $errors[] = "manifest.json is missing required field: '$key'.";
            }
        }

        if (isset($manifest['version']) && (int)$manifest['version'] < 1) {
            $errors[] = 'manifest.json: version must be an integer >= 1.';
        }

        if (isset($manifest['modecomponent'])) {
            $cleaned = clean_param((string)$manifest['modecomponent'], PARAM_COMPONENT);
            if ($cleaned === '') {
                $errors[] = 'manifest.json: modecomponent is not a valid Moodle component name.';
            }
        }

        // Validate optional JSON payload files.
        foreach (self::OPTIONAL_JSON_FILES as $filename) {
            $raw = $zip->getFromName($filename);
            if ($raw === false) {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $errors[] = "$filename is present but is not a valid JSON object.";
            }
        }

        $zip->close();
        return $errors;
    }

    /**
     * Return true when the ZIP has no validation errors.
     *
     * @param string $zippath Absolute filesystem path to the ZIP file.
     * @return bool
     */
    public static function is_valid(string $zippath): bool {
        return empty(self::validate_zip($zippath));
    }
}
