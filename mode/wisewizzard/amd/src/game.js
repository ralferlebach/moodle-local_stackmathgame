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
 * WiseWizzard (WiseGuy Tutor) mode game module for local_stackmathgame.
 *
 * A friendly animated tutor guides the player through the quiz in a
 * chat-style floating bubble. On correct answers the tutor congratulates
 * and shows the next question button. On wrong answers it delivers a
 * supportive hint from the slot narrative config.
 *
 * Based on alquiz-qpool-instant-tutoring.js
 * (c) 2022 Malte Neugebauer, Hochschule Bochum (MIT Licence).
 * Adapted for the local_stackmathgame subplugin AMD module contract.
 *
 * @module     stackmathgamemode_wisewizzard/game
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_stackmathgame/game_core'], function(GameCore) {

    'use strict';

    /** Default DM avatar URL (Hochschule Bochum public asset). */
    var AVATAR_URL = 'https://marvin.hs-bochum.de/~mneugebauer/dm-avatar-grin.svg';

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
     * Inject styles for the chat UI and tutor avatar.
     *
     * @returns {void}
     */
    function injectStyles() {
        if (document.getElementById('smg-wisewizzard-styles')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'smg-wisewizzard-styles';
        style.textContent = [
            '.smg-ww-chat {',
            '  position: fixed;',
            '  bottom: 20px;',
            '  right: 20px;',
            '  z-index: 100;',
            '  max-width: 360px;',
            '}',
            '.smg-ww-avatar {',
            '  width: 64px;',
            '  border-radius: 50%;',
            '  border: 2px solid #333;',
            '  cursor: pointer;',
            '  display: block;',
            '  margin-top: 8px;',
            '  margin-left: auto;',
            '}',
            '.smg-ww-bubble {',
            '  display: none;',
            '  position: relative;',
            '  background: #fcefdc;',
            '  padding: 1em 1.25em;',
            '  border-radius: 1rem;',
            '  box-shadow: 0 2px 8px rgba(0,0,0,.25);',
            '  font-size: 1em;',
            '  margin-bottom: 8px;',
            '}',
            '.smg-ww-bubble::after {',
            '  content: "";',
            '  position: absolute;',
            '  top: 100%;',
            '  right: 20px;',
            '  border: 10px solid transparent;',
            '  border-top-color: #fcefdc;',
            '}',
            '.smg-ww-chat.active .smg-ww-bubble {',
            '  display: block;',
            '}',
            '.smg-ww-next {',
            '  margin-top: .5em;',
            '  display: none;',
            '  float: right;',
            '}',
        ].join('\n');
        document.head.appendChild(style);
    }

    /**
     * Build the chat UI: bubble + avatar. Appended to document.body.
     *
     * @param {string} avatarUrl URL for the tutor avatar image.
     * @returns {{chat: Element, bubble: Element, next: Element}} UI elements.
     */
    function buildChatUI(avatarUrl) {
        var chat = document.createElement('div');
        chat.className = 'smg-ww-chat active';

        var bubble = document.createElement('div');
        bubble.className = 'smg-ww-bubble';

        var nextBtn = document.createElement('a');
        nextBtn.className = 'btn btn-success btn-sm smg-ww-next';
        nextBtn.textContent = 'Nächste Frage →';
        bubble.appendChild(nextBtn);

        var avatar = document.createElement('img');
        avatar.className = 'smg-ww-avatar';
        avatar.src = avatarUrl;
        avatar.alt = 'Tutor';
        avatar.addEventListener('click', function() {
            chat.classList.toggle('active');
        });

        chat.appendChild(bubble);
        chat.appendChild(avatar);
        document.body.appendChild(chat);

        return {chat: chat, bubble: bubble, next: nextBtn};
    }

    /**
     * Return the URL of a target slot from the Moodle quiz nav buttons.
     *
     * @param {number} targetSlot The target slot number.
     * @returns {string|null} The page URL, or null.
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

    // ── Public interface ───────────────────────────────────────────────────

    /**
     * Initialise the WiseWizzard (tutor) mode.
     *
     * @param {Object} gameState Bootstrap data from game_engine.
     * @param {Object} gameState.config PHP config.
     * @param {Object} gameState.design Design record.
     * @param {Object} gameState.profile Player profile.
     * @param {Array}  gameState.questionmap Array of questionmap rows.
     * @param {Array}  gameState.narrative Initial narrative lines.
     * @param {string} gameState.assetBaseUrl Base URL for design assets.
     * @returns {{onAnswer: Function}} Game module interface.
     */
    function init(gameState) {
        injectStyles();

        var slotMap = buildSlotMap(gameState.questionmap);
        var avatarUrl = gameState.assetBaseUrl
            ? gameState.assetBaseUrl + '/mentor_happy.svg'
            : AVATAR_URL;
        var ui = buildChatUI(avatarUrl);

        /**
         * Show text in the tutor bubble.
         *
         * @param {string} text HTML content to display.
         * @returns {void}
         */
        function say(text) {
            // Preserve the next button at the end of the bubble.
            ui.bubble.innerHTML = text;
            ui.bubble.appendChild(ui.next);
            ui.chat.classList.add('active');
        }

        // Show intro narrative on page load.
        var currentSlot = parseInt(
            (document.querySelector('.que') || {}).getAttribute
                ? (document.querySelector('.que').getAttribute('data-smg-slot') || '0')
                : '0',
            10
        );
        if (currentSlot) {
            var introCfg = slotMap[String(currentSlot)];
            var introText = introCfg && introCfg.narrative && introCfg.narrative.intro
                ? introCfg.narrative.intro
                : 'Willkommen! Ich begleite dich durch die Aufgaben.';
            say(introText);
        }

        return {
            /**
             * React to a submit_answer response.
             *
             * Shows success or hint text and updates next-question navigation.
             *
             * @param {Object} response The submit_answer web service response.
             * @returns {void}
             */
            onAnswer: function(response) {
                var slot = response.slot || currentSlot;
                var solved = !!response.cannext;
                var cfg = slotMap[String(slot)];
                var narrative = cfg && cfg.narrative ? cfg.narrative : {};
                var text = solved
                    ? (narrative.success || 'Sehr gut! Weiter zur nächsten Aufgabe.')
                    : (narrative.fail || 'Nicht ganz – schau dir die Aufgabe nochmal an!');

                say(text);

                // Show / hide next button.
                if (solved) {
                    var branching = cfg && cfg.branching ? cfg.branching : {};
                    var rule = branching.gradedright || branching.default || {};
                    var targetSlot = (rule.mode === 'slot' && rule.target) ? rule.target : null;
                    var nextUrl = targetSlot ? getSlotUrl(targetSlot) : null;
                    if (nextUrl) {
                        ui.next.href = nextUrl;
                        ui.next.style.display = 'inline-block';
                    } else {
                        ui.next.style.display = 'none';
                    }
                } else {
                    ui.next.style.display = 'none';
                }
            }
        };
    }

    return {init: init};
});
