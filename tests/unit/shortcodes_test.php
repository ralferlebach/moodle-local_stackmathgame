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
use local_stackmathgame\shortcodes;

/**
 * The [smg…] shortcodes teachers put into Moodle text areas.
 *
 * They run inside filter_shortcodes on arbitrary content, so the property that matters most is
 * that a bad or absent argument produces something harmless rather than a warning or a stack
 * trace in the middle of a course page.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\shortcodes
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcodes_test extends advanced_testcase {
    /** @var object A minimal shortcode environment. */
    private object $env;

    /**
     * Set up a user and an empty environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->env = (object)['context' => \context_system::instance()];
    }

    /**
     * Call a shortcode handler the way filter_shortcodes does.
     *
     * @param string $name The handler name.
     * @param array $args The shortcode arguments.
     * @return string The rendered output.
     */
    private function render(string $name, array $args = []): string {
        return shortcodes::$name($name, $args, null, $this->env, static fn(): string => '');
    }

    /**
     * Every handler returns a string for a user with no profile at all.
     *
     * This is the realistic case on a course page: the shortcode is in the text before anyone has
     * played. Returning null or emitting a notice there would surface as broken page output.
     */
    public function test_every_shortcode_survives_a_missing_profile(): void {
        foreach (['score', 'xp', 'level', 'progress', 'narrative', 'avatar', 'leaderboard'] as $name) {
            $out = $this->render($name);
            $this->assertIsString($out, "[$name] did not return a string.");
        }
    }

    /**
     * Numeric shortcodes fall back to zero rather than to an empty string.
     *
     * "0" reads as a value; "" reads as a broken shortcode, and a teacher cannot tell the
     * difference from the rendered page.
     */
    public function test_numeric_shortcodes_default_to_zero(): void {
        $this->assertSame('0', $this->render('score'));
        $this->assertSame('0', $this->render('xp'));
    }

    /**
     * An unknown profile field is refused rather than rendered as a property lookup.
     */
    public function test_unknown_field_is_not_rendered(): void {
        $out = $this->render('score', ['field' => 'no_such_field']);

        $this->assertIsString($out);
        $this->assertStringNotContainsString('no_such_field', $out);
    }

    /**
     * An unknown label produces empty output rather than an error.
     */
    public function test_unknown_label_is_harmless(): void {
        foreach (['score', 'xp', 'level', 'progress'] as $name) {
            $out = $this->render($name, ['label' => 'there-is-no-such-label']);
            $this->assertIsString($out, "[$name] failed on an unknown label.");
        }
    }

    /**
     * The shortcodes are registered so filter_shortcodes can find them.
     *
     * A handler nobody can reach is the same as no handler, and the registration lives in a
     * separate file from the code it points at.
     */
    public function test_handlers_are_registered(): void {
        global $CFG;

        $file = $CFG->dirroot . '/local/stackmathgame/db/shortcodes.php';
        $this->assertFileExists($file);

        $shortcodes = [];
        require($file);

        $this->assertNotEmpty($shortcodes, 'No shortcodes are registered.');
        foreach ($shortcodes as $name => $definition) {
            $callback = $definition['callback'] ?? null;
            $this->assertNotNull($callback, "[$name] has no callback.");
            $this->assertTrue(
                is_callable($callback),
                "[$name] points at a callback that does not exist: "
                    . (is_string($callback) ? $callback : gettype($callback))
            );
        }
    }
}
