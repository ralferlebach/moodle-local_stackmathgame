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
 * ExitGame mode game module for local_stackmathgame.
 *
 * Implements adaptive quiz navigation with speech-bubble feedback.
 * On gradedright: jumps forward or to the boss slot.
 * On gradedwrong: branches to support questions.
 *
 * Based on alquiz.js (c) 2022 Malte Neugebauer, Hochschule Bochum (MIT Licence).
 * Adapted for the local_stackmathgame subplugin AMD module contract.
 *
 * Contract: export an init(gameState) function that returns an object with
 * an onAnswer(response, store) method.
 *
 * @module     stackmathgamemode_exitgames/game
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_stackmathgame/game_core'], function(GameCore) {

    'use strict';

    /**
     * Build a slot-keyed navigation map from the questionmap array.
     *
     * @param {Array} questionmap Array of questionmap objects from get_quiz_config.
     * @returns {Object} Map of slotnumber (string) to configjson object.
     */
    function buildSlotMap(questionmap) {
        var map = {};
        (questionmap || []).forEach(function(row) {
            var cfg = null;
            try {
                cfg = row.configjson ? JSON.parse(row.configjson) : null;
            } catch (e) {
                cfg = null;
            }
            map[String(row.slotnumber)] = cfg || GameCore.defaultConfig();
        });
        return map;
    }

    /**
     * Inject shared bubble and nav styles for ExitGame mode.
     *
     * @returns {void}
     */
    function injectStyles() {
        if (document.getElementById('smg-exitgame-styles')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'smg-exitgame-styles';
        style.textContent = [
            /* Speech bubble */
            '.smg-eg-bubble {',
            '  position: relative;',
            '  background: #fcefdc;',
            '  padding: 1.125em 1.5em;',
            '  font-size: 1.1em;',
            '  border-radius: 1rem;',
            '  box-shadow: 0 .125rem .5rem rgba(0,0,0,.3), 0 .0625rem .125rem rgba(0,0,0,.2);',
            '  margin-bottom: 1em;',
            '}',
            '.smg-eg-bubble::before {',
            '  content: "";',
            '  position: absolute;',
            '  width: 0;',
            '  height: 0;',
            '  top: 100%;',
            '  left: 1.5em;',
            '  border: .75rem solid transparent;',
            '  border-bottom: none;',
            '  border-top-color: #fcefdc;',
            '  filter: drop-shadow(0 .0625rem .0625rem rgba(0,0,0,.1));',
            '}',
            /* Navigation button */
            '.smg-eg-next {',
            '  margin-top: .5em;',
            '  display: inline-block;',
            '}',
        ].join('\n');
        document.head.appendChild(style);
    }

    /**
     * Build the speech bubble DOM element and insert it before the first question.
     *
     * @returns {Element} The bubble element.
     */
    function buildBubble() {
        var bubble = document.createElement('div');
        bubble.className = 'smg-eg-bubble';
        bubble.style.display = 'none';
        var first = document.querySelector('.que');
        if (first && first.parentNode) {
            first.parentNode.insertBefore(bubble, first);
        }
        return bubble;
    }

    /**
     * Show narrative text from the slot config in the bubble element.
     *
     * @param {Element}  bubble   The bubble DOM element.
     * @param {Object}   slotMap  Map of slot → configjson.
     * @param {number}   slot     Current slot number.
     * @param {string}   sceneKey Narrative key: 'intro', 'success', or 'fail'.
     * @returns {void}
     */
    function showBubble(bubble, slotMap, slot, sceneKey) {
        var cfg = slotMap[String(slot)];
        var text = cfg && cfg.narrative && cfg.narrative[sceneKey]
            ? cfg.narrative[sceneKey]
            : '';
        if (!text || !bubble) {
            return;
        }
        bubble.innerHTML = text;
        bubble.style.display = 'block';
    }

    /**
     * Return the URL of a target slot by looking up Moodle quiz nav buttons.
     *
     * @param {number} targetSlot The target slot number.
     * @returns {string|null} The URL, or null if not found.
     */
    function getSlotUrl(targetSlot) {
        var btn = document.querySelector('#quiznavbutton' + targetSlot);
        if (!btn || !btn.href || btn.href === '#') {
            return null;
        }
        var href = btn.href;
        var relPos = href.indexOf('&scrollpos');
        if (relPos === -1) {
            relPos = href.indexOf('&page');
        }
        if (relPos === -1) {
            relPos = href.indexOf('#');
        }
        return relPos > -1 ? href.substring(0, relPos) + '&page=' + btn.dataset.page : href;
    }

    /**
     * Update the next-question navigation button after an answer.
     *
     * @param {Object}  slotMap  Map of slot → configjson.
     * @param {number}  slot     Current slot number.
     * @param {boolean} solved   Whether the answer was correct.
     * @returns {void}
     */
    function updateNav(slotMap, slot, solved) {
        var cfg = slotMap[String(slot)];
        var branching = cfg && cfg.branching ? cfg.branching : {};
        var rule = solved
            ? (branching.gradedright || branching.default || {})
            : (branching.gradedwrong || branching.default || {});
        var targetSlot = (rule.mode === 'slot' && rule.target) ? rule.target : null;
        var nextUrl = targetSlot ? getSlotUrl(targetSlot) : null;

        var nextBtn = document.querySelector('.smg-eg-next');
        if (!nextBtn) {
            nextBtn = document.createElement('a');
            nextBtn.className = 'btn btn-primary smg-eg-next';
            var shell = document.querySelector('.smg-runtime-shell');
            if (shell) {
                shell.appendChild(nextBtn);
            }
        }

        if (nextUrl) {
            nextBtn.href = nextUrl;
            nextBtn.textContent = 'Nächste Frage →';
            nextBtn.style.display = 'inline-block';
        } else {
            nextBtn.style.display = 'none';
        }
    }

    // ── Public interface ───────────────────────────────────────────────────

    /**
     * Initialise the ExitGame mode.
     *
     * Called by game_engine after bootstrapping quiz data.
     *
     * @param {Object} gameState Bootstrap data from game_engine.
     * @param {Object} gameState.config PHP config passed via js_call_amd.
     * @param {Object} gameState.design Design record from server.
     * @param {Object} gameState.profile Player profile.
     * @param {Array}  gameState.questionmap Array of questionmap rows.
     * @param {Array}  gameState.narrative Initial narrative lines.
     * @returns {{onAnswer: Function}} Game module interface.
     */
    function init(gameState) {
        injectStyles();
        var slotMap = buildSlotMap(gameState.questionmap);
        var bubble = buildBubble();
        var currentSlot = parseInt(
            (document.querySelector('.que') || {}).getAttribute
                ? (document.querySelector('.que').getAttribute('data-smg-slot') || '0')
                : '0',
            10
        );

        // Show intro narrative on page load.
        if (currentSlot) {
            showBubble(bubble, slotMap, currentSlot, 'intro');
        }

        return {
            /**
             * React to a submit_answer response.
             *
             * Updates the speech bubble and navigation button.
             *
             * @param {Object} response The submit_answer web service response.
             * @returns {void}
             */
            onAnswer: function(response) {
                var slot = response.slot || currentSlot;
                var solved = !!response.cannext;
                showBubble(bubble, slotMap, slot, solved ? 'success' : 'fail');
                updateNav(slotMap, slot, solved);
            }
        };
    }

    return {init: init};
});
