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
use local_stackmathgame\external\api;

/**
 * Unit tests for pure helper methods in local_stackmathgame\external\api.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\external\api
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_helper_test extends advanced_testcase {
    /**
     * Full payload passes through with correct types.
     */
    public function test_normalise_full_payload(): void {
        $input = ['questionid' => 7, 'slot' => 3, 'answers' => ['a', 'b']];
        $result = api::normalise_question_payload($input);
        $this->assertSame(7, $result['questionid']);
        $this->assertSame(3, $result['slot']);
        $this->assertSame(['a', 'b'], $result['answers']);
    }

    /**
     * Empty payload returns safe zero/empty defaults.
     */
    public function test_normalise_empty_payload(): void {
        $result = api::normalise_question_payload([]);
        $this->assertSame(0, $result['questionid']);
        $this->assertSame(0, $result['slot']);
        $this->assertSame([], $result['answers']);
    }

    /**
     * Numeric strings are cast to int by the normaliser.
     */
    public function test_normalise_string_values(): void {
        $result = api::normalise_question_payload(['questionid' => '42', 'slot' => '5']);
        $this->assertSame(42, $result['questionid']);
        $this->assertSame(5, $result['slot']);
    }

    /**
     * export_design(null) returns all required keys.
     */
    public function test_export_design_null_has_required_keys(): void {
        $export = api::export_design(null);
        $required = [
            'id', 'name', 'slug', 'modecomponent', 'description',
            'isbundled', 'isactive', 'narrativejson', 'uijson',
            'mechanicsjson', 'assetmanifestjson', 'runtimejson',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $export, "export_design(null) must have key '$key'");
        }
    }

    /**
     * export_design(null) returns zeros and empty strings.
     */
    public function test_export_design_null_default_values(): void {
        $export = api::export_design(null);
        $this->assertSame(0, $export['id']);
        $this->assertSame('', $export['name']);
        $this->assertSame('{}', $export['runtimejson']);
        $this->assertSame(0, $export['isbundled']);
    }

    /**
     * export_profile returns required keys with correct types.
     */
    public function test_export_profile_has_required_keys(): void {
        $profile = (object)[
            'id' => 1,
            'userid' => 2,
            'labelid' => 3,
            'score' => 100,
            'xp' => 250,
            'levelno' => 3,
            'softcurrency' => 10,
            'hardcurrency' => 5,
            'avatarconfigjson' => '{}',
            'progressjson' => '{}',
            'statsjson' => '{}',
            'flagsjson' => '{}',
            'lastquizid' => 7,
            'lastdesignid' => 4,
            'lastaccess' => 1700000000,
        ];
        $export = api::export_profile($profile);
        $this->assertSame(1, $export['id']);
        $this->assertSame(100, $export['score']);
        $this->assertSame(250, $export['xp']);
        $this->assertSame(3, $export['levelno']);
        $this->assertArrayHasKey('summaryjson', $export);
        $summary = json_decode($export['summaryjson'], true);
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('solvedcount', $summary);
    }
}
