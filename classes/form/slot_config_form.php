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
 * Form for one slot's direction card.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\form;

use local_stackmathgame\local\service\slot_config_schema;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Edits scene type, narrative, branching, rewards and display flags for a single slot.
 *
 * The form never exposes raw JSON. Teachers configure a scene; the schema decides what that
 * means on disk.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slot_config_form extends \moodleform {
    /**
     * Build the form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $slotoptions = (array)($this->_customdata['slotoptions'] ?? []);

        $mform->addElement('header', 'sceneheader', get_string('flow_scene', 'local_stackmathgame'));

        $scenetypes = [];
        foreach (slot_config_schema::SCENE_TYPES as $type) {
            $scenetypes[$type] = get_string('flow_scenetype_' . $type, 'local_stackmathgame');
        }
        $mform->addElement('select', 'scenetype', get_string('flow_scenetype', 'local_stackmathgame'), $scenetypes);
        $mform->addHelpButton('scenetype', 'flow_scenetype', 'local_stackmathgame');

        $mform->addElement('advcheckbox', 'enabled', get_string('flow_slotenabled', 'local_stackmathgame'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('header', 'narrativeheader', get_string('flow_narrative', 'local_stackmathgame'));
        foreach (['intro', 'success', 'fail'] as $key) {
            $mform->addElement(
                'textarea',
                'narrative_' . $key,
                get_string('flow_narrative_' . $key, 'local_stackmathgame'),
                ['rows' => 3, 'cols' => 60]
            );
            $mform->setType('narrative_' . $key, PARAM_TEXT);
        }

        $mform->addElement('header', 'branchingheader', get_string('flow_branching', 'local_stackmathgame'));
        $modes = [];
        foreach (slot_config_schema::BRANCH_MODES as $mode) {
            $modes[$mode] = get_string('flow_branchmode_' . $mode, 'local_stackmathgame');
        }
        foreach ($this->outcomes() as $outcome) {
            $mform->addElement(
                'select',
                'branch_' . $outcome . '_mode',
                get_string('flow_outcome_' . $outcome, 'local_stackmathgame'),
                $modes
            );
            $mform->addElement(
                'select',
                'branch_' . $outcome . '_target',
                get_string(
                    'flow_branchtarget_for',
                    'local_stackmathgame',
                    get_string('flow_outcome_' . $outcome, 'local_stackmathgame')
                ),
                $slotoptions
            );
            // The target only means anything for a slot jump. Hiding it otherwise is not
            // cosmetic: leaving a stale target visible next to "linear" invites the reader to
            // believe it still applies.
            $mform->hideIf(
                'branch_' . $outcome . '_target',
                'branch_' . $outcome . '_mode',
                'neq',
                slot_config_schema::BRANCH_MODE_SLOT
            );
        }

        $mform->addElement('header', 'rewardsheader', get_string('flow_rewards', 'local_stackmathgame'));
        $mform->addElement('text', 'reward_score', get_string('flow_reward_score', 'local_stackmathgame'), ['size' => 6]);
        $mform->setType('reward_score', PARAM_INT);
        $mform->setDefault('reward_score', 0);
        $mform->addElement('text', 'reward_xp', get_string('flow_reward_xp', 'local_stackmathgame'), ['size' => 6]);
        $mform->setType('reward_xp', PARAM_INT);
        $mform->setDefault('reward_xp', 0);
        $mform->addElement(
            'text',
            'reward_achievements',
            get_string('flow_reward_achievements', 'local_stackmathgame'),
            ['size' => 50]
        );
        $mform->setType('reward_achievements', PARAM_TEXT);
        $mform->addHelpButton('reward_achievements', 'flow_reward_achievements', 'local_stackmathgame');

        $mform->addElement('header', 'displayheader', get_string('flow_display', 'local_stackmathgame'));
        $mform->setExpanded('displayheader', false);
        foreach (['showxp', 'showinventory', 'showavatar'] as $flag) {
            $mform->addElement(
                'advcheckbox',
                'display_' . $flag,
                get_string('flow_display_' . $flag, 'local_stackmathgame')
            );
        }

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'slot');
        $mform->setType('slot', PARAM_INT);
        $mform->addElement('hidden', 'action', 'editslot');
        $mform->setType('action', PARAM_ALPHA);

        $this->add_action_buttons();
    }

    /**
     * Validate the submitted card.
     *
     * The authoritative check is slot_config_schema::validate(), called by flow_service on save.
     * What happens here is only what the schema cannot express: whether the teacher picked a
     * target at all when they asked for a jump.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by field name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        foreach ($this->outcomes() as $outcome) {
            $mode = (string)($data['branch_' . $outcome . '_mode'] ?? '');
            $target = (int)($data['branch_' . $outcome . '_target'] ?? 0);
            if ($mode === slot_config_schema::BRANCH_MODE_SLOT && $target <= 0) {
                $errors['branch_' . $outcome . '_target'] =
                    get_string('flow_err_targetrequired', 'local_stackmathgame');
            }
        }

        if ((int)($data['reward_score'] ?? 0) < 0) {
            $errors['reward_score'] = get_string('flow_err_negativereward', 'local_stackmathgame');
        }
        if ((int)($data['reward_xp'] ?? 0) < 0) {
            $errors['reward_xp'] = get_string('flow_err_negativereward', 'local_stackmathgame');
        }

        return $errors;
    }

    /**
     * Convert a stored config into flat form values.
     *
     * @param array $config The normalised slot config.
     * @param int $cmid The course-module ID.
     * @param int $slotnumber The slot number.
     * @return array Form values.
     */
    public static function config_to_form(array $config, int $cmid, int $slotnumber): array {
        $values = [
            'cmid' => $cmid,
            'slot' => $slotnumber,
            'action' => 'editslot',
            'scenetype' => (string)($config['scene']['type'] ?? slot_config_schema::SCENE_TYPE_CHALLENGE),
            'enabled' => !empty($config['enabled']) ? 1 : 0,
            'reward_score' => (int)($config['rewards']['score'] ?? 0),
            'reward_xp' => (int)($config['rewards']['xp'] ?? 0),
            'reward_achievements' => implode(', ', (array)($config['rewards']['achievementkeys'] ?? [])),
        ];
        foreach (['intro', 'success', 'fail'] as $key) {
            $values['narrative_' . $key] = (string)($config['narrative'][$key] ?? '');
        }
        foreach (self::outcome_keys() as $outcome) {
            $rule = (array)($config['branching'][$outcome] ?? []);
            $values['branch_' . $outcome . '_mode'] =
                (string)($rule['mode'] ?? slot_config_schema::BRANCH_MODE_LINEAR);
            $values['branch_' . $outcome . '_target'] = (int)($rule['target'] ?? 0);
        }
        foreach (['showxp', 'showinventory', 'showavatar'] as $flag) {
            $values['display_' . $flag] = !empty($config['display'][$flag]) ? 1 : 0;
        }
        return $values;
    }

    /**
     * Convert submitted form values back into a schema-shaped config.
     *
     * The existing config is passed in so sections the form does not cover - stash mappings and
     * badge IDs, which are managed elsewhere - survive an edit rather than being reset to empty.
     *
     * @param \stdClass $data Submitted form data.
     * @param array $existing The current stored config.
     * @return array The config to save.
     */
    public static function form_to_config(\stdClass $data, array $existing): array {
        $config = $existing;
        $config['enabled'] = !empty($data->enabled);
        $config['scene']['type'] = (string)$data->scenetype;

        foreach (['intro', 'success', 'fail'] as $key) {
            $config['narrative'][$key] = (string)($data->{'narrative_' . $key} ?? '');
        }

        foreach (self::outcome_keys() as $outcome) {
            $mode = (string)($data->{'branch_' . $outcome . '_mode'} ?? slot_config_schema::BRANCH_MODE_LINEAR);
            $rule = ['mode' => $mode];
            if ($mode === slot_config_schema::BRANCH_MODE_SLOT) {
                $rule['target'] = (int)($data->{'branch_' . $outcome . '_target'} ?? 0);
            }
            $config['branching'][$outcome] = $rule;
        }

        $config['rewards']['score'] = max(0, (int)($data->reward_score ?? 0));
        $config['rewards']['xp'] = max(0, (int)($data->reward_xp ?? 0));
        $achievements = array_filter(array_map(
            'trim',
            explode(',', (string)($data->reward_achievements ?? ''))
        ));
        $config['rewards']['achievementkeys'] = array_values($achievements);

        foreach (['showxp', 'showinventory', 'showavatar'] as $flag) {
            $config['display'][$flag] = !empty($data->{'display_' . $flag});
        }

        return $config;
    }

    /**
     * Return the outcome keys the form edits.
     *
     * @return string[] Outcome keys.
     */
    private function outcomes(): array {
        return self::outcome_keys();
    }

    /**
     * Return the outcome keys the form edits.
     *
     * @return string[] Outcome keys.
     */
    private static function outcome_keys(): array {
        return [
            slot_config_schema::OUTCOME_GRADEDRIGHT,
            slot_config_schema::OUTCOME_GRADEDWRONG,
            slot_config_schema::OUTCOME_COMPLETE,
            slot_config_schema::OUTCOME_DEFAULT,
        ];
    }
}
