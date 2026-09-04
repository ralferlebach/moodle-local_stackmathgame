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

use advanced_testcase;
use local_stackmathgame\external\api;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\packaging\package_registry;

/**
 * Unit tests for design asset resolution (issue #4).
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\game\theme_manager::asset_base_url
 * @covers     \local_stackmathgame\external\api::export_design
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class design_assets_test extends advanced_testcase {
    /**
     * Seed the bundled designs.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        theme_manager::seed_default_theme();
    }

    /**
     * Every bundled design resolves assets from its own package.
     *
     * This was the defect: asset_base_url() accepted a slug and ignored it, so every design -
     * bundled or imported - pointed at the same generic shared directory.
     */
    public function test_each_design_resolves_to_its_own_package(): void {
        global $DB;

        $designs = $DB->get_records('local_stackmathgame_design');
        $this->assertNotEmpty($designs, 'No bundled designs were seeded.');

        $seen = [];
        foreach ($designs as $design) {
            $url = theme_manager::asset_base_url((string)$design->slug);
            $this->assertStringNotContainsString(
                '/pix/packages/shared/',
                $url,
                'Design ' . $design->slug . ' still resolves to the generic shared directory.'
            );
            $seen[] = $url;
        }

        $this->assertSame(
            count($seen),
            count(array_unique($seen)),
            'Two designs resolved to the same asset directory.'
        );
    }

    /**
     * An unknown slug falls back to the shared directory rather than to a broken path.
     */
    public function test_unknown_slug_falls_back_to_shared(): void {
        $this->assertStringContainsString(
            '/pix/packages/shared/',
            theme_manager::asset_base_url('no_such_design')
        );
        $this->assertStringContainsString(
            '/pix/packages/shared/',
            theme_manager::asset_base_url('')
        );
    }

    /**
     * The registry resolves every key the manifest declares.
     */
    public function test_manifest_keys_all_resolve(): void {
        $assets = package_registry::build_runtime_assets('stackmathgamemode_rpg', 'rpg_default');

        $this->assertNotEmpty($assets, 'The RPG package resolved no assets at all.');
        $this->assertArrayHasKey('thumbnail', $assets);
        foreach ($assets as $key => $url) {
            $this->assertNotEmpty($url, 'Asset ' . $key . ' resolved to an empty URL.');
            $this->assertStringContainsString('/mode/rpg/packages/rpg_default/', $url);
        }
    }

    /**
     * export_design() carries the resolved assets as a structured field.
     *
     * The client used to have to dig them out of runtimejson, and read it at the wrong level -
     * so the map was always empty, and an empty map is a perfectly valid value that raises
     * nothing.
     */
    public function test_export_design_exposes_assets_structurally(): void {
        global $DB;

        $design = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default'], '*', MUST_EXIST);
        $exported = api::export_design($design);

        $this->assertArrayHasKey('runtimeassets', $exported);
        $this->assertNotEmpty($exported['runtimeassets']);
        foreach ($exported['runtimeassets'] as $asset) {
            $this->assertArrayHasKey('key', $asset);
            $this->assertArrayHasKey('url', $asset);
            $this->assertNotEmpty($asset['url']);
        }
        $this->assertNotEmpty($exported['thumbnailurl']);
        $this->assertStringContainsString('smg-mode-rpg', $exported['themeclass']);
    }

    /**
     * A null design exports the same shape, so the client never has to test for absence.
     */
    public function test_null_design_exports_the_same_shape(): void {
        $exported = api::export_design(null);

        $this->assertSame([], $exported['runtimeassets']);
        $this->assertSame('', $exported['thumbnailurl']);
        $this->assertSame('', $exported['themeclass']);
    }

    /**
     * Every file a manifest points at actually exists on disk.
     *
     * A missing file is invisible at runtime: the element renders empty and nothing is logged.
     */
    public function test_declared_asset_files_exist(): void {
        global $DB;

        foreach ($DB->get_records('local_stackmathgame_design') as $design) {
            $manifest = package_registry::get_bundled_package(
                (string)$design->modecomponent,
                (string)$design->slug
            );
            if (!$manifest) {
                continue;
            }
            $packagepath = (string)$manifest['_packagepath'];
            $files = (array)($manifest['assetslots'] ?? []);
            if (!empty($manifest['thumbnail'])) {
                $files['thumbnail'] = $manifest['thumbnail'];
            }
            foreach ($files as $key => $relative) {
                $this->assertFileExists(
                    $packagepath . '/' . ltrim((string)$relative, '/'),
                    $design->slug . ' declares ' . $key . ' but the file is missing.'
                );
            }
        }
        // Guard against the whole loop silently doing nothing.
        $this->assertGreaterThan(0, $DB->count_records('local_stackmathgame_design'));
    }
}
