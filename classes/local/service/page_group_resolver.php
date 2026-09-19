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
 * Resolves which questions of a shared quiz page a player actually gets.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

/**
 * The three ways several questions on one quiz page can relate to each other.
 *
 * Resolution happens here and only here, on the server. The client is told which slot is active;
 * it never works it out. That is not tidiness: a client that decides which alternative counts can
 * be told to decide differently, and answering a question the player was never given is exactly
 * the kind of thing a scoring system must not permit.
 *
 * Each STACK question stays its own question-engine slot throughout. Nothing here bypasses the
 * engine or invents a second attempt model - a hidden alternative is a slot the game declines to
 * present, not a slot that does not exist.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page_group_resolver {
    /**
     * Resolve one quiz page into the slots the player may answer, in play order.
     *
     * @param int $cmid The course-module ID.
     * @param int $page The zero-based attempt page.
     * @param int $attemptid The attempt, used to keep a random pick stable.
     * @param array $solved Slot numbers already solved, as slot => true.
     * @return array {
     *     mode: string, the group mode in force;
     *     slots: int[], the slots of the page in quiz order;
     *     playable: int[], the slots this player may answer;
     *     active: int, the slot to present now, or 0 when the group is finished;
     *     total: int, how many stages the player must complete;
     *     done: int, how many they have completed.
     * }
     */
    public static function resolve(int $cmid, int $page, int $attemptid, array $solved = []): array {
        $slots = self::slots_on_page($cmid, $page);
        $empty = [
            'mode' => slot_config_schema::GROUP_MODE_SCENES,
            'slots' => $slots,
            'playable' => $slots,
            'active' => (int)(reset($slots) ?: 0),
            'total' => count($slots),
            'done' => 0,
        ];
        if (count($slots) < 2) {
            // A page with one question is the ordinary case and needs no group at all. Returning
            // early keeps that path exactly as it was before groups existed.
            $first = (int)(reset($slots) ?: 0);
            $empty['done'] = ($first && !empty($solved[$first])) ? 1 : 0;
            $empty['active'] = ($empty['done'] === 0) ? $first : 0;
            return $empty;
        }

        // The group is a property of the page, so it is read from the page's first slot. Reading
        // it from every member would let the members disagree about what they collectively are.
        $config = flow_service::get_slot_config($cmid, (int)reset($slots)) ?? slot_config_schema::defaults();
        $group = (array)($config['group'] ?? []);
        $mode = (string)($group['mode'] ?? slot_config_schema::GROUP_MODE_SCENES);

        if ($mode === slot_config_schema::GROUP_MODE_ALTERNATIVES) {
            return self::resolve_alternatives($slots, $group, $attemptid, $solved);
        }
        if ($mode === slot_config_schema::GROUP_MODE_QUEST) {
            return self::resolve_quest($slots, $solved);
        }

        $done = count(array_filter($slots, static fn(int $s): bool => !empty($solved[$s])));
        $unsolved = array_values(array_filter($slots, static fn(int $s): bool => empty($solved[$s])));
        $empty['done'] = $done;
        $empty['active'] = (int)($unsolved[0] ?? 0);
        return $empty;
    }

    /**
     * Report whether a player may answer a given slot right now.
     *
     * The security half of this feature. Without it, a hidden alternative could be answered by
     * posting its slot number directly - the question engine would happily accept it, because as
     * far as the engine is concerned every slot of the attempt is legitimate.
     *
     * @param int $cmid The course-module ID.
     * @param int $page The zero-based attempt page.
     * @param int $slot The slot the player is trying to answer.
     * @param int $attemptid The attempt.
     * @param array $solved Slot numbers already solved, as slot => true.
     * @return bool True when the slot is one this player was given.
     */
    public static function is_slot_playable(
        int $cmid,
        int $page,
        int $slot,
        int $attemptid,
        array $solved = []
    ): bool {
        $resolved = self::resolve($cmid, $page, $attemptid, $solved);
        return in_array($slot, $resolved['playable'], true);
    }

    /**
     * Choose the alternatives for this attempt.
     *
     * The choice has to be stable: a reload that produced a different question would lose the
     * work already done on the first one, and would also let a player reshuffle until they liked
     * the question. Seeding from the attempt and the page gives the same answer every time
     * without storing anything.
     *
     * @param int[] $slots The page's slots in quiz order.
     * @param array $group The group configuration.
     * @param int $attemptid The attempt.
     * @param array $solved Slot numbers already solved.
     * @return array The resolution.
     */
    private static function resolve_alternatives(
        array $slots,
        array $group,
        int $attemptid,
        array $solved
    ): array {
        $pick = max(1, min(count($slots), (int)($group['pick'] ?? 1)));
        $strategy = (string)($group['strategy'] ?? slot_config_schema::PICK_RANDOM);

        $ordered = $slots;
        if ($strategy === slot_config_schema::PICK_RANDOM) {
            // Ordered by a hash of attempt and slot rather than by a seeded shuffle: the result
            // is stable across reloads, differs between players, and needs no random generator -
            // Random\Randomizer would tie this to PHP 8.2, and shuffle() with a global seed
            // would disturb whatever else in the request relies on it.
            //
            // Stability is the point. A reload that produced a different question would discard
            // the work already done on the first, and would let a player reshuffle until they
            // liked what they got. Which alternative you receive is not a secret, so a hash is
            // ample.
            usort($ordered, static function (int $a, int $b) use ($attemptid): int {
                return strcmp(
                    md5($attemptid . ':' . $a),
                    md5($attemptid . ':' . $b)
                );
            });
        }

        $playable = array_slice($ordered, 0, $pick);
        sort($playable);
        $unsolved = array_values(array_filter($playable, static fn(int $s): bool => empty($solved[$s])));

        return [
            'mode' => slot_config_schema::GROUP_MODE_ALTERNATIVES,
            'slots' => $slots,
            'playable' => $playable,
            'active' => (int)($unsolved[0] ?? 0),
            'total' => count($playable),
            'done' => count($playable) - count($unsolved),
        ];
    }

    /**
     * Resolve a staged quest: every stage in order, the first unsolved one is active.
     *
     * Every stage stays playable rather than only the current one, so a player who has solved
     * stage two can still look back at stage one. What advances is which stage is presented.
     *
     * @param int[] $slots The page's slots in quiz order.
     * @param array $solved Slot numbers already solved.
     * @return array The resolution.
     */
    private static function resolve_quest(array $slots, array $solved): array {
        $unsolved = array_values(array_filter($slots, static fn(int $s): bool => empty($solved[$s])));

        return [
            'mode' => slot_config_schema::GROUP_MODE_QUEST,
            'slots' => $slots,
            'playable' => $slots,
            'active' => (int)($unsolved[0] ?? 0),
            'total' => count($slots),
            'done' => count($slots) - count($unsolved),
        ];
    }

    /**
     * Return the slot numbers on one attempt page, in quiz order.
     *
     * @param int $cmid The course-module ID.
     * @param int $page The zero-based attempt page.
     * @return int[] The slot numbers.
     */
    private static function slots_on_page(int $cmid, int $page): array {
        $found = [];
        foreach (flow_service::get_structure($cmid) as $level) {
            foreach ($level['pages'] as $pagenumber => $slots) {
                if ((int)$pagenumber === $page) {
                    $found = array_merge($found, $slots);
                }
            }
        }
        sort($found);
        return array_values(array_unique($found));
    }
}
