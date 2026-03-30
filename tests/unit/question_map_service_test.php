<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_stackmathgame\tests\unit;

use advanced_testcase;
use local_stackmathgame\external\api;
use local_stackmathgame\local\service\question_map_service;

/**
 * Tests for question_map_service.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\question_map_service
 * @covers     \local_stackmathgame\external\api
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_map_service_test extends advanced_testcase {
    /**
     * ensure_for_cmid() creates canonical rows from quiz slots.
     *
     * @group local_stackmathgame_db
     */
    public function test_ensure_for_cmid_creates_rows_from_quiz_slots(): void {
        global $DB;

        $this->resetAfterTest();
        [$quiz, $cmid] = $this->create_quiz_with_questions(2);

        $summary = question_map_service::ensure_for_cmid($cmid);
        $records = $DB->get_records('local_stackmathgame_questionmap', ['cmid' => $cmid], 'slotnumber ASC');

        $this->assertSame(2, (int)$summary['slots']);
        $this->assertSame(2, (int)$summary['created']);
        $this->assertCount(2, $records);

        $slotrows = question_map_service::get_quiz_slot_records((int)$quiz->id);
        foreach ($records as $record) {
            $slotnumber = (int)$record->slotnumber;
            $this->assertArrayHasKey($slotnumber, $slotrows);
            $this->assertSame('slot_' . $slotnumber, (string)$record->nodekey);
            $this->assertSame('question', (string)$record->nodetype);
            $this->assertSame($slotnumber, (int)$record->sortorder);
            $this->assertSame((int)$slotrows[$slotnumber]->questionid, (int)$record->questionid);
            $config = json_decode((string)$record->configjson, true);
            $this->assertIsArray($config);
            $this->assertSame('challenge', (string)($config['scene']['type'] ?? ''));
        }
    }

    /**
     * ensure_for_cmid() preserves customised slot config and removes stale rows.
     *
     * @group local_stackmathgame_db
     */
    public function test_ensure_for_cmid_preserves_config_and_deletes_stale_rows(): void {
        global $DB;

        $this->resetAfterTest();
        [$quiz, $cmid] = $this->create_quiz_with_questions(1);
        $slotrows = question_map_service::get_quiz_slot_records((int)$quiz->id);
        $slotrow = reset($slotrows);
        $now = time();

        $record = (object)[
            'cmid' => $cmid,
            'questionid' => (int)$slotrow->questionid,
            'slotnumber' => (int)$slotrow->slotnumber,
            'designid' => null,
            'nodekey' => 'custom_node',
            'nodetype' => 'reward',
            'sortorder' => 77,
            'configjson' => json_encode(['scene' => ['type' => 'reward']], JSON_UNESCAPED_UNICODE),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        if (question_map_service::questionmap_has_field('quizid')) {
            $record->quizid = (int)$quiz->id;
        }
        $DB->insert_record('local_stackmathgame_questionmap', $record);

        $stale = clone $record;
        $stale->slotnumber = 99;
        $stale->nodekey = 'stale_node';
        $DB->insert_record('local_stackmathgame_questionmap', $stale);

        $summary = question_map_service::ensure_for_cmid($cmid);
        $records = $DB->get_records('local_stackmathgame_questionmap', ['cmid' => $cmid]);

        $this->assertSame(1, (int)$summary['deleted']);
        $this->assertCount(1, $records);
        $saved = reset($records);
        $this->assertSame('custom_node', (string)$saved->nodekey);
        $this->assertSame('reward', (string)$saved->nodetype);
        $this->assertSame(77, (int)$saved->sortorder);
        $this->assertStringContainsString('reward', (string)$saved->configjson);
    }

    /**
     * get_question_map() self-heals by rebuilding rows when none exist yet.
     *
     * @group local_stackmathgame_db
     */
    public function test_api_get_question_map_self_heals_missing_rows(): void {
        global $DB;

        $this->resetAfterTest();
        [$quiz, $cmid] = $this->create_quiz_with_questions(2);

        $this->assertSame(0, $DB->count_records('local_stackmathgame_questionmap', ['cmid' => $cmid]));

        $result = api::get_question_map($cmid, (int)$quiz->id);

        $this->assertCount(2, $result);
        $this->assertSame(2, $DB->count_records('local_stackmathgame_questionmap', ['cmid' => $cmid]));
        $this->assertSame('slot_1', (string)$result[0]['nodekey']);
        $this->assertSame(1, (int)$result[0]['slotnumber']);
    }

    /**
     * rebuild_all() rebuilds each configured activity exactly once.
     *
     * @group local_stackmathgame_db
     */
    public function test_rebuild_all_processes_each_activity(): void {
        $this->resetAfterTest();
        [, $cmidone] = $this->create_quiz_with_questions(1);
        [, $cmidtwo] = $this->create_quiz_with_questions(2);

        $summary = question_map_service::rebuild_all([$cmidtwo, $cmidone, $cmidone]);

        $this->assertSame(2, (int)$summary['activities']);
        $this->assertSame(3, (int)$summary['slots']);
        $this->assertCount(2, $summary['results']);
        $this->assertSame($cmidone, (int)$summary['results'][0]['cmid']);
        $this->assertSame($cmidtwo, (int)$summary['results'][1]['cmid']);
    }

    /**
     * Create a quiz activity with a given number of questions.
     *
     * @param int $questioncount Number of questions to add.
     * @return array{0:\stdClass,1:int} Quiz record and cmid.
     */
    private function create_quiz_with_questions(int $questioncount): array {
        global $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', (int)$quiz->id, (int)$course->id, false, MUST_EXIST);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questioncategory = $questiongenerator->create_question_category([
            'contextid' => \context_course::instance((int)$course->id)->id,
        ]);
        $category = (int)$questioncategory->id . ',' . (int)$questioncategory->contextid;

        for ($i = 1; $i <= $questioncount; $i++) {
            $question = $questiongenerator->create_question('truefalse', null, [
                'category' => $category,
                'name' => 'Question ' . $i,
                'questiontext' => 'Question text ' . $i,
            ]);
            quiz_add_quiz_question((int)$question->id, $quiz);
        }

        return [$quiz, (int)$cm->id];
    }
}
