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
 * Design import form for the Game Design Studio.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\form\studio;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Form for importing a zipped design package into the studio.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class design_import_form extends \moodleform {
    /**
     * Define the form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'action', 'import');
        $mform->setType('action', PARAM_ALPHA);
        $options = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.zip']];
        $mform->addElement(
            'filepicker',
            'importzip',
            get_string('studio_importzip', 'local_stackmathgame'),
            null,
            $options
        );
        $mform->addRule('importzip', null, 'required', null, 'client');
        $mform->addElement(
            'static',
            'importformat',
            '',
            get_string('studio_importformat', 'local_stackmathgame')
        );
        $this->add_action_buttons(true, get_string('importdesign', 'local_stackmathgame'));
    }
}
