<?php
// This file is part of Moodle - http://moodle.org/

namespace local_stackmathgame\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use local_stackmathgame\game\state_machine;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\mechanic_registry;

/**
 * get_gamestate: load the current user's game state for a label.
 *
 * @package    local_stackmathgame
 */
class get_gamestate extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'labelid' => new external_value(PARAM_INT, 'Label ID'),
            'cmid'    => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'solved'          => new external_value(PARAM_RAW, 'JSON array of solved question IDs'),
            'solved_variants' => new external_value(PARAM_RAW, 'JSON array of solved variant pages'),
            'scores'          => new external_single_structure([
                'fairies' => new external_value(PARAM_INT),
                'mana'    => new external_value(PARAM_INT),
            ]),
            'timemodified'    => new external_value(PARAM_INT, 'Unix timestamp of last save'),
        ]);
    }

    public static function execute(int $labelid, int $cmid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'labelid' => $labelid,
            'cmid'    => $cmid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        $state = state_machine::load((int) $USER->id, $params['labelid']);

        return [
            'solved'          => json_encode($state->solved),
            'solved_variants' => json_encode($state->solved_variants),
            'scores'          => [
                'fairies' => (int) ($state->scores['fairies'] ?? 0),
                'mana'    => (int) ($state->scores['mana']    ?? state_machine::default_scores()['mana']),
            ],
            'timemodified'    => (int) $state->timemodified,
        ];
    }
}

// =============================================================================

/**
 * save_gamestate: persist the full game state from client (bulk save).
 * Used for sync on page unload / quiz navigation.
 *
 * @package    local_stackmathgame
 */
class save_gamestate extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'labelid'         => new external_value(PARAM_INT,  'Label ID'),
            'cmid'            => new external_value(PARAM_INT,  'Course module ID'),
            'solved'          => new external_value(PARAM_RAW,  'JSON array of solved question IDs'),
            'solved_variants' => new external_value(PARAM_RAW,  'JSON array of variant pages'),
            'score_fairies'   => new external_value(PARAM_INT,  'Fairy count'),
            'score_mana'      => new external_value(PARAM_INT,  'Mana value'),
            'sesskey'         => new external_value(PARAM_RAW,  'Moodle sesskey'),
        ]);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether save succeeded'),
            'timemodified' => new external_value(PARAM_INT,  'Server timestamp of save'),
        ]);
    }

    public static function execute(
        int    $labelid,
        int    $cmid,
        string $solved,
        string $solved_variants,
        int    $score_fairies,
        int    $score_mana,
        string $sesskey
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'labelid'         => $labelid,
            'cmid'            => $cmid,
            'solved'          => $solved,
            'solved_variants' => $solved_variants,
            'score_fairies'   => $score_fairies,
            'score_mana'      => $score_mana,
            'sesskey'         => $sesskey,
        ]);

        if (!confirm_sesskey($params['sesskey'])) {
            throw new \moodle_exception('invalidsesskey');
        }

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        $solved_arr          = json_decode($params['solved'],          true) ?? [];
        $solved_variants_arr = json_decode($params['solved_variants'], true) ?? [];

        state_machine::save(
            (int) $USER->id,
            $params['labelid'],
            $solved_arr,
            $solved_variants_arr,
            ['fairies' => $params['score_fairies'], 'mana' => $params['score_mana']]
        );

        return ['success' => true, 'timemodified' => time()];
    }
}

// =============================================================================

/**
 * get_quizconfig: returns the compiled game configuration for a quiz.
 *
 * @package    local_stackmathgame
 */
class get_quizconfig extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz instance ID'),
            'cmid'   => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'configjson'  => new external_value(PARAM_RAW, 'Full game config as JSON string'),
            'themejson'   => new external_value(PARAM_RAW, 'Theme config as JSON string'),
            'labelid'     => new external_value(PARAM_INT, 'Label ID'),
        ]);
    }

    public static function execute(int $quizid, int $cmid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'cmid'   => $cmid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/stackmathgame:play', $context);

        $config   = quiz_configurator::build_config($params['quizid'], (int) $USER->id);
        $plugincfg = quiz_configurator::get_plugin_config($params['quizid']);
        $labelid  = (int) ($plugincfg->labelid ?? 0);
        $themeid  = (int) ($plugincfg->themeid ?? 0);

        // Load theme config.
        $themeconfig = \local_stackmathgame\game\theme_manager::get_theme_config($themeid);

        // Add mechanic client configs.
        $mechovrs  = json_decode($plugincfg->configjson ?? '{}', true)['mechanics'] ?? [];
        $config['mechanicClientConfigs'] = mechanic_registry::get_all_client_configs($mechovrs);

        return [
            'configjson' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'themejson'  => json_encode($themeconfig, JSON_UNESCAPED_UNICODE),
            'labelid'    => $labelid,
        ];
    }
}

// =============================================================================

/**
 * get_labels: autocomplete search across all site-wide labels.
 *
 * @package    local_stackmathgame
 */
class get_labels extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search string', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'   => new external_value(PARAM_INT,  'Label ID'),
                'name' => new external_value(PARAM_TEXT, 'Label name'),
            ])
        );
    }

    public static function execute(string $query): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['query' => $query]);

        // Requires configure capability at system level.
        $syscontext = \context_system::instance();
        self::validate_context($syscontext);
        require_capability('local/stackmathgame:configure', $syscontext);

        $search = '%' . $DB->sql_like_escape($params['query']) . '%';
        $labels = $DB->get_records_sql(
            "SELECT id, name FROM {local_stackmathgame_label}
              WHERE " . $DB->sql_like('name', ':search', false) . "
              ORDER BY name ASC
              LIMIT 20",
            ['search' => $search]
        );

        return array_values(array_map(fn($l) => ['id' => (int)$l->id, 'name' => $l->name], $labels));
    }
}

// =============================================================================

/**
 * create_label: create a new site-wide label (called from autocomplete tag flow).
 *
 * @package    local_stackmathgame
 */
class create_label extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'name'    => new external_value(PARAM_TEXT, 'New label name'),
            'sesskey' => new external_value(PARAM_RAW,  'Moodle sesskey'),
        ]);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'   => new external_value(PARAM_INT,  'New label ID'),
            'name' => new external_value(PARAM_TEXT, 'Label name'),
        ]);
    }

    public static function execute(string $name, string $sesskey): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'name' => $name, 'sesskey' => $sesskey,
        ]);

        if (!confirm_sesskey($params['sesskey'])) {
            throw new \moodle_exception('invalidsesskey');
        }

        $syscontext = \context_system::instance();
        self::validate_context($syscontext);
        require_capability('local/stackmathgame:managelabels', $syscontext);

        $cleanname = clean_param(trim($params['name']), PARAM_TEXT);
        if (strlen($cleanname) < 1 || strlen($cleanname) > 100) {
            throw new \moodle_exception('invalidlabelname', 'local_stackmathgame');
        }

        // Check for duplicate.
        if ($DB->record_exists('local_stackmathgame_label', ['name' => $cleanname])) {
            throw new \moodle_exception('labelexists', 'local_stackmathgame');
        }

        $now = time();
        $id  = $DB->insert_record('local_stackmathgame_label', (object) [
            'name'         => $cleanname,
            'contextid'    => SYSCONTEXTID,
            'createdby'    => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        return ['id' => (int) $id, 'name' => $cleanname];
    }
}
