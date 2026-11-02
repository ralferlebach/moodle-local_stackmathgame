<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_stackmathgame\unit;

use advanced_testcase;
use local_stackmathgame\external\search_labels;
use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\quiz_settings_service;
use local_stackmathgame\game\theme_manager;

/**
 * Saving the quiz-level game settings (issue #14).
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\game\quiz_settings_service
 * @covers     \local_stackmathgame\external\search_labels
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_settings_service_test extends advanced_testcase {
    /** @var \stdClass The quiz course module. */
    private $cm;

    /** @var \context_module The quiz context. */
    private $context;

    /**
     * Create a plain quiz and log in as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->cm = get_coursemodule_from_id('quiz', $quiz->cmid);
        $this->context = \context_module::instance($quiz->cmid);
        theme_manager::ensure_default_design();
        quiz_configurator::ensure_default_label();
    }

    /**
     * Submitted values as the form delivers them.
     *
     * @param array $values Values that differ from the defaults.
     * @return array The data.
     */
    private function data(array $values = []): array {
        $current = quiz_settings_service::current_values((int)$this->cm->id);
        return array_merge([
            'enabled' => 0,
            'teacherdisplayname' => '',
            'labelid' => (int)$current->labelid,
            'newlabel' => '',
            'designid' => (int)$current->designid,
        ], $values);
    }

    /**
     * The reported bug: a new name typed while the default label is selected was dropped.
     */
    public function test_a_new_label_wins_over_the_selected_one(): void {
        global $DB;

        $record = quiz_settings_service::save((int)$this->cm->id, $this->data(['newlabel' => 'Algebra  Term 1']), $this->context);

        $label = $DB->get_record('local_stackmathgame_label', ['id' => $record->labelid], '*', MUST_EXIST);
        $this->assertSame('Algebra Term 1', $label->name);
        $this->assertSame('algebra_term_1', $label->idnumber);
        // Reopening shows it.
        $this->assertSame((int)$label->id, (int)quiz_settings_service::current_values((int)$this->cm->id)->labelid);
    }

    /**
     * An existing active label of the same name is joined, ignoring case, not duplicated.
     */
    public function test_a_matching_active_label_is_joined(): void {
        global $DB;

        $first = quiz_settings_service::save((int)$this->cm->id, $this->data(['newlabel' => 'Geometry']), $this->context);
        $second = quiz_settings_service::save((int)$this->cm->id, $this->data(['newlabel' => 'GEOMETRY']), $this->context);

        $this->assertSame((int)$first->labelid, (int)$second->labelid);
        $this->assertSame(1, $DB->count_records_select('local_stackmathgame_label', 'LOWER(name) = ?', ['geometry']));
    }

    /**
     * An archived label is never assigned silently, by name or by id.
     */
    public function test_an_archived_label_is_refused(): void {
        global $DB;

        $id = $DB->insert_record('local_stackmathgame_label', (object)[
            'name' => 'Old world', 'idnumber' => 'old_world', 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $errors = quiz_settings_service::validate((int)$this->cm->id, $this->data(['newlabel' => 'old world']), $this->context);
        $this->assertArrayHasKey('newlabel', $errors);

        $errors = quiz_settings_service::validate((int)$this->cm->id, $this->data(['labelid' => $id]), $this->context);
        $this->assertArrayHasKey('labelid', $errors);
    }

    /**
     * Empty, whitespace-only and overlong values are field errors.
     */
    public function test_names_are_validated(): void {
        $cmid = (int)$this->cm->id;

        $errors = quiz_settings_service::validate($cmid, $this->data(['newlabel' => "   \t "]), $this->context);
        $this->assertArrayHasKey('newlabel', $errors);

        $errors = quiz_settings_service::validate($cmid, $this->data(['newlabel' => str_repeat('ä', 101)]), $this->context);
        $this->assertArrayHasKey('newlabel', $errors);

        $errors = quiz_settings_service::validate(
            $cmid,
            $this->data(['teacherdisplayname' => str_repeat('x', 256)]),
            $this->context
        );
        $this->assertArrayHasKey('teacherdisplayname', $errors);

        $errors = quiz_settings_service::validate($cmid, $this->data(['labelid' => 0]), $this->context);
        $this->assertArrayHasKey('labelid', $errors);
    }

    /**
     * Names in other scripts and long names still yield a valid, unique idnumber.
     */
    public function test_idnumbers_are_always_valid(): void {
        $greek = quiz_settings_service::make_idnumber('Γεωμετρία');
        $this->assertMatchesRegularExpression('/^label_[0-9a-f]{10}$/', $greek);

        $long = quiz_settings_service::make_idnumber(str_repeat('a', 100));
        $this->assertLessThanOrEqual(quiz_settings_service::LABEL_IDNUMBER_MAX, strlen($long));

        quiz_settings_service::save((int)$this->cm->id, $this->data(['newlabel' => 'Twin']), $this->context);
        $this->assertSame('twin_1', quiz_settings_service::make_idnumber('Twin'));
    }

    /**
     * Opening the page writes nothing: the defaults are computed.
     */
    public function test_reading_the_defaults_writes_nothing(): void {
        global $DB;

        $before = $DB->count_records('local_stackmathgame');
        $values = quiz_settings_service::current_values((int)$this->cm->id);

        $this->assertSame($before, $DB->count_records('local_stackmathgame'));
        $this->assertSame(0, (int)$values->enabled);
        $this->assertGreaterThan(0, (int)$values->labelid);
    }

    /**
     * A draft saves without a design; a running game does not.
     */
    public function test_a_design_is_required_only_for_a_running_game(): void {
        global $DB;

        $DB->set_field('local_stackmathgame_design', 'isactive', 0);
        $cmid = (int)$this->cm->id;
        $current = quiz_settings_service::current_values($cmid);
        $this->assertSame(0, (int)$current->designid);

        $errors = quiz_settings_service::validate($cmid, $this->data(['designid' => 0]), $this->context);
        $this->assertArrayNotHasKey('designid_group', $errors);

        $errors = quiz_settings_service::validate($cmid, $this->data(['designid' => 0, 'enabled' => 1]), $this->context);
        $this->assertArrayHasKey('designid_group', $errors);
    }

    /**
     * Activation is checked against the submitted design, not the stored one.
     */
    public function test_activation_checks_the_submitted_design(): void {
        global $DB;

        $designid = theme_manager::ensure_default_design();
        $cmid = (int)$this->cm->id;
        quiz_settings_service::save($cmid, $this->data(), $this->context);
        // A stored design that has since gone away.
        $DB->set_field('local_stackmathgame', 'designid', 0, ['cmid' => $cmid]);

        $errors = quiz_settings_service::validate($cmid, $this->data(['designid' => $designid, 'enabled' => 1]), $this->context);
        $this->assertStringNotContainsString(
            get_string('prereq_design_missing', 'local_stackmathgame'),
            (string)($errors['enabled'] ?? '')
        );
    }

    /**
     * The settings page judges the preselected design of an activity that has no configuration.
     *
     * Found by the end-to-end journey: the page writes nothing on opening any more, and the
     * prerequisite panel then built a configuration of the design alone, without the fields the
     * other checks read - the first visit to a new quiz's settings failed with a PHP warning.
     */
    public function test_the_panel_works_before_the_first_save(): void {
        global $DB;

        $cmid = (int)$this->cm->id;
        $DB->delete_records('local_stackmathgame', ['cmid' => $cmid]);
        $designid = (int)quiz_settings_service::current_values($cmid)->designid;

        $checks = \local_stackmathgame\local\service\prerequisite_checker::check($cmid, $designid);
        $design = array_values(array_filter($checks, static fn($c) => $c['key'] === 'design'))[0];
        $this->assertSame(\local_stackmathgame\local\service\prerequisite_checker::STATUS_OK, $design['status']);
        $this->assertNull(quiz_configurator::get_plugin_config($cmid));
    }

    /**
     * A teacher without managelabels cannot create a label, even by posting the field anyway.
     */
    public function test_creating_a_label_needs_the_capability(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_and_enrol(get_course($this->cm->course), 'editingteacher');
        $this->setUser($teacher);
        $this->assertFalse(has_capability('local/stackmathgame:managelabels', $this->context));

        $before = $DB->count_records('local_stackmathgame_label');
        try {
            quiz_settings_service::save((int)$this->cm->id, $this->data(['newlabel' => 'Sneaky']), $this->context);
            $this->fail('A label was created without the capability.');
        } catch (\moodle_exception $e) {
            $this->assertSame($before, $DB->count_records('local_stackmathgame_label'));
        }
    }

    /**
     * Rejected input leaves nothing behind - no label, no configuration.
     */
    public function test_a_rejected_save_writes_nothing(): void {
        global $DB;

        $labels = $DB->count_records('local_stackmathgame_label');
        try {
            quiz_settings_service::save(
                (int)$this->cm->id,
                $this->data(['newlabel' => 'Fresh', 'designid' => 999999]),
                $this->context
            );
            $this->fail('An unknown design was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame($labels, $DB->count_records('local_stackmathgame_label'));
            $this->assertFalse($DB->record_exists('local_stackmathgame', ['cmid' => $this->cm->id]));
        }
    }

    /**
     * Saving again changes nothing it was not asked to change, such as requiresbehaviour.
     */
    public function test_untouched_settings_survive_a_save(): void {
        global $DB;

        $cmid = (int)$this->cm->id;
        quiz_settings_service::save($cmid, $this->data(), $this->context);
        $DB->set_field('local_stackmathgame', 'requiresbehaviour', 0, ['cmid' => $cmid]);

        quiz_settings_service::save($cmid, $this->data(['teacherdisplayname' => 'Ms. Lovelace']), $this->context);

        $record = quiz_configurator::get_plugin_config($cmid);
        $this->assertSame(0, (int)$record->requiresbehaviour);
        $this->assertSame('Ms. Lovelace', $record->teacherdisplayname);
        $this->assertSame(1, $DB->count_records('local_stackmathgame', ['cmid' => $cmid]));
    }

    /**
     * The selector search returns active labels only, a page at a time.
     */
    public function test_label_search(): void {
        global $DB;

        for ($i = 0; $i < search_labels::LIMIT + 5; $i++) {
            $DB->insert_record('local_stackmathgame_label', (object)[
                'name' => sprintf('World %03d', $i), 'idnumber' => 'world_' . $i, 'status' => 1,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $DB->insert_record('local_stackmathgame_label', (object)[
            'name' => 'World archived', 'idnumber' => 'world_arch', 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $result = search_labels::execute((int)$this->cm->id, 'world');
        $this->assertCount(search_labels::LIMIT, $result['labels']);
        $this->assertTrue($result['more']);
        $this->assertNotContains('World archived', array_column($result['labels'], 'name'));

        $result = search_labels::execute((int)$this->cm->id, 'World 004');
        $this->assertSame(['World 004'], array_column($result['labels'], 'name'));
        $this->assertFalse($result['more']);
    }
}
