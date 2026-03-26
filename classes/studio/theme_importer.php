<?php
namespace local_stackmathgame\studio;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\theme_manager;

class theme_importer {
    public static function process_upload(int $draftid): array {
        global $DB, $USER;

        if (!$draftid) {
            return ['success' => false, 'themeid' => null, 'error' => get_string('errornoupload', 'local_stackmathgame')];
        }

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id', false);
        if (!$files) {
            return ['success' => false, 'themeid' => null, 'error' => get_string('errornoupload', 'local_stackmathgame')];
        }

        $file = reset($files);
        $filename = $file->get_filename();
        $slug = clean_param(pathinfo($filename, PATHINFO_FILENAME), PARAM_ALPHANUMEXT) ?: 'importedtheme';
        $now = time();

        $record = (object)[
            'name' => ucwords(str_replace(['_', '-'], ' ', $slug)),
            'slug' => $slug,
            'modecomponent' => 'stackmathgamemode_rpg',
            'description' => get_string('studio_importeddescription', 'local_stackmathgame', $filename),
            'thumbnailfilename' => null,
            'thumbnailfileitemid' => null,
            'isbundled' => 0,
            'isactive' => 1,
            'versioncode' => 1,
            'narrativejson' => json_encode(theme_manager_studio::get_default_narrative_schema(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'uijson' => json_encode(theme_manager::default_fantasy_config(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'mechanicsjson' => json_encode(theme_manager_studio::get_default_mechanics_schema(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'assetmanifestjson' => json_encode(theme_manager_studio::get_default_asset_manifest_schema(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'importmetajson' => json_encode(['sourcefilename' => $filename, 'importedat' => $now], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'timemodified' => $now,
            'modifiedby' => $USER->id,
        ];

        $existing = $DB->get_record('local_stackmathgame_design', ['slug' => $slug]);
        if ($existing && !empty($existing->isbundled)) {
            return ['success' => false, 'themeid' => null, 'error' => get_string('errorcannotoverwritebundled', 'local_stackmathgame')];
        }
        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $record->createdby = $existing->createdby;
            $record->thumbnailfilename = $existing->thumbnailfilename;
            $record->thumbnailfileitemid = $existing->thumbnailfileitemid;
            $DB->update_record('local_stackmathgame_design', $record);
            $designid = (int)$existing->id;
        } else {
            $record->timecreated = $now;
            $record->createdby = $USER->id;
            $designid = (int)$DB->insert_record('local_stackmathgame_design', $record);
        }

        file_save_draft_area_files($draftid, \context_system::instance()->id, 'local_stackmathgame', 'designpackage', $designid, ['subdirs' => 0, 'maxfiles' => 1]);
        theme_manager_studio::export_design_package($designid);
        theme_manager::purge_cache();
        return ['success' => true, 'themeid' => $designid, 'error' => null];
    }
}
