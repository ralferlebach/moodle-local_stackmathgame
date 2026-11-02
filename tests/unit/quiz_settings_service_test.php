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

    /**
     * A fresh installation provides the default label, so a teacher can save without creating one.
     *
     * The two Behat scenarios that failed (re-audit N05) ran on a fresh site: the default label
     * used to be created lazily by the first configuration write, opening the page writes nothing
     * any more, and a teacher may not create labels - so the form had no label to offer and could
     * never be saved. The installer creates it now; setUp() is bypassed here by deleting the one it
     * made and re-running the installer's code path.
     */
    public function test_a_teacher_on_a_fresh_site_can_save(): void {
        global $CFG, $DB;

        $DB->delete_records('local_stackmathgame_label');
        require_once($CFG->dirroot . '/local/stackmathgame/db/install.php');
        xmldb_local_stackmathgame_install();
        $this->assertTrue($DB->record_exists('local_stackmathgame_label', ['name' => 'default', 'status' => 1]));

        $teacher = $this->getDataGenerator()->create_and_enrol(get_course($this->cm->course), 'editingteacher');
        $this->setUser($teacher);
        $this->assertFalse(has_capability('local/stackmathgame:managelabels', $this->context));

        $record = quiz_settings_service::save((int)$this->cm->id, $this->data(), $this->context);

        $this->assertSame(
            (int)$DB->get_field('local_stackmathgame_label', 'id', ['name' => 'default']),
            (int)$record->labelid
        );
        // No label was created by the teacher's save.
        $this->assertSame(1, $DB->count_records('local_stackmathgame_label'));
    }

    /**
     * An unchanged label is checked again: archived or deleted since, it is refused.
     */
    public function test_an_unchanged_label_is_rechecked(): void {
        global $DB;

        $cmid = (int)$this->cm->id;
        $saved = quiz_settings_service::save($cmid, $this->data(), $this->context);
        $DB->set_field('local_stackmathgame_label', 'status', 0, ['id' => $saved->labelid]);

        $errors = quiz_settings_service::validate($cmid, $this->data(['labelid' => (int)$saved->labelid]), $this->context);
        $this->assertArrayHasKey('labelid', $errors);

        $DB->set_field('local_stackmathgame_label', 'status', 1, ['id' => $saved->labelid]);
        $DB->set_field('local_stackmathgame_label', 'timedeleted', time(), ['id' => $saved->labelid]);
        $errors = quiz_settings_service::validate($cmid, $this->data(['labelid' => (int)$saved->labelid]), $this->context);
        $this->assertArrayHasKey('labelid', $errors);

        $DB->delete_records('local_stackmathgame_label', ['id' => $saved->labelid]);
        $errors = quiz_settings_service::validate($cmid, $this->data(['labelid' => (int)$saved->labelid]), $this->context);
        $this->assertArrayHasKey('labelid', $errors);
    }

    /**
     * Keep, clear and invalid design values are three different things.
     */
    public function test_design_keep_clear_and_invalid_are_distinguished(): void {
        $this->assertSame([quiz_settings_service::DESIGN_KEEP, 0], quiz_settings_service::design_intent([]));
        $this->assertSame([quiz_settings_service::DESIGN_KEEP, 0], quiz_settings_service::design_intent(['designid' => '']));
        $this->assertSame([quiz_settings_service::DESIGN_KEEP, 0], quiz_settings_service::design_intent(['designid' => null]));
        $this->assertSame([quiz_settings_service::DESIGN_CLEAR, 0], quiz_settings_service::design_intent(['designid' => 0]));
        $this->assertSame([quiz_settings_service::DESIGN_CLEAR, 0], quiz_settings_service::design_intent(['designid' => '0']));
        $this->assertSame([quiz_settings_service::DESIGN_SET, 7], quiz_settings_service::design_intent(['designid' => '7']));
        $this->assertSame([quiz_settings_service::DESIGN_INVALID, 0], quiz_settings_service::design_intent(['designid' => -3]));
        $this->assertSame([quiz_settings_service::DESIGN_INVALID, 0], quiz_settings_service::design_intent(['designid' => 'abc']));

        $cmid = (int)$this->cm->id;
        $designid = theme_manager::ensure_default_design();
        quiz_settings_service::save($cmid, $this->data(['designid' => $designid]), $this->context);

        // A negative id used to keep the old design silently; it is a field error now.
        $errors = quiz_settings_service::validate($cmid, $this->data(['designid' => -1]), $this->context);
        $this->assertArrayHasKey('designid_group', $errors);

        // Absent keeps it.
        $data = $this->data();
        unset($data['designid']);
        $this->assertSame($designid, (int)quiz_settings_service::save($cmid, $data, $this->context)->designid);

        // 0 clears it for a draft ...
        $this->assertSame(0, (int)quiz_settings_service::save($cmid, $this->data(['designid' => 0]), $this->context)->designid);
        // ... and is refused for a running game.
        $errors = quiz_settings_service::validate($cmid, $this->data(['designid' => 0, 'enabled' => 1]), $this->context);
        $this->assertArrayHasKey('designid_group', $errors);
    }

    /**
     * The first save stores what was submitted, not the defaults ensure_default() would add.
     */
    public function test_the_first_save_stores_a_draft_without_a_design(): void {
        $cmid = (int)$this->cm->id;
        $this->assertNull(quiz_configurator::get_plugin_config($cmid));

        $record = quiz_settings_service::save($cmid, $this->data(['designid' => 0]), $this->context);

        $this->assertSame(0, (int)$record->designid, 'The first save filled in the default design.');
        $this->assertSame(1, (int)$record->requiresbehaviour);
    }

    /**
     * The service checks the capability itself; the settings page is not the boundary.
     */
    public function test_the_service_checks_the_capability(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_and_enrol(get_course($this->cm->course), 'student');
        $this->setUser($student);

        try {
            quiz_settings_service::save((int)$this->cm->id, $this->data(['enabled' => 0]), $this->context);
            $this->fail('A student saved game settings.');
        } catch (\required_capability_exception $e) {
            $this->assertFalse($DB->record_exists('local_stackmathgame', ['cmid' => $this->cm->id]));
        }
        $this->expectException(\required_capability_exception::class);
        quiz_settings_service::validate((int)$this->cm->id, $this->data(), $this->context);
    }

    /**
     * A context that is not the activity's is refused, whatever the caller may do there.
     */
    public function test_a_context_of_another_activity_is_refused(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_module('quiz', ['course' => $this->cm->course]);

        $contexts = [
            \context_module::instance($other->cmid),
            \context_course::instance($this->cm->course),
            \context_system::instance(),
        ];
        foreach ($contexts as $context) {
            try {
                quiz_settings_service::save((int)$this->cm->id, $this->data(), $context);
                $this->fail('A save in a foreign context was accepted.');
            } catch (\moodle_exception $e) {
                $this->assertSame('err_contextmismatch', $e->errorcode);
            }
        }
        $this->assertFalse($DB->record_exists('local_stackmathgame', ['cmid' => $this->cm->id]));
    }

    /**
     * A failure after the label insert leaves neither a configuration nor an unassigned label.
     */
    public function test_a_failure_after_the_label_insert_leaves_nothing(): void {
        global $DB;

        // The advanced_testcase runs each test inside a transaction of its own; the service's
        // transaction would then only be a nested one, whose rollback undoes nothing until the
        // test ends. Without it the rollback is real, as it is on a site.
        $this->preventResetByRollback();

        $service = new class extends quiz_settings_service {
            /**
             * Fail the configuration write, after the label was inserted.
             *
             * @param int $cmid The course-module ID.
             * @param array $values The values.
             * @param int $userid The user.
             * @return \stdClass Never.
             */
            protected static function write_config(int $cmid, array $values, int $userid): \stdClass {
                global $DB;
                // The label is there at this point - inside the transaction.
                if (!$DB->record_exists('local_stackmathgame_label', ['name' => 'Brand new world'])) {
                    throw new \coding_exception('The label should exist inside the transaction.');
                }
                throw new \dml_write_exception('injected failure after the label insert');
            }
        };
        $labels = $DB->count_records('local_stackmathgame_label');

        try {
            $service::save((int)$this->cm->id, $this->data(['newlabel' => 'Brand new world']), $this->context);
            $this->fail('The injected failure did not surface.');
        } catch (\dml_write_exception $e) {
            $this->assertStringContainsString('injected failure', $e->getMessage());
        }

        $this->assertSame($labels, $DB->count_records('local_stackmathgame_label'));
        $this->assertFalse($DB->record_exists('local_stackmathgame_label', ['name' => 'Brand new world']));
        $this->assertFalse($DB->record_exists('local_stackmathgame', ['cmid' => $this->cm->id]));
    }

    /**
     * The retry after a lost race validates again before it writes.
     *
     * The race is simulated: the first write loses on a unique index, and by the time of the retry
     * the other request has made the submitted label invalid. Only a second validation can notice.
     */
    public function test_the_retry_validates_again(): void {
        global $DB;

        $this->preventResetByRollback();

        $service = new class extends quiz_settings_service {
            /** @var int How often the configuration write was attempted. */
            public static int $writes = 0;

            /** @var int How often the settings were validated. */
            public static int $validations = 0;

            /**
             * Validate; after the lost race the label has gone.
             *
             * @param int $cmid The course-module ID.
             * @param array $data The data.
             * @param \context $context The context.
             * @return array The errors.
             */
            public static function validate(int $cmid, array $data, \context $context): array {
                self::$validations++;
                $errors = parent::validate($cmid, $data, $context);
                if (self::$writes > 0) {
                    $errors['labelid'] = 'archived by the concurrent request';
                }
                return $errors;
            }

            /**
             * Lose the first race.
             *
             * @param int $cmid The course-module ID.
             * @param array $values The values.
             * @param int $userid The user.
             * @return \stdClass The saved record.
             */
            protected static function write_config(int $cmid, array $values, int $userid): \stdClass {
                self::$writes++;
                throw new \dml_write_exception('lost the race');
            }
        };

        try {
            $service::save((int)$this->cm->id, $this->data(), $this->context);
            $this->fail('The retry wrote although the data had become invalid meanwhile.');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_settingsinvalid', $e->errorcode);
        }
        $this->assertSame(2, $service::$validations, 'The retry did not validate again.');
        $this->assertSame(1, $service::$writes, 'The retry wrote without validating again.');
        $this->assertFalse($DB->record_exists('local_stackmathgame', ['cmid' => $this->cm->id]));
    }

    /**
     * The design picker loads a bounded list and never hides the current design.
     */
    public function test_designs_are_loaded_bounded(): void {
        global $DB;

        $template = $DB->get_record('local_stackmathgame_design', ['id' => theme_manager::ensure_default_design()]);
        $ids = [];
        for ($i = 0; $i < theme_manager::PICKER_LIMIT + 5; $i++) {
            $copy = clone $template;
            unset($copy->id);
            $copy->name = sprintf('Design %03d', $i);
            $copy->slug = 'bounded_' . $i;
            $ids[] = (int)$DB->insert_record('local_stackmathgame_design', $copy);
        }
        // Sorts last by name, so it is outside the first page.
        $last = end($ids);
        $DB->set_field('local_stackmathgame_design', 'name', 'ZZZ last', ['id' => $last]);

        [$designs, $more] = theme_manager::get_selectable();
        $this->assertCount(theme_manager::PICKER_LIMIT, $designs);
        $this->assertTrue($more);
        $this->assertNotContains($last, array_map(static fn($d) => (int)$d->id, $designs));

        [$designs] = theme_manager::get_selectable($last);
        $this->assertContains($last, array_map(static fn($d) => (int)$d->id, $designs));
        $this->assertCount(theme_manager::PICKER_LIMIT + 1, $designs);
    }
}
