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
 * Turns a branching decision into everything the client needs to navigate.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

/**
 * Resolves where the player goes next, on the server.
 *
 * The branching schema has exactly one interpreter, and it is this one. Before, the server
 * resolved branches in branch_resolver while each mode subplugin independently re-read the same
 * configjson in JavaScript - and read it differently: the modes only ever produced a link for an
 * explicit `slot` jump, so `linear` (the default every auto-created slot gets) and `end` left the
 * player on a finished scene with no way forward. Two interpretations of one schema is the
 * defect; this service exists so there is only one.
 *
 * A mode receives a resolved navigation array and renders it. It does not decide anything.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation_resolver {
    /** The player continues to another question. */
    const ACTION_CONTINUE = 'continue';
    /** No further question: the attempt should be finished. */
    const ACTION_FINISH = 'finish';
    /** The player stays where they are, typically after a wrong answer. */
    const ACTION_STAY = 'stay';

    /** Another stage of the same page group follows; the run has not moved on. */
    const ACTION_SUBSTEP = 'substep';

    /**
     * Resolve the navigation step following an outcome on a slot.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @param int $currentslot The slot the player is on.
     * @param string $outcome One of the slot_config_schema OUTCOME_* values.
     * @param \stdClass $profile The player profile.
     * @param int $attemptid The quiz attempt ID, or 0 when not known.
     * @return array The navigation payload.
     */
    public static function resolve(
        int $cmid,
        int $quizid,
        int $currentslot,
        string $outcome,
        \stdClass $profile,
        int $attemptid = 0
    ): array {
        // A wrong answer keeps the player on the scene. Resolving a target for it would let the
        // client offer a way forward the moment an answer is graded wrong, which is the opposite
        // of what a game wants: the retry is the point.
        if ($outcome === slot_config_schema::OUTCOME_GRADEDWRONG) {
            return self::payload(self::ACTION_STAY, 0, 0, $attemptid, $cmid);
        }

        // A quiz page that is a group is finished before the run moves on. Stepping to the next
        // node while a quest still has stages left would skip them silently, and a page of
        // alternatives would move on after the first of several the player was given.
        //
        // The distinction is deliberate: advancing within a group is a different fact from
        // advancing between nodes, and the two used to be the same word.
        $group = self::group_for($cmid, $quizid, $currentslot, $profile, $attemptid);
        $subslot = ($group !== null && $group['mode'] !== slot_config_schema::GROUP_MODE_SCENES)
            ? (int)$group['active']
            : 0;
        if ($subslot > 0) {
            return self::payload(
                self::ACTION_SUBSTEP,
                $subslot,
                self::page_for_slot($quizid, $subslot),
                $attemptid,
                $cmid,
                $group
            );
        }

        $nextslot = branch_resolver::resolve_next_slot(
            $cmid,
            $quizid,
            $currentslot,
            $outcome,
            $profile
        );

        if ($nextslot <= 0) {
            return self::payload(self::ACTION_FINISH, 0, 0, $attemptid, $cmid);
        }

        return self::payload(
            self::ACTION_CONTINUE,
            $nextslot,
            self::page_for_slot($quizid, $nextslot),
            $attemptid,
            $cmid,
            null
        );
    }

    /**
     * Resolve the page group the current slot belongs to.
     *
     * Zero when the page is not a group, or when the group is done - the caller then resolves an
     * ordinary branch. Keeping "another stage here" and "another node there" apart is what lets a
     * staged quest exist at all: with one answer for both, finishing stage one of three would
     * look exactly like finishing the whole quest.
     *
     * @param int $cmid The course-module ID.
     * @param int $quizid The quiz instance ID.
     * @param int $currentslot The slot just answered.
     * @param \stdClass $profile The player's profile.
     * @param int $attemptid The attempt, which fixes the choice of alternatives.
     * @return array|null The group resolution, or null when the page is not a group.
     */
    private static function group_for(
        int $cmid,
        int $quizid,
        int $currentslot,
        \stdClass $profile,
        int $attemptid
    ): ?array {
        $page = self::page_for_slot($quizid, $currentslot);
        $solved = profile_service::solved_slots($profile);
        // The slot just answered counts as solved: the profile is written after navigation is
        // resolved, so reading it alone would offer the same stage again.
        $solved[$currentslot] = true;

        return page_group_resolver::resolve($cmid, $page, $attemptid, $solved);
    }

    /**
     * Resolve the page index a slot is rendered on.
     *
     * quiz_slots.page is one-based; attempt.php's page parameter is zero-based. Getting this
     * wrong is not a visible error - the player simply lands on the wrong question - so the
     * conversion lives here rather than being repeated at each call site.
     *
     * @param int $quizid The quiz instance ID.
     * @param int $slot The slot number.
     * @return int The zero-based page index.
     */
    public static function page_for_slot(int $quizid, int $slot): int {
        global $DB;

        $page = $DB->get_field('quiz_slots', 'page', ['quizid' => $quizid, 'slot' => $slot]);
        if ($page === false || $page === null) {
            return 0;
        }
        return max(0, (int)$page - 1);
    }

    /**
     * Build a navigation payload.
     *
     * @param string $action One of the ACTION_* constants.
     * @param int $nextslot The resolved slot, or 0.
     * @param int $nextpage The zero-based page index, or 0.
     * @param int $attemptid The attempt ID, or 0 when not known.
     * @param int $cmid The course-module ID, needed to resolve the level. 0 skips that.
     * @param array|null $group The resolved page group, when the next slot belongs to one.
     * @return array The navigation payload.
     */
    private static function payload(
        string $action,
        int $nextslot,
        int $nextpage,
        int $attemptid,
        int $cmid = 0,
        ?array $group = null
    ): array {
        $url = '';
        if ($attemptid > 0) {
            if ($action === self::ACTION_CONTINUE || $action === self::ACTION_SUBSTEP) {
                $url = (new \moodle_url('/mod/quiz/attempt.php', [
                    'attempt' => $attemptid,
                    'page' => $nextpage,
                ]))->out(false);
            } else if ($action === self::ACTION_FINISH) {
                $url = (new \moodle_url('/mod/quiz/summary.php', ['attempt' => $attemptid]))->out(false);
            }
        }

        // Whether the next slot opens a new level, and what that level is called. Resolved here
        // because the level structure is the quiz's, not the game mode's - a mode that worked it
        // out itself would be reading quiz_sections, which is exactly the kind of second
        // interpretation this resolver exists to prevent.
        $level = ($cmid > 0 && $nextslot > 0)
            ? flow_service::level_for_slot($cmid, $nextslot)
            : null;

        return [
            'action' => $action,
            'nextslot' => $nextslot,
            'nextpage' => $nextpage,
            'url' => $url,
            'enterslevel' => (bool)($level['isfirst'] ?? false) && ($level['heading'] ?? '') !== '',
            'levelheading' => (string)($level['heading'] ?? ''),
            // Where the player stands in the group, for a mode that wants to show it.
            // Resolved here so every mode shows the same thing, and so no mode has to know
            // what a group is.
            'groupmode' => (string)($group['mode'] ?? slot_config_schema::GROUP_MODE_SCENES),
            'grouptotal' => (int)($group['total'] ?? 0),
            'groupdone' => (int)($group['done'] ?? 0),
            // The label is resolved server-side too. A mode that invented its own wording would
            // be making a decision about a state it does not own - and the three modes disagreed
            // about what "no next slot" even meant.
            'label' => get_string('nav_' . $action, 'local_stackmathgame'),
        ];
    }

    /**
     * Describe the navigation payload for a web service return structure.
     *
     * @return \core_external\external_single_structure The structure.
     */
    public static function external_structure(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'action' => new \core_external\external_value(
                PARAM_ALPHA,
                'continue, substep, finish or stay. "substep" means another stage of the same '
                    . 'page group follows - the run itself has not advanced.'
            ),
            'nextslot' => new \core_external\external_value(PARAM_INT, 'Resolved next slot number, 0 when none'),
            'nextpage' => new \core_external\external_value(PARAM_INT, 'Zero-based attempt page of the next slot'),
            'url' => new \core_external\external_value(PARAM_URL, 'Where to navigate, empty when staying'),
            'label' => new \core_external\external_value(PARAM_TEXT, 'Label for the navigation control'),
            'enterslevel' => new \core_external\external_value(
                PARAM_BOOL,
                'True when the next slot opens a new, named level - play the chapter_start '
                    . 'narrative once here rather than on every question of the level.'
            ),
            'levelheading' => new \core_external\external_value(
                PARAM_TEXT,
                'The level name, from the quiz section heading. Empty when there is none.'
            ),
            'groupmode' => new \core_external\external_value(
                PARAM_ALPHA,
                'scenes, alternatives or quest - how the questions of this page relate'
            ),
            'grouptotal' => new \core_external\external_value(
                PARAM_INT,
                'How many stages this page group has, 0 when it is not a group'
            ),
            'groupdone' => new \core_external\external_value(
                PARAM_INT,
                'How many stages of the group the player has completed'
            ),
        ]);
    }
}
