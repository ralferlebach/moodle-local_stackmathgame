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

namespace local_stackmathgame\unit;

use advanced_testcase;
use local_stackmathgame\game\quiz_configurator;

/**
 * Regression tests for the requiresbehaviour field (issue #3).
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\game\quiz_configurator::save_for_quiz
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class requires_behaviour_test extends advanced_testcase {
    /**
     * Create a quiz and return its course-module ID.
     *
     * @return int The cmid.
     */
    private function make_cmid(): int {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', [
            'course' => $course->id,
            'preferredbehaviour' => 'stackmathgame',
        ]);
        return (int)get_coursemodule_from_instance(
            'quiz',
            $quiz->id,
            $course->id,
            false,
            MUST_EXIST
        )->id;
    }

    /**
     * A new configuration enforces the behaviour requirement.
     */
    public function test_default_is_enforced(): void {
        $this->resetAfterTest();
        $config = quiz_configurator::ensure_default($this->make_cmid());
        $this->assertSame(1, (int)$config->requiresbehaviour);
    }

    /**
     * Saving teacher settings must not clear the flag.
     *
     * This is the regression. The teacher form never carries requiresbehaviour, and the old
     * `empty($data['requiresbehaviour']) ? 0 : 1` therefore reset the stored 1 to 0 on every
     * save - turning enforcement off at precisely the moment a teacher was configuring the game,
     * and leaving no trace of having done so.
     */
    public function test_saving_without_the_key_preserves_the_flag(): void {
        $this->resetAfterTest();
        $cmid = $this->make_cmid();
        $config = quiz_configurator::ensure_default($cmid);

        $saved = quiz_configurator::save_for_quiz($cmid, [
            'enabled' => 1,
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
        ]);

        $this->assertSame(1, (int)$saved->requiresbehaviour);
    }

    /**
     * Repeated saves must not erode the flag either.
     *
     * The original bug only became visible after a save, so a single-save assertion would have
     * caught it - but a "preserve" implementation that reads its own output can still drift.
     */
    public function test_repeated_saves_preserve_the_flag(): void {
        $this->resetAfterTest();
        $cmid = $this->make_cmid();
        $config = quiz_configurator::ensure_default($cmid);

        for ($i = 0; $i < 3; $i++) {
            $config = quiz_configurator::save_for_quiz($cmid, [
                'enabled' => $i % 2,
                'labelid' => (int)$config->labelid,
                'designid' => (int)$config->designid,
            ]);
        }

        $this->assertSame(1, (int)$config->requiresbehaviour);
    }

    /**
     * A caller that explicitly states a value is still obeyed.
     *
     * The fix preserves the stored value when the key is absent; it must not become a field that
     * can no longer be written at all, or an administrator could never override enforcement.
     */
    public function test_explicit_value_is_written(): void {
        $this->resetAfterTest();
        $cmid = $this->make_cmid();
        $config = quiz_configurator::ensure_default($cmid);

        $saved = quiz_configurator::save_for_quiz($cmid, [
            'enabled' => 1,
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'requiresbehaviour' => 0,
        ]);
        $this->assertSame(0, (int)$saved->requiresbehaviour);

        $saved = quiz_configurator::save_for_quiz($cmid, [
            'enabled' => 1,
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'requiresbehaviour' => 1,
        ]);
        $this->assertSame(1, (int)$saved->requiresbehaviour);
    }
}
