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

use local_stackmathgame\local\service\site_check;

/**
 * Detecting MathJax loaded from outside the site.
 *
 * @package    local_stackmathgame
 * @covers     \local_stackmathgame\local\service\site_check
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_check_test extends \advanced_testcase {
    /**
     * A CDN is reported, a local copy and a disabled filter are not.
     */
    public function test_only_an_external_mathjax_is_reported(): void {
        global $CFG;
        $this->resetAfterTest();

        filter_set_global_state('mathjaxloader', TEXTFILTER_ON);

        set_config('httpsurl', 'https://cdn.jsdelivr.net/npm/mathjax@3.2.2/es5/tex-mml-chtml.js', 'filter_mathjaxloader');
        $this->assertSame('cdn.jsdelivr.net', site_check::mathjax_external_host());

        set_config('httpsurl', $CFG->wwwroot . '/lib/mathjax/tex-mml-chtml.js', 'filter_mathjaxloader');
        $this->assertNull(site_check::mathjax_external_host(), 'A copy on this site is not external');

        set_config('httpsurl', '/lib/mathjax/tex-mml-chtml.js', 'filter_mathjaxloader');
        $this->assertNull(site_check::mathjax_external_host(), 'A relative URL is served by this site');

        set_config('httpsurl', 'https://cdn.jsdelivr.net/npm/mathjax@3.2.2/es5/tex-mml-chtml.js', 'filter_mathjaxloader');
        filter_set_global_state('mathjaxloader', TEXTFILTER_DISABLED);
        $this->assertNull(site_check::mathjax_external_host(), 'A disabled filter loads nothing');
    }
}
