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
 * Reads and writes the per-slot direction cards that make up a quiz's game flow.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

use local_stackmathgame\game\quiz_configurator;

/**
 * The authoring side of the question map.
 *
 * Everything here reads and writes local_stackmathgame_questionmap.configjson through
 * slot_config_schema. There is deliberately no second table and no second schema: the runtime
 * already consumes configjson, and a parallel authoring model would be a second source of truth
 * that drifts silently, because both halves would keep working while disagreeing.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_service {
    /**
     * List every slot of an activity with its question metadata and its direction card.
     *
     * @param int $cmid The course-module ID.
     * @return array[] One entry per slot, keyed by slot number, in quiz order.
     */
    public static function get_slots(int $cmid): array {
        global $DB;

        $cm = quiz_configurator::get_supported_cm($cmid);
        $quizid = (int)$cm->instance;

        // Sync first. A teacher who has just edited the quiz expects the flow page to show what
        // the quiz now contains, not what it contained when the map was last touched.
        question_map_service::ensure_for_cmid($cmid);

        $slots = question_map_service::get_quiz_slot_records($quizid);
        $maprows = $DB->get_records('local_stackmathgame_questionmap', ['cmid' => $cmid], 'slotnumber ASC');
        $byslot = [];
        foreach ($maprows as $row) {
            $byslot[(int)$row->slotnumber] = $row;
        }

        $questionids = array_values(array_filter(array_map(
            static fn($slot): int => (int)$slot->questionid,
            $slots
        )));
        $questions = [];
        if ($questionids) {
            [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
            $questions = $DB->get_records_select(
                'question',
                'id ' . $insql,
                $params,
                '',
                'id, name, qtype'
            );
        }

        $result = [];
        foreach ($slots as $slotnumber => $slot) {
            $row = $byslot[$slotnumber] ?? null;
            $question = $questions[(int)$slot->questionid] ?? null;
            $config = $row && !empty($row->configjson)
                ? slot_config_schema::parse((string)$row->configjson)
                : null;

            $result[$slotnumber] = [
                'slotnumber' => $slotnumber,
                'questionid' => (int)$slot->questionid,
                'questionname' => $question ? (string)$question->name : '',
                'qtype' => $question ? (string)$question->qtype : '',
                'isstack' => $question && (string)$question->qtype === prerequisite_checker::REQUIRED_QTYPE,
                'config' => $config ?? slot_config_schema::defaults(),
                'mapid' => $row ? (int)$row->id : 0,
            ];
        }

        return $result;
    }

    /**
     * Return the slot numbers that exist in the activity.
     *
     * @param int $cmid The course-module ID.
     * @return int[] Slot numbers in ascending order.
     */
    public static function get_valid_slots(int $cmid): array {
        $cm = quiz_configurator::get_supported_cm($cmid);
        return array_keys(question_map_service::get_quiz_slot_records((int)$cm->instance));
    }

    /**
     * Load one slot's direction card.
     *
     * @param int $cmid The course-module ID.
     * @param int $slotnumber The slot number.
     * @return array|null The normalised config, or null when the slot does not exist.
     */
    public static function get_slot_config(int $cmid, int $slotnumber): ?array {
        $slots = self::get_slots($cmid);
        return $slots[$slotnumber]['config'] ?? null;
    }

    /**
     * Persist one slot's direction card.
     *
     * Validation happens here rather than only in the form, because the form is not the only
     * caller: bulk apply writes through the same path, and so would any future web service.
     *
     * @param int $cmid The course-module ID.
     * @param int $slotnumber The slot number.
     * @param array $config The config to store.
     * @return string[] Validation errors; the config is not written when this is non-empty.
     */
    public static function save_slot_config(int $cmid, int $slotnumber, array $config): array {
        global $DB;

        $validslots = self::get_valid_slots($cmid);
        if (!in_array($slotnumber, $validslots, true)) {
            return [get_string('flow_err_unknownslot', 'local_stackmathgame', $slotnumber)];
        }

        // Round-trip through the schema so what is validated is exactly what will be stored.
        // Validating the input and storing something else is how the two drift apart.
        $normalised = slot_config_schema::parse(json_encode($config, JSON_UNESCAPED_UNICODE));
        if ($normalised === null) {
            return [get_string('flow_err_unparseable', 'local_stackmathgame')];
        }

        $errors = slot_config_schema::validate($normalised, $validslots);
        if ($errors) {
            return $errors;
        }

        $row = $DB->get_record(
            'local_stackmathgame_questionmap',
            ['cmid' => $cmid, 'slotnumber' => $slotnumber],
            '*',
            MUST_EXIST
        );
        $DB->update_record('local_stackmathgame_questionmap', (object)[
            'id' => $row->id,
            'configjson' => json_encode($normalised, JSON_UNESCAPED_UNICODE),
            'timemodified' => time(),
        ]);

        return [];
    }

    /**
     * Apply a partial configuration to several slots at once.
     *
     * Only the sections present in $partial are touched, so applying a scene type to twenty
     * slots does not silently wipe twenty narratives.
     *
     * @param int $cmid The course-module ID.
     * @param int[] $slotnumbers The slots to change.
     * @param array $partial Sections to overwrite: any of scene, branching, rewards, display.
     * @return array Map of slot number to validation errors; empty values mean success.
     */
    public static function apply_to_slots(int $cmid, array $slotnumbers, array $partial): array {
        $results = [];
        foreach ($slotnumbers as $slotnumber) {
            $slotnumber = (int)$slotnumber;
            $current = self::get_slot_config($cmid, $slotnumber);
            if ($current === null) {
                $results[$slotnumber] = [get_string('flow_err_unknownslot', 'local_stackmathgame', $slotnumber)];
                continue;
            }
            foreach (['scene', 'branching', 'rewards', 'display', 'narrative'] as $section) {
                if (array_key_exists($section, $partial)) {
                    $current[$section] = array_merge($current[$section], (array)$partial[$section]);
                }
            }
            $results[$slotnumber] = self::save_slot_config($cmid, $slotnumber, $current);
        }
        return $results;
    }

    /**
     * Report the quiz's own structure: levels from section headings, groups from pages.
     *
     * Read-only, and deliberately so. Moodle already lets an author express both of these -
     * a section heading starts a new part of the quiz, and several questions on one page belong
     * together - and the game has been ignoring both. Surfacing them costs nothing and is the
     * foundation the rest of issue #9 needs: you cannot configure what a page of questions means
     * until the editor can show that the page exists.
     *
     * Nothing here changes how the game plays. Deciding whether the questions on one page are
     * alternatives, the stages of one quest, or simply separate scenes is a further step, and it
     * needs a data model that does not exist yet.
     *
     * @param int $cmid The course-module ID.
     * @return array[] One entry per level: heading, and its pages with their slot numbers.
     */
    public static function get_structure(int $cmid): array {
        global $DB;

        $cm = quiz_configurator::get_supported_cm($cmid);
        $quizid = (int)$cm->instance;

        $sections = $DB->get_records('quiz_sections', ['quizid' => $quizid], 'firstslot ASC');
        $slots = self::get_slots($cmid);

        // Moodle always has a section starting at slot 1, usually without a heading. An unnamed
        // first section is the quiz itself, not a level, so it gets no title rather than an
        // empty one.
        $boundaries = [];
        foreach ($sections as $section) {
            $boundaries[(int)$section->firstslot] = trim((string)$section->heading);
        }

        $levels = [];
        $current = null;
        foreach ($slots as $slotnumber => $slot) {
            if (isset($boundaries[$slotnumber]) || $current === null) {
                $heading = $boundaries[$slotnumber] ?? '';
                $current = [
                    'heading' => $heading,
                    'firstslot' => $slotnumber,
                    'pages' => [],
                ];
                $levels[] = &$current;
                unset($current);
                $current = &$levels[count($levels) - 1];
            }
            $page = navigation_resolver::page_for_slot($quizid, $slotnumber);
            if (!isset($current['pages'][$page])) {
                $current['pages'][$page] = [];
            }
            $current['pages'][$page][] = $slotnumber;
        }
        unset($current);

        return $levels;
    }

    /**
     * Return the level a slot belongs to, and whether that slot opens it.
     *
     * The runtime needs both halves: which level the player is in, so it can name it, and whether
     * they have just entered it, so the intro plays once rather than on every question of the
     * level.
     *
     * @param int $cmid The course-module ID.
     * @param int $slot The slot number.
     * @return array{heading: string, firstslot: int, isfirst: bool, index: int}|null
     *         The level, or null when the slot is unknown.
     */
    public static function level_for_slot(int $cmid, int $slot): ?array {
        foreach (self::get_structure($cmid) as $index => $level) {
            foreach ($level['pages'] as $slots) {
                if (in_array($slot, $slots, true)) {
                    return [
                        'heading' => (string)$level['heading'],
                        'firstslot' => (int)$level['firstslot'],
                        'isfirst' => (int)$level['firstslot'] === $slot,
                        'index' => (int)$index,
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Report pages that hold more than one question.
     *
     * The branch resolver navigates between pages, so today a page with several questions plays
     * only its first one and silently skips the rest. Until issue #9 gives those pages a meaning,
     * naming them is the honest thing to do - a game that quietly ignores a teacher's questions
     * is worse than one that says it cannot use them yet.
     *
     * @param int $cmid The course-module ID.
     * @return array[] Page number to the slot numbers it holds, only for pages with several.
     */
    public static function get_crowded_pages(int $cmid): array {
        $pages = [];
        foreach (self::get_structure($cmid) as $level) {
            foreach ($level['pages'] as $page => $slots) {
                if (count($slots) > 1) {
                    $pages[$page] = $slots;
                }
            }
        }
        ksort($pages);
        return $pages;
    }

    /**
     * Report slots a player can never reach, and slots a player can never leave.
     *
     * Both are authoring mistakes that no single direction card is wrong about - each one is
     * valid on its own, and only the graph as a whole shows the problem. Without this, the fault
     * surfaces as a player stuck mid-quiz, which is the most expensive place to find it.
     *
     * @param int $cmid The course-module ID.
     * @return array Two lists: 'unreachable' and 'deadends', each of slot numbers.
     */
    public static function analyse_reachability(int $cmid): array {
        $slots = self::get_slots($cmid);
        $numbers = array_keys($slots);
        if (!$numbers) {
            return ['unreachable' => [], 'deadends' => []];
        }
        sort($numbers);

        $edges = [];
        $deadends = [];
        foreach ($slots as $slotnumber => $slot) {
            $targets = [];
            $ends = 0;
            $rules = (array)($slot['config']['branching'] ?? []);
            foreach (
                [
                slot_config_schema::OUTCOME_GRADEDRIGHT,
                slot_config_schema::OUTCOME_COMPLETE,
                slot_config_schema::OUTCOME_DEFAULT,
                ] as $outcome
            ) {
                $rule = (array)($rules[$outcome] ?? []);
                $mode = (string)($rule['mode'] ?? slot_config_schema::BRANCH_MODE_LINEAR);
                if ($mode === slot_config_schema::BRANCH_MODE_END) {
                    $ends++;
                    continue;
                }
                if ($mode === slot_config_schema::BRANCH_MODE_SLOT) {
                    $target = (int)($rule['target'] ?? 0);
                    if (in_array($target, $numbers, true)) {
                        $targets[$target] = true;
                    }
                    continue;
                }
                $next = self::next_linear($numbers, $slotnumber);
                if ($next > 0) {
                    $targets[$next] = true;
                } else {
                    // The last slot under linear branching finishes the run. That is an ending,
                    // not a dead end.
                    $ends++;
                }
            }
            $edges[$slotnumber] = array_keys($targets);
            if (!$targets && !$ends) {
                $deadends[] = $slotnumber;
            }
        }

        $start = $numbers[0];
        $seen = [$start => true];
        $queue = [$start];
        while ($queue) {
            $current = array_shift($queue);
            foreach ($edges[$current] ?? [] as $target) {
                if (!isset($seen[$target])) {
                    $seen[$target] = true;
                    $queue[] = $target;
                }
            }
        }

        return [
            'unreachable' => array_values(array_diff($numbers, array_keys($seen))),
            'deadends' => $deadends,
        ];
    }

    /**
     * Return the next slot number in ascending order.
     *
     * @param int[] $numbers All slot numbers, sorted ascending.
     * @param int $current The current slot number.
     * @return int The next slot, or 0 when there is none.
     */
    private static function next_linear(array $numbers, int $current): int {
        foreach ($numbers as $number) {
            if ($number > $current) {
                return $number;
            }
        }
        return 0;
    }
}
