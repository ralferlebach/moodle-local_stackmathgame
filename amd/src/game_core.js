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
 * Shared game-core helpers for local_stackmathgame mode subplugins.
 *
 * Provides the defaultConfig() helper that all mode subplugins use when
 * a questionmap row has no configjson, and any future shared utilities.
 *
 * All three game modules (stackmathgamemode_exitgames/game,
 * stackmathgamemode_wisewizzard/game, stackmathgamemode_rpg/game) depend
 * on this module via require(['local_stackmathgame/game_core']).
 *
 * @module     local_stackmathgame/game_core
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    /**
     * Return a safe default slot config matching slot_config_schema::defaults().
     *
     * Used by mode subplugins when a questionmap row has no configjson.
     *
     * @returns {Object} Default config with linear branching and empty narrative.
     */
    function defaultConfig() {
        return {
            version: 1,
            enabled: true,
            scene: {type: 'challenge'},
            branching: {
                gradedright: {mode: 'linear'},
                gradedwrong: {mode: 'linear'},
                complete:    {mode: 'linear'},
                default:     {mode: 'linear'}
            },
            rewards: {
                score: 0,
                xp: 0,
                achievementkeys: [],
                badgeids: [],
                stash: []
            },
            narrative: {intro: '', success: '', fail: ''},
            display: {showxp: true, showinventory: false, showavatar: false}
        };
    }

    /**
     * Read the navigation the server resolved.
     *
     * A mode calls this and renders the result. It must not look at cfg.branching itself: the
     * server resolver is canonical, and a second interpretation in JavaScript is exactly the
     * defect this replaced - the modes only handled an explicit `slot` jump, so `linear`, which
     * is the default every auto-created slot gets, and `end` produced no way forward at all.
     *
     * Tolerates a missing navigation block so a mode running against an older server payload
     * degrades to "no control shown" rather than throwing.
     *
     * @param {Object} source A submit response or a prefetch response.
     * @returns {{action: string, hasNext: boolean, isEnd: boolean, url: string, label: string,
     *   nextslot: number}} The resolved navigation.
     */
    function navigationFrom(source) {
        var nav = (source && source.navigation) || {};
        var action = nav.action || 'stay';
        return {
            action: action,
            hasNext: action === 'continue' || action === 'finish',
            isEnd: action === 'finish',
            url: nav.url || '',
            label: nav.label || '',
            nextslot: parseInt(nav.nextslot, 10) || 0
        };
    }

    /**
     * Apply a resolved navigation to a link element.
     *
     * Centralised so the three modes cannot drift apart on what "no next step" looks like. They
     * previously disagreed: one hid the button, one left it pointing at the current page.
     *
     * @param {Element} element The anchor or button to update.
     * @param {Object} navigation A value returned by navigationFrom().
     * @returns {void}
     */
    function applyNavigation(element, navigation) {
        if (!element) {
            return;
        }
        if (!navigation.hasNext || !navigation.url) {
            element.style.display = 'none';
            element.removeAttribute('href');
            return;
        }
        element.href = navigation.url;
        if (navigation.label) {
            element.textContent = navigation.label;
        }
        // The end of a run is a different act from continuing, and a mode may want to style it
        // differently without having to work out which case it is in.
        element.classList.toggle('smg-nav-finish', navigation.isEnd);
        element.style.display = 'inline-block';
    }

    /**
     * Escape a value for safe insertion as HTML.
     *
     * Narrative text is authored content that reaches the DOM through innerHTML in every mode.
     * Escaping belongs here rather than in each mode, where it was simply absent.
     *
     * @param {*} value The value to escape.
     * @returns {string} The escaped string.
     */
    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    return {
        defaultConfig: defaultConfig,
        navigationFrom: navigationFrom,
        applyNavigation: applyNavigation,
        escapeHtml: escapeHtml
    };
});
