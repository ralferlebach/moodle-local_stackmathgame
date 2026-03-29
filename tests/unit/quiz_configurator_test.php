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
use local_stackmathgame\game\quiz_configurator;

/**
 * Unit tests for local_stackmathgame\game\quiz_configurator.
 *
 * DB-dependent tests are tagged with @group local_stackmathgame_db.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\game\quiz_configurator
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_configurator_test extends advanced_testcase {
    /**
     * ensure_default_label() creates the default label when it does not exist.
     *
     * @group local_stackmathgame_db
     */
    public function test_ensure_default_label_creates_record(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('local_stackmathgame_label', ['name' => 'default']);

        $labelid = quiz_configurator::ensure_default_label();

        $this->assertGreaterThan(0, $labelid, 'Label ID must be positive');
        $record = $DB->get_record('local_stackmathgame_label', ['id' => $labelid]);
        $this->assertNotFalse($record, 'Label record must exist in DB');
        $this->assertSame('default', $record->name, 'Label name must be "default"');
        $this->assertSame(1, (int)$record->status, 'Label must be active');
    }

    /**
     * ensure_default_label() is idempotent – returns same ID on second call.
     *
     * @group local_stackmathgame_db
     */
    public function test_ensure_default_label_idempotent(): void {
        $this->resetAfterTest();
        $id1 = quiz_configurator::ensure_default_label();
        $id2 = quiz_configurator::ensure_default_label();
        $this->assertSame($id1, $id2, 'Must return same ID on second call');
    }

    /**
     * get_plugin_config() returns null for a cmid with no config record.
     *
     * @group local_stackmathgame_db
     */
    public function test_get_plugin_config_null_for_missing(): void {
        $this->resetAfterTest();
        $result = quiz_configurator::get_plugin_config(PHP_INT_MAX);
        $this->assertNull($result, 'Should return null for unknown cmid');
    }

    /**
     * save_for_quiz() does not overwrite labelid with 0 (FK guard).
     *
     * @group local_stackmathgame_db
     */
    public function test_save_for_quiz_guards_labelid_zero(): void {
        $this->resetAfterTest();
        $courseid = $this->getDataGenerator()->create_course()->id;
        $quiz     = $this->getDataGenerator()->create_module('quiz', ['course' => $courseid]);
        $quizid   = (int)$quiz->id;
        $cm       = get_coursemodule_from_instance('quiz', $quizid, $courseid, false, MUST_EXIST);
        $cmid     = (int)$cm->id;

        $config      = quiz_configurator::ensure_default($cmid);
        $origlabelid = (int)$config->labelid;
        $this->assertGreaterThan(0, $origlabelid);

        quiz_configurator::save_for_quiz($cmid, ['labelid' => 0, 'enabled' => 1]);

        $updated = quiz_configurator::get_plugin_config($cmid);
        $this->assertSame($origlabelid, (int)$updated->labelid, 'labelid=0 must not overwrite');
        $this->assertSame(1, (int)$updated->enabled, 'enabled flag must be saved');
    }
}
