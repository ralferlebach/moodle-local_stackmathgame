<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Studio-facing design management helper.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\studio;

use local_stackmathgame\game\theme_manager;
use local_stackmathgame\local\packaging\package_registry;

/**
 * Studio-facing design listing and persistence helper.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_manager_studio {
    /**
     * Export all active designs for the studio gallery.
     *
     * @return array<int, array<string, mixed>> Array of design export arrays.
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
                'slug'          => (string)$design->slug,
                'modecomponent' => (string)$design->modecomponent,
                'description'   => (string)($design->description ?? ''),
                'isbundled'     => !empty($design->isbundled),
                'isactive'      => !empty($design->isactive),
                'thumbnailurl'  => (string)($runtimeassets['thumbnail'] ?? ''),
                'canexport'     => true,
            ];
        }
        return $designs;
    }

    /**
     * Export a single design record for the studio edit form.
     *
     * @param int $designid The design record ID.
     * @return array<string, mixed>|null The design export array, or null if not found.
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
     * Persist a design record from studio form data.
     *
     * @param array $data Form data array (id, name, slug, modecomponent, isactive,
     *                    description, narrativejson, uijson, mechanicsjson, assetmanifestjson).
     * @return int The design record ID.
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
            $record->narrativejson    = (string)($data['narrativejson'] ?? '{}');
            $record->uijson           = (string)($data['uijson'] ?? '{}');
            $record->mechanicsjson    = (string)($data['mechanicsjson'] ?? '{}');
            $record->assetmanifestjson = (string)($data['assetmanifestjson'] ?? '{}');
            $record->timemodified     = $now;
            $record->modifiedby       = (int)$USER->id;
            $DB->update_record('local_stackmathgame_design', $record);
        } else {
            $slug = clean_param(
                (string)($data['slug'] ?? 'design_' . time()),
                PARAM_ALPHANUMEXT
            );
            $id = (int)$DB->insert_record('local_stackmathgame_design', (object)[
                'name'             => clean_param((string)($data['name'] ?? ''), PARAM_TEXT),
                'slug'             => $slug,
                'modecomponent'    => clean_param((string)($data['modecomponent'] ?? ''), PARAM_COMPONENT),
                'isactive'         => empty($data['isactive']) ? 0 : 1,
                'isbundled'        => 0,
                'versioncode'      => 1,
                'description'      => clean_param((string)($data['description'] ?? ''), PARAM_TEXT),
                'narrativejson'    => (string)($data['narrativejson'] ?? '{}'),
                'uijson'           => (string)($data['uijson'] ?? '{}'),
                'mechanicsjson'    => (string)($data['mechanicsjson'] ?? '{}'),
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
