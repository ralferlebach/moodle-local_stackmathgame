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

namespace local_stackmathgame\game;

use local_stackmathgame\local\service\prerequisite_checker;

/**
 * Validation and saving of the quiz-level game settings, in one place.
 *
 * The settings page used to validate in the form and save in the page script, with the form
 * checking the stored configuration rather than the submitted one and the page script creating
 * labels outside any transaction (issue #14). Both halves now call this class: validate() states
 * every rule once, save() applies them again on the server - a form is not a security boundary -
 * and writes label and configuration together or not at all.
 *
 * Rules for labels, as shown to the teacher in the help text:
 *  - A name typed into "new label" wins over the selection: typing a name is the explicit act.
 *  - A name that matches an active label (ignoring case) joins that label instead of creating a
 *    second one; label names are site-wide and unique.
 *  - A name that matches an archived or deleted label is refused rather than reusing it silently.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_settings_service {
    /** Longest label name the label table stores. */
    const LABEL_NAME_MAX = 100;

    /** Longest label idnumber the label table stores. */
    const LABEL_IDNUMBER_MAX = 100;

    /** Longest display name the configuration table stores. */
    const DISPLAYNAME_MAX = 255;

    /**
     * Return the values the form starts with, without writing anything.
     *
     * Opening or cancelling the settings page must not change the site. The page used to call
     * ensure_default(), which inserted a configuration row, the default label and possibly the
     * bundled designs as soon as a teacher merely looked at the page.
     *
     * @param int $cmid The course-module ID.
     * @return \stdClass Values with labelid, designid, enabled and teacherdisplayname.
     */
    public static function current_values(int $cmid): \stdClass {
        global $DB;

        $config = quiz_configurator::get_plugin_config($cmid);
        if ($config) {
            return $config;
        }
        $label = $DB->get_record('local_stackmathgame_label', ['name' => 'default', 'status' => 1], 'id');
        $design = $DB->get_record('local_stackmathgame_design', ['slug' => 'rpg_default', 'isactive' => 1], 'id');
        return (object)[
            'labelid' => $label ? (int)$label->id : 0,
            'designid' => $design ? (int)$design->id : 0,
            'enabled' => 0,
            'teacherdisplayname' => '',
        ];
    }

    /**
     * Validate submitted settings.
     *
     * @param int $cmid The course-module ID.
     * @param array $data The submitted values.
     * @param \context $context The activity context, for capability checks.
     * @return array<string, string> Errors keyed by form field; empty when valid.
     */
    public static function validate(int $cmid, array $data, \context $context): array {
        global $DB;

        $errors = [];
        $current = self::current_values($cmid);
        $enabled = !empty($data['enabled']);

        // Display name.
        $displayname = trim((string)($data['teacherdisplayname'] ?? ''));
        if (\core_text::strlen($displayname) > self::DISPLAYNAME_MAX) {
            $errors['teacherdisplayname'] = get_string('maximumchars', '', self::DISPLAYNAME_MAX);
        }

        // Label: a typed name, or a selection.
        $rawnew = (string)($data['newlabel'] ?? '');
        $newlabel = self::normalise_label_name($rawnew);
        if ($rawnew !== '' && $newlabel === '') {
            $errors['newlabel'] = get_string('err_labelnameempty', 'local_stackmathgame');
        } else if ($newlabel !== '') {
            if (!has_capability('local/stackmathgame:managelabels', $context)) {
                $errors['newlabel'] = get_string('newlabel_restricted', 'local_stackmathgame');
            } else if (\core_text::strlen($newlabel) > self::LABEL_NAME_MAX) {
                $errors['newlabel'] = get_string('maximumchars', '', self::LABEL_NAME_MAX);
            } else {
                $match = self::find_label_by_name($newlabel);
                if ($match && !self::label_is_active($match)) {
                    $errors['newlabel'] = get_string('err_labelarchived', 'local_stackmathgame', s($match->name));
                }
            }
        } else {
            $labelid = (int)($data['labelid'] ?? 0);
            if ($labelid <= 0) {
                $errors['labelid'] = get_string('err_labelrequired', 'local_stackmathgame');
            } else if ($labelid !== (int)$current->labelid) {
                // An unchanged label is kept as it is; a newly chosen one must exist and be active.
                $label = $DB->get_record('local_stackmathgame_label', ['id' => $labelid]);
                if (!$label || !self::label_is_active($label)) {
                    $errors['labelid'] = get_string('err_labelinvalid', 'local_stackmathgame');
                }
            }
        }

        // Design: required for a running game, optional for a draft.
        $designid = (int)($data['designid'] ?? 0);
        if ($designid > 0 && $designid !== (int)$current->designid) {
            if (!has_capability('local/stackmathgame:selectdesign', $context)) {
                $errors['designid_group'] = get_string('err_designnotallowed', 'local_stackmathgame');
            } else if (!$DB->record_exists('local_stackmathgame_design', ['id' => $designid, 'isactive' => 1])) {
                $errors['designid_group'] = get_string('err_designinvalid', 'local_stackmathgame');
            }
        }
        $effectivedesign = $designid > 0 ? $designid : (int)$current->designid;
        if ($enabled && $effectivedesign <= 0) {
            $errors['designid_group'] = get_string('err_designrequired', 'local_stackmathgame');
        }

        // Switching the game on is checked against the submitted design, not the stored one: a
        // teacher choosing a design and activating in the same save must not be refused because
        // the old design was missing.
        if ($enabled && !isset($errors['designid_group'])) {
            $blockers = prerequisite_checker::get_blockers($cmid, $effectivedesign);
            if ($blockers) {
                $errors['enabled'] = get_string(
                    'err_prerequisitesunmet',
                    'local_stackmathgame',
                    implode(' ', array_column($blockers, 'message'))
                );
            }
        }

        return $errors;
    }

    /**
     * Validate and save submitted settings atomically.
     *
     * Label and configuration are written in one transaction. A concurrent save of the same new
     * label or the same activity loses the race on a unique index; the whole save is then
     * repeated once, and the repetition finds the row the other request wrote.
     *
     * @param int $cmid The course-module ID.
     * @param array $data The submitted values.
     * @param \context $context The activity context, for capability checks.
     * @return \stdClass The saved configuration record.
     * @throws \moodle_exception When the data is invalid.
     */
    public static function save(int $cmid, array $data, \context $context): \stdClass {
        $errors = self::validate($cmid, $data, $context);
        if ($errors) {
            throw new \moodle_exception('err_settingsinvalid', 'local_stackmathgame', '', implode(' ', $errors));
        }
        try {
            return self::save_in_transaction($cmid, $data);
        } catch (\dml_write_exception $e) {
            return self::save_in_transaction($cmid, $data);
        }
    }

    /**
     * Write label and configuration in one transaction.
     *
     * @param int $cmid The course-module ID.
     * @param array $data The validated values.
     * @return \stdClass The saved configuration record.
     */
    private static function save_in_transaction(int $cmid, array $data): \stdClass {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();
        try {
            $record = self::write($cmid, $data, (int)$USER->id);
        } catch (\Throwable $e) {
            // Rolls back label and configuration together, then rethrows.
            $transaction->rollback($e);
        }
        $transaction->allow_commit();
        return $record;
    }

    /**
     * The writes of one save, run inside the caller's transaction.
     *
     * @param int $cmid The course-module ID.
     * @param array $data The validated values.
     * @param int $userid The saving user.
     * @return \stdClass The saved configuration record.
     */
    private static function write(int $cmid, array $data, int $userid): \stdClass {
        $current = self::current_values($cmid);

        $newlabel = self::normalise_label_name((string)($data['newlabel'] ?? ''));
        if ($newlabel !== '') {
            $match = self::find_label_by_name($newlabel);
            $labelid = $match ? (int)$match->id : self::create_label($newlabel, $userid);
        } else {
            $labelid = (int)($data['labelid'] ?? 0) ?: (int)$current->labelid;
        }
        $designid = (int)($data['designid'] ?? 0) ?: (int)$current->designid;

        $values = [
            'labelid' => $labelid,
            'designid' => $designid,
            'enabled' => empty($data['enabled']) ? 0 : 1,
            'teacherdisplayname' => trim((string)($data['teacherdisplayname'] ?? '')),
        ];
        return quiz_configurator::save_for_quiz($cmid, $values);
    }

    /**
     * Find a label by name, ignoring case and status.
     *
     * @param string $name The normalised name.
     * @return \stdClass|null The label record.
     */
    public static function find_label_by_name(string $name): ?\stdClass {
        global $DB;
        $select = $DB->sql_equal('name', ':name', false, false);
        $records = $DB->get_records_select('local_stackmathgame_label', $select, ['name' => $name], 'id ASC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Whether a label may be assigned to a quiz.
     *
     * @param \stdClass $label The label record.
     * @return bool True when active and not deleted.
     */
    public static function label_is_active(\stdClass $label): bool {
        return (int)$label->status === 1 && empty($label->timedeleted);
    }

    /**
     * Trim a label name and collapse inner whitespace.
     *
     * @param string $name The raw name.
     * @return string The normalised name.
     */
    public static function normalise_label_name(string $name): string {
        return trim((string)preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Derive a unique idnumber from a label name.
     *
     * Names in other scripts reduce to nothing under PARAM_ALPHANUMEXT; they get an idnumber from
     * a hash of the name instead of an empty one.
     *
     * @param string $name The normalised name.
     * @return string An idnumber not yet in use, at most LABEL_IDNUMBER_MAX characters.
     */
    public static function make_idnumber(string $name): string {
        global $DB;

        $base = clean_param(str_replace(' ', '_', \core_text::strtolower($name)), PARAM_ALPHANUMEXT);
        if ($base === '') {
            $base = 'label_' . substr(sha1($name), 0, 10);
        }
        // Room for a suffix, so a long name stays within the column after deduplication.
        $base = \core_text::substr($base, 0, self::LABEL_IDNUMBER_MAX - 6);
        $candidate = $base;
        $suffix = 1;
        while ($DB->record_exists('local_stackmathgame_label', ['idnumber' => $candidate])) {
            $candidate = $base . '_' . $suffix++;
        }
        return $candidate;
    }

    /**
     * Create an active label.
     *
     * @param string $name The normalised name.
     * @param int $userid The creating user.
     * @return int The new label ID.
     */
    private static function create_label(string $name, int $userid): int {
        global $DB;
        $now = time();
        return (int)$DB->insert_record('local_stackmathgame_label', (object)[
            'name' => $name,
            'idnumber' => self::make_idnumber($name),
            'description' => '',
            'status' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $userid > 0 ? $userid : null,
            'timedeleted' => null,
        ]);
    }
}
