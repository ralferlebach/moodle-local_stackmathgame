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
                'default':   {mode: 'linear'}
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
            hasNext: action === 'continue' || action === 'substep' || action === 'finish',
            isEnd: action === 'finish',
            // The server decides whether a new level begins: the level structure is the quiz's,
            // and a mode that worked it out itself would be reading quiz sections it has no
            // business knowing about.
            entersLevel: !!nav.enterslevel,
            levelHeading: nav.levelheading || '',
            groupMode: nav.groupmode || 'scenes',
            groupTotal: Number(nav.grouptotal || 0),
            groupDone: Number(nav.groupdone || 0),
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

    /**
     * Look up a design asset by its manifest key.
     *
     * Modes must go through this rather than assembling a URL. A hand-built path works only for
     * a bundled package and breaks as soon as a design is imported - and it breaks silently,
     * because a broken image src renders as nothing rather than as an error.
     *
     * @param {Object} gameState The state handed to init().
     * @param {string} key The asset key from the package manifest.
     * @param {string} [fallback] URL to use when the design does not supply that key.
     * @returns {string} The resolved URL, or the fallback, or an empty string.
     */
    function assetUrl(gameState, key, fallback) {
        var assets = (gameState && gameState.assets) || {};
        if (assets[key]) {
            return assets[key];
        }
        return fallback || '';
    }

    /**
     * Render "stage 2 of 3" into an element, or hide it when there is nothing to say.
     *
     * Shared rather than written three times: the three modes would otherwise each decide when a
     * group is worth mentioning, and would disagree - one showing "1 of 1" for an ordinary
     * question, another counting a finished quest as still in progress.
     *
     * @param {Element} element The element to write into.
     * @param {Object} navigation The decision from navigationFrom().
     * @param {string} template A label with {done} and {total} placeholders.
     * @returns {void}
     */
    function applyGroupProgress(element, navigation, template) {
        if (!element) {
            return;
        }
        // Only a real group with more than one stage is worth showing. A single-question page is
        // the ordinary case and "1 of 1" is noise.
        if (!navigation || navigation.groupMode === 'scenes' || navigation.groupTotal < 2) {
            element.classList.add('d-none');
            element.textContent = '';
            return;
        }
        element.classList.remove('d-none');
        // The stage the player is on is the one after those already done, capped at the total so
        // a finished group reads "3 of 3" rather than "4 of 3".
        var current = Math.min(navigation.groupDone + 1, navigation.groupTotal);
        element.textContent = String(template || '{done} / {total}')
            .replace('{done}', String(current))
            .replace('{total}', String(navigation.groupTotal));
    }

    /**
     * Mark the landmarks of a run in Moodle's quiz navigation, as the original game did.
     *
     * Moodle already groups the navigation buttons under the quiz's section headings - the
     * chapters. What the original added is a symbol where the run is not an ordinary task: a house
     * on the starting scene, a skull on a boss, and a chequered flag on the finish - on Moodle's
     * "Finish attempt" link, not on the last scene, because the last scene is often the boss and
     * the two symbols would compete for one button. The
     * symbols are Font Awesome Free, which Moodle ships, so nothing is loaded for them; the number
     * stays for screen readers, next to a word saying what the symbol means.
     *
     * @param {Array} questionmap Question map rows, each with slotnumber and configjson.
     * @returns {void}
     */
    function decorateQuizNavigation(questionmap) {
        var buttons = document.querySelectorAll('#mod_quiz_navblock a.qnbutton[id^="quiznavbutton"]');
        if (!buttons.length) {
            return;
        }
        var types = {};
        (questionmap || []).forEach(function(row) {
            var slot = parseInt(row.slotnumber, 10) || 0;
            try {
                var cfg = row.configjson ? JSON.parse(row.configjson) : {};
                types[slot] = cfg && cfg.scene && cfg.scene.type ? cfg.scene.type : '';
            } catch (e) {
                types[slot] = '';
            }
        });

        var marks = {
            start: {icon: 'fa-house', label: 'navstart'},
            boss: {icon: 'fa-skull', label: 'navboss'},
            finish: {icon: 'fa-flag-checkered', label: 'navfinish'}
        };

        buttons.forEach(function(button) {
            if (button.classList.contains('smg-nav-marked')) {
                return;
            }
            var slot = parseInt(button.id.replace('quiznavbutton', ''), 10) || 0;
            var mark = null;
            if (types[slot] === 'instruction' && slot === 1) {
                mark = marks.start;
            } else if (types[slot] === 'boss') {
                mark = marks.boss;
            }
            if (!mark) {
                return;
            }
            // The number is a bare text node beside Moodle's own hidden labels; wrapped so it can be
            // hidden visually while staying in the accessible name.
            Array.prototype.slice.call(button.childNodes).forEach(function(node) {
                if (node.nodeType === 3 && /\d/.test(node.textContent)) {
                    var num = document.createElement('span');
                    num.className = 'smg-nav-num';
                    num.textContent = node.textContent;
                    button.replaceChild(num, node);
                }
            });
            var icon = document.createElement('i');
            icon.className = 'icon fa fa-solid ' + mark.icon + ' smg-nav-icon';
            icon.setAttribute('aria-hidden', 'true');
            var word = document.createElement('span');
            word.className = 'accesshide';
            word.textContent = ' ' + M.util.get_string(mark.label, 'local_stackmathgame');
            button.appendChild(icon);
            button.appendChild(word);
            button.classList.add('smg-nav-marked');
        });

        var finish = document.querySelector('#mod_quiz_navblock .endtestlink');
        if (finish && !finish.querySelector('.smg-nav-icon')) {
            var flag = document.createElement('i');
            flag.className = 'icon fa fa-solid ' + marks.finish.icon + ' smg-nav-icon me-1';
            flag.setAttribute('aria-hidden', 'true');
            finish.insertBefore(flag, finish.firstChild);
        }

        if (!document.getElementById('smg-nav-styles')) {
            var style = document.createElement('style');
            style.id = 'smg-nav-styles';
            style.textContent = '#mod_quiz_navblock .smg-nav-marked .smg-nav-num{position:absolute;width:1px;height:1px;'
                + 'overflow:hidden;clip:rect(0 0 0 0);}'
                + '#mod_quiz_navblock .smg-nav-marked .smg-nav-icon{margin:0;font-size:1.05em;}';
            document.head.appendChild(style);
        }
    }

    /** STACK's answer fields: named by the question engine, q<usage>:<slot>_<input>. */
    var STACK_FIELDS = 'input[type=text][name*=":"], textarea[name*=":"]';

    /**
     * Report whether something inside the question hides an element.
     *
     * Asked within the question, not on the page: after the first answer the RPG itself hides
     * the emptied .que, and a page-level check would then call every field hidden and move none.
     * Each element's own computed display is independent of its ancestors, so walking up to the
     * question and asking at every step sees exactly what the question - or a plugin inside it -
     * did to the field.
     *
     * @param {Element} el The element.
     * @param {Element} root The question; the walk stops below it.
     * @returns {boolean} True when the element or an ancestor below root is hidden.
     */
    function hiddenWithin(el, root) {
        for (var node = el; node && node !== root; node = node.parentElement) {
            if (node.classList && node.classList.contains('d-none')) {
                return true;
            }
            var style = window.getComputedStyle(node);
            if (style.display === 'none' || style.visibility === 'hidden' || isVisuallyHidden(style)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recognise the "visually hidden" pattern from a computed style.
     *
     * Accessible input surfaces hide the field they replace without display:none, so that it
     * stays in the accessibility tree: positioned out of the flow and shrunk to a pixel, or moved
     * far off screen, or clipped away. The STACK Math Editor does exactly this with its original
     * STACK input. Read from the computed style rather than the element's box, which is empty for
     * every element of a question the RPG has already hidden.
     *
     * @param {CSSStyleDeclaration} style The computed style.
     * @returns {boolean} True when the element is hidden from sight but not from assistive tech.
     */
    function isVisuallyHidden(style) {
        if (style.position !== 'absolute' && style.position !== 'fixed') {
            return false;
        }
        var width = parseFloat(style.width);
        var height = parseFloat(style.height);
        var left = parseFloat(style.left);
        return (width <= 1 || height <= 1 || left <= -999
            || style.clip === 'rect(0px, 0px, 0px, 0px)' || style.clipPath === 'inset(50%)');
    }

    /**
     * Report whether a question's STACK fields are currently stood in for by an input surface.
     *
     * A field hidden from sight - not by the question itself, which uses d-none for a welcome
     * page - is the interface of an editor in front of it.
     *
     * @param {Element} que The question; parts moved out of it are included.
     * @returns {boolean} True when at least one field is enhanced.
     */
    function hasInputSurface(que) {
        // The question and every part of it a mode has moved elsewhere on the page: the RPG
        // carries the formulation - fields included - into its panel, and a check of .que alone
        // then never saw the editor it had to keep.
        var roots = (que ? [que] : []).concat(Array.prototype.slice.call(
            document.querySelectorAll('[data-smg-question-part]')
        ));
        return roots.some(function(root) {
            return Array.prototype.some.call(root.querySelectorAll(STACK_FIELDS), function(field) {
                return !field.closest('.d-none') && hiddenWithin(field, root);
            });
        });
    }

    /**
     * Report whether the stored progress marks a slot as solved.
     *
     * Read on page load by every mode: a scene solved on an earlier visit - or before a reload -
     * must offer its way on at once, not wait for an answer that has already been given.
     *
     * @param {Object} gameState The state handed to a mode's init().
     * @param {number} slot The slot number.
     * @returns {boolean} True when the slot is solved.
     */
    function isSlotSolved(gameState, slot) {
        try {
            var progress = JSON.parse((gameState.profile || {}).progressjson || '{}') || {};
            return !!(progress.slots && progress.slots[String(slot)] && progress.slots[String(slot)].solved);
        } catch (e) {
            return false;
        }
    }

    return {
        STACK_FIELDS: STACK_FIELDS,
        isSlotSolved: isSlotSolved,
        hasInputSurface: hasInputSurface,
        hiddenWithin: hiddenWithin,
        decorateQuizNavigation: decorateQuizNavigation,
        defaultConfig: defaultConfig,
        navigationFrom: navigationFrom,
        applyGroupProgress: applyGroupProgress,
        applyNavigation: applyNavigation,
        escapeHtml: escapeHtml,
        assetUrl: assetUrl
    };
});
