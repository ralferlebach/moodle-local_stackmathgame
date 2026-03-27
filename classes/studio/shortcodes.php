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
 * Studio shortcode renderer for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\studio;

/**
 * Minimal shortcode parser for studio-authored narrative text.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortcodes {
    /**
     * Replace double-brace variables in a template string.
     *
     * @param string $text Text containing {{key}} placeholders.
     * @param array  $vars Key-value replacement pairs.
     * @return string The text with placeholders replaced.
     */
    public static function render(string $text, array $vars = []): string {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string)$value, $text);
        }
        return $text;
    }
}
