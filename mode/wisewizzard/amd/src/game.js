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
 * Digital Mentoring (instant tutoring) mode: a tutor answers every attempt.
 *
 * The layout follows the original "Digital Mentoring" instant-tutoring quiz: a round tutor avatar
 * beside the question, and a speech bubble that the tutor fills after every check. The avatar's
 * expression follows the attempt - grinning while idle, thinking while the student types, happy on
 * a right answer, sad on a wrong one - and a red dot marks a message the student has not opened.
 * On a wide screen avatar and bubble sit under the question in a row, the bubble's tail pointing
 * at the avatar; on a narrow one they float at the bottom right, the bubble above the avatar.
 *
 * The original's on-screen operator keyboard is not reproduced: the STACK maths editor replaces it
 * as a separate plugin.
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

    /** The four expressions, by asset key. A missing one falls back to the next in the list. */
    var MOODS = {
        grin: ['mentor_grin', 'mentor_happy'],
        think: ['mentor_think', 'mentor_hint', 'mentor_grin', 'mentor_happy'],
        happy: ['mentor_happy', 'mentor_grin'],
        sad: ['mentor_sad', 'mentor_hint', 'mentor_happy']
    };

    /**
     * Build a slot-keyed map from the question map.
     *
     * @param {Array} questionmap Question map rows from get_quiz_config.
     * @returns {Object} Map of slot number (string) to its parsed direction card.
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
     * Inject the tutor styles once. Colours, sizes and shadows are the original's.
     *
     * @returns {void}
     */
    function injectStyles() {
        if (document.getElementById('smg-ww-styles')) {
            return;
        }
        var css = [
            // Narrow screens: avatar and bubble float at the bottom right, bubble on top.
            '.smg-ww-chat{position:fixed;bottom:50px;right:50px;z-index:1030;display:flex;',
            'flex-direction:column;align-items:flex-end;gap:.9rem;}',
            '.smg-ww-avatarwrap{position:relative;flex:0 0 auto;cursor:pointer;background:none;border:0;padding:0;}',
            '.smg-ww-avatar{display:block;width:7.5em;height:7.5em;border:2px solid #000;border-radius:50%;',
            'background:#fff center/cover no-repeat;',
            'box-shadow:0 .125rem .5rem rgba(0,0,0,.3),0 .0625rem .125rem rgba(0,0,0,.2);}',
            '.smg-ww-dot{position:absolute;top:2%;right:0;width:1.6em;height:1.6em;border-radius:50%;',
            'background:#e2001a;display:none;}',
            '.smg-ww-chat.has-unread .smg-ww-dot{display:block;}',
            '.smg-ww-bubble{position:relative;max-width:15em;background:#fcefdc;padding:1.125em 1.5em;',
            'font-size:1.25em;border-radius:1rem;',
            'box-shadow:0 .125rem .5rem rgba(0,0,0,.3),0 .0625rem .125rem rgba(0,0,0,.2);',
            'transform:scale(1,0);transform-origin:bottom;transition:transform .6s;max-height:60vh;',
            // Above the avatar on a narrow screen, its tail pointing down at it, as in the original.
            'order:-1;}',
            '.smg-ww-chat.is-open .smg-ww-bubble{transform:scale(1,1);}',
            '.smg-ww-bubble::before{content:"";position:absolute;top:100%;right:1.5em;width:0;',
            'border:.75rem solid transparent;border-bottom:none;border-top-color:#fcefdc;',
            'filter:drop-shadow(0 .0625rem .0625rem rgba(0,0,0,.1));}',
            '.smg-ww-content{overflow:auto;max-height:50vh;}',
            '.smg-ww-next{display:inline-block;margin-top:.6em;font-size:.8em;}',
            // Wide screens: a row under the question, the tail pointing left at the avatar.
            '@media (min-width:991px){',
            '.smg-ww-chat{position:relative;bottom:auto;right:auto;flex-direction:row;align-items:flex-start;',
            'width:100%;margin:1rem 0;z-index:1;}',
            '.smg-ww-bubble{order:2;max-width:none;flex:1 1 auto;transform:scale(1,0);transform-origin:top;',
            'max-height:0;transition:transform .6s,max-height .6s;}',
            '.smg-ww-chat.is-open .smg-ww-bubble{max-height:20em;}',
            '.smg-ww-bubble::before{top:1.5em;right:100%;border:.75rem solid transparent;',
            'border-left:none;border-right-color:#fcefdc;}',
            '.smg-ww-content{max-height:19em;}',
            '}',
            // The tutor explains errors; STACK's own error line under the input would say it twice.
            '.smg-ww-active .stackinputerror{display:none!important;}',
            // One check control, not two: the engine's, which is what lets the tutor answer.
            '.smg-ww-active .que .im-controls{display:none;}'
        ].join('');
        var style = document.createElement('style');
        style.id = 'smg-ww-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    /**
     * Find the artwork for an expression, falling back along its list.
     *
     * @param {Object} gameState The state handed to init().
     * @param {string} mood One of the keys of MOODS.
     * @returns {string} The image URL, or an empty string.
     */
    function moodUrl(gameState, mood) {
        var keys = MOODS[mood] || MOODS.grin;
        var assets = gameState.assets || {};
        for (var i = 0; i < keys.length; i++) {
            if (assets[keys[i]]) {
                return assets[keys[i]];
            }
        }
        return '';
    }

    /**
     * Build the tutor: avatar, unread dot, bubble and the way on.
     *
     * Placed beside the question, not inside it: after every answer the engine swaps the whole
     * .que for a freshly rendered one, and a tutor living inside it would vanish with it.
     *
     * @returns {Object} References to the parts.
     */
    function buildTutor() {
        var chat = document.createElement('div');
        chat.className = 'smg-ww-chat';

        var wrap = document.createElement('button');
        wrap.type = 'button';
        wrap.className = 'smg-ww-avatarwrap';
        wrap.setAttribute('aria-label', M.util.get_string('tutorname', 'local_stackmathgame'));
        wrap.setAttribute('aria-expanded', 'false');
        var avatar = document.createElement('span');
        avatar.className = 'smg-ww-avatar';
        var dot = document.createElement('span');
        dot.className = 'smg-ww-dot';
        wrap.appendChild(avatar);
        wrap.appendChild(dot);

        var bubble = document.createElement('div');
        bubble.className = 'smg-ww-bubble';
        bubble.setAttribute('aria-live', 'polite');
        var content = document.createElement('div');
        content.className = 'smg-ww-content';
        var next = document.createElement('a');
        next.className = 'btn btn-primary btn-sm smg-ww-next';
        next.style.display = 'none';
        bubble.appendChild(content);
        bubble.appendChild(next);

        chat.appendChild(wrap);
        chat.appendChild(bubble);

        var que = GameCore.currentQuestion();
        if (que && que.parentNode) {
            que.parentNode.insertBefore(chat, que.nextSibling);
        } else {
            document.body.appendChild(chat);
        }
        document.body.classList.add('smg-ww-active');

        wrap.addEventListener('click', function() {
            var open = !chat.classList.contains('is-open');
            chat.classList.toggle('is-open', open);
            chat.classList.remove('has-unread');
            wrap.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        return {chat: chat, wrap: wrap, avatar: avatar, content: content, next: next};
    }

    /**
     * Start the mode on the current page.
     *
     * @param {Object} gameState The state handed over by the engine.
     * @returns {{onAnswer: function}} The mode's contract with the engine.
     */
    function init(gameState) {
        injectStyles();

        var slotMap = buildSlotMap(gameState.questionmap);
        var currentSlot = parseInt(gameState.currentslot || 0, 10) || 0;
        var tutor = buildTutor();

        /**
         * Change the tutor's expression.
         *
         * @param {string} mood One of the keys of MOODS.
         * @returns {void}
         */
        function setMood(mood) {
            var url = moodUrl(gameState, mood);
            tutor.avatar.style.backgroundImage = url ? 'url("' + url + '")' : 'none';
            tutor.chat.setAttribute('data-smg-mood', mood);
        }

        /**
         * Put a message in the bubble and open it.
         *
         * @param {string} text The message.
         * @returns {void}
         */
        function say(text) {
            // Nothing to say is not a message: an empty bubble popping open after a validation
            // looks like the tutor lost its words. The last message stays instead.
            if (!text) {
                return;
            }
            tutor.content.textContent = text;
            // As in the original: on a wide screen the bubble opens by itself and there is nothing
            // unread; on a narrow one the tutor floats over the page, the message arrives folded,
            // and the red dot says there is something to open.
            if (window.matchMedia && window.matchMedia('(min-width: 991px)').matches) {
                tutor.chat.classList.add('is-open');
                tutor.chat.classList.remove('has-unread');
                tutor.wrap.setAttribute('aria-expanded', 'true');
            } else if (!tutor.chat.classList.contains('is-open')) {
                tutor.chat.classList.add('has-unread');
            }
        }

        setMood('grin');
        var cfg = slotMap[String(currentSlot)] || {};
        say(cfg.narrative && cfg.narrative.intro ? cfg.narrative.intro : '');

        // Offered at once where nothing is left to answer: an instruction scene, and a scene solved
        // on an earlier visit or before a reload. Only instruction scenes used to be covered, so a
        // solved scene revisited - or reloaded behind an older maths editor - had no way on at all.
        var type = cfg.scene && cfg.scene.type ? cfg.scene.type : '';
        var solvedBefore = currentSlot > 0 && GameCore.isSlotSolved(gameState, currentSlot);
        if ((type === 'instruction' || solvedBefore) && gameState.navigation) {
            GameCore.applyNavigation(tutor.next, GameCore.navigationFrom({navigation: gameState.navigation}));
        }

        // Thinking along while the student types. Bound on the document because the engine
        // replaces the question - and its input - after every check.
        document.addEventListener('input', function(e) {
            if (e.target && e.target.closest && e.target.closest('.que')) {
                setMood('think');
            }
        });

        return {
            /**
             * React to a graded answer.
             *
             * @param {Object} response The submit_answer response.
             * @returns {void}
             */
            onAnswer: function(response) {
                var slot = response.slot || currentSlot;
                var slotCfg = slotMap[String(slot)] || {};
                var narrative = slotCfg.narrative || {};
                var solved = !!response.cannext;

                setMood(solved ? 'happy' : 'sad');
                say(solved ? (narrative.success || '') : (narrative.fail || ''));

                var decision = GameCore.navigationFrom(response);
                GameCore.applyNavigation(tutor.next, decision);
                GameCore.applyGroupProgress(
                    stageCounter(tutor),
                    decision,
                    M.util.get_string('stageprogress', 'local_stackmathgame')
                );
            }
        };
    }

    /**
     * Return the stage counter element, created on first use.
     *
     * @param {Object} tutor The tutor parts.
     * @returns {Element} The element.
     */
    function stageCounter(tutor) {
        var existing = tutor.content.parentNode.querySelector('.smg-ww-stage');
        if (existing) {
            return existing;
        }
        var el = document.createElement('div');
        el.className = 'smg-ww-stage small d-none';
        tutor.content.parentNode.insertBefore(el, tutor.next);
        return el;
    }

    return {init: init};
});
