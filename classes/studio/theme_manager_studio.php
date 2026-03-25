<?php
namespace local_stackmathgame\studio;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\theme_manager;

/**
 * Studio-facing theme listing helper.
 */
class theme_manager_studio {
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function export_all(): array {
        $themes = [];
        foreach (theme_manager::get_all_enabled() as $theme) {
            $themes[] = [
                'id' => (int)$theme->id,
                'name' => (string)$theme->name,
                'shortname' => (string)$theme->shortname,
                'isbuiltin' => !empty($theme->isbuiltin),
                'enabled' => !empty($theme->enabled),
            ];
        }
        return $themes;
    }
}
