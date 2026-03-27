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
 * Mechanic registry for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\game;

/**
 * Registry for named mechanics used by the JS layer.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mechanic_registry {
    /**
     * Return all registered game mechanics with their descriptions.
     *
     * @return array<string, string> Mechanic key => description map.
     */
    public static function all(): array {
        return [
            'adaptivepath'   => 'Adaptive pathing between questions and instruction pages.',
            'speechbubbles'  => 'Narrative feedback bubble wrapper.',
            'gamifiednav'    => 'Level-style navigation and progress states.',
            'mobilemathkeys' => 'Math input helper buttons on mobile devices.',
        ];
    }
}
