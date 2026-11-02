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
 * Server-side search for the label selector on the game settings page.
 *
 * Transport for core/form-autocomplete: labels are site-wide and can be many, so they are
 * fetched page by page as the teacher types rather than shipped with the page.
 *
 * @module     local_stackmathgame/label_search
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    'use strict';

    return {
        /**
         * Fetch matching labels.
         *
         * @param {string} selector The select element's selector.
         * @param {string} query What the teacher typed.
         * @param {Function} success Called with the results.
         * @param {Function} failure Called on error.
         * @returns {void}
         */
        transport: function(selector, query, success, failure) {
            var select = document.querySelector(selector);
            var cmid = select ? parseInt(select.getAttribute('data-cmid') || '0', 10) : 0;
            Ajax.call([{
                methodname: 'local_stackmathgame_search_labels',
                args: {cmid: cmid, query: query}
            }])[0].then(success).catch(failure);
        },

        /**
         * Turn the response into autocomplete options.
         *
         * @param {string} selector The select element's selector.
         * @param {Object} response The web service response.
         * @returns {Array} The options.
         */
        processResults: function(selector, response) {
            return (response.labels || []).map(function(label) {
                return {value: label.id, label: label.name};
            });
        }
    };
});
