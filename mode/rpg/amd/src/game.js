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
 * Fantasy RPG mode game module for local_stackmathgame.
 *
 * Implements the visual RPG layer: mana bar, fairy counter, scene narrative
 * bubble, and teleport-style next-scene navigation. Scene types drive mana
 * gain (boss > miniboss > challenge > instruction).
 *
 * Based on alquiz-fantasy-bg-ver3.js
 * (c) 2022 Malte Neugebauer, Hochschule Bochum (MIT Licence).
 * Adapted for the local_stackmathgame subplugin AMD module contract.
 *
 * @module     stackmathgamemode_rpg/game
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_stackmathgame/game_core'], function(GameCore) {

    'use strict';

    /** Mana gained per scene type on first solve. */
    var MANA_GAIN = {
        boss:        30,
        miniboss:    15,
        challenge:   10,
        reward:      10,
        transition:   5,
        instruction:  0,
        outro:        5
    };

    /** Starting mana value (mirrors alquiz-fantasy default). */
    var MANA_START = 20;

    /**
     * Build a slot-keyed map from the questionmap array.
     *
     * @param {Array} questionmap Array of questionmap rows from get_quiz_config.
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
     * Inject Fantasy RPG CSS into the document head.
     *
     * Styles the HUD bar (mana / fairies), narrative bubble, and
     * teleport-style next button.
     *
     * @returns {void}
     */
    function injectStyles() {
        if (document.getElementById('smg-rpg-styles')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'smg-rpg-styles';
        style.textContent = [
            /* HUD container */
            '.smg-rpg-hud {',
            '  background: linear-gradient(135deg, #1a1a2e, #16213e);',
            '  color: #e0e0ff;',
            '  padding: .75em 1em;',
            '  border-radius: .5em;',
            '  margin-bottom: 1em;',
            '}',
            '.smg-rpg-hud-inner {',
            '  display: flex;',
            '  align-items: center;',
            '  gap: .75em;',
            '  flex-wrap: wrap;',
            '}',
            /* Mana bar */
            '.smg-rpg-manabar-outer {',
            '  flex: 1;',
            '  background: #333;',
            '  border-radius: 4px;',
            '  height: 12px;',
            '  min-width: 80px;',
            '}',
            '.smg-rpg-manabar-inner {',
            '  height: 100%;',
            '  background: linear-gradient(90deg, #4a90e2, #9b59b6);',
            '  border-radius: 4px;',
            '  transition: width .6s ease;',
            '}',
            /* Narrative bubble */
            '.smg-rpg-narrative {',
            '  display: none;',
            '  position: relative;',
            '  background: #fcefdc;',
            '  padding: 1.125em 1.5em;',
            '  font-size: 1.1em;',
            '  border-radius: 1rem;',
            '  box-shadow: 0 .125rem .5rem rgba(0,0,0,.3);',
            '  margin-bottom: 1em;',
            '}',
            '.smg-rpg-narrative::before {',
            '  content: "";',
            '  position: absolute;',
            '  top: 100%;',
            '  left: 1.5em;',
            '  border: .75rem solid transparent;',
            '  border-bottom: none;',
            '  border-top-color: #fcefdc;',
            '  filter: drop-shadow(0 .0625rem .0625rem rgba(0,0,0,.1));',
            '}',
            /* Teleport next-scene button */
            '.smg-rpg-next {',
            '  display: none;',
            '  margin-top: .5em;',
            '  background: linear-gradient(90deg, #4a90e2, #9b59b6);',
            '  color: #fff;',
            '  border: none;',
            '  padding: .5em 1.25em;',
            '  border-radius: .4em;',
            '  cursor: pointer;',
            '  font-size: 1em;',
            '}',
            '.smg-rpg-next:hover {',
            '  opacity: .85;',
            '}',
        ].join('\n');
        document.head.appendChild(style);
    }

    /**
     * Build the HUD DOM element and insert it before the first question.
     *
     * @returns {{hud: Element, manaBar: Element, manaText: Element, fairyCount: Element}}
     */
    function buildHUD() {
        var hud = document.createElement('div');
        hud.className = 'smg-rpg-hud';
        hud.innerHTML = [
            '<div class="smg-rpg-hud-inner">',
            '  <span class="smg-rpg-label">🧙 Mana</span>',
            '  <div class="smg-rpg-manabar-outer">',
            '    <div class="smg-rpg-manabar-inner" style="width:' + MANA_START + '%"></div>',
            '  </div>',
            '  <span class="smg-rpg-mana-text">' + MANA_START + '</span>',
            '  <span class="smg-rpg-fairies ms-3">🧚 <span class="smg-rpg-fairy-count">0</span></span>',
            '</div>',
        ].join('');
        var first = document.querySelector('.que');
        if (first && first.parentNode) {
            first.parentNode.insertBefore(hud, first);
        }
        return {
            hud: hud,
            manaBar:    hud.querySelector('.smg-rpg-manabar-inner'),
            manaText:   hud.querySelector('.smg-rpg-mana-text'),
            fairyCount: hud.querySelector('.smg-rpg-fairy-count')
        };
    }

    /**
     * Build the narrative bubble and insert it after the HUD.
     *
     * @param {Element} hud The HUD element.
     * @returns {Element} The narrative bubble element.
     */
    function buildNarrativeBubble(hud) {
        var bubble = document.createElement('div');
        bubble.className = 'smg-rpg-narrative';
        if (hud && hud.parentNode) {
            hud.parentNode.insertBefore(bubble, hud.nextSibling);
        }
        return bubble;
    }

    /**
     * Build the next-scene button and insert it after the narrative bubble.
     *
     * @param {Element} bubble The narrative bubble element.
     * @returns {Element} The next-scene anchor element.
     */
    function buildNextButton(bubble) {
        var btn = document.createElement('a');
        btn.className = 'smg-rpg-next';
        btn.textContent = '⚡ Nächste Szene';
        if (bubble && bubble.parentNode) {
            bubble.parentNode.insertBefore(btn, bubble.nextSibling);
        }
        return btn;
    }

    /**
     * Update the HUD mana bar and fairy counter.
     *
     * @param {{manaBar: Element, manaText: Element, fairyCount: Element}} hudParts HUD elements.
     * @param {number} mana    Current mana value (0–100).
     * @param {number} fairies Current fairy count.
     * @returns {void}
     */
    function updateHUD(hudParts, mana, fairies) {
        var safeMana = Math.max(0, Math.min(100, mana));
        if (hudParts.manaBar) {
            hudParts.manaBar.style.width = safeMana + '%';
        }
        if (hudParts.manaText) {
            hudParts.manaText.textContent = String(safeMana);
        }
        if (hudParts.fairyCount) {
            hudParts.fairyCount.textContent = String(fairies);
        }
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
     * Initialise the Fantasy RPG mode.
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

        var slotMap  = buildSlotMap(gameState.questionmap);
        var hudParts = buildHUD();
        var bubble   = buildNarrativeBubble(hudParts.hud);
        var nextBtn  = buildNextButton(bubble);

        var score = {mana: MANA_START, fairies: 0};

        // Restore score from sessionStorage if available.
        try {
            var stored = sessionStorage.getItem('smg_rpg_score');
            if (stored) {
                var parsed = JSON.parse(stored);
                if (parsed && typeof parsed.mana === 'number') {
                    score.mana    = parsed.mana;
                    score.fairies = parsed.fairies || 0;
                }
            }
        } catch (e) { /* ignore */ }

        updateHUD(hudParts, score.mana, score.fairies);

        // Show intro narrative.
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
                : '';
            if (introText) {
                bubble.innerHTML = introText;
                bubble.style.display = 'block';
            }
        }

        return {
            /**
             * React to a submit_answer response.
             *
             * Updates mana/fairy score, shows narrative bubble, and configures
             * the next-scene navigation link.
             *
             * @param {Object} response The submit_answer web service response.
             * @returns {void}
             */
            onAnswer: function(response) {
                var slot   = response.slot || currentSlot;
                var solved = !!response.cannext;
                var cfg    = slotMap[String(slot)] || GameCore.defaultConfig();
                var sceneType = cfg.scene && cfg.scene.type ? cfg.scene.type : 'challenge';

                // Update score on first solve only (prevent farming).
                if (solved) {
                    var gain = MANA_GAIN[sceneType] !== undefined ? MANA_GAIN[sceneType] : 10;
                    score.mana = Math.min(100, score.mana + gain);
                    score.fairies++;
                    try {
                        sessionStorage.setItem('smg_rpg_score', JSON.stringify(score));
                    } catch (e) { /* ignore */ }
                }

                updateHUD(hudParts, score.mana, score.fairies);

                // Show narrative text.
                var narrativeKey = solved ? 'success' : 'fail';
                var narrativeText = cfg.narrative && cfg.narrative[narrativeKey]
                    ? cfg.narrative[narrativeKey]
                    : '';
                if (narrativeText) {
                    bubble.innerHTML = narrativeText;
                    bubble.style.display = 'block';
                } else {
                    bubble.style.display = 'none';
                }

                // Update next-scene button.
                if (solved) {
                    var branching = cfg.branching || {};
                    var rule = branching.gradedright || branching.default || {};
                    var targetSlot = (rule.mode === 'slot' && rule.target) ? rule.target : null;
                    var nextUrl = targetSlot ? getSlotUrl(targetSlot) : null;
                    if (nextUrl) {
                        nextBtn.href = nextUrl;
                        nextBtn.style.display = 'inline-block';
                    } else {
                        nextBtn.style.display = 'none';
                    }
                } else {
                    nextBtn.style.display = 'none';
                }
            }
        };
    }

    return {init: init};
});
