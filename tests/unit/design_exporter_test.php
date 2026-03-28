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
use local_stackmathgame\studio\design_exporter;
use local_stackmathgame\studio\design_validator;

/**
 * Tests for design_exporter.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\studio\design_exporter
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class design_exporter_test extends advanced_testcase {
    /**
     * Nonexistent design returns null.
     *
     * @group local_stackmathgame_db
     */
    public function test_build_zip_null_for_nonexistent_design(): void {
        $this->resetAfterTest();
        $result = design_exporter::build_zip(PHP_INT_MAX);
        $this->assertNull($result);
    }

    /**
     * Exported ZIP is a valid ZIP with correct manifest structure.
     *
     * @group local_stackmathgame_db
     */
    public function test_build_zip_produces_valid_zip(): void {
        global $DB;
        $this->resetAfterTest();

        $designid = (int)$DB->insert_record('local_stackmathgame_design', (object)[
            'name' => 'Test Design',
            'slug' => 'test_design',
            'modecomponent' => 'stackmathgamemode_rpg',
            'description' => 'A test design.',
            'isbundled' => 0,
            'isactive' => 1,
            'narrativejson' => '{"world_enter":"Hello!"}',
            'uijson' => '{"theme":"dark"}',
            'mechanicsjson' => '{}',
            'assetmanifestjson' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $content = design_exporter::build_zip($designid);

        $this->assertNotNull($content, 'Export must succeed for existing design');
        $this->assertIsString($content);
        $this->assertGreaterThan(0, strlen($content));

        // Write to temp file and validate.
        $tmp = tempnam(sys_get_temp_dir(), 'smg_test_') . '.zip';
        file_put_contents($tmp, $content);
        $this->assertTrue(design_validator::is_valid($tmp), 'Exported ZIP must pass validation');

        // Verify manifest content.
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::RDONLY);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame('test_design', $manifest['slug']);
        $this->assertSame('stackmathgamemode_rpg', $manifest['modecomponent']);

        // Verify narrative payload.
        $narrative = json_decode($zip->getFromName('narrative.json'), true);
        $this->assertArrayHasKey('world_enter', $narrative);
        $this->assertSame('Hello!', $narrative['world_enter']);

        $zip->close();
        @unlink($tmp);
    }

    /**
     * get_filename returns a safe downloadable filename.
     */
    public function test_get_filename_format(): void {
        $name = design_exporter::get_filename(42, 'my design');
        $this->assertStringStartsWith('smg_design_', $name);
        $this->assertStringEndsWith('.zip', $name);
        $this->assertStringNotContainsString(' ', $name);
    }
}
