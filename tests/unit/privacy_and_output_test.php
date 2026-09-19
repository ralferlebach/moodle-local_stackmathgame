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

use local_stackmathgame\tests\game_quiz_testcase;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\service\profile_service;
use local_stackmathgame\local\service\question_map_service;
use local_stackmathgame\output\flow_list;
use local_stackmathgame\output\prerequisite_panel;
use local_stackmathgame\privacy\provider;

/**
 * The privacy provider and the two renderables.
 *
 * Grouped because both answer the same kind of question - "what does this component say about a
 * user, and what does it put on screen" - and both are easy to leave untested until a data
 * request or a broken page makes it urgent.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\privacy\provider
 * @covers     \local_stackmathgame\output\flow_list
 * @covers     \local_stackmathgame\output\prerequisite_panel
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_and_output_test extends game_quiz_testcase {
    /** @var \stdClass The student who has played. */
    private \stdClass $student;

    /** @var int The course-module ID. */
    private int $cmid;
    /** @var int The quiz instance ID. */
    private int $quizid;

    /**
     * Build a game-enabled quiz and a profile with progress in it.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->create_game_quiz(2, false);
        $this->cmid = $this->fixturecmid;
        $this->quizid = $this->fixturequizid;

        $this->student = $this->create_enrolled_student();
        $this->setAdminUser();
        $profile = profile_service::get_or_create_for_quiz((int)$this->student->id, $this->quizid);
        profile_service::apply_progress((int)$profile->id, [
            'score' => 10,
            'xp' => 5,
            'progress' => ['slots' => ['1' => ['state' => 'complete', 'solved' => 1]]],
        ]);
    }

    /**
     * The plugin declares what it stores.
     *
     * An empty metadata collection would mean the plugin claims to hold nothing, which is not
     * true - it holds a per-user profile.
     */
    public function test_metadata_is_declared(): void {
        $collection = provider::get_metadata(new collection('local_stackmathgame'));

        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * A user who has played is found by context and by user list.
     */
    public function test_a_player_is_found(): void {
        $contextlist = provider::get_contexts_for_userid((int)$this->student->id);
        $this->assertGreaterThan(0, count($contextlist->get_contextids()));

        // The provider stores profiles site-wide and reports them in the system context, not
        // per user - so that is the context to ask about.
        $userlist = new userlist(\context_system::instance(), 'local_stackmathgame');
        provider::get_users_in_context($userlist);
        $this->assertContains((int)$this->student->id, $userlist->get_userids());
    }

    /**
     * A user who has never played is not reported as having data.
     */
    public function test_a_stranger_has_no_data(): void {
        $stranger = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid((int)$stranger->id);

        $this->assertCount(0, $contextlist->get_contextids());
    }

    /**
     * Deleting for a user removes their profile and nobody else's.
     */
    public function test_deleting_for_a_user_removes_only_their_data(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_user();
        profile_service::get_or_create_for_quiz((int)$other->id, $this->quizid);
        $before = $DB->count_records('local_stackmathgame_profile');

        $context = \context_system::instance();
        provider::delete_data_for_user(new approved_contextlist(
            \core_user::get_user((int)$this->student->id),
            'local_stackmathgame',
            [$context->id]
        ));

        $this->assertLessThan($before, $DB->count_records('local_stackmathgame_profile'));
        $this->assertTrue(
            $DB->record_exists('local_stackmathgame_profile', ['userid' => $other->id]),
            'Another user lost their profile.'
        );
    }

    /**
     * The flow list exports one row per slot with everything the template needs.
     */
    public function test_flow_list_exports_one_row_per_slot(): void {
        global $PAGE;

        $data = (new flow_list($this->cmid))->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasrows']);
        $this->assertCount(2, $data['rows']);
        foreach ($data['rows'] as $row) {
            $this->assertArrayHasKey('slotnumber', $row);
            $this->assertNotEmpty($row['editurl']);
            $this->assertNotEmpty($row['scenetype']);
        }
    }

    /**
     * The prerequisite panel exports a status and a message for every check.
     *
     * A row with an empty cell is worse than no panel: it looks like the check ran and found
     * nothing to say.
     */
    public function test_prerequisite_panel_exports_complete_rows(): void {
        global $PAGE;

        $data = prerequisite_panel::for_cmid($this->cmid)
            ->export_for_template($PAGE->get_renderer('core'));

        $this->assertNotEmpty($data['rows']);
        $this->assertNotEmpty($data['summary']);
        foreach ($data['rows'] as $row) {
            $this->assertNotEmpty($row['label']);
            $this->assertNotEmpty($row['message']);
            $this->assertNotEmpty($row['statuslabel']);
            // Exactly one status flag is set, so the template cannot render two badges.
            $flags = (int)$row['isok'] + (int)$row['iswarning'] + (int)$row['iserror'];
            $this->assertSame(1, $flags, 'Row ' . $row['key'] . ' has ' . $flags . ' status flags.');
        }
    }
}
