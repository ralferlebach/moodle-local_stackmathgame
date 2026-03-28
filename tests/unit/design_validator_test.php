<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_stackmathgame\tests\unit;

use advanced_testcase;
use local_stackmathgame\studio\design_validator;

/**
 * Tests for design_validator and design_exporter.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\studio\design_validator
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class design_validator_test extends advanced_testcase {
    /**
     * Path to the valid fixture ZIP.
     */
    private function valid_zip(): string {
        return __DIR__ . '/../fixtures/demo_design_valid.zip';
    }

    /**
     * Path to the invalid fixture ZIP (missing modecomponent).
     */
    private function invalid_zip(): string {
        return __DIR__ . '/../fixtures/demo_design_invalid.zip';
    }

    /**
     * Path to the corrupt fixture (not a ZIP).
     */
    private function corrupt_zip(): string {
        return __DIR__ . '/../fixtures/demo_design_corrupt.zip';
    }

    /**
     * Valid ZIP has no errors.
     */
    public function test_valid_zip_has_no_errors(): void {
        $errors = design_validator::validate_zip($this->valid_zip());
        $this->assertEmpty($errors, 'Valid ZIP must have no validation errors');
        $this->assertTrue(design_validator::is_valid($this->valid_zip()));
    }

    /**
     * Missing modecomponent produces an error.
     */
    public function test_missing_modecomponent_produces_error(): void {
        $errors = design_validator::validate_zip($this->invalid_zip());
        $this->assertNotEmpty($errors);
        $combined = implode(' ', $errors);
        $this->assertStringContainsString('modecomponent', $combined);
    }

    /**
     * Corrupt file that is not a ZIP produces an error.
     */
    public function test_corrupt_file_produces_error(): void {
        $errors = design_validator::validate_zip($this->corrupt_zip());
        $this->assertNotEmpty($errors, 'Corrupt file must produce at least one error');
        $this->assertFalse(design_validator::is_valid($this->corrupt_zip()));
    }

    /**
     * Non-existent path produces an error.
     */
    public function test_nonexistent_path_produces_error(): void {
        $errors = design_validator::validate_zip('/nonexistent/path/to/nothing.zip');
        $this->assertNotEmpty($errors);
    }

    /**
     * Valid ZIP contains all canonical expected keys.
     */
    public function test_valid_zip_manifest_has_required_keys(): void {
        $zip = new \ZipArchive();
        $zip->open($this->valid_zip(), \ZipArchive::RDONLY);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();

        foreach (design_validator::MANIFEST_REQUIRED as $key) {
            $this->assertArrayHasKey($key, $manifest, "Manifest must have '$key'");
        }
    }

    /**
     * All optional JSON files in the valid fixture are valid JSON.
     */
    public function test_valid_zip_optional_files_are_valid_json(): void {
        $zip = new \ZipArchive();
        $zip->open($this->valid_zip(), \ZipArchive::RDONLY);
        foreach (design_validator::OPTIONAL_JSON_FILES as $filename) {
            $raw = $zip->getFromName($filename);
            if ($raw === false) {
                continue;
            }
            $decoded = json_decode($raw, true);
            $this->assertIsArray($decoded, "$filename must be a valid JSON object");
        }
        $zip->close();
    }
}
