<?php
namespace local_stackmathgame\studio;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\theme_manager;

/**
 * Import zipped theme bundles.
 */
class theme_importer {
    /**
     * @return array{success:bool,themeid:int|null,error:string|null}
     */
    public static function process_upload(): array {
        global $DB, $USER;

        $draftid = optional_param('importzip', 0, PARAM_INT);
        if (!$draftid) {
            return ['success' => false, 'themeid' => null, 'error' => 'No file uploaded.'];
        }

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id', false);
        if (!$files) {
            return ['success' => false, 'themeid' => null, 'error' => 'No file uploaded.'];
        }

        $file = reset($files);
        $filename = $file->get_filename();
        $shortname = clean_param(pathinfo($filename, PATHINFO_FILENAME), PARAM_SAFEDIR);
        if ($shortname === '') {
            $shortname = 'importedtheme';
        }

        $record = (object)[
            'name' => ucwords(str_replace(['_', '-'], ' ', $shortname)),
            'shortname' => $shortname,
            'configjson' => json_encode(theme_manager::default_fantasy_config(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'isbuiltin' => 0,
            'enabled' => 1,
            'sortorder' => 999,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $existing = $DB->get_record('local_stackmathgame_theme', ['shortname' => $shortname]);
        if ($existing) {
            if (!empty($existing->isbuiltin)) {
                return ['success' => false, 'themeid' => null, 'error' => 'Built-in themes cannot be overwritten.'];
            }
            $record->id = $existing->id;
            $DB->update_record('local_stackmathgame_theme', $record);
            $themeid = (int)$existing->id;
        } else {
            $themeid = (int)$DB->insert_record('local_stackmathgame_theme', $record);
        }

        theme_manager::purge_cache();
        return ['success' => true, 'themeid' => $themeid, 'error' => null];
    }
}
