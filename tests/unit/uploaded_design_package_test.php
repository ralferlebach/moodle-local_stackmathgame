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

namespace local_stackmathgame\unit;

use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\packaging\design_assets;
use local_stackmathgame\studio\design_validator;

/**
 * The artwork of design packages imported through the Game Design Studio.
 *
 * Until these existed, the importer kept a package's manifest and discarded its files, so an
 * uploaded design had no artwork at all.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\packaging\design_assets
 * @covers     \local_stackmathgame\studio\design_validator
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class uploaded_design_package_test extends \advanced_testcase {
    /**
     * A one-pixel PNG.
     *
     * @return string The PNG bytes.
     */
    private static function png(): string {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4//8/AwAI/AL+p5qgoAAAAABJRU5ErkJggg==');
    }

    /**
     * Build a package ZIP.
     *
     * @param array $manifest The manifest.
     * @param array|null $files Map of path to content, or null to create one PNG per slot.
     * @return string Path of the ZIP.
     */
    private function make_package(array $manifest, ?array $files = null): string {
        $path = make_request_directory() . '/package.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('manifest.json', json_encode($manifest));
        if ($files === null) {
            $files = [];
            foreach ((array)($manifest['assetslots'] ?? []) as $relative) {
                $files[$relative] = self::png();
            }
        }
        foreach ($files as $relative => $content) {
            $zip->addFromString($relative, $content);
        }
        $zip->close();
        return $path;
    }

    /**
     * A manifest that passes every check.
     *
     * @return array The manifest.
     */
    private function valid_manifest(): array {
        return [
            'name' => 'Test design',
            'slug' => 'test_design',
            'modecomponent' => 'stackmathgamemode_rpg',
            'version' => 1,
            'assetslots' => ['elf_idle' => 'assets/elf.png', 'bg_forest' => 'assets/forest.png'],
            'sprites' => [
                'elf_idle' => ['frames' => 10, 'framewidth' => 225, 'frameheight' => 290, 'frameduration' => 75],
            ],
        ];
    }

    /**
     * A complete package is accepted.
     */
    public function test_a_complete_package_is_valid(): void {
        $this->resetAfterTest();
        $this->assertSame([], design_validator::validate_zip($this->make_package($this->valid_manifest())));
    }

    /**
     * A declared file that is not in the package is named, rather than stored as a hole.
     */
    public function test_a_missing_asset_is_reported(): void {
        $this->resetAfterTest();
        $path = $this->make_package($this->valid_manifest(), ['assets/elf.png' => self::png()]);

        $errors = design_validator::validate_zip($path);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('bg_forest', implode(' ', $errors));
    }

    /**
     * Paths out of the package, unsupported types and orphaned sprites are all refused.
     */
    public function test_unsafe_or_inconsistent_entries_are_refused(): void {
        $this->resetAfterTest();
        $manifest = $this->valid_manifest();
        $manifest['assetslots']['evil'] = '../../config.php';
        $manifest['assetslots']['script'] = 'assets/run.js';
        $manifest['sprites']['ghost'] = ['frames' => 2, 'framewidth' => 1, 'frameheight' => 1, 'frameduration' => 1];

        $errors = implode(' ', design_validator::validate_zip($this->make_package($manifest, [
            'assets/elf.png' => self::png(),
            'assets/forest.png' => self::png(),
            'assets/run.js' => 'alert(1)',
        ])));
        $this->assertStringContainsString('outside the package', $errors);
        $this->assertStringContainsString("'.js'", $errors);
        $this->assertStringContainsString("Sprite 'ghost'", $errors);
    }

    /**
     * An SVG that could run script on the site is refused.
     *
     * The artwork is served from the site's own domain, where script inside an image is script
     * on the site.
     */
    public function test_an_svg_with_script_is_refused(): void {
        $this->resetAfterTest();
        $manifest = $this->valid_manifest();
        $manifest['assetslots']['bg_forest'] = 'assets/forest.svg';

        $unsafe = [
            '<svg><script>alert(1)</script></svg>',
            '<svg onload="alert(1)"></svg>',
            '<svg><a href="javascript:alert(1)"/></svg>',
        ];
        foreach ($unsafe as $svg) {
            $errors = design_validator::validate_zip($this->make_package($manifest, [
                'assets/elf.png' => self::png(),
                'assets/forest.svg' => $svg,
            ]));
            $this->assertNotEmpty($errors, "Accepted: $svg");
        }
        $this->assertTrue(design_assets::svg_is_safe('<svg viewBox="0 0 1 1"><rect width="1" height="1"/></svg>'));
    }

    /**
     * Stored artwork resolves to pluginfile URLs, with its sprite geometry, for the runtime.
     */
    public function test_stored_artwork_reaches_the_runtime(): void {
        global $DB;
        $this->resetAfterTest();

        $now = time();
        $designid = (int)$DB->insert_record('local_stackmathgame_design', (object)[
            'name' => 'Test design',
            'slug' => 'test_design',
            'modecomponent' => 'stackmathgamemode_rpg',
            'description' => '',
            'isbundled' => 0,
            'isactive' => 1,
            'versioncode' => 1,
            'narrativejson' => '{}',
            'uijson' => '{}',
            'mechanicsjson' => '{}',
            'assetmanifestjson' => '{}',
            'importmetajson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $zip = new \ZipArchive();
        $zip->open($this->make_package($this->valid_manifest()));
        $stored = design_assets::store($zip, $this->valid_manifest(), $designid);
        $zip->close();
        $DB->set_field('local_stackmathgame_design', 'assetmanifestjson', json_encode($stored), ['id' => $designid]);

        // Stored under the asset key, not the name the package used.
        $this->assertSame('elf_idle.png', $stored['assetslots']['elf_idle']);

        theme_manager::purge_cache();
        $config = theme_manager::get_theme_config($designid);
        $this->assertStringContainsString('/pluginfile.php/', $config['runtimeassets']['elf_idle']);
        $this->assertStringContainsString('/designasset/' . $designid . '/elf_idle.png', $config['runtimeassets']['elf_idle']);
        $this->assertSame(10, $config['sprites']['elf_idle']['frames']);
        $this->assertSame(225, $config['sprites']['elf_idle']['framewidth']);

        // A re-import replaces the artwork instead of piling up files.
        $zip->open($this->make_package($this->valid_manifest()));
        design_assets::store($zip, $this->valid_manifest(), $designid);
        $zip->close();
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_stackmathgame',
            design_assets::FILEAREA,
            $designid,
            'id',
            false
        );
        $this->assertCount(2, $files);
    }
}
