<?php
namespace local_stackmathgame\plugininfo;

defined('MOODLE_INTERNAL') || die();

/**
 * Plugininfo handler for stackmathgame mode subplugins.
 */
class stackmathgamemode extends \core\plugininfo\base {
    public function is_uninstall_allowed() {
        return true;
    }
}
