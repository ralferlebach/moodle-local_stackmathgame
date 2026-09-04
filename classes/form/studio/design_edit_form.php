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
 * Design edit form for the Game Design Studio.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\form\studio;

use local_stackmathgame\local\service\narrative_resolver;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Form for creating or editing a game design record in the studio.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class design_edit_form extends \moodleform {
    /**
     * Define the form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform   = $this->_form;
        $custom  = $this->_customdata;
        $design  = $custom['design'] ?? null;
        $caps    = $custom['caps'] ?? [];

        $mform->addElement('hidden', 'id', $design->id ?? 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement(
            'text',
            'name',
            get_string('designname', 'local_stackmathgame'),
            ['size' => 50]
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement(
            'text',
            'slug',
            get_string('designslug', 'local_stackmathgame'),
            ['size' => 40]
        );
        $mform->setType('slug', PARAM_ALPHANUMEXT);
        $mform->addRule('slug', null, 'required', null, 'client');

        $mform->addElement(
            'select',
            'modecomponent',
            get_string('designmode', 'local_stackmathgame'),
            $custom['modeoptions'] ?? []
        );
        $mform->setType('modecomponent', PARAM_COMPONENT);

        $mform->addElement('advcheckbox', 'isactive', get_string('active'));

        $mform->addElement(
            'textarea',
            'description',
            get_string('description'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        if (!empty($caps['manageassets'])) {
            $fileopts = [
                'subdirs' => 0,
                'maxfiles' => 1,
                'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp', '.svg'],
            ];
            $mform->addElement(
                'filemanager',
                'thumbnaildraftid',
                get_string('designthumbnail', 'local_stackmathgame'),
                null,
                $fileopts
            );
            $mform->addElement(
                'textarea',
                'assetmanifestjson',
                get_string('designassetsmanifest', 'local_stackmathgame'),
                ['rows' => 8, 'cols' => 80]
            );
            $mform->setType('assetmanifestjson', PARAM_RAW);
        }

        if (!empty($caps['managenarratives'])) {
            $mform->addElement(
                'header',
                'narrativeheader',
                get_string('designnarrative', 'local_stackmathgame')
            );
            $mform->addElement(
                'static',
                'narrativehint',
                '',
                get_string('designnarrative_hint', 'local_stackmathgame')
            );
            // One field per canonical scene rather than one JSON blob. The scene list is closed -
            // narrative_resolver::canonical_scenes() defines it - so a free-form editor would only
            // add ways to misspell a key.
            foreach (narrative_resolver::canonical_scenes() as $scene) {
                $mform->addElement(
                    'textarea',
                    'narrative_' . $scene,
                    get_string('designscene_' . $scene, 'local_stackmathgame'),
                    ['rows' => 3, 'cols' => 70]
                );
                $mform->setType('narrative_' . $scene, PARAM_TEXT);
            }
        }

        if (!empty($caps['managemechanics'])) {
            $mform->addElement(
                'header',
                'presentationheader',
                get_string('designpresentation', 'local_stackmathgame')
            );
            // The theme names a design package. The installed designs are the authoritative
            // list of those, so the options come from there rather than from a directory scan.
            global $DB;
            $themes = [];
            foreach ($DB->get_records('local_stackmathgame_design', null, 'name ASC', 'slug, name') as $row) {
                $themes[$row->slug] = format_string($row->name) . ' (' . $row->slug . ')';
            }
            if ($themes) {
                $mform->addElement(
                    'select',
                    'uitheme',
                    get_string('designuitheme', 'local_stackmathgame'),
                    $themes
                );
            } else {
                $mform->addElement('text', 'uitheme', get_string('designuitheme', 'local_stackmathgame'));
                $mform->setType('uitheme', PARAM_ALPHANUMEXT);
            }
            // The mechanics field is not offered at all: "mode" follows from modecomponent and
            // "version" from the plugin. A field that can only be filled in wrongly is better
            // left out.
        }

        // The raw JSON stays available, collapsed, as a fallback and as a view of what the
        // fields above currently amount to. The baseline copies below let the save path tell
        // whether a human edited the JSON: if they did, it wins; if they did not, it is rebuilt
        // from the fields. Without that comparison the two halves would silently overwrite each
        // other depending on the order of the code.
        if (!empty($caps['managenarratives']) || !empty($caps['managemechanics'])) {
            $mform->addElement('header', 'rawheader', get_string('designraw', 'local_stackmathgame'));
            $mform->setExpanded('rawheader', false);
            $mform->addElement(
                'static',
                'rawhint',
                '',
                get_string('designraw_hint', 'local_stackmathgame')
            );
            foreach (['narrativejson', 'uijson', 'mechanicsjson'] as $field) {
                $mform->addElement(
                    'textarea',
                    $field,
                    get_string('design' . $field, 'local_stackmathgame'),
                    ['rows' => 10, 'cols' => 80]
                );
                $mform->setType($field, PARAM_RAW);
                $mform->addElement('hidden', $field . '_baseline');
                $mform->setType($field . '_baseline', PARAM_RAW);
            }
        }

        $this->add_action_buttons(true, get_string('savedesign', 'local_stackmathgame'));
    }

    /**
     * Turn a stored design into flat form values.
     *
     * @param \stdClass $design The design record.
     * @return array Form values, including the JSON baselines.
     */
    public static function design_to_form(\stdClass $design): array {
        $values = (array)$design;

        $narrative = json_decode((string)($design->narrativejson ?? '{}'), true) ?: [];
        foreach (narrative_resolver::canonical_scenes() as $scene) {
            $lines = $narrative[$scene] ?? [];
            // One line per narrative entry: the schema stores a list, and a textarea is the
            // shortest honest editor for a list of short strings.
            $values['narrative_' . $scene] = is_array($lines)
                ? implode("\n", array_map('strval', $lines))
                : (string)$lines;
        }

        $ui = json_decode((string)($design->uijson ?? '{}'), true) ?: [];
        $values['uitheme'] = (string)($ui['theme'] ?? ($design->slug ?? ''));

        // The baselines are what the raw fields were filled with. Comparing against them on save
        // is how "edited JSON wins" is decided.
        foreach (['narrativejson', 'uijson', 'mechanicsjson'] as $field) {
            $values[$field . '_baseline'] = (string)($design->{$field} ?? '');
        }

        return $values;
    }

    /**
     * Resolve the submitted form into the three JSON columns.
     *
     * The precedence rule, stated once: a raw JSON field that the user actually changed wins
     * over the structured fields. One that they left alone is rebuilt from those fields. Without
     * the comparison the two halves would overwrite each other in whichever order the code
     * happened to run, and a teacher editing a scene would silently lose it to a stale blob.
     *
     * Keys the structured fields do not model - anything an imported package brought along - are
     * merged rather than dropped.
     *
     * @param \stdClass $data Submitted form data.
     * @return array Map of column name to JSON string.
     */
    public static function form_to_design(\stdClass $data): array {
        $result = [];

        foreach (['narrativejson', 'uijson', 'mechanicsjson'] as $field) {
            $submitted = (string)($data->{$field} ?? '');
            $baseline  = (string)($data->{$field . '_baseline'} ?? '');
            if (self::json_was_edited($submitted, $baseline)) {
                $result[$field] = $submitted;
            }
        }

        if (!array_key_exists('narrativejson', $result)) {
            $narrative = json_decode((string)($data->narrativejson_baseline ?? '{}'), true) ?: [];
            foreach (narrative_resolver::canonical_scenes() as $scene) {
                $raw = trim((string)($data->{'narrative_' . $scene} ?? ''));
                $lines = $raw === '' ? [] : array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\r\n|\r|\n/', $raw)
                ), static fn(string $line): bool => $line !== ''));
                if ($lines) {
                    $narrative[$scene] = $lines;
                } else {
                    unset($narrative[$scene]);
                }
            }
            $result['narrativejson'] = json_encode($narrative, JSON_UNESCAPED_UNICODE);
        }

        if (!array_key_exists('uijson', $result) && isset($data->uitheme)) {
            $ui = json_decode((string)($data->uijson_baseline ?? '{}'), true) ?: [];
            $ui['theme'] = (string)$data->uitheme;
            $result['uijson'] = json_encode($ui, JSON_UNESCAPED_UNICODE);
        }

        if (!array_key_exists('mechanicsjson', $result)) {
            // Derived, never typed: mode follows from the mode component, version from the
            // plugin. Existing keys survive.
            $mechanics = json_decode((string)($data->mechanicsjson_baseline ?? '{}'), true) ?: [];
            $component = (string)($data->modecomponent ?? '');
            if ($component !== '') {
                $mechanics['mode'] = preg_replace('/^stackmathgamemode_/', '', $component);
            }
            $mechanics['version'] = (int)($mechanics['version'] ?? 1);
            $result['mechanicsjson'] = json_encode($mechanics, JSON_UNESCAPED_UNICODE);
        }

        return $result;
    }

    /**
     * Report whether a raw JSON field differs from what it was rendered with.
     *
     * Compared as decoded structures where both parse, so that reformatting or a reordered key
     * does not count as an edit - otherwise merely opening the collapsed section and saving
     * would hand the JSON precedence it was never meant to have.
     *
     * @param string $submitted The submitted value.
     * @param string $baseline The value the field was rendered with.
     * @return bool True when the user changed it.
     */
    private static function json_was_edited(string $submitted, string $baseline): bool {
        if (trim($submitted) === trim($baseline)) {
            return false;
        }
        $a = json_decode($submitted, true);
        $b = json_decode($baseline, true);
        if ($a === null || $b === null) {
            // One of them is not valid JSON; treat any textual difference as an edit so the
            // user's typing is never silently discarded.
            return true;
        }
        return $a !== $b;
    }

    /**
     * Validate JSON fields in the submitted data.
     *
     * @param array $data  Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by field name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach (['narrativejson', 'uijson', 'mechanicsjson', 'assetmanifestjson'] as $field) {
            if (array_key_exists($field, $data) && trim((string)$data[$field]) !== '') {
                json_decode((string)$data[$field], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[$field] = get_string('err_invalidjson', 'local_stackmathgame', $field);
                }
            }
        }
        return $errors;
    }
}
