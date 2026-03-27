<?php
namespace local_stackmathgame\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Return game configuration for a quiz.
 */
class get_quiz_config extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
        ]);
    }

    public static function execute(int $quizid): array {
        [, , $config, $profile, $design] = api::validate_quiz_access($quizid);
        return [
            'quizid' => $quizid,
            'enabled' => (int)$config->enabled,
            'requiresbehaviour' => (int)$config->requiresbehaviour,
            'labelid' => (int)$config->labelid,
            'designid' => (int)$config->designid,
            'teacherdisplayname' => (string)($config->teacherdisplayname ?? ''),
            'configjson' => (string)($config->configjson ?? '{}'),
            'design' => api::export_design($design),
            'profile' => api::export_profile($profile),
            'questionmap' => api::get_question_map($quizid),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'quizid' => new \external_value(PARAM_INT, 'Quiz id'),
            'enabled' => new \external_value(PARAM_BOOL, 'Enabled'),
            'requiresbehaviour' => new \external_value(PARAM_BOOL, 'Requires custom behaviour'),
            'labelid' => new \external_value(PARAM_INT, 'Label id'),
            'designid' => new \external_value(PARAM_INT, 'Design id'),
            'teacherdisplayname' => new \external_value(PARAM_TEXT, 'Teacher-facing display name'),
            'configjson' => new \external_value(PARAM_RAW, 'Raw config JSON'),
            'design' => self::design_structure(),
            'profile' => self::profile_structure(),
            'questionmap' => new \external_multiple_structure(self::questionmap_structure()),
        ]);
    }

    public static function profile_structure(): \external_single_structure {
        return new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'Profile id'),
            'userid' => new \external_value(PARAM_INT, 'User id'),
            'labelid' => new \external_value(PARAM_INT, 'Label id'),
            'score' => new \external_value(PARAM_INT, 'Score'),
            'xp' => new \external_value(PARAM_INT, 'XP'),
            'levelno' => new \external_value(PARAM_INT, 'Level'),
            'softcurrency' => new \external_value(PARAM_INT, 'Soft currency'),
            'hardcurrency' => new \external_value(PARAM_INT, 'Hard currency'),
            'avatarconfigjson' => new \external_value(PARAM_RAW, 'Avatar config json'),
            'progressjson' => new \external_value(PARAM_RAW, 'Progress json'),
            'statsjson' => new \external_value(PARAM_RAW, 'Stats json'),
            'flagsjson' => new \external_value(PARAM_RAW, 'Flags json'),
            'lastquizid' => new \external_value(PARAM_INT, 'Last quiz id'),
            'lastdesignid' => new \external_value(PARAM_INT, 'Last design id'),
            'lastaccess' => new \external_value(PARAM_INT, 'Last access timestamp'),
            'summaryjson' => new \external_value(PARAM_RAW, 'Profile summary json'),
        ]);
    }

    public static function design_structure(): \external_single_structure {
        return new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'Design id'),
            'name' => new \external_value(PARAM_TEXT, 'Design name'),
            'slug' => new \external_value(PARAM_ALPHANUMEXT, 'Design slug'),
            'modecomponent' => new \external_value(PARAM_PLUGIN, 'Mode component'),
            'description' => new \external_value(PARAM_TEXT, 'Description'),
            'isbundled' => new \external_value(PARAM_BOOL, 'Bundled flag'),
            'isactive' => new \external_value(PARAM_BOOL, 'Active flag'),
            'narrativejson' => new \external_value(PARAM_RAW, 'Narrative json'),
            'uijson' => new \external_value(PARAM_RAW, 'UI json'),
            'mechanicsjson' => new \external_value(PARAM_RAW, 'Mechanics json'),
            'assetmanifestjson' => new \external_value(PARAM_RAW, 'Asset manifest json'),
            'runtimejson' => new \external_value(PARAM_RAW, 'Runtime config json'),
        ]);
    }

    public static function questionmap_structure(): \external_single_structure {
        return new \external_single_structure([
            'slotnumber' => new \external_value(PARAM_INT, 'Quiz slot'),
            'questionid' => new \external_value(PARAM_INT, 'Question id'),
            'nodekey' => new \external_value(PARAM_TEXT, 'Node key'),
            'nodetype' => new \external_value(PARAM_TEXT, 'Node type'),
            'sortorder' => new \external_value(PARAM_INT, 'Sort order'),
            'configjson' => new \external_value(PARAM_RAW, 'Node config json'),
        ]);
    }
}
