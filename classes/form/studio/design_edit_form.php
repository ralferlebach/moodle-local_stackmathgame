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
                'textarea',
                'narrativejson',
                get_string('designnarrativejson', 'local_stackmathgame'),
                ['rows' => 12, 'cols' => 80]
            );
            $mform->setType('narrativejson', PARAM_RAW);
        }

        if (!empty($caps['managemechanics'])) {
            $mform->addElement(
                'textarea',
                'uijson',
                get_string('designuijson', 'local_stackmathgame'),
                ['rows' => 12, 'cols' => 80]
            );
            $mform->setType('uijson', PARAM_RAW);
            $mform->addElement(
                'textarea',
                'mechanicsjson',
                get_string('designmechanicsjson', 'local_stackmathgame'),
                ['rows' => 12, 'cols' => 80]
            );
            $mform->setType('mechanicsjson', PARAM_RAW);
        }

        $this->add_action_buttons(true, get_string('savedesign', 'local_stackmathgame'));
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
