<?php
// This file is part of Moodle - http://moodle.org/

namespace local_stackmathgame\game;

/**
 * Interface for game mechanic plugins.
 *
 * Each mechanic handles specific game events and may:
 *  - Modify scores
 *  - Grant inventory items
 *  - Trigger client-side animations
 *
 * @package    local_stackmathgame
 */
interface game_mechanic {

    /**
     * Called after a question is correctly solved.
     *
     * @param  int      $userid
     * @param  int      $labelid
     * @param  array    $context  Quiz context: quizid, questionid, variantpage, attempt
     * @return array    Delta to send to client: ['score_delta' => [...], 'events' => [...]]
     */
    public function on_question_solved(int $userid, int $labelid, array $context): array;

    /**
     * Called after a question attempt fails.
     *
     * @param  int      $userid
     * @param  int      $labelid
     * @param  array    $context
     * @return array
     */
    public function on_question_failed(int $userid, int $labelid, array $context): array;

    /**
     * Returns the mechanic configuration that should be sent to the client-side JS.
     * This is merged into the game config JSON.
     *
     * @return array
     */
    public function get_client_config(): array;
}

/**
 * Registry for game mechanics. Mechanics are registered at boot time.
 *
 * Usage:
 *   mechanic_registry::register('score', new score_mechanic($overrides));
 *   $results = mechanic_registry::trigger('on_question_solved', $userid, $labelid, $ctx);
 *
 * @package    local_stackmathgame
 */
class mechanic_registry {

    /** @var game_mechanic[] $mechanics */
    private static array $mechanics = [];

    /** @var bool Whether the built-in mechanics have been registered. */
    private static bool $initialised = false;

    /**
     * Register a mechanic under a given name.
     * Later registrations with the same name replace earlier ones.
     */
    public static function register(string $name, game_mechanic $mechanic): void {
        self::$mechanics[$name] = $mechanic;
    }

    /**
     * Initialise built-in mechanics. Called lazily before first trigger.
     *
     * @param  array $mechanicscfg  Overrides from quiz config JSON
     */
    public static function init(array $mechanicscfg = []): void {
        if (self::$initialised) {
            return;
        }

        // Built-in mechanics.
        self::register('score', new mechanics\score_mechanic($mechanicscfg));
        self::register('fairy', new mechanics\fairy_mechanic($mechanicscfg));

        // Future: items, booster, avatar…
        // self::register('items',   new mechanics\item_drop_mechanic($mechanicscfg));
        // self::register('booster', new mechanics\booster_mechanic($mechanicscfg));
        // self::register('avatar',  new mechanics\avatar_xp_mechanic($mechanicscfg));

        self::$initialised = true;
    }

    /**
     * Trigger an event on all registered mechanics.
     * Aggregates their return arrays (score_delta values are summed per type).
     *
     * @param  string $event    'on_question_solved' | 'on_question_failed'
     * @param  int    $userid
     * @param  int    $labelid
     * @param  array  $context
     * @return array  Merged result: ['score_delta' => ['mana' => -3, 'fairies' => +1], 'events' => [...]]
     */
    public static function trigger(
        string $event,
        int $userid,
        int $labelid,
        array $context
    ): array {
        self::init($context['mechanicscfg'] ?? []);

        $merged = ['score_delta' => [], 'events' => []];

        foreach (self::$mechanics as $name => $mechanic) {
            $result = $mechanic->$event($userid, $labelid, $context);

            // Merge score deltas (additive).
            foreach (($result['score_delta'] ?? []) as $type => $delta) {
                $merged['score_delta'][$type] = ($merged['score_delta'][$type] ?? 0) + $delta;
            }

            // Collect client-side animation events.
            foreach (($result['events'] ?? []) as $ev) {
                $merged['events'][] = $ev;
            }
        }

        return $merged;
    }

    /**
     * Collect the client config for all registered mechanics.
     * Merged into the game config JSON sent to the frontend.
     */
    public static function get_all_client_configs(array $mechanicscfg = []): array {
        self::init($mechanicscfg);
        $config = [];
        foreach (self::$mechanics as $name => $mechanic) {
            $config[$name] = $mechanic->get_client_config();
        }
        return $config;
    }

    /**
     * Reset for testing.
     */
    public static function reset(): void {
        self::$mechanics    = [];
        self::$initialised  = false;
    }
}
