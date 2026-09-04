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
use local_stackmathgame\form\studio\design_edit_form;

/**
 * The design editor's precedence rule between structured fields and the raw JSON fallback.
 *
 * This is the part that is easy to build the wrong way round, so it is pinned down here rather
 * than left to a browser test: whichever half runs last would otherwise win silently, and a
 * designer would lose an edit without any error.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\form\studio\design_edit_form
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class design_edit_form_test extends advanced_testcase {
    /** A stored narrative with two scenes. */
    private const NARRATIVE = '{"world_enter":["Welcome."],"victory":["Well done.","Onwards!"]}';

    /**
     * Build submitted form data around the stored baselines.
     *
     * @param array $overrides Fields to set or replace.
     * @return \stdClass The submitted data.
     */
    private function submission(array $overrides = []): \stdClass {
        $data = [
            'modecomponent' => 'stackmathgamemode_rpg',
            'narrativejson' => self::NARRATIVE,
            'narrativejson_baseline' => self::NARRATIVE,
            'uijson' => '{"theme":"rpg_default"}',
            'uijson_baseline' => '{"theme":"rpg_default"}',
            'mechanicsjson' => '{"mode":"rpg","version":1}',
            'mechanicsjson_baseline' => '{"mode":"rpg","version":1}',
            'uitheme' => 'rpg_default',
            'narrative_world_enter' => 'Welcome.',
            'narrative_victory' => "Well done.\nOnwards!",
        ];
        return (object)array_merge($data, $overrides);
    }

    /**
     * A stored design fills the per-scene fields, one line per entry.
     */
    public function test_design_fills_the_scene_fields(): void {
        $values = design_edit_form::design_to_form((object)[
            'slug' => 'rpg_default',
            'narrativejson' => self::NARRATIVE,
            'uijson' => '{"theme":"rpg_default"}',
            'mechanicsjson' => '{"mode":"rpg","version":1}',
        ]);

        $this->assertSame('Welcome.', $values['narrative_world_enter']);
        $this->assertSame("Well done.\nOnwards!", $values['narrative_victory']);
        $this->assertSame('rpg_default', $values['uitheme']);
        // The baselines are what makes the precedence decision possible at all.
        $this->assertSame(self::NARRATIVE, $values['narrativejson_baseline']);
    }

    /**
     * With the raw JSON untouched, the scene fields are what gets stored.
     */
    public function test_untouched_json_is_rebuilt_from_the_fields(): void {
        $result = design_edit_form::form_to_design($this->submission([
            'narrative_victory' => 'Changed in the field.',
        ]));

        $narrative = json_decode($result['narrativejson'], true);
        $this->assertSame(['Changed in the field.'], $narrative['victory']);
    }

    /**
     * An edited raw JSON wins over the scene fields.
     */
    public function test_edited_json_wins(): void {
        $result = design_edit_form::form_to_design($this->submission([
            'narrativejson' => '{"victory":["Changed in the JSON."]}',
            'narrative_victory' => 'Changed in the field too.',
        ]));

        $narrative = json_decode($result['narrativejson'], true);
        $this->assertSame(['Changed in the JSON.'], $narrative['victory']);
        $this->assertArrayNotHasKey('world_enter', $narrative, 'The edited JSON was merged rather than obeyed.');
    }

    /**
     * Reformatting the JSON without changing its meaning is not an edit.
     *
     * Otherwise merely opening the collapsed section and saving would hand the JSON a precedence
     * the designer never asked for, and quietly discard their field edits.
     */
    public function test_reformatting_is_not_an_edit(): void {
        $result = design_edit_form::form_to_design($this->submission([
            'narrativejson' => json_encode(json_decode(self::NARRATIVE, true), JSON_PRETTY_PRINT),
            'narrative_victory' => 'The field should still win.',
        ]));

        $narrative = json_decode($result['narrativejson'], true);
        $this->assertSame(['The field should still win.'], $narrative['victory']);
    }

    /**
     * An empty scene field removes the scene rather than storing an empty list.
     */
    public function test_clearing_a_scene_removes_it(): void {
        $result = design_edit_form::form_to_design($this->submission([
            'narrative_victory' => '   ',
        ]));

        $this->assertArrayNotHasKey('victory', json_decode($result['narrativejson'], true));
    }

    /**
     * Keys the form does not model survive a save.
     *
     * An imported package may carry anything in uijson; dropping it because this form has no
     * control for it would destroy work the designer never touched.
     */
    public function test_unmodelled_keys_survive(): void {
        $result = design_edit_form::form_to_design($this->submission([
            'uijson' => '{"theme":"rpg_default","palette":"dark"}',
            'uijson_baseline' => '{"theme":"rpg_default","palette":"dark"}',
            'uitheme' => 'exitgames_default',
        ]));

        $ui = json_decode($result['uijson'], true);
        $this->assertSame('exitgames_default', $ui['theme'], 'The theme selection was ignored.');
        $this->assertSame('dark', $ui['palette'], 'An unmodelled key was dropped.');
    }

    /**
     * Mechanics are derived from the mode component, never typed.
     */
    public function test_mechanics_follow_the_mode_component(): void {
        $result = design_edit_form::form_to_design($this->submission([
            'modecomponent' => 'stackmathgamemode_wisewizzard',
        ]));

        $mechanics = json_decode($result['mechanicsjson'], true);
        $this->assertSame('wisewizzard', $mechanics['mode']);
        $this->assertSame(1, $mechanics['version']);
    }

    /**
     * Invalid JSON in the raw field counts as an edit and is handed on unchanged.
     *
     * The form's own validation reports it; silently replacing it with rebuilt JSON would throw
     * away what the designer typed and hide the mistake.
     */
    public function test_invalid_json_is_treated_as_an_edit(): void {
        $result = design_edit_form::form_to_design($this->submission([
            'narrativejson' => '{ this is not json',
        ]));

        $this->assertSame('{ this is not json', $result['narrativejson']);
    }
}
