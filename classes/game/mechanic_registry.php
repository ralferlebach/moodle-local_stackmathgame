<?php
namespace local_stackmathgame\game;

defined('MOODLE_INTERNAL') || die();

/**
 * Registry for named mechanics used by the JS layer.
 */
class mechanic_registry {
    /**
     * @return array<string,string>
     */
    public static function all(): array {
        return [
            'adaptivepath' => 'Adaptive pathing between questions and instruction pages',
            'speechbubbles' => 'Narrative feedback bubble wrapper',
            'gamifiednav' => 'Level-style navigation and progress states',
            'mobilemathkeys' => 'Math input helper buttons on mobile devices',
        ];
    }
}
