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
 * Injects a Game Settings option into the quiz tertiary navigation selector.
 *
 * Modelled after local_stackmatheditor/configure.php integration.
 * The option is only added once; duplicate injections are guarded by a
 * data-smg attribute on the <option> element.
 *
 * @module     local_stackmathgame/tertiary_nav
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Find the quiz tertiary navigation <select> element.
     *
     * Moodle renders it inside .tertiary-navigation .urlselect select.
     *
     * @returns {Element|null} The select element, or null if not found.
     */
    function findSelect() {
        return document.querySelector(
            '.tertiary-navigation .urlselect select'
        );
    }

    /**
     * Inject the Game Settings option into the tertiary nav selector.
     *
     * @param {Object} config Module configuration.
     * @param {number} config.cmid  The course-module ID.
     * @param {string} config.label The label text for the menu option.
     * @param {string} config.url   The full URL to navigate to.
     * @returns {void}
     */
    function inject(config) {
        const select = findSelect();
        if (!select) {
            return;
        }
        // Guard: do not add the option twice.
        const existing = select.querySelector('option[data-smg="quiz"]');
        if (existing) {
            return;
        }
        const option = document.createElement('option');
        option.value = config.url;
        option.textContent = config.label;
        option.setAttribute('data-smg', 'quiz');
        select.appendChild(option);
    }

    /**
     * Initialise the tertiary nav injection.
     *
     * Waits for the DOM to be ready before injecting the option.
     * The function is called by PHP via $PAGE->requires->js_call_amd().
     *
     * @param {Object} config Module configuration from PHP.
     * @param {number} config.cmid  The course-module ID.
     * @param {string} config.label The label text for the menu option.
     * @param {string} config.url   The target URL for the option.
     * @returns {void}
     */
    function init(config) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                inject(config);
            });
        } else {
            inject(config);
        }
    }

    return {init: init};
});
