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
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\studio\design_exporter;
use local_stackmathgame\studio\theme_manager_studio;

/**
 * The Game Design Studio's storage side: saving, importing and the export round trip.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\studio\theme_manager_studio
 * @covers     \local_stackmathgame\studio\design_exporter
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class studio_storage_test extends advanced_testcase {
    /**
     * Seed the bundled designs.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        theme_manager::seed_default_theme();
    }

    /**
     * Saving a new design creates it and returns its id.
     */
    public function test_save_creates_a_design(): void {
        global $DB;

        $id = theme_manager_studio::save_from_form([
            'id' => 0,
            'name' => 'A brand new design',
            'slug' => 'brand_new',
            'modecomponent' => 'stackmathgamemode_rpg',
            'description' => 'Created by a test.',
            'isactive' => 1,
            'narrativejson' => '{"world_enter":["Hello."]}',
            'uijson' => '{"theme":"brand_new"}',
            'mechanicsjson' => '{"mode":"rpg","version":1}',
        ]);

        $this->assertGreaterThan(0, $id);
        $row = $DB->get_record('local_stackmathgame_design', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('brand_new', $row->slug);
        $this->assertSame('stackmathgamemode_rpg', $row->modecomponent);
    }

    /**
     * Saving an existing design updates it rather than creating a second one.
     */
    public function test_save_updates_in_place(): void {
        global $DB;

        $before = $DB->count_records('local_stackmathgame_design');
        $design = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default'], '*', MUST_EXIST);

        $id = theme_manager_studio::save_from_form([
            'id' => (int)$design->id,
            'name' => 'Renamed',
            'slug' => $design->slug,
            'modecomponent' => $design->modecomponent,
            'description' => (string)$design->description,
            'isactive' => 1,
            'narrativejson' => (string)$design->narrativejson,
            'uijson' => (string)$design->uijson,
            'mechanicsjson' => (string)$design->mechanicsjson,
        ]);

        $this->assertSame((int)$design->id, $id);
        $this->assertSame($before, $DB->count_records('local_stackmathgame_design'));
        $this->assertSame('Renamed', $DB->get_field('local_stackmathgame_design', 'name', ['id' => $id]));
    }

    /**
     * A bundled design exports to a readable ZIP containing its manifest.
     *
     * The import half (theme_importer::process_upload) is not covered here: it reads from
     * Moodle's file API during a form submission, so it needs a real upload rather than a
     * fixture. The Behat studio feature exercises that path.
     */
    public function test_design_exports_to_a_readable_zip(): void {
        global $DB;

        $design = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default'], '*', MUST_EXIST);
        $zip = design_exporter::build_zip((int)$design->id);

        $this->assertNotNull($zip, 'The bundled design could not be exported.');
        // Note that build_zip() returns the archive's bytes, not a path: the caller streams them
        // straight to the browser. Writing them out makes the contents inspectable here.
        $this->assertStringStartsWith('PK', $zip, 'The export is not a ZIP archive.');

        $path = make_request_directory() . '/export.zip';
        file_put_contents($path, $zip);

        $archive = new \zip_archive();
        $this->assertTrue($archive->open($path, \file_archive::OPEN));
        $names = [];
        foreach ($archive->list_files() as $entry) {
            $names[] = $entry->pathname;
        }
        $archive->close();

        $this->assertNotEmpty($names, 'The exported archive is empty.');
        $this->assertTrue(
            (bool)array_filter($names, static fn(string $n): bool => str_contains($n, 'manifest.json')),
            'The export carries no manifest: ' . implode(', ', $names)
        );
    }

    /**
     * Exporting a design that does not exist returns nothing rather than an empty archive.
     */
    public function test_exporting_an_unknown_design_returns_null(): void {
        $this->assertNull(design_exporter::build_zip(999999));
    }

    /**
     * Every installed design can be listed for export.
     */
    public function test_all_designs_can_be_listed(): void {
        $all = theme_manager_studio::export_all();

        $this->assertNotEmpty($all);
        foreach ($all as $entry) {
            $this->assertArrayHasKey('slug', (array)$entry);
        }
    }

    /**
     * A single design exports with its own slug.
     */
    public function test_single_design_exports(): void {
        global $DB;

        $design = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default'], '*', MUST_EXIST);
        $one = theme_manager_studio::export_one((int)$design->id);

        $this->assertNotNull($one);
        $this->assertSame('rpg_default', (string)((array)$one)['slug']);
    }

    /**
     * The exported file name identifies the design.
     */
    public function test_export_filename_names_the_design(): void {
        global $DB;

        $design = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default'], '*', MUST_EXIST);
        $name = design_exporter::get_filename((int)$design->id, (string)$design->slug);

        $this->assertStringContainsString('rpg_default', $name);
        $this->assertStringEndsWith('.zip', $name);
    }
}
