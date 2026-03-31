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
 * Service for reading local reward inventory state.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

/**
 * Load local reward inventory rows for profiles and activities.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class inventory_service {
    /**
     * Load all inventory items for a profile, keyed by itemkey.
     *
     * @param int $profileid The profile ID.
     * @return array<int|string, \stdClass> Inventory rows keyed by itemkey.
     */
    public static function get_for_profile(int $profileid): array {
        global $DB;

        if ($profileid <= 0) {
            return [];
        }

        $rows = $DB->get_records('local_stackmathgame_inventory', ['profileid' => $profileid], 'itemkey ASC');
        $byitem = [];
        foreach ($rows as $row) {
            $byitem[(string)$row->itemkey] = $row;
        }
        return $byitem;
    }

    /**
     * Load all inventory items for the current activity identity.
     *
     * @param int $userid The user ID.
     * @param int $cmid The course-module ID.
     * @param string $modname The module name.
     * @param int $instanceid The activity instance ID.
     * @return array<int|string, \stdClass> Inventory rows keyed by itemkey.
     */
    public static function get_for_activity(
        int $userid,
        int $cmid,
        string $modname = 'quiz',
        int $instanceid = 0
    ): array {
        $profile = profile_service::get_or_create_for_activity($userid, $cmid, $modname, $instanceid);
        return self::get_for_profile((int)$profile->id);
    }
}
