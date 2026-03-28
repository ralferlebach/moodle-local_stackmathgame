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
use local_stackmathgame\local\service\narrative_resolver;

/**
 * Tests for narrative_resolver.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\narrative_resolver
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class narrative_resolver_test extends advanced_testcase {
    /**
     * Build a minimal design stdClass with the given narrative map.
     *
     * @param array $narrative Map of scene key to string|string[].
     * @return \stdClass
     */
    private function make_design(array $narrative): \stdClass {
        return (object)['narrativejson' => json_encode($narrative)];
    }

    /**
     * Null design returns empty array.
     */
    public function test_resolve_null_design(): void {
        $this->assertSame([], narrative_resolver::resolve(null, 'world_enter'));
    }

    /**
     * Missing scene returns empty array.
     */
    public function test_resolve_missing_scene(): void {
        $design = $this->make_design(['victory' => 'Well done!']);
        $this->assertSame([], narrative_resolver::resolve($design, 'world_enter'));
    }

    /**
     * String value is returned as single-element array.
     */
    public function test_resolve_string_value(): void {
        $design = $this->make_design(['world_enter' => 'The quest begins.']);
        $this->assertSame(['The quest begins.'], narrative_resolver::resolve($design, 'world_enter'));
    }

    /**
     * Array value is returned with empty strings filtered out.
     */
    public function test_resolve_array_filters_empty(): void {
        $design = $this->make_design(['victory' => ['Great!', '', 'You win!', '  ']]);
        $this->assertSame(['Great!', 'You win!'], narrative_resolver::resolve($design, 'victory'));
    }

    /**
     * resolve_as_string joins lines with separator.
     */
    public function test_resolve_as_string_default_separator(): void {
        $design = $this->make_design(['defeat' => ['Try again.', 'Keep going!']]);
        $this->assertSame(
            'Try again. Keep going!',
            narrative_resolver::resolve_as_string($design, 'defeat')
        );
    }

    /**
     * resolve_as_string uses custom separator.
     */
    public function test_resolve_as_string_custom_separator(): void {
        $design = $this->make_design(['reward' => ['Gold key!', 'Bronze key!']]);
        $this->assertSame(
            'Gold key! | Bronze key!',
            narrative_resolver::resolve_as_string($design, 'reward', ' | ')
        );
    }

    /**
     * resolve_as_string returns empty string for missing scene.
     */
    public function test_resolve_as_string_missing_scene(): void {
        $design = $this->make_design([]);
        $this->assertSame('', narrative_resolver::resolve_as_string($design, 'boss_intro'));
    }

    /**
     * resolve_all returns all canonical scenes.
     */
    public function test_resolve_all_covers_all_canonical_scenes(): void {
        $design = $this->make_design(['victory' => 'You won!']);
        $all = narrative_resolver::resolve_all($design);
        foreach (narrative_resolver::canonical_scenes() as $scene) {
            $this->assertArrayHasKey($scene, $all, "resolve_all must include '$scene'");
        }
        $this->assertSame(['You won!'], $all['victory']);
        $this->assertSame([], $all['defeat']);
    }

    /**
     * All canonical constants are non-empty strings.
     */
    public function test_canonical_scenes_are_strings(): void {
        foreach (narrative_resolver::canonical_scenes() as $scene) {
            $this->assertIsString($scene);
            $this->assertNotEmpty($scene);
        }
    }

    /**
     * is_canonical returns true for known keys and false for unknown.
     */
    public function test_is_canonical(): void {
        $this->assertTrue(narrative_resolver::is_canonical('world_enter'));
        $this->assertTrue(narrative_resolver::is_canonical('victory'));
        $this->assertTrue(narrative_resolver::is_canonical('outro'));
        $this->assertFalse(narrative_resolver::is_canonical('unknown_scene'));
        $this->assertFalse(narrative_resolver::is_canonical(''));
    }

    /**
     * Broken JSON in narrativejson returns empty array.
     */
    public function test_resolve_broken_json(): void {
        $design = (object)['narrativejson' => '{not valid json'];
        $this->assertSame([], narrative_resolver::resolve($design, 'world_enter'));
    }
}
