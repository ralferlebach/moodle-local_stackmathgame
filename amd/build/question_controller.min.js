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
 * Question controller helpers for STACK Math Game.
 *
 * @module     local_stackmathgame/question_controller
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Return the current quiz attempt id from the URL.
     *
     * @returns {number} The attempt id or 0 if absent.
     */
    const getAttemptId = function() {
        const params = new URLSearchParams(window.location.search);
        return parseInt(params.get('attempt') || '0', 10);
    };

    /**
     * Return the controlled question element or the first question on the page.
     *
     * @returns {Element|null} The question DOM element.
     */
    const getCurrentQuestion = function() {
        return document.querySelector('.que[data-smg-controlled="1"]')
            || document.querySelector('.que');
    };

    /**
     * Return the slot number of the current question.
     *
     * @returns {number} The slot number or 0.
     */
    const getCurrentSlot = function() {
        const question = getCurrentQuestion();
        if (!question) {
            return 0;
        }
        return parseInt(question.getAttribute('data-smg-slot') || '0', 10);
    };

    /**
     * Collect all answer field values from the current question.
     *
     * @returns {Array<{name: string, value: string}>} Array of name/value pairs.
     */
    const collectAnswers = function() {
        const question = getCurrentQuestion();
        if (!question) {
            return [];
        }
        const fields = question.querySelectorAll('input, textarea, select');
        const answers = [];
        fields.forEach(function(field) {
            if (!field.name || field.disabled || field.type === 'hidden') {
                return;
            }
            if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                return;
            }
            answers.push({name: field.name, value: field.value});
        });
        return answers;
    };

    /**
     * Programmatically click the native quiz submit button.
     *
     * Looks for a submit button inside .smg-native-controls or .submitbtns.
     *
     * @returns {boolean} True when a button was found and clicked.
     */
    const triggerNativeSubmit = function() {
        const selector = [
            '.smg-native-controls input[type="submit"]',
            '.smg-native-controls button[type="submit"]',
            '.submitbtns input[type="submit"]',
            '.submitbtns button[type="submit"]'
        ].join(', ');
        const submit = document.querySelector(selector);
        if (submit) {
            submit.click();
            return true;
        }
        return false;
    };

    return {
        getAttemptId: getAttemptId,
        getCurrentSlot: getCurrentSlot,
        collectAnswers: collectAnswers,
        triggerNativeSubmit: triggerNativeSubmit
    };
});
