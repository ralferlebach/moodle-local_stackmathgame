<?php
namespace local_stackmathgame\game\mechanics;

use local_stackmathgame\game\game_mechanic;
use local_stackmathgame\game\state_machine;

/**
 * Fairy mechanic: awards fairy collectibles on question solve.
 *
 * @package    local_stackmathgame
 */
class fairy_mechanic implements game_mechanic {

    private int $fairies_on_win;

    public function __construct(array $cfg = []) {
        $this->fairies_on_win = (int) ($cfg['fairies_on_win'] ?? 1);
    }

    public function on_question_solved(int $userid, int $labelid, array $context): array {
        $new_fairies = state_machine::apply_score(
            $userid, $labelid, 'fairies', $this->fairies_on_win
        );
        return [
            'score_delta' => ['fairies' => $this->fairies_on_win],
            'new_scores'  => ['fairies' => $new_fairies],
            'events'      => [['type' => 'fairy_freed', 'count' => $this->fairies_on_win]],
        ];
    }

    public function on_question_failed(int $userid, int $labelid, array $context): array {
        return ['score_delta' => [], 'events' => []];
    }

    public function get_client_config(): array {
        return ['fairies_on_win' => $this->fairies_on_win];
    }
}
