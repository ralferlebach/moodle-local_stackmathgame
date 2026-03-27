<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin availability checks for local_stackmathgame integrations.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\integration;

/**
 * Checks the availability of required and optional companion plugins.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class availability {
    /** @var string[] Required plugin components. */
    private const REQUIRED = [
        'qtype_stack',
        'qbehaviour_stackmathgame',
        'filter_shortcodes',
    ];

    /** @var string[] Optional plugin components. */
    private const OPTIONAL = [
        'block_xp',
        'block_stash',
    ];

    /**
     * Return true when the given component is installed and enabled.
     *
     * @param string $component The component name (e.g. 'block_xp').
     * @return bool True if the component is available.
     */
    public static function is_component_available(string $component): bool {
        if (!\core_component::get_component_directory($component)) {
            return false;
        }
        return true;
    }

    /**
     * Return the list of missing required components.
     *
     * @return string[] Array of missing component names.
     */
    public static function get_missing_required_components(): array {
        $missing = [];
        foreach (self::REQUIRED as $component) {
            if (!self::is_component_available($component)) {
                $missing[] = $component;
            }
        }
        return $missing;
    }

    /**
     * Return the list of available optional components.
     *
     * @return string[] Array of available optional component names.
     */
    public static function get_available_optional_components(): array {
        $available = [];
        foreach (self::OPTIONAL as $component) {
            if (self::is_component_available($component)) {
                $available[] = $component;
            }
        }
        return $available;
    }

    /**
     * Check whether block_xp is available.
     *
     * @return bool True if block_xp is installed.
     */
    public static function has_block_xp(): bool {
        return self::is_component_available('block_xp');
    }

    /**
     * Check whether block_stash is available.
     *
     * @return bool True if block_stash is installed.
     */
    public static function has_block_stash(): bool {
        return self::is_component_available('block_stash');
    }
}
