<?php
namespace local_stackmathgame\game\mechanics;

use local_stackmathgame\game\game_mechanic;
use local_stackmathgame\game\state_machine;

/**
 * Score mechanic: handles mana and (in cooperation with fairy_mechanic) fairy scores.
 *
 * @package    local_stackmathgame
 */
class score_mechanic implements game_mechanic {

    private int $mana_on_fail;
    private int $mana_on_win;
    private int $mana_start;

    public function __construct(array $cfg = []) {
        $this->mana_start   = (int) ($cfg['mana_start']   ?? 20);
        $this->mana_on_fail = (int) ($cfg['mana_on_fail'] ?? -3);
        $this->mana_on_win  = (int) ($cfg['mana_on_win']  ?? 0);
    }

    public function on_question_solved(int $userid, int $labelid, array $context): array {
        $new_mana = state_machine::apply_score($userid, $labelid, 'mana', $this->mana_on_win);
        return [
            'score_delta' => ['mana' => $this->mana_on_win],
            'new_scores'  => ['mana' => $new_mana],
            'events'      => [],
        ];
    }

    public function on_question_failed(int $userid, int $labelid, array $context): array {
        $new_mana = state_machine::apply_score($userid, $labelid, 'mana', $this->mana_on_fail);
        return [
            'score_delta' => ['mana' => $this->mana_on_fail],
            'new_scores'  => ['mana' => $new_mana],
            'events'      => [['type' => 'mana_loss', 'delta' => $this->mana_on_fail]],
        ];
    }

    public function get_client_config(): array {
        return [
            'mana_start'   => $this->mana_start,
            'mana_on_fail' => $this->mana_on_fail,
            'mana_on_win'  => $this->mana_on_win,
        ];
    }
}
