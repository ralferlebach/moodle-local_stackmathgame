<?php
// This file is part of Moodle - http://moodle.org/

namespace local_stackmathgame\game\integrations;

/**
 * Bridge to block_xp (FMCorz/moodle-block_xp).
 * Fails silently if block_xp is not installed.
 *
 * @package    local_stackmathgame
 */
class xp_bridge {

    /**
     * Award XP to a user in a course.
     * Uses block_xp's course world factory if available.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $xp         Amount of XP to award
     */
    public static function award(int $userid, int $courseid, int $xp): void {
        if ($xp <= 0 || !self::is_available()) {
            return;
        }
        try {
            // block_xp 3.x API (Moodle 4.x compatible)
            $world = \block_xp\di::get('course_world_factory')->get_world($courseid);
            $world->get_store()->increase($userid, $xp);
        } catch (\Throwable $e) {
            debugging('[stackmathgame] block_xp award failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Get the current level of a user in a course.
     * Returns 0 if block_xp is not available.
     */
    public static function get_level(int $userid, int $courseid): int {
        if (!self::is_available()) {
            return 0;
        }
        try {
            $world = \block_xp\di::get('course_world_factory')->get_world($courseid);
            $state = $world->get_store()->get_state($userid);
            return $state->get_level()->get_level();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function is_available(): bool {
        return class_exists('\block_xp\di');
    }
}

// =============================================================================

/**
 * Bridge to block_stash (FMCorz/moodle-block_stash).
 * Fails silently if block_stash is not installed.
 *
 * @package    local_stackmathgame
 */
class stash_bridge {

    /**
     * Drop (award) an item to a user in a course.
     *
     * @param int    $userid
     * @param int    $courseid
     * @param string $itemidnumber  The item's ID number as configured in block_stash
     */
    public static function drop_item(int $userid, int $courseid, string $itemidnumber): bool {
        if (!self::is_available()) {
            return false;
        }
        try {
            $manager = \block_stash\manager::get($courseid);
            $item    = $manager->get_item_by_idnumber($itemidnumber);
            if (!$item) {
                debugging("[stackmathgame] Stash item '{$itemidnumber}' not found in course {$courseid}.", DEBUG_DEVELOPER);
                return false;
            }
            $manager->pickup_item($userid, $item->get_id());
            return true;
        } catch (\Throwable $e) {
            debugging('[stackmathgame] block_stash drop failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Check whether a user has at least one of a given item.
     */
    public static function has_item(int $userid, int $courseid, string $itemidnumber): bool {
        if (!self::is_available()) {
            return false;
        }
        try {
            $manager = \block_stash\manager::get($courseid);
            $item    = $manager->get_item_by_idnumber($itemidnumber);
            if (!$item) {
                return false;
            }
            $useritem = $manager->get_user_item($userid, $item->get_id());
            return $useritem && $useritem->get_quantity() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function is_available(): bool {
        return class_exists('\block_stash\manager');
    }
}
