<?php
namespace local_stackmathgame\event;

/**
 * Event fired when a player solves a question.
 *
 * This event can be consumed by block_xp (for XP awarding),
 * block_stash (for item drops), and any other observers.
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_solved extends \core\event\base {

    protected function init(): void {
        $this->data['crud']        = 'u'; // update (progress changes)
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_stackmathgame_gamestate';
    }

    public static function get_name(): string {
        return get_string('event_question_solved', 'local_stackmathgame');
    }

    public function get_description(): string {
        $qid = $this->other['questionid'] ?? 'unknown';
        return "User {$this->userid} solved question '{$qid}'.";
    }

    public static function get_explanation(): string {
        return 'Triggered when a student correctly solves a gamified STACK question.';
    }

    /**
     * Factory: create from solve context.
     *
     * @param int    $userid
     * @param int    $labelid
     * @param string $questionid
     * @param int    $cmid       Course module ID (for context)
     * @param int    $courseid
     */
    public static function create_from_solve(
        int    $userid,
        int    $labelid,
        string $questionid,
        int    $cmid,
        int    $courseid
    ): self {
        return static::create([
            'userid'    => $userid,
            'context'   => \context_module::instance($cmid),
            'objectid'  => $labelid,
            'other'     => [
                'questionid' => $questionid,
                'labelid'    => $labelid,
                'courseid'   => $courseid,
            ],
        ]);
    }
}

/**
 * Event fired when a player fails a question attempt.
 *
 * @package    local_stackmathgame
 */
class question_failed extends \core\event\base {

    protected function init(): void {
        $this->data['crud']     = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_name(): string {
        return get_string('event_question_failed', 'local_stackmathgame');
    }

    public function get_description(): string {
        $qid = $this->other['questionid'] ?? 'unknown';
        return "User {$this->userid} failed question '{$qid}'.";
    }

    public static function create_from_fail(
        int    $userid,
        int    $labelid,
        string $questionid,
        int    $cmid
    ): self {
        return static::create([
            'userid'   => $userid,
            'context'  => \context_module::instance($cmid),
            'objectid' => $labelid,
            'other'    => ['questionid' => $questionid, 'labelid' => $labelid],
        ]);
    }
}
