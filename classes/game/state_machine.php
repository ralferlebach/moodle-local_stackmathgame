<?php
// This file is part of Moodle - http://moodle.org/

namespace local_stackmathgame\game;

/**
 * Server-side game state machine.
 *
 * Manages reading and writing player progress (solved questions, solved variants,
 * score values) to the DB tables. All mutations are atomic (upsert pattern).
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state_machine {

    // -------------------------------------------------------------------------
    // GAMESTATE
    // -------------------------------------------------------------------------

    /**
     * Load a player's full game state for a label.
     * Returns a normalised object with 'solved', 'solved_variants', and 'scores'.
     *
     * @param  int    $userid
     * @param  int    $labelid
     * @return \stdClass  { solved: string[], solved_variants: int[], scores: {type: int} }
     */
    public static function load(int $userid, int $labelid): \stdClass {
        global $DB;

        $state = $DB->get_record(
            'local_stackmathgame_gamestate',
            ['userid' => $userid, 'labelid' => $labelid]
        );

        $result = new \stdClass();
        $result->solved          = $state ? (json_decode($state->solved ?? '[]', true) ?: []) : [];
        $result->solved_variants = $state ? (json_decode($state->solved_variants ?? '[]', true) ?: []) : [];
        $result->scores          = self::load_scores($userid, $labelid);
        $result->timemodified    = $state ? (int) $state->timemodified : 0;

        return $result;
    }

    /**
     * Persist a player's game state. Upserts both gamestate and score rows.
     *
     * @param  int    $userid
     * @param  int    $labelid
     * @param  array  $solved           Array of question ID strings
     * @param  array  $solved_variants  Array of variant page integers
     * @param  array  $scores           Assoc array ['fairies' => 3, 'mana' => 17]
     * @return bool
     */
    public static function save(
        int $userid,
        int $labelid,
        array $solved,
        array $solved_variants,
        array $scores
    ): bool {
        global $DB;

        $now = time();

        // --- Upsert gamestate row ---
        $existing = $DB->get_record(
            'local_stackmathgame_gamestate',
            ['userid' => $userid, 'labelid' => $labelid],
            'id'
        );

        $row = (object) [
            'userid'          => $userid,
            'labelid'         => $labelid,
            'solved'          => json_encode(array_values(array_unique($solved))),
            'solved_variants' => json_encode(array_values(array_unique($solved_variants))),
            'timemodified'    => $now,
        ];

        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_stackmathgame_gamestate', $row);
        } else {
            $row->timecreated = $now;
            $DB->insert_record('local_stackmathgame_gamestate', $row);
        }

        // --- Upsert score rows ---
        foreach ($scores as $type => $value) {
            self::upsert_score($userid, $labelid, (string) $type, (int) $value, $now);
        }

        return true;
    }

    /**
     * Mark a single question as solved (merges into existing state).
     *
     * @param  int    $userid
     * @param  int    $labelid
     * @param  string $questionid
     * @param  int    $variantpage  Optional: the specific variant page solved
     * @return void
     */
    public static function mark_solved(
        int $userid,
        int $labelid,
        string $questionid,
        int $variantpage = -1
    ): void {
        $state = self::load($userid, $labelid);

        if (!in_array($questionid, $state->solved, true)) {
            $state->solved[] = $questionid;
        }

        if ($variantpage >= 0 && !in_array($variantpage, $state->solved_variants, true)) {
            $state->solved_variants[] = $variantpage;
        }

        self::save(
            $userid,
            $labelid,
            $state->solved,
            $state->solved_variants,
            (array) $state->scores
        );
    }

    // -------------------------------------------------------------------------
    // SCORES
    // -------------------------------------------------------------------------

    /**
     * Apply a score delta (positive or negative) for a given score type.
     * Returns the new value.
     *
     * @param  int    $userid
     * @param  int    $labelid
     * @param  string $scoretype  e.g. 'mana', 'fairies'
     * @param  int    $delta      Amount to add (negative = subtract)
     * @param  int    $min        Floor value (default 0)
     * @param  int    $max        Ceiling value (default PHP_INT_MAX)
     * @return int    New score value
     */
    public static function apply_score(
        int $userid,
        int $labelid,
        string $scoretype,
        int $delta,
        int $min = 0,
        int $max = PHP_INT_MAX
    ): int {
        global $DB;

        $existing = $DB->get_record(
            'local_stackmathgame_score',
            ['userid' => $userid, 'labelid' => $labelid, 'scoretype' => $scoretype]
        );

        $current  = $existing ? (int) $existing->value : self::default_score($scoretype);
        $newvalue = max($min, min($max, $current + $delta));

        self::upsert_score($userid, $labelid, $scoretype, $newvalue, time());

        return $newvalue;
    }

    /**
     * Load all score types for a user + label as an associative array.
     *
     * @param  int    $userid
     * @param  int    $labelid
     * @return array  ['fairies' => 0, 'mana' => 20, …]
     */
    public static function load_scores(int $userid, int $labelid): array {
        global $DB;

        $rows = $DB->get_records(
            'local_stackmathgame_score',
            ['userid' => $userid, 'labelid' => $labelid]
        );

        // Start from defaults so client always gets a full object.
        $scores = self::default_scores();
        foreach ($rows as $row) {
            $scores[$row->scoretype] = (int) $row->value;
        }

        return $scores;
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Default score values (used when no DB row exists yet).
     */
    public static function default_scores(): array {
        return [
            'fairies' => 0,
            'mana'    => 20,
        ];
    }

    /**
     * Default for a single score type.
     */
    private static function default_score(string $scoretype): int {
        return self::default_scores()[$scoretype] ?? 0;
    }

    /**
     * Upsert a single score row.
     */
    private static function upsert_score(
        int $userid,
        int $labelid,
        string $scoretype,
        int $value,
        int $now
    ): void {
        global $DB;

        $existing = $DB->get_record(
            'local_stackmathgame_score',
            ['userid' => $userid, 'labelid' => $labelid, 'scoretype' => $scoretype],
            'id'
        );

        $row = (object) [
            'userid'       => $userid,
            'labelid'      => $labelid,
            'scoretype'    => $scoretype,
            'value'        => $value,
            'timemodified' => $now,
        ];

        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_stackmathgame_score', $row);
        } else {
            $row->timecreated = $now;
            $DB->insert_record('local_stackmathgame_score', $row);
        }
    }
}
