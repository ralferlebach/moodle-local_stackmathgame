<?php
namespace local_stackmathgame\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Quiz-level settings form for STACK Math Game.
 */
class quiz_settings_form extends \moodleform {
    /**
     * Define the form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $config = $customdata['config'];
        $designs = $customdata['designs'];
        $labeloptions = $customdata['labeloptions'];
        $canselectdesign = !empty($customdata['canselectdesign']);
        $canmanagelabels = !empty($customdata['canmanagelabels']);

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_stackmathgame'));
        $mform->addHelpButton('enabled', 'enabled', 'local_stackmathgame');
        $mform->setDefault('enabled', (int)($config->enabled ?? 0));

        $mform->addElement('text', 'teacherdisplayname', get_string('teacherdisplayname', 'local_stackmathgame'), ['size' => 50]);
        $mform->setType('teacherdisplayname', PARAM_TEXT);
        $mform->setDefault('teacherdisplayname', (string)($config->teacherdisplayname ?? ''));
        $mform->addHelpButton('teacherdisplayname', 'teacherdisplayname', 'local_stackmathgame');

        $mform->addElement('header', 'labelheader', get_string('labelsettings', 'local_stackmathgame'));

        $autocompleteoptions = [
            'multiple' => false,
            'noselectionstring' => get_string('choosedots'),
        ];
        $mform->addElement('autocomplete', 'labelid', get_string('label', 'local_stackmathgame'), $labeloptions, $autocompleteoptions);
        $mform->addHelpButton('labelid', 'label', 'local_stackmathgame');
        if (!empty($config->labelid)) {
            $mform->setDefault('labelid', (int)$config->labelid);
        }
        if (!$canmanagelabels) {
            $mform->freeze('labelid');
        }

        $mform->addElement('text', 'newlabel', get_string('newlabel', 'local_stackmathgame'), ['size' => 40, 'placeholder' => get_string('newlabelplaceholder', 'local_stackmathgame')]);
        $mform->setType('newlabel', PARAM_TEXT);
        $mform->addHelpButton('newlabel', 'newlabel', 'local_stackmathgame');
        if (!$canmanagelabels) {
            $mform->freeze('newlabel');
        }

        $mform->addElement('static', 'labelnote', '', get_string('labelselectionnotice', 'local_stackmathgame'));

        $mform->addElement('header', 'designheader', get_string('designsettings', 'local_stackmathgame'));

        if (empty($designs)) {
            $mform->addElement('static', 'nodesigns', '', get_string('nodesignsavailable', 'local_stackmathgame'));
        } else {
            $radios = [];
            foreach ($designs as $design) {
                $labelhtml = $this->render_design_tile($design);
                $radios[] = $mform->createElement('radio', 'designid', '', $labelhtml, (int)$design->id);
            }
            $mform->addGroup($radios, 'designid_group', get_string('design', 'local_stackmathgame'), ['<br>'], false);
            $mform->addHelpButton('designid_group', 'design', 'local_stackmathgame');
            $mform->setDefault('designid', (int)($config->designid ?? 0));
            if (!$canselectdesign) {
                $mform->freeze('designid_group');
            }
        }

        $mform->addElement('hidden', 'quizid');
        $mform->setType('quizid', PARAM_INT);
        $mform->setDefault('quizid', (int)$customdata['quizid']);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', (int)$customdata['cmid']);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $designid = (int)($data['designid'] ?? 0);
        $labelid = (int)($data['labelid'] ?? 0);
        $newlabel = trim((string)($data['newlabel'] ?? ''));

        if ($designid <= 0) {
            $errors['designid_group'] = get_string('err_designrequired', 'local_stackmathgame');
        }

        if ($labelid <= 0 && $newlabel === '') {
            $errors['labelid'] = get_string('err_labelrequired', 'local_stackmathgame');
        }

        return $errors;
    }

    /**
     * Render a simple design tile.
     *
     * @param \stdClass $design
     * @return string
     */
    private function render_design_tile(\stdClass $design): string {
        $thumb = '';
        if (!empty($design->thumbnailfilename) && !empty($design->slug)) {
            $url = new \moodle_url('/local/stackmathgame/pix/themes/' . rawurlencode((string)$design->slug) . '/' . rawurlencode((string)$design->thumbnailfilename));
            $thumb = \html_writer::empty_tag('img', [
                'src' => $url,
                'alt' => format_string((string)$design->name),
                'style' => 'display:block;width:160px;height:90px;object-fit:cover;border-radius:8px;margin-bottom:8px;',
            ]);
        } else {
            $thumb = \html_writer::div('🎮', '', [
                'style' => 'display:flex;align-items:center;justify-content:center;width:160px;height:90px;border-radius:8px;margin-bottom:8px;background:#f5f5f5;font-size:32px;'
            ]);
        }

        $mode = '';
        if (!empty($design->modecomponent)) {
            $mode = \html_writer::div(s((string)$design->modecomponent), 'text-muted', ['style' => 'font-size:0.85em;']);
        }

        $description = '';
        if (!empty($design->description)) {
            $description = \html_writer::div(s((string)$design->description), '', ['style' => 'font-size:0.9em;']);
        }

        return \html_writer::div(
            $thumb .
            \html_writer::tag('strong', format_string((string)$design->name)) .
            $mode .
            $description,
            'local-stackmathgame-design-tile',
            [
                'style' => 'display:inline-block;max-width:220px;padding:12px;border:1px solid #d0d7de;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04);'
            ]
        );
    }
}
