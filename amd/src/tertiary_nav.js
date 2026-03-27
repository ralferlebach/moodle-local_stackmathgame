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
 * Modelled after local_stackmatheditor: the option is added to the existing
 * <select> inside .tertiary-navigation .urlselect. The select form POSTs to
 * course/jumpto.php; a full absolute URL in the option value is accepted.
 *
 * Injection is attempted immediately and, as a fallback after 300 ms, to
 * handle cases where the tertiary nav is rendered by a deferred Mustache call.
 *
 * @module     local_stackmathgame/tertiary_nav
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Selector for the tertiary navigation <select> element.
     *
     * Moodle renders it as: .tertiary-navigation .urlselect select
     *
     * @type {string}
     */
    var SELECT_SELECTOR = '.tertiary-navigation .urlselect select';

    /**
     * Attribute used to mark our injected option (prevents duplicates).
     *
     * @type {string}
     */
    var DATA_ATTR = 'data-smg';

    /**
     * Attempt to inject the Game Settings option into the select element.
     *
     * Does nothing if the select is not yet in the DOM or the option
     * has already been added.
     *
     * @param {string} url   Full URL for the quiz_settings.php page.
     * @param {string} label Display label for the option.
     * @returns {boolean} True when the option was injected successfully.
     */
    function tryInject(url, label) {
        var select = document.querySelector(SELECT_SELECTOR);
        if (!select) {
            return false;
        }
        // Guard: do not add the option twice.
        if (select.querySelector('option[' + DATA_ATTR + '="quiz"]')) {
            return true;
        }
        var option = document.createElement('option');
        option.value = url;
        option.textContent = label;
        option.setAttribute(DATA_ATTR, 'quiz');
        select.appendChild(option);
        return true;
    }

    /**
     * Initialise the tertiary nav injection.
     *
     * Called by PHP via $PAGE->requires->js_call_amd().
     * Tries to inject immediately; if the select is not yet in the DOM
     * (e.g. rendered by a deferred Mustache template), retries after 300 ms.
     *
     * @param {Object} config Configuration object passed from PHP.
     * @param {number} config.cmid  The course-module ID (unused in URL, for reference).
     * @param {string} config.label Label text to show in the dropdown.
     * @param {string} config.url   Absolute URL to navigate to on selection.
     * @returns {void}
     */
    function init(config) {
        var url   = config.url;
        var label = config.label;

        // Attempt 1: immediate (works when select is already in DOM).
        if (tryInject(url, label)) {
            return;
        }

        // Attempt 2: after DOMContentLoaded (for inline scripts before DOM ready).
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                tryInject(url, label);
            });
            return;
        }

        // Attempt 3: 300 ms timeout fallback for deferred Mustache renders.
        setTimeout(function() {
            tryInject(url, label);
        }, 300);

        // Attempt 4: 1 s final fallback.
        setTimeout(function() {
            tryInject(url, label);
        }, 1000);
    }

    return {init: init};
});
