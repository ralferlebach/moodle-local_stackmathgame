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
 * Upgrade steps for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The old version.
 * @return bool
 */
function xmldb_local_stackmathgame_upgrade(int $oldversion): bool {
    if ($oldversion < 2026032825) {
        // Patch 2026032825: two runtime bug fixes.
        // - submit_answer: question_state_todo::get_name() replaced with string cast.
        // - game_engine.js: getCurrentSlot() derives slot from Moodle DOM id attribute
        //   "question-{attempt}-{slot}" so slot is never 0 on attempt pages.
        upgrade_plugin_savepoint(true, 2026032825, 'local', 'stackmathgame');
    }

    return true;
}
