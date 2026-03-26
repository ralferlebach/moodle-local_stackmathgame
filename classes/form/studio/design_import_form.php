<?php
namespace local_stackmathgame\form\studio;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class design_import_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'action', 'import');
        $mform->setType('action', PARAM_ALPHA);
        $options = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.zip']];
        $mform->addElement('filepicker', 'importzip', get_string('studio_importzip', 'local_stackmathgame'), null, $options);
        $mform->addRule('importzip', null, 'required', null, 'client');
        $mform->addElement('static', 'importformat', '', get_string('studio_importformat', 'local_stackmathgame'));
        $this->add_action_buttons(true, get_string('import', 'core'));
    }
}
