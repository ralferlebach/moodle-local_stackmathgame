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
use local_stackmathgame\local\integration\availability;
use local_stackmathgame\local\integration\bridge_dispatcher;
use local_stackmathgame\local\integration\xp_bridge;
use local_stackmathgame\local\integration\stash_bridge;

/**
 * Tests for bridge_dispatcher and integration bridges.
 *
 * Covers two scenarios:
 *   - block_xp / block_stash NOT installed (silent-fail, no exception).
 *   - block_xp installed: verifies that Moodle events are fired (block_xp's
 *     event-based collection picks these up automatically without any direct
 *     PHP API call from our side).
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\integration\bridge_dispatcher
 * @covers     \local_stackmathgame\local\integration\xp_bridge
 * @covers     \local_stackmathgame\local\integration\stash_bridge
 * @covers     \local_stackmathgame\local\integration\availability
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bridge_dispatcher_test extends advanced_testcase {
    /**
     * Build a minimal profile stdClass for testing.
     *
     * @param int $userid
     * @return \stdClass
     */
    private function make_profile(int $userid = 2): \stdClass {
        return (object)[
            'id' => 1,
            'userid' => $userid,
            'labelid' => 1,
            'score' => 0,
            'xp' => 0,
            'levelno' => 1,
            'progressjson' => '{}',
            'flagsjson' => '{}',
            'statsjson' => '{}',
            'lastquizid' => null,
            'lastdesignid' => null,
            'lastaccess' => time(),
        ];
    }

    /**
     * Availability check returns false when block_xp is not installed.
     * This is the common case in CI where only stackmathgame is installed.
     */
    public function test_has_block_xp_false_when_not_installed(): void {
        // Block_xp is not installed in a standard CI run.
        if (availability::has_block_xp()) {
            $this->markTestSkipped('block_xp is installed; this test requires it absent.');
        }
        $this->assertFalse(availability::has_block_xp());
    }

    /**
     * Availability check returns false when block_stash is not installed.
     */
    public function test_has_block_stash_false_when_not_installed(): void {
        if (availability::has_block_stash()) {
            $this->markTestSkipped('block_stash is installed; this test requires it absent.');
        }
        $this->assertFalse(availability::has_block_stash());
    }

    /**
     * xp_bridge::dispatch() returns available=false and dispatched=false
     * without throwing when block_xp is not installed.
     */
    public function test_xp_bridge_silent_fail_without_block_xp(): void {
        if (availability::has_block_xp()) {
            $this->markTestSkipped('block_xp is installed; this test requires it absent.');
        }
        $profile = $this->make_profile();
        $result = xp_bridge::dispatch($profile, 10, 1, 3, ['state' => 'gradedright'], [
            'score' => 10,
            'xp' => 5,
            'solved' => true,
        ]);
        $this->assertFalse($result['available'], 'available must be false when block_xp absent');
        $this->assertFalse($result['dispatched'], 'dispatched must be false when block_xp absent');
    }

    /**
     * stash_bridge::dispatch() returns dispatched=false without throwing
     * when block_stash is not installed and the slot was solved.
     */
    public function test_stash_bridge_falls_back_to_local_inventory_without_block_stash(): void {
        if (availability::has_block_stash()) {
            $this->markTestSkipped('block_stash is installed; this test requires it absent.');
        }
        $this->resetAfterTest();
        $profile = $this->make_profile();
        $result = stash_bridge::dispatch($profile, 10, 1, 3, ['state' => 'gradedright'], [
            'score' => 10,
            'xp' => 5,
            'solved' => true,
        ]);
        // Without block_stash, the bridge falls back to the local inventory table.
        // Dispatched is therefore true (local write succeeded), stash=false (no real stash used).
        $this->assertTrue(
            $result['dispatched'],
            'Must dispatch to local inventory when block_stash is absent'
        );
        $this->assertFalse(
            $result['stash'],
            'Stash flag must be false when falling back to local inventory'
        );
    }

    /**
     * stash_bridge::dispatch() skips (dispatched=false) when slot was NOT solved,
     * regardless of whether block_stash is installed.
     */
    public function test_stash_bridge_skips_when_not_solved(): void {
        $profile = $this->make_profile();
        $result = stash_bridge::dispatch($profile, 10, 1, 3, ['state' => 'gradedwrong'], [
            'score' => 0,
            'xp' => 0,
            'solved' => false,
        ]);
        $this->assertFalse($result['dispatched'], 'stash must not dispatch when solved=false');
    }

    /**
     * bridge_dispatcher::on_answer_result() returns a result array with
     * 'xp' and 'stash' keys, never throws, even when both plugins are absent.
     */
    public function test_dispatcher_returns_structured_result_without_plugins(): void {
        if (availability::has_block_xp() || availability::has_block_stash()) {
            $this->markTestSkipped('One or both integration plugins are installed.');
        }
        $this->resetAfterTest();
        $profile = $this->make_profile();
        $result = bridge_dispatcher::on_answer_result(
            $profile,
            10,
            1,
            3,
            ['state' => 'gradedright', 'questionid' => 5],
            ['score' => 10, 'xp' => 5, 'solved' => true]
        );
        $this->assertArrayHasKey('xp', $result);
        $this->assertArrayHasKey('stash', $result);
        $this->assertFalse($result['xp']['available']);
        $this->assertFalse($result['xp']['dispatched']);
    }

    /**
     * bridge_dispatcher::on_answer_result() does not dispatch when solved=false.
     * Neither bridge should fire for an incorrect answer.
     */
    public function test_dispatcher_no_dispatch_on_wrong_answer(): void {
        $profile = $this->make_profile();
        $result = bridge_dispatcher::on_answer_result(
            $profile,
            10,
            1,
            3,
            ['state' => 'gradedwrong', 'questionid' => 5],
            ['score' => 0, 'xp' => 0, 'solved' => false]
        );
        $this->assertArrayHasKey('stash', $result);
        $this->assertFalse($result['stash']['dispatched']);
    }

    /**
     * When block_xp IS installed, xp_bridge fires Moodle events.
     * block_xp collects XP via its own event observer – we verify the event
     * fires, not that XP was stored (that would require block_xp's full setup).
     *
     * @group local_stackmathgame_db
     */
    public function test_xp_bridge_fires_events_when_block_xp_installed(): void {
        if (!availability::has_block_xp()) {
            $this->markTestSkipped('block_xp is not installed.');
        }
        $this->resetAfterTest();

        // Listen for the progress_updated event.
        $sink = $this->redirectEvents();

        $profile = $this->make_profile((int)$this->getDataGenerator()->create_user()->id);

        $result = xp_bridge::dispatch(
            $profile,
            0, // Quizid=0 falls back to system context, no CM lookup needed.
            1,
            3,
            ['state' => 'gradedright', 'questionid' => 5],
            ['score' => 10, 'xp' => 5, 'solved' => true]
        );

        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($result['available'], 'block_xp must be detected as available');
        $this->assertTrue($result['dispatched'], 'events must be dispatched');

        // At least progress_updated should have fired.
        $eventnames = array_map(fn($e) => $e->eventname, $events);
        $this->assertContains(
            '\\local_stackmathgame\\event\\progress_updated',
            $eventnames,
            'progress_updated event must fire when xp_bridge dispatches'
        );

        // Question_solved should also fire (solved=true).
        $this->assertContains(
            '\\local_stackmathgame\\event\\question_solved',
            $eventnames,
            'question_solved event must fire when solved=true'
        );
    }

    /**
     * When block_xp IS installed and solved=false, only progress_updated fires,
     * not question_solved.
     *
     * @group local_stackmathgame_db
     */
    public function test_xp_bridge_no_question_solved_event_when_not_solved(): void {
        if (!availability::has_block_xp()) {
            $this->markTestSkipped('block_xp is not installed.');
        }
        $this->resetAfterTest();

        $sink = $this->redirectEvents();

        $profile = $this->make_profile((int)$this->getDataGenerator()->create_user()->id);

        xp_bridge::dispatch(
            $profile,
            0,
            1,
            3,
            ['state' => 'gradedpartial', 'questionid' => 5],
            ['score' => 5, 'xp' => 2, 'solved' => false]
        );

        $events = $sink->get_events();
        $sink->close();

        $eventnames = array_map(fn($e) => $e->eventname, $events);
        $this->assertContains(
            '\\local_stackmathgame\\event\\progress_updated',
            $eventnames
        );
        $this->assertNotContains(
            '\\local_stackmathgame\\event\\question_solved',
            $eventnames,
            'question_solved must NOT fire when solved=false'
        );
    }


    /**
     * bridge_dispatcher forwards the activity payload to the stash bridge path.
     */
    public function test_dispatcher_source_passes_activity_argument_to_stash_bridge(): void {
        $file = __DIR__ . '/../../classes/local/integration/bridge_dispatcher.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $normalised = preg_replace('/\s+/', ' ', $content);
        $this->assertMatchesRegularExpression(
            '/stash_bridge::dispatch\(\$profile, \$quizid, \$designid, \$slot, \$slotdata, \$deltas, \$activity\)/',
            $normalised
        );
    }

    /**
     * Verify that the submit_answer.php source contains a bridge_dispatcher call.
     * This is a static analysis smoke test that catches accidental regression.
     */
    public function test_submit_answer_contains_bridge_dispatcher_call(): void {
        $file = __DIR__ . '/../../classes/external/submit_answer.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString(
            'bridge_dispatcher::on_answer_result(',
            $content,
            'submit_answer.php must call bridge_dispatcher::on_answer_result()'
        );
    }

    /**
     * Verify the bridge_dispatcher call in submit_answer.php is wrapped in try/catch
     * so it never breaks the quiz flow.
     */
    public function test_submit_answer_bridge_call_is_protected(): void {
        $file = __DIR__ . '/../../classes/external/submit_answer.php';
        $content = file_get_contents($file);
        // The relevant try/catch must surround the bridge_dispatcher call.
        $bridgepos = strpos($content, 'bridge_dispatcher::on_answer_result(');
        $this->assertNotFalse($bridgepos, 'bridge_dispatcher call not found');

        $beforebridge = substr($content, 0, $bridgepos);
        $trypos = strrpos($beforebridge, 'try {');
        $catchpos = strpos($content, '} catch (\Throwable $bridgeerr)', $bridgepos);

        $this->assertNotFalse($trypos, 'try block not found in submit_answer.php');
        $this->assertNotFalse($catchpos, 'catch block not found');
        $this->assertGreaterThan(
            $trypos,
            $bridgepos,
            'bridge_dispatcher call must be inside try block'
        );
        $this->assertGreaterThan(
            $bridgepos,
            $catchpos,
            'catch block must come after bridge_dispatcher call'
        );
    }
}
