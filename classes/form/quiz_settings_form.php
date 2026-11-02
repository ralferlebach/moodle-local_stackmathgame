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
 * Quiz settings form for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\form;

use local_stackmathgame\game\quiz_settings_service;
use local_stackmathgame\game\theme_manager;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Quiz-level settings form for STACK Math Game.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_settings_form extends \moodleform {
    /**
     * Define the form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform          = $this->_form;
        $customdata     = $this->_customdata;
        $config         = $customdata['config'];
        $designs        = $customdata['designs'];
        $labeloptions   = $customdata['labeloptions'];
        $canselectdesign = !empty($customdata['canselectdesign']);
        $canmanagelabels = !empty($customdata['canmanagelabels']);

        // The panel goes first, before any control. A teacher who is about to enable a game
        // needs to know it cannot start before making choices about how it should look.
        if (!empty($customdata['prerequisitehtml'])) {
            $mform->addElement('html', $customdata['prerequisitehtml']);
        }

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_stackmathgame'));
        $mform->addHelpButton('enabled', 'enabled', 'local_stackmathgame');
        $mform->setDefault('enabled', (int)($config->enabled ?? 0));

        $mform->addElement(
            'text',
            'teacherdisplayname',
            get_string('teacherdisplayname', 'local_stackmathgame'),
            ['size' => 50]
        );
        $mform->setType('teacherdisplayname', PARAM_TEXT);
        $mform->addRule('teacherdisplayname', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->setDefault('teacherdisplayname', (string)($config->teacherdisplayname ?? ''));
        $mform->addHelpButton('teacherdisplayname', 'teacherdisplayname', 'local_stackmathgame');

        $mform->addElement('header', 'labelheader', get_string('labelsettings', 'local_stackmathgame'));

        // Searched on the server as the teacher types: labels are site-wide and a site can hold
        // far more of them than one page should carry. The options passed here are only the
        // current label, so it shows as selected.
        $autocompleteoptions = [
            'multiple' => false,
            'noselectionstring' => get_string('choosedots'),
            'ajax' => 'local_stackmathgame/label_search',
            'data-cmid' => (int)$customdata['cmid'],
        ];
        $mform->addElement(
            'autocomplete',
            'labelid',
            get_string('label', 'local_stackmathgame'),
            $labeloptions,
            $autocompleteoptions
        );
        $mform->addHelpButton('labelid', 'label', 'local_stackmathgame');
        if (!empty($config->labelid)) {
            $mform->setDefault('labelid', (int)$config->labelid);
        }
        if (!empty($customdata['currentlabelname'])) {
            $mform->addElement(
                'static',
                'currentlabel',
                get_string('currentlabel', 'local_stackmathgame'),
                s($customdata['currentlabelname'])
            );
        }
        // Selecting an existing label stays available to anyone who may configure the quiz.
        // Freezing this too would leave a teacher unable to save at all, because a label is
        // required - the game would become unusable for exactly the role that runs it.

        $mform->addElement(
            'text',
            'newlabel',
            get_string('newlabel', 'local_stackmathgame'),
            [
                'size' => 40,
                'placeholder' => get_string('newlabelplaceholder', 'local_stackmathgame'),
            ]
        );
        $mform->setType('newlabel', PARAM_TEXT);
        $mform->addRule('newlabel', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $mform->addHelpButton('newlabel', 'newlabel', 'local_stackmathgame');
        // Creating one is the restricted half: a new label adds to a site-wide namespace that
        // other courses share, which is a governance decision rather than a course setting.
        if (!$canmanagelabels) {
            $mform->freeze('newlabel');
            $mform->addElement(
                'static',
                'newlabelnotice',
                '',
                get_string('newlabel_restricted', 'local_stackmathgame')
            );
        }

        $mform->addElement(
            'static',
            'labelnote',
            '',
            get_string('labelselectionnotice', 'local_stackmathgame')
        );

        $mform->addElement('header', 'designheader', get_string('designsettings', 'local_stackmathgame'));

        if (empty($designs)) {
            $mform->addElement(
                'static',
                'nodesigns',
                '',
                get_string('nodesignsavailable', 'local_stackmathgame')
            );
        } else {
            $radios = [];
            foreach ($designs as $design) {
                $labelhtml = $this->render_design_tile($design);
                $radios[]  = $mform->createElement('radio', 'designid', '', $labelhtml, (int)$design->id);
            }
            $mform->addGroup(
                $radios,
                'designid_group',
                get_string('design', 'local_stackmathgame'),
                ['<br>'],
                false
            );
            $mform->addHelpButton('designid_group', 'design', 'local_stackmathgame');
            $mform->setDefault('designid', (int)($config->designid ?? 0));
            if (!$canselectdesign) {
                $mform->freeze('designid_group');
            }
        }

        // Stash integration section (only shown when block_stash is installed).
        // The stash reward used to be edited here, in a second per-slot list parallel to the
        // direction cards. It never actually appeared: quiz_settings.php never supplied
        // 'stashitems', so the whole block was dead, and stash_mapping_service::save_for_quiz()
        // had no caller outside its own tests. A stash reward is per-slot game configuration and
        // now lives where the other rewards live - in the direction card on flow.php.

        $mform->addElement('hidden', 'quizid');
        $mform->setType('quizid', PARAM_INT);
        $mform->setDefault('quizid', (int)$customdata['quizid']);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', (int)$customdata['cmid']);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data  Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by field name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        // One set of rules for the form and the save: the service applies them again on the
        // server, because a form is not a security boundary.
        $cmid = (int)$this->_customdata['cmid'];
        return array_merge($errors, quiz_settings_service::validate(
            $cmid,
            (array)$data,
            \context_module::instance($cmid)
        ));
    }

    /**
     * Render a compact visual tile for a design option.
     *
     * @param \stdClass $design The design record.
     * @return string HTML tile markup.
     */
    private function render_design_tile(\stdClass $design): string {
        // Resolved the way the game resolves its artwork, so the preview shows what the game
        // will show - for bundled packages and uploaded ones alike.
        $previewurl = theme_manager::preview_url($design);
        if ($previewurl !== '') {
            $thumb = \html_writer::empty_tag('img', [
                'src'   => $previewurl,
                'alt'   => format_string((string)$design->name),
                'style' => 'display:block;width:160px;height:90px;object-fit:cover;' .
                           'border-radius:8px;margin-bottom:8px;',
            ]);
        } else {
            $thumb = \html_writer::div(get_string('nopreview', 'local_stackmathgame'), 'text-muted small', [
                'style' => 'display:flex;align-items:center;justify-content:center;' .
                           'width:160px;height:90px;border-radius:8px;margin-bottom:8px;' .
                           'background:#f5f5f5;',
            ]);
        }
        $mode = '';
        if (!empty($design->modecomponent)) {
            $mode = \html_writer::div(
                s((string)$design->modecomponent),
                'text-muted',
                ['style' => 'font-size:0.85em;']
            );
        }
        $desc = '';
        if (!empty($design->description)) {
            $desc = \html_writer::div(
                s((string)$design->description),
                '',
                ['style' => 'font-size:0.9em;']
            );
        }
        return \html_writer::div(
            $thumb .
            \html_writer::tag('strong', format_string((string)$design->name)) .
            $mode .
            $desc,
            'local-stackmathgame-design-tile',
            [
                'style' => 'display:inline-block;max-width:220px;padding:12px;' .
                           'border:1px solid #d0d7de;border-radius:12px;background:#fff;',
            ]
        );
    }
}
