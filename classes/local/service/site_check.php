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

namespace local_stackmathgame\local\service;

/**
 * Checks on the site around the game that the game itself cannot fix.
 *
 * The game loads nothing from elsewhere. Moodle's MathJax filter, which typesets every formula in
 * a STACK question, does by default: it fetches MathJax from a public CDN. That is a site setting
 * and not the plugin's to change behind an administrator's back - but it is the plugin's to point
 * out, where the game is set up.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_check {
    /**
     * Return the external host MathJax is loaded from, if it is loaded from one.
     *
     * @return string|null The host, or null when the filter is off or MathJax is served locally.
     */
    public static function mathjax_external_host(): ?string {
        global $CFG, $DB;

        // Read directly rather than through filter_get_globally_enabled(), whose request cache
        // would keep reporting a filter the administrator has just switched off.
        $state = $DB->get_field('filter_active', 'active', [
            'filter' => 'mathjaxloader',
            'contextid' => \context_system::instance()->id,
        ]);
        if ($state === false || (int)$state === TEXTFILTER_DISABLED) {
            return null;
        }
        $url = (string)get_config('filter_mathjaxloader', 'httpsurl');
        if ($url === '') {
            return null;
        }
        // A relative URL is served by this site.
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }
        $sitehost = parse_url($CFG->wwwroot, PHP_URL_HOST);
        return strcasecmp($host, (string)$sitehost) === 0 ? null : $host;
    }
}
