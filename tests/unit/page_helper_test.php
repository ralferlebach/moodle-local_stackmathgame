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

namespace local_stackmatheditor\tests\unit;

use advanced_testcase;
use local_stackmatheditor\output\page_helper;

/**
 * Unit tests for local_stackmatheditor\output\page_helper.
 *
 * @package    local_stackmatheditor
 * @covers     \local_stackmatheditor\output\page_helper
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page_helper_test extends advanced_testcase {
    /**
     * inject_json_element() must not throw for a valid array payload.
     * The method calls $PAGE->requires->js_amd_inline() which is available
     * in Moodle's test environment.
     *
     * @group local_stackmatheditor_db
     */
    public function test_inject_does_not_throw(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = ['key' => 'value', 'nested' => [1, 2, 3]];
        // If this throws, the test fails automatically.
        page_helper::inject_json_element('sme-test-element', $data);
        $this->assertTrue(true, 'inject_json_element must not throw');
    }

    /**
     * inject_json_element() must throw for un-serialisable data.
     */
    public function test_inject_throws_for_invalid_data(): void {
        // A resource cannot be JSON-encoded.
        $resource = fopen('php://memory', 'r');
        $this->expectException(\JsonException::class);
        try {
            page_helper::inject_json_element('test', $resource);
        } finally {
            fclose($resource);
        }
    }
}
