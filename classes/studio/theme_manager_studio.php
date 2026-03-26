<?php
namespace local_stackmathgame\studio;

defined('MOODLE_INTERNAL') || die();

use local_stackmathgame\game\theme_manager;

class theme_manager_studio {
    public static function get_mode_options(): array {
        return [
            'stackmathgamemode_exitgames' => 'ExitGames',
            'stackmathgamemode_wisewizzard' => 'TheWiseWizzard',
            'stackmathgamemode_rpg' => 'RPG',
        ];
    }

    public static function get_design(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('local_stackmathgame_design', ['id' => $id]) ?: null;
    }

    public static function get_design_for_form(?int $id = null): \stdClass {
        $design = $id ? self::get_design($id) : null;
        if ($design) {
            return $design;
        }
        $now = time();
        return (object)[
            'id' => 0,
            'name' => '',
            'slug' => '',
            'modecomponent' => 'stackmathgamemode_rpg',
            'description' => '',
            'isactive' => 1,
            'narrativejson' => json_encode(self::get_default_narrative_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'uijson' => json_encode(theme_manager::default_fantasy_config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'mechanicsjson' => json_encode(self::get_default_mechanics_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'assetmanifestjson' => json_encode(self::get_default_asset_manifest_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
    }

    public static function save_from_form(\stdClass $data): int {
        global $DB, $USER;
        $now = time();
        $record = (object)[
            'name' => trim((string)$data->name),
            'slug' => clean_param((string)$data->slug, PARAM_ALPHANUMEXT),
            'modecomponent' => clean_param((string)$data->modecomponent, PARAM_COMPONENT),
            'description' => trim((string)($data->description ?? '')),
            'isactive' => !empty($data->isactive) ? 1 : 0,
            'narrativejson' => self::normalise_json_string($data->narrativejson ?? json_encode(self::get_default_narrative_schema())),
            'uijson' => self::normalise_json_string($data->uijson ?? json_encode(theme_manager::default_fantasy_config())),
            'mechanicsjson' => self::normalise_json_string($data->mechanicsjson ?? json_encode(self::get_default_mechanics_schema())),
            'assetmanifestjson' => self::normalise_json_string($data->assetmanifestjson ?? json_encode(self::get_default_asset_manifest_schema())),
            'timemodified' => $now,
            'modifiedby' => $USER->id,
        ];

        if (!empty($data->id)) {
            $existing = self::get_design((int)$data->id);
            if (!$existing) {
                throw new \moodle_exception('invalidrecord');
            }
            $record->id = (int)$existing->id;
            $record->isbundled = $existing->isbundled ?? 0;
            $record->versioncode = ((int)($existing->versioncode ?? 1)) + 1;
            $record->timecreated = $existing->timecreated;
            $record->createdby = $existing->createdby;
            $record->thumbnailfilename = $existing->thumbnailfilename;
            $record->thumbnailfileitemid = $existing->thumbnailfileitemid;
            $record->importmetajson = $existing->importmetajson;
            $DB->update_record('local_stackmathgame_design', $record);
            $designid = (int)$existing->id;
        } else {
            $record->thumbnailfilename = null;
            $record->thumbnailfileitemid = null;
            $record->isbundled = 0;
            $record->versioncode = 1;
            $record->importmetajson = json_encode(['createdinstudio' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $record->timecreated = $now;
            $record->createdby = $USER->id;
            $designid = (int)$DB->insert_record('local_stackmathgame_design', $record);
        }

        self::save_thumbnail_draft($designid, (int)($data->thumbnaildraftid ?? 0));
        self::upsert_design_package($designid, [
            'format' => 'stackmathgame-design-package',
            'schema' => 1,
            'modecomponent' => $record->modecomponent,
            'exportedat' => $now,
        ]);
        theme_manager::purge_cache();
        return $designid;
    }

    public static function get_thumbnail_url(int $designid): ?string {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $files = $fs->get_area_files($context->id, 'local_stackmathgame', 'designthumbnail', $designid, 'itemid, filepath, filename', false);
        if (!$files) {
            return null;
        }
        $file = reset($files);
        return \moodle_url::make_pluginfile_url($context->id, 'local_stackmathgame', 'designthumbnail', $designid, $file->get_filepath(), $file->get_filename(), false)->out(false);
    }

    public static function prepare_thumbnail_draft(?\stdClass $design): int {
        $draftid = file_get_submitted_draft_itemid('thumbnaildraftid');
        file_prepare_draft_area($draftid, \context_system::instance()->id, 'local_stackmathgame', 'designthumbnail', (int)($design->id ?? 0), ['subdirs' => 0, 'maxfiles' => 1]);
        return $draftid;
    }

    private static function save_thumbnail_draft(int $designid, int $draftid): void {
        if ($draftid <= 0) {
            return;
        }
        file_save_draft_area_files($draftid, \context_system::instance()->id, 'local_stackmathgame', 'designthumbnail', $designid, ['subdirs' => 0, 'maxfiles' => 1]);
    }

    public static function list_designs_for_gallery(bool $canexport = true): array {
        $designs = [];
        foreach (theme_manager::get_all_enabled() as $design) {
            $designs[] = [
                'id' => (int)$design->id,
                'name' => (string)$design->name,
                'slug' => (string)$design->slug,
                'modecomponent' => (string)$design->modecomponent,
                'description' => (string)($design->description ?? ''),
                'isbundled' => !empty($design->isbundled),
                'isactive' => !empty($design->isactive),
                'thumbnailurl' => self::get_thumbnail_url((int)$design->id),
                'canexport' => $canexport,
            ];
        }
        return $designs;
    }

    public static function export_design_package(int $designid): ?array {
        $design = self::get_design($designid);
        if (!$design) {
            return null;
        }
        $manifest = [
            'format' => 'stackmathgame-design-package',
            'schema' => 1,
            'name' => $design->name,
            'slug' => $design->slug,
            'modecomponent' => $design->modecomponent,
            'description' => $design->description,
            'narrative' => json_decode((string)$design->narrativejson, true),
            'ui' => json_decode((string)$design->uijson, true),
            'mechanics' => json_decode((string)$design->mechanicsjson, true),
            'assets' => json_decode((string)$design->assetmanifestjson, true),
        ];
        self::upsert_design_package($designid, $manifest);
        return $manifest;
    }

    private static function upsert_design_package(int $designid, array $manifest): void {
        global $DB, $USER;
        $now = time();
        $record = $DB->get_record('local_stackmathgame_design_package', ['designid' => $designid, 'packageversion' => 'v1']);
        $payload = (object)[
            'designid' => $designid,
            'packageidentifier' => 'design-' . $designid,
            'packageversion' => 'v1',
            'manifestjson' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'checksum' => hash('sha256', json_encode($manifest)),
            'origin' => 'studio',
            'timemodified' => $now,
        ];
        if ($record) {
            $payload->id = $record->id;
            $payload->timecreated = $record->timecreated;
            $payload->importedby = $record->importedby;
            $payload->exportedby = $USER->id;
            $DB->update_record('local_stackmathgame_design_package', $payload);
        } else {
            $payload->timecreated = $now;
            $payload->importedby = $USER->id;
            $payload->exportedby = $USER->id;
            $DB->insert_record('local_stackmathgame_design_package', $payload);
        }
    }

    private static function normalise_json_string(string $value): string {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return json_encode(new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('invalidjsonpayload', 'local_stackmathgame');
        }
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public static function get_default_narrative_schema(): array {
        return [
            'world_enter' => ['Welcome to the world.'],
            'victory' => ['Well done.'],
            'defeat' => ['Try again.'],
            'boss_intro' => ['A tougher challenge awaits.'],
            'reward' => ['You gained progress.'],
        ];
    }

    public static function get_default_mechanics_schema(): array {
        return [
            'version' => 1,
            'notes' => 'Mode-specific mechanics. Teachers cannot edit this.',
            'runtime' => ['prefetch' => true],
        ];
    }

    public static function get_default_asset_manifest_schema(): array {
        return [
            'thumbnail' => null,
            'backgrounds' => [],
            'characters' => [],
            'ui' => [],
            'audio' => [],
        ];
    }
}
