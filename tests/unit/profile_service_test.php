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
use local_stackmathgame\local\service\profile_service;

/**
 * Unit tests for profile_service (no DB needed).
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\profile_service
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_service_test extends advanced_testcase {
    /**
     * Level calculation: boundary and typical values.
     *
     * @dataProvider xp_level_provider
     * @param int $xp      Input XP.
     * @param int $expected Expected level.
     */
    public function test_calculate_level_from_xp(int $xp, int $expected): void {
        $this->assertSame($expected, profile_service::calculate_level_from_xp($xp));
    }

    /**
     * Data provider for test_calculate_level_from_xp.
     *
     * @return array<string, array{int, int}>
     */
    public static function xp_level_provider(): array {
        return [
            'zero xp is level 1'        => [0, 1],
            '50 xp is level 1'          => [50, 1],
            '99 xp is level 1'          => [99, 1],
            '100 xp is level 2'         => [100, 2],
            '199 xp is level 2'         => [199, 2],
            '200 xp is level 3'         => [200, 3],
            '500 xp is level 6'         => [500, 6],
            'negative xp clamped to 1'  => [-10, 1],
        ];
    }

    /**
     * Null returns empty array.
     */
    public function test_decode_json_field_null(): void {
        $this->assertSame([], profile_service::decode_json_field(null));
    }

    /**
     * Empty string returns empty array.
     */
    public function test_decode_json_field_empty_string(): void {
        $this->assertSame([], profile_service::decode_json_field(''));
    }

    /**
     * Valid JSON is decoded to the correct array.
     */
    public function test_decode_json_field_valid(): void {
        $result = profile_service::decode_json_field('{"key":"value","num":42}');
        $this->assertSame('value', $result['key']);
        $this->assertSame(42, $result['num']);
    }

    /**
     * Invalid JSON returns empty array.
     */
    public function test_decode_json_field_broken(): void {
        $this->assertSame([], profile_service::decode_json_field('{not valid json'));
    }

    /**
     * Missing slot returns empty string.
     */
    public function test_get_slot_state_missing(): void {
        $profile = (object)['progressjson' => json_encode(['slots' => []])];
        $this->assertSame('', profile_service::get_slot_state($profile, 1));
    }

    /**
     * Slot stored as scalar string is returned as-is.
     */
    public function test_get_slot_state_scalar(): void {
        $profile = (object)[
            'progressjson' => json_encode(['slots' => ['3' => 'gradedright']]),
        ];
        $this->assertSame('gradedright', profile_service::get_slot_state($profile, 3));
    }

    /**
     * Slot stored as array reads the 'state' key.
     */
    public function test_get_slot_state_array(): void {
        $profile = (object)[
            'progressjson' => json_encode([
                'slots' => ['2' => ['state' => 'gradedpartial', 'attempts' => 2]],
            ]),
        ];
        $this->assertSame('gradedpartial', profile_service::get_slot_state($profile, 2));
    }

    /**
     * First correct answer yields score+10, xp+5, solved=true.
     */
    public function test_deltas_first_correct(): void {
        $d = profile_service::calculate_submit_deltas('', 'gradedright');
        $this->assertSame(10, $d['score']);
        $this->assertSame(5, $d['xp']);
        $this->assertTrue($d['solved']);
    }

    /**
     * Already correct answer yields zeros (idempotent).
     */
    public function test_deltas_already_correct(): void {
        $d = profile_service::calculate_submit_deltas('gradedright', 'gradedright');
        $this->assertSame(0, $d['score']);
        $this->assertSame(0, $d['xp']);
        $this->assertTrue($d['solved']);
    }

    /**
     * First partial answer yields score+5, xp+2, solved=false.
     */
    public function test_deltas_first_partial(): void {
        $d = profile_service::calculate_submit_deltas('', 'gradedpartial');
        $this->assertSame(5, $d['score']);
        $this->assertSame(2, $d['xp']);
        $this->assertFalse($d['solved']);
    }

    /**
     * Wrong answer yields zeros and solved=false.
     */
    public function test_deltas_wrong(): void {
        $d = profile_service::calculate_submit_deltas('', 'gradedwrong');
        $this->assertSame(0, $d['score']);
        $this->assertSame(0, $d['xp']);
        $this->assertFalse($d['solved']);
    }

    /**
     * Upgrade from partial to correct yields score+10, xp+5.
     */
    public function test_deltas_upgrade_partial_to_correct(): void {
        $d = profile_service::calculate_submit_deltas('gradedpartial', 'gradedright');
        $this->assertSame(10, $d['score']);
        $this->assertSame(5,  $d['xp']);
        $this->assertTrue($d['solved']);
    }

    /**
     * Empty progress gives all-zero summary.
     */
    public function test_build_summary_empty(): void {
        $profile = (object)['progressjson' => '[]', 'xp' => 0];
        $summary = profile_service::build_summary($profile);
        $this->assertSame(0, $summary['solvedcount']);
        $this->assertSame(0, $summary['partialcount']);
        $this->assertSame(0, $summary['trackedslots']);
        $this->assertSame(0, $summary['levelprogress']);
    }

    /**
     * Mixed slots are counted correctly in the summary.
     */
    public function test_build_summary_mixed_slots(): void {
        $progress = [
            'slots' => [
                '1' => ['state' => 'gradedright'],
                '2' => ['state' => 'gradedwrong'],
                '3' => ['state' => 'gradedpartial'],
                '4' => ['state' => 'gradedright'],
                '5' => 'complete',
            ],
        ];
        $profile = (object)[
            'progressjson' => json_encode($progress),
            'xp'           => 150,
        ];
        $summary = profile_service::build_summary($profile);
        $this->assertSame(3, $summary['solvedcount'], '3 gradedright/complete');
        $this->assertSame(1, $summary['partialcount'], '1 gradedpartial');
        $this->assertSame(5, $summary['trackedslots'], '5 total slots');
        $this->assertSame(50, $summary['levelprogress'], '150 xp -> progress 50');
    }
}
