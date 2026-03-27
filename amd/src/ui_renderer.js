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
 * UI renderer for the STACK Math Game runtime shell.
 *
 * @module     local_stackmathgame/ui_renderer
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Ensure the runtime shell card exists in the DOM.
     *
     * Creates the element if it does not already exist and inserts it
     * before the first question or at the top of the main region.
     *
     * @returns {Element} The shell element.
     */
    const ensureShell = function() {
        let shell = document.querySelector('.smg-runtime-shell');
        if (shell) {
            return shell;
        }
        shell = document.createElement('section');
        shell.className = 'smg-runtime-shell card mb-3';
        shell.innerHTML = '' +
            '<div class="card-body">' +
                '<div class="smg-runtime-header d-flex ' +
                'justify-content-between align-items-center mb-2">' +
                    '<strong class="smg-runtime-title">STACK Math Game</strong>' +
                    '<span class="badge text-bg-secondary smg-runtime-badge">runtime</span>' +
                '</div>' +
                '<div class="smg-runtime-status small text-muted mb-2"></div>' +
                '<div class="smg-runtime-profile mb-2"></div>' +
                '<div class="smg-runtime-narrative alert alert-info d-none"></div>' +
                '<div class="smg-runtime-next small text-muted mb-2"></div>' +
                '<div class="smg-runtime-actions btn-group btn-group-sm" role="group">' +
                    '<button type="button" class="btn btn-primary" ' +
                    'data-smg-action="check"></button>' +
                    '<button type="button" class="btn btn-outline-secondary" ' +
                    'data-smg-action="native"></button>' +
                '</div>' +
            '</div>';
        const firstQuestion = document.querySelector('.que')
            || document.querySelector('#region-main');
        if (firstQuestion && firstQuestion.parentNode) {
            firstQuestion.parentNode.insertBefore(shell, firstQuestion);
        } else {
            document.body.prepend(shell);
        }
        return shell;
    };

    /**
     * Update the shell UI to reflect the provided game state.
     *
     * @param {Object} state The current game state object.
     * @param {boolean} state.loading Whether the game layer is loading.
     * @param {string}  [state.error] An error message, if any.
     * @param {Object}  [state.lastSubmit] Last submit result.
     * @param {Object}  [state.profile] Current profile data.
     * @param {Object}  [state.design] Current design data.
     * @param {Array}   [state.narrativeLines] Narrative lines to display.
     * @param {string}  [state.nextNodeDescription] Next node description.
     * @param {Object}  [state.strings] Localised string map.
     * @returns {void}
     */
    const render = function(state) {
        const shell = ensureShell();
        const strings = state.strings || {};
        shell.querySelector('[data-smg-action="check"]').textContent =
            strings.check || 'Game check';
        shell.querySelector('[data-smg-action="native"]').textContent =
            strings.native || 'Use native controls';

        const statusEl    = shell.querySelector('.smg-runtime-status');
        const profileEl   = shell.querySelector('.smg-runtime-profile');
        const narrativeEl = shell.querySelector('.smg-runtime-narrative');
        const nextEl      = shell.querySelector('.smg-runtime-next');

        if (state.loading) {
            statusEl.textContent = strings.loading || 'Loading game layer...';
        } else if (state.error) {
            statusEl.textContent = (strings.error || 'Runtime error') + ': ' + state.error;
        } else if (state.lastSubmit && state.lastSubmit.message) {
            statusEl.textContent = state.lastSubmit.message;
        } else {
            statusEl.textContent = strings.ready || 'Game runtime ready';
        }

        const profiledata = state.profile || {};
        const designName = (state.design && state.design.name) ? state.design.name : '—';
        profileEl.innerHTML = '' +
            '<div><strong>' + (strings.profile || 'Profile') + '</strong></div>' +
            '<div>Score: ' + (profiledata.score || 0) +
            ' · XP: ' + (profiledata.xp || 0) +
            ' · Level: ' + (profiledata.levelno || 1) + '</div>' +
            '<div>' + (strings.design || 'Design') + ': ' + designName + '</div>';

        const lines = state.narrativeLines || [];
        if (lines.length) {
            narrativeEl.classList.remove('d-none');
            narrativeEl.innerHTML = lines.map(function(line) {
                return '<div>' + String(line)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;') + '</div>';
            }).join('');
        } else {
            narrativeEl.classList.add('d-none');
            narrativeEl.innerHTML = '';
        }

        if (state.nextNodeDescription) {
            nextEl.textContent =
                (strings.nextnode || 'Next node') + ': ' + state.nextNodeDescription;
        } else {
            nextEl.textContent = '';
        }
    };

    return {
        ensureShell: ensureShell,
        render: render
    };
});
