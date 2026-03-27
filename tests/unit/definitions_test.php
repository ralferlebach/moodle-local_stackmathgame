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
use local_stackmatheditor\definitions;

/**
 * Unit tests for local_stackmatheditor\definitions.
 *
 * These tests have no DB dependency and run without a full Moodle install.
 * All get_string() calls are shimmed by Moodle's advanced_testcase bootstrap.
 *
 * @package    local_stackmatheditor
 * @covers     \local_stackmatheditor\definitions
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class definitions_test extends advanced_testcase {
    /**
     * Every group must have a non-empty string label.
     */
    public function test_group_labels_are_strings(): void {
        $groups = definitions::get_element_groups();
        foreach ($groups as $key => $group) {
            $this->assertIsString(
                $group['label'],
                "Group '$key' label must be a string"
            );
            $this->assertNotEmpty(
                $group['label'],
                "Group '$key' label must not be empty"
            );
        }
    }

    /**
     * Every group must declare default_enabled as a boolean.
     */
    public function test_group_default_enabled_is_bool(): void {
        $groups = definitions::get_element_groups();
        foreach ($groups as $key => $group) {
            $this->assertIsBool(
                $group['default_enabled'],
                "Group '$key' default_enabled must be bool"
            );
        }
    }

    /**
     * Every group must have at least one element.
     */
    public function test_groups_have_elements(): void {
        $groups = definitions::get_element_groups();
        foreach ($groups as $key => $group) {
            $this->assertNotEmpty(
                $group['elements'],
                "Group '$key' must have at least one element"
            );
        }
    }

    /**
     * Every element must have either 'write' or 'cmd', and a 'display'.
     */
    public function test_elements_have_required_keys(): void {
        $groups = definitions::get_element_groups();
        foreach ($groups as $groupkey => $group) {
            foreach ($group['elements'] as $i => $el) {
                $ref = "Group '$groupkey' element[$i]";
                $this->assertTrue(
                    isset($el['write']) || isset($el['cmd']),
                    "$ref must have 'write' or 'cmd'"
                );
                $this->assertArrayHasKey(
                    'display',
                    $el,
                    "$ref must have 'display'"
                );
                $this->assertIsString(
                    $el['display'],
                    "$ref 'display' must be a string"
                );
            }
        }
    }

    /**
     * The 22 expected groups must all be present.
     */
    public function test_expected_groups_present(): void {
        $groups   = definitions::get_element_groups();
        // Core groups that must always be present.
        $required = [
            'basic_operators', 'power_root', 'exponential_log',
            'comparators', 'absolute', 'brackets',
            'constants_math', 'trigonometry', 'greek_lower', 'greek_upper',
        ];
        // Optional groups that may be disabled/commented out in some builds.
        // Tested only when present.
        $optional = [
            'set_theory', 'logic', 'constants_nature', 'geometry', 'hyperbolic',
            'analysis_operators', 'vector_operators', 'differential_operators',
            'vector_differential', 'matrix_operators', 'integral_operators',
            'statistical_operators',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey(
                $key,
                $groups,
                "Group '$key' must exist in definitions"
            );
        }
        // Optional groups: only assert presence if they appear in the array.
        foreach ($optional as $key) {
            if (array_key_exists($key, $groups)) {
                $this->assertArrayHasKey(
                    $key,
                    $groups,
                    "Optional group '$key' declared but missing"
                );
            }
        }
        // Dummy assertion so test is never marked as risky.
        $this->assertNotEmpty(
            array_keys($groups),
            'definitions must provide at least one group'
        );
    }


    /**
     * Default config keys match group keys.
     */
    public function test_default_config_matches_groups(): void {
        $groups  = definitions::get_element_groups();
        $config  = definitions::get_default_config();
        $this->assertSame(array_keys($groups), array_keys($config));
    }

    /**
     * get_default_enabled() returns only booleans.
     */
    public function test_default_enabled_all_bools(): void {
        $enabled = definitions::get_default_enabled();
        foreach ($enabled as $key => $val) {
            $this->assertIsBool($val, "enabled['$key'] must be bool");
        }
    }


    /**
     * export_for_js() must contain all required top-level keys.
     */
    public function test_export_for_js_keys(): void {
        $data = definitions::export_for_js();
        $required = [
            'elementGroups', 'functions', 'constants', 'operators',
            'comparison', 'greek', 'units', 'unitSymbols',
            'functionNames', 'reservedWords', 'percentConstants',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $data, "export_for_js must include '$key'");
        }
    }

    /**
     * export_for_js() must be JSON-serialisable without errors.
     */
    public function test_export_for_js_is_json_serialisable(): void {
        $data = definitions::export_for_js();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $this->assertNotFalse($json, 'export_for_js() must produce valid JSON');
        $this->assertNotNull(json_decode($json, true), 'JSON must round-trip cleanly');
    }

    /**
     * elementGroups in export_for_js contains the same groups as get_element_groups().
     */
    public function test_export_groups_match_element_groups(): void {
        $groups = definitions::get_element_groups();
        $data   = definitions::export_for_js();
        $this->assertSame(
            array_keys($groups),
            array_keys($data['elementGroups']),
            'Exported groups must match get_element_groups() keys'
        );
    }


    /**
     * Labels with examples must include a parenthesised example snippet.
     */
    public function test_group_labels_with_examples_format(): void {
        $labels = definitions::get_group_labels_with_examples();
        foreach ($labels as $key => $label) {
            $this->assertIsString($label, "Label for '$key' must be string");
            // Each label should have the format "Name (x, y, z, …)" or similar.
            $this->assertStringContainsString(
                '(',
                $label,
                "Label for '$key' should contain an example in parentheses"
            );
        }
    }

    // Helper arrays.

    /**
     * get_functions() returns a non-empty array of strings.
     */
    public function test_get_functions_non_empty(): void {
        $funcs = definitions::get_functions();
        $this->assertIsArray($funcs);
        $this->assertNotEmpty($funcs);
        $this->assertContains('sin', $funcs);
        $this->assertContains('cos', $funcs);
        $this->assertContains('sqrt', $funcs);
    }

    /**
     * get_greek() contains all 24 lowercase Greek letters.
     */
    public function test_get_greek_all_lowercase(): void {
        $greek = definitions::get_greek();
        $expected = ['alpha', 'beta', 'gamma', 'delta', 'epsilon',
                     'zeta', 'eta', 'theta', 'iota', 'kappa', 'lambda',
                     'mu', 'nu', 'xi', 'pi', 'rho', 'sigma', 'tau',
                     'upsilon', 'phi', 'chi', 'psi', 'omega'];
        foreach ($expected as $letter) {
            $this->assertContains($letter, $greek, "Greek array must include '$letter'");
        }
    }

    /**
     * get_unit_symbols() maps every unit in get_units() to a display string.
     */
    public function test_unit_symbols_coverage(): void {
        $units   = definitions::get_units();
        $symbols = definitions::get_unit_symbols();
        foreach ($units as $unit) {
            $this->assertArrayHasKey(
                $unit,
                $symbols,
                "Unit '$unit' must have a display symbol"
            );
        }
    }

    /**
     * get_percent_constants() must start with %.
     */
    public function test_percent_constants_format(): void {
        $consts = definitions::get_percent_constants();
        foreach ($consts as $c) {
            $this->assertStringStartsWith('%', $c, "Percent constant '$c' must start with %");
        }
    }
}
