<?php
namespace local_stackmathgame\plugininfo;

defined('MOODLE_INTERNAL') || die();

/**
 * Plugin info class for stackmathgame mode subplugins.
 */
class stackmathgamemode extends \core\plugininfo\base {
    /**
     * Subplugins are managed via the host plugin package and should not be
     * uninstalled independently through the admin UI.
     *
     * @return bool
     */
    public function is_uninstall_allowed(): bool {
        return false;
    }
}
