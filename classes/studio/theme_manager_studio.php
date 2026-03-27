<?php
namespace local_stackmathgame\studio;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\packaging\package_registry;

/**
 * Studio-facing design/theme listing helper.
 *
 * Fixed issues:
 * 1. export_all() referenced $theme->shortname  → correct field is $theme->slug
 * 2. export_all() referenced $theme->isbuiltin  → correct field is $theme->isbundled
 * 3. export_all() referenced $theme->enabled    → correct field is $theme->isactive
 *    These mismatches caused PHP notices and a broken studio gallery.
 */
class theme_manager_studio {

    /**
     * Export all active designs for the studio gallery.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function export_all(): array {
        $designs = [];
        foreach (theme_manager::get_all_enabled() as $design) {
            $runtimeassets = package_registry::build_runtime_assets(
                (string)$design->modecomponent,
                (string)$design->slug
            );
            $designs[] = [
                'id'            => (int)$design->id,
                'name'          => (string)$design->name,
                // *** BUG FIX: was 'shortname' → correct field is 'slug' ***
                'slug'          => (string)$design->slug,
                'modecomponent' => (string)$design->modecomponent,
                'description'   => (string)($design->description ?? ''),
                // *** BUG FIX: was 'isbuiltin' → correct field is 'isbundled' ***
                'isbundled'     => !empty($design->isbundled),
                // *** BUG FIX: was 'enabled' → correct field is 'isactive' ***
                'isactive'      => !empty($design->isactive),
                'thumbnailurl'  => (string)($runtimeassets['thumbnail'] ?? ''),
                'canexport'     => true,
            ];
        }
        return $designs;
    }

    /**
     * Export a single design record for editing.
     *
     * @param int $designid
     * @return array<string, mixed>|null
     */
    public static function export_one(int $designid): ?array {
        global $DB;
        $design = $DB->get_record('local_stackmathgame_design', ['id' => $designid]);
        if (!$design) {
            return null;
        }
        $runtimeassets = package_registry::build_runtime_assets(
            (string)$design->modecomponent,
            (string)$design->slug
        );
        return [
            'id'               => (int)$design->id,
            'name'             => (string)$design->name,
            'slug'             => (string)$design->slug,
            'modecomponent'    => (string)$design->modecomponent,
            'description'      => (string)($design->description ?? ''),
            'isbundled'        => !empty($design->isbundled),
            'isactive'         => !empty($design->isactive),
            'narrativejson'    => (string)($design->narrativejson ?? '{}'),
            'uijson'           => (string)($design->uijson ?? '{}'),
            'mechanicsjson'    => (string)($design->mechanicsjson ?? '{}'),
            'assetmanifestjson' => (string)($design->assetmanifestjson ?? '{}'),
            'thumbnailurl'     => (string)($runtimeassets['thumbnail'] ?? ''),
            'canexport'        => true,
        ];
    }

    /**
     * Save a design record from form data.
     *
     * @param array $data Form data (id, name, slug, modecomponent, isactive,
     *                    description, narrativejson, uijson, mechanicsjson,
     *                    assetmanifestjson)
     * @return int Design id
     */
    public static function save_from_form(array $data): int {
        global $DB, $USER;

        $now = time();
        $id  = (int)($data['id'] ?? 0);

        if ($id > 0) {
            $record = $DB->get_record('local_stackmathgame_design', ['id' => $id], '*', MUST_EXIST);
            $record->name             = clean_param((string)($data['name'] ?? ''), PARAM_TEXT);
            $record->slug             = clean_param((string)($data['slug'] ?? ''), PARAM_ALPHANUMEXT);
            $record->modecomponent    = clean_param((string)($data['modecomponent'] ?? ''), PARAM_COMPONENT);
            $record->isactive         = empty($data['isactive']) ? 0 : 1;
            $record->description      = clean_param((string)($data['description'] ?? ''), PARAM_TEXT);
            $record->narrativejson    = (string)($data['narrativejson']    ?? '{}');
            $record->uijson           = (string)($data['uijson']           ?? '{}');
            $record->mechanicsjson    = (string)($data['mechanicsjson']    ?? '{}');
            $record->assetmanifestjson = (string)($data['assetmanifestjson'] ?? '{}');
            $record->timemodified     = $now;
            $record->modifiedby       = (int)$USER->id;
            $DB->update_record('local_stackmathgame_design', $record);
        } else {
            $id = (int)$DB->insert_record('local_stackmathgame_design', (object)[
                'name'             => clean_param((string)($data['name'] ?? ''), PARAM_TEXT),
                'slug'             => clean_param((string)($data['slug'] ?? 'design_' . time()), PARAM_ALPHANUMEXT),
                'modecomponent'    => clean_param((string)($data['modecomponent'] ?? ''), PARAM_COMPONENT),
                'isactive'         => empty($data['isactive']) ? 0 : 1,
                'isbundled'        => 0,
                'versioncode'      => 1,
                'description'      => clean_param((string)($data['description'] ?? ''), PARAM_TEXT),
                'narrativejson'    => (string)($data['narrativejson']    ?? '{}'),
                'uijson'           => (string)($data['uijson']           ?? '{}'),
                'mechanicsjson'    => (string)($data['mechanicsjson']    ?? '{}'),
                'assetmanifestjson' => (string)($data['assetmanifestjson'] ?? '{}'),
                'importmetajson'   => json_encode(['origin' => 'studio'], JSON_UNESCAPED_UNICODE),
                'timecreated'      => $now,
                'timemodified'     => $now,
                'createdby'        => (int)$USER->id,
                'modifiedby'       => (int)$USER->id,
            ]);
        }

        theme_manager::purge_cache();
        return $id;
    }
}
