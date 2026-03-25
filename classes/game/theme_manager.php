<?php
namespace local_stackmathgame\game;

/**
 * Theme manager: loads and caches theme configurations.
 *
 * @package    local_stackmathgame
 */
class theme_manager {

    /** @var array<int, \stdClass> Simple in-request cache. */
    private static array $cache = [];

    /**
     * Load a theme record by ID. Returns null if not found or disabled.
     */
    public static function get_theme(int $themeid): ?\stdClass {
        global $DB;

        if (isset(self::$cache[$themeid])) {
            return self::$cache[$themeid];
        }

        $theme = $DB->get_record(
            'local_stackmathgame_theme',
            ['id' => $themeid, 'enabled' => 1]
        );
        self::$cache[$themeid] = $theme ?: null;
        return self::$cache[$themeid];
    }

    /**
     * Return the decoded configjson for a theme, falling back to the first
     * enabled theme if themeid is 0 or the theme is not found.
     *
     * @return array  Decoded theme config, or empty array on failure.
     */
    public static function get_theme_config(int $themeid): array {
        global $DB;

        $theme = $themeid > 0 ? self::get_theme($themeid) : null;

        if (!$theme) {
            // Fallback: first enabled theme (lowest sortorder).
            $theme = $DB->get_record_select(
                'local_stackmathgame_theme',
                'enabled = 1',
                [],
                '*',
                IGNORE_MULTIPLE
            );
        }

        if (!$theme) {
            return [];
        }

        return json_decode($theme->configjson, true) ?? [];
    }

    /**
     * Get all enabled themes (for the theme picker UI).
     *
     * @return \stdClass[]
     */
    public static function get_all_enabled(): array {
        global $DB;
        return array_values($DB->get_records(
            'local_stackmathgame_theme',
            ['enabled' => 1],
            'sortorder ASC'
        ));
    }

    /**
     * Get the asset base URL for a theme shortname.
     *
     * @param  string $shortname  e.g. 'fantasy'
     * @return string  Full URL ending with /
     */
    public static function asset_base_url(string $shortname): string {
        return (string) new \moodle_url(
            '/local/stackmathgame/pix/themes/' . clean_param($shortname, PARAM_SAFEDIR) . '/'
        );
    }

    /**
     * Insert or update the default 'fantasy' theme on plugin install/upgrade.
     * Called from db/install.php.
     */
    public static function seed_default_theme(): void {
        global $DB;

        if ($DB->record_exists('local_stackmathgame_theme', ['shortname' => 'fantasy'])) {
            return;
        }

        $config = self::default_fantasy_config();
        $now    = time();

        $DB->insert_record('local_stackmathgame_theme', (object) [
            'name'        => 'Fantasy Adventure',
            'shortname'   => 'fantasy',
            'configjson'  => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'enabled'     => 1,
            'sortorder'   => 0,
            'timecreated' => $now,
        ]);
    }

    /**
     * The complete default fantasy theme configuration.
     * Mirrors the original script's hardcoded sprite definitions,
     * now resolved via plugin pix/ paths at runtime.
     */
    public static function default_fantasy_config(): array {
        return [
            'thumbnail'   => 'thumbnail.png',
            'description' => 'A classic fantasy RPG with an elf hero, trolls and golems in an enchanted forest.',
            'preview'     => [
                'palette' => ['#2d6a4f', '#40916c', '#b7e4c7'],
            ],
            'player' => [
                'class'   => 'Elf',
                'sprites' => [
                    'idle'   => ['file' => 'Elf_03__IDLE_spritesheet.png',   'w' => 450, 'h' => 580, 'frames' => 10, 'interval' => 75],
                    'attack' => ['file' => 'Elf_03__ATTACK_spritesheet.png', 'w' => 450, 'h' => 580, 'frames' => 10, 'interval' => 75],
                    'run'    => ['file' => 'Elf_03__RUN_spritesheet.png',    'w' => 465, 'h' => 580, 'frames' => 10, 'interval' => 75],
                ],
            ],
            'enemies' => [
                [
                    'class'  => 'ForestGolem',
                    'weight' => 2,
                    'sprites' => [
                        'idle'  => ['file' => 'Golem_02_1_IDLE_spritesheet.png', 'w' => 700, 'h' => 750, 'frames' => 18, 'interval' => 40],
                        'hurt'  => ['file' => 'Golem_02_1_HURT_spritesheet.png', 'w' => 700, 'h' => 750, 'frames' => 12, 'interval' => 40],
                        'die'   => ['file' => 'Golem_02_1_DIE_spritesheet.png',  'w' => 700, 'h' => 750, 'frames' => 15, 'interval' => 40],
                    ],
                ],
                [
                    'class'  => 'IceGolem',
                    'weight' => 2,
                    'sprites' => [
                        'idle'  => ['file' => 'Golem_01_1_IDLE_spritesheet.png', 'w' => 700, 'h' => 750, 'frames' => 18, 'interval' => 50],
                        'hurt'  => ['file' => 'Golem_01_1_HURT_spritesheet.png', 'w' => 700, 'h' => 750, 'frames' => 12, 'interval' => 50],
                        'die'   => ['file' => 'Golem_01_1_DIE_spritesheet.png',  'w' => 700, 'h' => 750, 'frames' => 15, 'interval' => 50],
                    ],
                ],
                [
                    'class'  => 'Troll',
                    'weight' => 1,
                    'sprites' => [
                        'idle'   => ['file' => 'Troll_01_1_IDLE_spritesheet.png',   'w' => 750, 'h' => 580, 'frames' => 10, 'interval' => 90],
                        'hurt'   => ['file' => 'Troll_01_1_HURT_spritesheet.png',   'w' => 770, 'h' => 580, 'frames' => 10, 'interval' => 90],
                        'die'    => ['file' => 'Troll_01_1_DIE_spritesheet.png',    'w' => 1010,'h' => 580, 'frames' => 10, 'interval' => 90],
                        'walk'   => ['file' => 'Troll_01_1_WALK_spritesheet.png',   'w' => 775, 'h' => 580, 'frames' => 10, 'interval' => 90],
                        'attack' => ['file' => 'Troll_01_1_ATTACK_spritesheet.png', 'w' => 900, 'h' => 830, 'frames' => 10, 'interval' => 90],
                    ],
                ],
            ],
            'backgrounds' => [
                'battle'   => 'bg-forest1.png',
                'city'     => 'bg-elven_land4.png',
                'clearing' => 'bg-forest4.png',
            ],
            'ui' => [
                'fairy'             => 'fairy.svg',
                'fairy_freed'       => 'fairy-black.svg',
                'fairy_paused'      => 'fairy-black-paused.svg',
                'sign_post'         => 'sign-post.png',
                'sign_post_right'   => 'wooden-sign-post-standalone-right.png',
                'sign_post_stop'    => 'wooden-sign-post-stop.png',
                'sign_post_bnw'     => 'wooden-sign-posts-cropped-bnw.png',
                'sign_post_info'    => 'wooden-sign-posts-info.png',
                'house'             => 'house-solid.svg',
                'skull'             => 'skull.svg',
                'flag'              => 'flag.svg',
                'spiral'            => 'oily-spiral-svgrepo-com.svg',
            ],
            'narrative' => [
                'world_enter' => [
                    "Welcome to {{world_name}}, {{player_name}}! Mysterious creatures await.",
                    "The forest falls silent as you enter {{world_name}}…",
                    "{{player_name}}, prepare yourself — {{world_name}} holds many challenges.",
                ],
                'victory' => [
                    "Your spell strikes true! The creature recoils.",
                    "Brilliant! {{score_fairies}} fairies freed so far.",
                    "The monster stumbles — your magic is formidable!",
                ],
                'defeat' => [
                    "The creature barely noticed. You lose {{mana_lost}} mana.",
                    "Not quite, {{player_name}}. {{score_mana}} mana remaining.",
                    "The spell fizzles. Try again — you can do this.",
                ],
                'low_mana' => [
                    "Warning! Only {{score_mana}} mana remaining. Choose your next spell wisely.",
                ],
                'boss_defeated' => [
                    "You've defeated the guardian of {{world_name}}! A new path opens.",
                    "Victory! The creatures of {{world_name}} have been vanquished.",
                ],
                'game_complete' => [
                    "Incredible, {{player_name}}! You've freed {{score_fairies}} fairies and mastered every challenge.",
                    "The realm is saved! Your journey through the enchanted forest is complete.",
                ],
                'intro' => [
                    "Welcome, {{player_name}}! The enchanted forest needs your help. Defeat the creatures by solving the challenges!",
                ],
            ],
        ];
    }
}
