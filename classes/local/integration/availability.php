<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_stackmathgame\local\integration;

defined('MOODLE_INTERNAL') || die();

/**
 * Checks the availability of required and optional companion plugins.
 */
final class availability {
    /** @var string[] required plugin components. */
    private const REQUIRED = [
        'qtype_stack',
        'qbehaviour_stackmathgame',
        'filter_shortcodes',
    ];

    /** @var string[] optional plugin components. */
    private const OPTIONAL = [
        'block_xp',
        'block_stash',
    ];

    /**
     * Return true when the given component is installed and enabled.
     *
     * @param string $component
     * @return bool
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
     * @return string[]
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
     * @return string[]
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
     * @return bool
     */
    public static function has_block_xp(): bool {
        return self::is_component_available('block_xp');
    }

    /**
     * Check whether block_stash is available.
     *
     * @return bool
     */
    public static function has_block_stash(): bool {
        return self::is_component_available('block_stash');
    }
}
