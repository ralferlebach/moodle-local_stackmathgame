<?php
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

    public static function is_component_available(string $component): bool {
        return (bool) \core_component::get_component_directory($component);
    }

    public static function get_missing_required_components(): array {
        $missing = [];
        foreach (self::REQUIRED as $component) {
            if (!self::is_component_available($component)) {
                $missing[] = $component;
            }
        }
        return $missing;
    }

    public static function get_available_optional_components(): array {
        $available = [];
        foreach (self::OPTIONAL as $component) {
            if (self::is_component_available($component)) {
                $available[] = $component;
            }
        }
        return $available;
    }

    public static function has_block_xp(): bool {
        return self::is_component_available('block_xp');
    }

    public static function has_block_stash(): bool {
        return self::is_component_available('block_stash');
    }

    public static function export_status(): array {
        return [
            'requiredmissing' => self::get_missing_required_components(),
            'optionalavailable' => self::get_available_optional_components(),
            'hasblockxp' => self::has_block_xp(),
            'hasblockstash' => self::has_block_stash(),
        ];
    }
}
