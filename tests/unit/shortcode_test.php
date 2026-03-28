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

namespace local_stackmathgame\tests\unit;

use advanced_testcase;
use local_stackmathgame\shortcodes;
use local_stackmathgame\local\service\narrative_resolver;

/**
 * Tests for local_stackmathgame shortcode callbacks.
 *
 * All shortcodes must return empty string or a safe default (not throw)
 * when context or profile is unavailable. Tests use a minimal stub env.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\shortcodes
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcode_test extends advanced_testcase {
    /**
     * Return a stub shortcode env with no context.
     *
     * @return object
     */
    private function env(): object {
        return (object)['context' => null];
    }

    /**
     * Return a pass-through next-filter callable.
     *
     * @return callable
     */
    private function next(): callable {
        return static fn(?string $c): string => (string)$c;
    }

    /**
     * smgscore returns '0' when no profile can be resolved.
     */
    public function test_score_without_context(): void {
        $this->assertSame('0', shortcodes::score('smgscore', [], null, $this->env(), $this->next()));
    }

    /**
     * smgxp returns '0' when no profile can be resolved.
     */
    public function test_xp_without_context(): void {
        $this->assertSame('0', shortcodes::xp('smgxp', [], null, $this->env(), $this->next()));
    }

    /**
     * smglevel returns '0' when no profile can be resolved.
     */
    public function test_level_without_context(): void {
        $this->assertSame('0', shortcodes::level('smglevel', [], null, $this->env(), $this->next()));
    }

    /**
     * smgprogress returns '0%' when no profile can be resolved.
     */
    public function test_progress_without_context(): void {
        $this->assertSame('', shortcodes::progress('smgprogress', [], null, $this->env(), $this->next()));
    }

    /**
     * smgnarrative returns empty string when no design is resolvable.
     */
    public function test_narrative_without_design(): void {
        $result = shortcodes::narrative('smgnarrative', ['scene' => 'victory'], null, $this->env(), $this->next());
        $this->assertSame('', $result);
    }

    /**
     * smgnarrative returns inner content as fallback when no design but content given.
     */
    public function test_narrative_content_fallback(): void {
        $result = shortcodes::narrative('smgnarrative', ['scene' => 'victory'], 'Fallback', $this->env(), $this->next());
        $this->assertSame('Fallback', $result);
    }

    /**
     * smgnarrative without scene arg defaults to world_enter.
     */
    public function test_narrative_default_scene_is_world_enter(): void {
        $env = $this->env();
        $noarg   = shortcodes::narrative('smgnarrative', [], null, $env, $this->next());
        $explicit = shortcodes::narrative(
            'smgnarrative',
            ['scene' => narrative_resolver::SCENE_WORLD_ENTER],
            null,
            $env,
            $this->next()
        );
        $this->assertSame($noarg, $explicit);
    }

    /**
     * smgprogress with explicit field arg returns empty string when no profile.
     */
    public function test_progress_field_without_profile(): void {
        $result = shortcodes::progress('smgprogress', ['field' => 'solvedcount'], null, $this->env(), $this->next());
        $this->assertSame('', $result);
    }

    /**
     * smgscore and smgxp resolve a real profile when label arg is supplied.
     *
     * @group local_stackmathgame_db
     */
    public function test_score_and_xp_with_real_profile(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $labelid = (int)$DB->insert_record('local_stackmathgame_label', (object)[
            'name'        => 'testlabel',
            'idnumber'    => 'testlabel',
            'description' => '',
            'status'      => 1,
            'timecreated'  => time(),
            'timemodified' => time(),
            'createdby'   => (int)$user->id,
            'timedeleted'  => null,
        ]);
        $DB->insert_record('local_stackmathgame_profile', (object)[
            'userid'          => (int)$user->id,
            'labelid'         => $labelid,
            'score'           => 42,
            'xp'              => 100,
            'levelno'         => 2,
            'softcurrency'    => 0,
            'hardcurrency'    => 0,
            'avatarconfigjson' => '{}',
            'progressjson'    => '{}',
            'statsjson'       => '{}',
            'flagsjson'       => '{}',
            'lastquizid'      => null,
            'lastdesignid'    => null,
            'lastaccess'      => time(),
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $args  = ['label' => 'testlabel'];
        $score = shortcodes::score('smgscore', $args, null, $this->env(), $this->next());
        $xp    = shortcodes::xp('smgxp', $args, null, $this->env(), $this->next());

        $this->assertSame('42', $score);
        $this->assertSame('100', $xp);
    }
}
