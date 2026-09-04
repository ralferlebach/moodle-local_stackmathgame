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

/**
 * Guards the claim that every shipped graphic is the plugin's own.
 *
 * The prototype this plugin grew out of used sprites from craftpix.net and UI elements from
 * freepik, neither redistributable inside a published Moodle plugin. None of it was carried over,
 * and the licence notes now say so - but a note is only true until somebody drops a file in. This
 * turns that from a promise into something the pipeline checks.
 *
 * @package    local_stackmathgame
 * @coversNothing
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shipped_assets_test extends advanced_testcase {
    /**
     * Return every graphic the plugin ships.
     *
     * @return string[] Absolute paths.
     */
    private function shipped_graphics(): array {
        global $CFG;

        $root = $CFG->dirroot . '/local/stackmathgame';
        $found = [];
        foreach (['/pix', '/mode'] as $dir) {
            if (!is_dir($root . $dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . $dir));
            foreach ($iterator as $file) {
                if (in_array(strtolower($file->getExtension()), ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
                    $found[] = $file->getPathname();
                }
            }
        }
        return $found;
    }

    /**
     * Every package states its licence.
     */
    public function test_every_package_states_its_licence(): void {
        global $CFG;

        $packages = glob($CFG->dirroot . '/local/stackmathgame/mode/*/packages/*', GLOB_ONLYDIR);
        $this->assertNotEmpty($packages, 'No design packages found at all.');

        foreach ($packages as $package) {
            $licence = $package . '/LICENSE.txt';
            $this->assertFileExists($licence, basename($package) . ' ships no LICENSE.txt.');
            $this->assertStringContainsString(
                'GPL',
                file_get_contents($licence),
                basename($package) . ' does not state a licence compatible with the plugin.'
            );
        }
    }

    /**
     * No shipped graphic names a source the plugin may not redistribute.
     *
     * A crude check on purpose: it looks for the names of the sources the prototype used, plus
     * the metadata a downloaded asset usually carries. It cannot prove provenance - only a human
     * can - but it catches the realistic accident, which is someone dropping a downloaded file in
     * beside the placeholders.
     */
    public function test_no_graphic_names_a_foreign_source(): void {
        $forbidden = ['craftpix', 'freepik', 'flaticon', 'shutterstock', 'envato', 'adobe stock'];

        foreach ($this->shipped_graphics() as $file) {
            if (filesize($file) > 200 * 1024) {
                $this->fail(basename($file) . ' is unexpectedly large for a placeholder - check its origin.');
            }
            $contents = strtolower((string)file_get_contents($file));
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $contents,
                    basename($file) . ' mentions "' . $needle . '" - check its licence before shipping.'
                );
            }
        }
    }

    /**
     * The graphics are vector placeholders, not imported bitmaps.
     *
     * Stated as an expectation rather than a rule: if this ever fails because real artwork was
     * added deliberately, the licence notes need updating in the same commit.
     */
    public function test_graphics_are_vector_placeholders(): void {
        $graphics = $this->shipped_graphics();
        $this->assertNotEmpty($graphics, 'No graphics found - has the asset layout changed?');

        foreach ($graphics as $file) {
            $this->assertSame(
                'svg',
                strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                basename($file) . ' is not an SVG placeholder; confirm its licence and update '
                    . 'mode/*/packages/*/LICENSE.txt.'
            );
        }
    }
}
