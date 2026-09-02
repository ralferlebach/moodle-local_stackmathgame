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
use local_stackmathgame\local\service\profile_service;

/**
 * Reward logic, tested without a CAS (issue #5).
 *
 * The end-to-end tests in stack_submit_test need Maxima and skip without it. The arithmetic of
 * who gets paid what does not, and it is the part most likely to be got wrong - so it is tested
 * here where nothing can skip it.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\profile_service::calculate_submit_deltas
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reward_logic_test extends advanced_testcase {
    /** A direction card paying 42 score and 17 XP. */
    private const CONFIG = ['rewards' => ['score' => 42, 'xp' => 17]];

    /**
     * A first correct answer pays the configured reward.
     *
     * The figures used to be hardcoded at 10 and 5, so the fields the flow editor exposes had no
     * effect whatsoever.
     */
    public function test_first_correct_answer_pays_the_configured_reward(): void {
        $deltas = profile_service::calculate_submit_deltas('todo', 'complete', self::CONFIG, 1.0, null);

        $this->assertSame(42, $deltas['score']);
        $this->assertSame(17, $deltas['xp']);
        $this->assertTrue($deltas['solved']);
    }

    /**
     * "complete" with a full mark counts as solved.
     *
     * Under adaptivemultipart - which the STACK Math Game behaviour extends - a correct answer
     * during an attempt lands in "complete"; "gradedright" only appears once the attempt is
     * finished. Keying the reward off state names paid nothing for a correct answer.
     */
    public function test_complete_with_full_mark_is_solved(): void {
        $this->assertTrue(
            profile_service::calculate_submit_deltas('todo', 'complete', self::CONFIG, 1.0, null)['solved']
        );
    }

    /**
     * "complete" with no mark is not solved.
     *
     * The mirror image of the case above, and the reason the state name alone cannot decide:
     * "complete" means answered, not correct.
     */
    public function test_complete_without_mark_is_not_solved(): void {
        $deltas = profile_service::calculate_submit_deltas('todo', 'complete', self::CONFIG, 0.0, null);

        $this->assertSame(0, $deltas['score']);
        $this->assertFalse($deltas['solved']);
    }

    /**
     * Partial credit pays a share of the same figure.
     */
    public function test_partial_credit_pays_a_share(): void {
        $deltas = profile_service::calculate_submit_deltas('todo', 'complete', self::CONFIG, 0.5, null);

        $this->assertSame(21, $deltas['score']);
        $this->assertSame(8, $deltas['xp'], 'Half of 17 rounds down, so a partial answer never pays more than half.');
        $this->assertFalse($deltas['solved']);
    }

    /**
     * Solving an already solved scene pays nothing.
     *
     * The sequential half of the at-most-once guarantee.
     */
    public function test_resolving_pays_nothing(): void {
        $deltas = profile_service::calculate_submit_deltas('complete', 'complete', self::CONFIG, 1.0, 1.0);

        $this->assertSame(0, $deltas['score']);
        $this->assertSame(0, $deltas['xp']);
        $this->assertTrue($deltas['solved'], 'An already solved scene must still allow moving on.');
    }

    /**
     * Improving from partial to full pays only the remainder is NOT the rule: it pays nothing.
     *
     * Stated explicitly because it is a deliberate choice rather than an oversight. Paying the
     * difference would mean a player who deliberately answers partially first collects more in
     * total than one who answers correctly straight away.
     */
    public function test_partial_then_full_does_not_pay_again(): void {
        $deltas = profile_service::calculate_submit_deltas('complete', 'complete', self::CONFIG, 1.0, 0.5);

        $this->assertSame(42, $deltas['score'], 'Reaching a full mark for the first time pays the reward.');
        $this->assertTrue($deltas['solved']);
    }

    /**
     * Repeating a partial answer pays nothing the second time.
     */
    public function test_repeated_partial_pays_once(): void {
        $deltas = profile_service::calculate_submit_deltas('complete', 'complete', self::CONFIG, 0.5, 0.5);

        $this->assertSame(0, $deltas['score']);
        $this->assertSame(0, $deltas['xp']);
    }

    /**
     * A slot with no configured reward falls back to the plugin default rather than paying zero.
     */
    public function test_unconfigured_slot_uses_the_default(): void {
        $deltas = profile_service::calculate_submit_deltas('todo', 'complete', null, 1.0, null);

        $this->assertSame(profile_service::DEFAULT_SCORE, $deltas['score']);
        $this->assertSame(profile_service::DEFAULT_XP, $deltas['xp']);
    }

    /**
     * A zero reward is honoured rather than silently replaced by the default.
     *
     * A teacher who sets a scene to award nothing means it.
     */
    public function test_zero_reward_is_honoured(): void {
        $deltas = profile_service::calculate_submit_deltas(
            'todo',
            'complete',
            ['rewards' => ['score' => 0, 'xp' => 0]],
            1.0,
            null
        );

        $this->assertSame(0, $deltas['score']);
        $this->assertSame(0, $deltas['xp']);
        $this->assertTrue($deltas['solved'], 'A scene worth nothing is still solved.');
    }

    /**
     * Without a mark the state names are used, so older callers keep working.
     */
    public function test_falls_back_to_state_names_without_a_mark(): void {
        $deltas = profile_service::calculate_submit_deltas('todo', 'gradedright', self::CONFIG, null, null);

        $this->assertSame(42, $deltas['score']);
        $this->assertTrue($deltas['solved']);
    }

    /**
     * A negative configured reward cannot produce a negative payout.
     */
    public function test_negative_configuration_cannot_drain_a_profile(): void {
        $deltas = profile_service::calculate_submit_deltas(
            'todo',
            'complete',
            ['rewards' => ['score' => -100, 'xp' => -50]],
            1.0,
            null
        );

        $this->assertGreaterThanOrEqual(0, $deltas['score']);
        $this->assertGreaterThanOrEqual(0, $deltas['xp']);
    }
}
