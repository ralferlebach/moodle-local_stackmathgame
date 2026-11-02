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

/**
 * Cleaning session keys out of answer payloads logged before 2026090251 (re-audit N01).
 *
 * The historic payloads are written here exactly in the shape the old submit_answer produced:
 * array_values() of the request, so the list holds bare values and no field names - the session
 * key among them.
 *
 * @package    local_stackmathgame
 * @covers     ::local_stackmathgame_redact_eventlog_sesskeys
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eventlog_redaction_test extends advanced_testcase {
    /** An artificial session key: ten characters from [A-Za-z0-9], as sesskey() makes them. */
    const SENTINEL = 'Ab3dE6gH9k';

    /**
     * Load the upgrade helpers.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/stackmathgame/db/upgradelib.php');
    }

    /**
     * Write one event log row.
     *
     * @param string $eventtype The event type.
     * @param array $payload The payload.
     * @return int The row ID.
     */
    private function log(string $eventtype, array $payload): int {
        global $DB;

        return (int)$DB->insert_record('local_stackmathgame_eventlog', (object)[
            'userid' => 2,
            'labelid' => 1,
            'quizid' => 1,
            'profileid' => 1,
            'eventtype' => $eventtype,
            'source' => 'external.submit_answer',
            'payloadjson' => json_encode($payload),
            'timecreated' => time(),
        ]);
    }

    /**
     * Read a row's payload back.
     *
     * @param int $id The row ID.
     * @return array The payload.
     */
    private function payload(int $id): array {
        global $DB;

        return json_decode((string)$DB->get_field('local_stackmathgame_eventlog', 'payloadjson', ['id' => $id]), true);
    }

    /**
     * The old list of bare values loses the value shaped like a session key, and only that.
     */
    public function test_a_historic_value_list_loses_its_session_key(): void {
        // What 2026090250 stored: answer, its validation, sequence check, attempt, page, slots,
        // session key, then the two submit flags the server added.
        $id = $this->log('answer_submitted', [
            'attemptid' => 12,
            'slot' => 1,
            'answers' => ['x^3', 'x^3', '2', '12', '0', '1', self::SENTINEL, '1', '1'],
            'processed' => true,
        ]);

        $this->assertSame(1, local_stackmathgame_redact_eventlog_sesskeys());

        $payload = $this->payload($id);
        $this->assertNotContains(self::SENTINEL, $payload['answers']);
        $this->assertSame(['x^3', 'x^3', '2', '12', '0', '1', '1', '1'], $payload['answers']);
        $this->assertSame(1, $payload['sesskeyredacted']);
        // Everything outside the answers is untouched.
        $this->assertSame(12, $payload['attemptid']);
        $this->assertTrue($payload['processed']);
        $this->assertStringNotContainsString(self::SENTINEL, json_encode($payload));
    }

    /**
     * Name/value pairs and maps lose the entry named sesskey.
     */
    public function test_named_shapes_lose_the_sesskey_entry(): void {
        $pairs = $this->log('answer_submitted', ['answers' => [
            ['name' => 'q1:1_ans1', 'value' => 'x'],
            ['name' => 'sesskey', 'value' => self::SENTINEL],
        ]]);
        $map = $this->log('answer_submitted', ['answers' => ['ans1' => 'x', 'sesskey' => self::SENTINEL]]);

        $this->assertSame(2, local_stackmathgame_redact_eventlog_sesskeys());

        $this->assertSame([['name' => 'q1:1_ans1', 'value' => 'x']], $this->payload($pairs)['answers']);
        $this->assertSame(['ans1' => 'x'], $this->payload($map)['answers']);
    }

    /**
     * Payloads without a session key, and other event types, are left exactly as they were.
     */
    public function test_nothing_else_is_rewritten(): void {
        global $DB;

        $clean = $this->log('answer_submitted', ['answers' => ['ans1' => 'x^2', ':sequencecheck' => '3']]);
        $other = $this->log('narrative_requested', ['answers' => [self::SENTINEL], 'scene' => 'intro']);
        $before = [
            $clean => $DB->get_field('local_stackmathgame_eventlog', 'payloadjson', ['id' => $clean]),
            $other => $DB->get_field('local_stackmathgame_eventlog', 'payloadjson', ['id' => $other]),
        ];

        $this->assertSame(0, local_stackmathgame_redact_eventlog_sesskeys());

        foreach ($before as $id => $json) {
            $this->assertSame($json, $DB->get_field('local_stackmathgame_eventlog', 'payloadjson', ['id' => $id]));
        }
    }

    /**
     * Running the clean-up twice changes nothing the second time.
     */
    public function test_the_clean_up_is_idempotent(): void {
        $id = $this->log('answer_submitted', ['answers' => ['x', self::SENTINEL]]);

        $this->assertSame(1, local_stackmathgame_redact_eventlog_sesskeys());
        $after = $this->payload($id);
        $this->assertSame(0, local_stackmathgame_redact_eventlog_sesskeys());
        $this->assertSame($after, $this->payload($id));
    }

    /**
     * The rule removes a genuine answer of a session key's shape too; that is the accepted cost.
     *
     * Documented at the helper: the authoritative copy of every answer is the question attempt
     * step, and keeping a credential is the worse error.
     */
    public function test_an_answer_shaped_like_a_session_key_is_removed_as_well(): void {
        $id = $this->log('answer_submitted', ['answers' => ['abcdefghij', 'x^2+1', '1234567890a']]);

        local_stackmathgame_redact_eventlog_sesskeys();

        // Ten characters are removed, eleven are not: the rule is the exact shape, not "long".
        $this->assertSame(['x^2+1', '1234567890a'], $this->payload($id)['answers']);
    }
}
