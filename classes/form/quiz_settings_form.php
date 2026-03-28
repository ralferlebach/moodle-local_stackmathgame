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
        $mform->setDefault('teacherdisplayname', (string)($config->teacherdisplayname ?? ''));
        $mform->addHelpButton('teacherdisplayname', 'teacherdisplayname', 'local_stackmathgame');

        $mform->addElement('header', 'labelheader', get_string('labelsettings', 'local_stackmathgame'));

        $autocompleteoptions = [
            'multiple' => false,
            'noselectionstring' => get_string('choosedots'),
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
        if (!$canmanagelabels) {
            $mform->freeze('labelid');
        }

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
        $mform->addHelpButton('newlabel', 'newlabel', 'local_stackmathgame');
        if (!$canmanagelabels) {
            $mform->freeze('newlabel');
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
        if (!empty($customdata['stashitems'])) {
            $stashitems = $customdata['stashitems'];
            $stashslots = $customdata['stashslots'] ?? [];

            $mform->addElement(
                'header',
                'stashheader',
                get_string('stashmapping_header', 'local_stackmathgame')
            );
            $mform->addElement(
                'html',
                html_writer::tag(
                    'p',
                    get_string('stashmapping_desc', 'local_stackmathgame'),
                    ['class' => 'text-muted small']
                )
            );

            if (empty($stashslots)) {
                $mform->addElement(
                    'html',
                    html_writer::tag(
                        'p',
                        get_string('stashmapping_noslots', 'local_stackmathgame'),
                        ['class' => 'alert alert-info']
                    )
                );
            } else {
                $existingmaps = $customdata['stashmappings'] ?? [];
                foreach ($stashslots as $slot) {
                    $existing = $existingmaps[$slot] ?? null;
                    $prefix = 'stashmap_' . $slot . '_';

                    $mform->addElement(
                        'header',
                        $prefix . 'slothdr',
                        get_string('stashmapping_slot', 'local_stackmathgame', $slot)
                    );

                    $mform->addElement(
                        'select',
                        $prefix . 'itemid',
                        get_string('stashmapping_item', 'local_stackmathgame'),
                        $stashitems
                    );
                    $mform->setDefault($prefix . 'itemid', (int)($existing->stashitemid ?? 0));

                    $mform->addElement(
                        'text',
                        $prefix . 'qty',
                        get_string('stashmapping_qty', 'local_stackmathgame'),
                        ['size' => 4]
                    );
                    $mform->setType($prefix . 'qty', PARAM_INT);
                    $mform->setDefault($prefix . 'qty', (int)($existing->grantquantity ?? 1));

                    $mform->addElement(
                        'advcheckbox',
                        $prefix . 'enabled',
                        get_string('stashmapping_enabled', 'local_stackmathgame')
                    );
                    $mform->setDefault($prefix . 'enabled', (int)($existing->enabled ?? 1));

                    $mform->addElement('hidden', $prefix . 'slot', $slot);
                    $mform->setType($prefix . 'slot', PARAM_INT);
                }
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
     * Validate the submitted form data.
     *
     * @param array $data  Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by field name.
     */
    public function validation($data, $files): array {
        $errors   = parent::validation($data, $files);
        $designid = (int)($data['designid'] ?? 0);
        $labelid  = (int)($data['labelid'] ?? 0);
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
     * Render a compact visual tile for a design option.
     *
     * @param \stdClass $design The design record.
     * @return string HTML tile markup.
     */
    private function render_design_tile(\stdClass $design): string {
        if (!empty($design->thumbnailfilename) && !empty($design->slug)) {
            $imgurl = new \moodle_url(
                '/local/stackmathgame/pix/themes/' .
                rawurlencode((string)$design->slug) . '/' .
                rawurlencode((string)$design->thumbnailfilename)
            );
            $thumb = \html_writer::empty_tag('img', [
                'src'   => $imgurl,
                'alt'   => format_string((string)$design->name),
                'style' => 'display:block;width:160px;height:90px;object-fit:cover;' .
                           'border-radius:8px;margin-bottom:8px;',
            ]);
        } else {
            $thumb = \html_writer::div('', '', [
                'style' => 'display:flex;align-items:center;justify-content:center;' .
                           'width:160px;height:90px;border-radius:8px;margin-bottom:8px;' .
                           'background:#f5f5f5;font-size:32px;',
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
