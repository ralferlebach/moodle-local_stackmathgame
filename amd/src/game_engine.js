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
 * STACK Math Game engine router.
 *
 * Bootstraps game data from the server, then dynamically loads the mode-specific
 * game module from the active design's modecomponent subplugin. The subplugin AMD
 * module is loaded as <modecomponent>/game (e.g. stackmathgamemode_rpg/game).
 *
 * All AJAX communication (submit_answer, get_quiz_config, etc.) lives here.
 * Visual rendering and game mechanics live in the subplugin game modules.
 *
 * @module     local_stackmathgame/game_engine
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    'use strict';

    /** @type {Object} Shared runtime state for this page. */
    const state = {
        config: null,
        runtime: {},
        activeGame: null,
        store: {
            profile: null,
            design: null,
            questionmap: [],
            narrative: [],
            nextnode: null,
            lastsubmit: null
        },
        activeInput: null
    };

    // ── Utility functions ──────────────────────────────────────────────────

    /**
     * Return the quiz attempt id from the URL or page inputs.
     *
     * @returns {number} The attempt id, or 0 if not found.
     */
    function getAttemptId() {
        const params = new URLSearchParams(window.location.search);
        const raw = params.get('attempt') || $('input[name="attempt"]').val() || '0';
        return parseInt(raw, 10) || 0;
    }

    /**
     * Return the controlled question element or the first question on the page.
     *
     * @returns {Element|null} The question DOM element.
     */
    function getCurrentQuestion() {
        return document.querySelector('.que[data-smg-controlled="1"]')
            || document.querySelector('.que');
    }

    /**
     * Return the slot number of the current question.
     *
     * @returns {number} The slot number, or 0.
     */
    function getCurrentSlot() {
        const question = getCurrentQuestion();
        if (!question) {
            return 0;
        }
        // Prefer explicit attribute when set by game_engine on init.
        const explicit = question.getAttribute('data-smg-slot');
        if (explicit) {
            return parseInt(explicit, 10) || 0;
        }
        // Moodle 4.x quiz attempt DOM id format: "question-{attempt}-{slot}".
        const qid = question.id || '';
        const stdMatch = qid.match(/^question-\d+-(\d+)$/);
        if (stdMatch) {
            return parseInt(stdMatch[1], 10) || 0;
        }
        // STACK format: "q{attempt}:{slot}_...".
        const stackMatch = qid.match(/^q\d+:(\d+)_/);
        return stackMatch ? parseInt(stackMatch[1], 10) || 0 : 0;
    }

    /**
     * Collect all answer field values from the current question.
     *
     * @returns {Array<{name: string, value: string}>} Array of name/value pairs.
     */
    function collectAnswers() {
        const question = getCurrentQuestion();
        const result = [];
        if (!question) {
            return result;
        }
        const seen = new Set();
        question.querySelectorAll('input, textarea, select').forEach(function(node) {
            if (!node.name || node.disabled) {
                return;
            }
            if ((node.type === 'checkbox' || node.type === 'radio') && !node.checked) {
                return;
            }
            if (seen.has(node.name) && node.type !== 'checkbox') {
                return;
            }
            seen.add(node.name);
            result.push({name: node.name, value: node.value || ''});
        });
        ['attempt', 'thispage', 'nextpage', 'slots', 'sesskey'].forEach(function(name) {
            const field = document.querySelector('input[name="' + name + '"]');
            if (field) {
                result.push({name: name, value: field.value || ''});
            }
        });
        return result;
    }

    /**
     * Shortcut wrapper for a single Ajax call.
     *
     * @param {string} methodname The web service name.
     * @param {Object} args The call arguments.
     * @returns {Promise} The Ajax promise.
     */
    function call(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    }

    /**
     * Return whether a canonical activity identity is available.
     *
     * @returns {boolean} True when cmid is available.
     */
    function hasActivityIdentity() {
        return !!(state.config && parseInt(state.config.cmid, 10));
    }

    /**
     * Build canonical activity arguments for activity-aware web services.
     *
     * @param {Object=} extra Additional arguments to merge in.
     * @returns {Object} Canonical activity argument object.
     */
    function getActivityArgs(extra) {
        var args = {
            cmid: parseInt(state.config.cmid, 10) || 0,
            modname: state.config.modname || 'quiz',
            instanceid: parseInt(state.config.instanceid || state.config.quizid, 10) || 0
        };
        return Object.assign(args, extra || {});
    }

    /**
     * Call the appropriate configuration endpoint for the current identity.
     *
     * @returns {Promise} The Ajax promise.
     */
    function callConfigEndpoint() {
        if (hasActivityIdentity()) {
            return call('local_stackmathgame_get_activity_config', getActivityArgs());
        }
        return call('local_stackmathgame_get_quiz_config', {quizid: state.config.quizid});
    }

    /**
     * Call the appropriate profile-state endpoint for the current identity.
     *
     * @returns {Promise} The Ajax promise.
     */
    function callProfileStateEndpoint() {
        if (hasActivityIdentity()) {
            return call('local_stackmathgame_get_activity_profile_state', getActivityArgs());
        }
        return call('local_stackmathgame_get_profile_state', {quizid: state.config.quizid});
    }

    /**
     * Call the appropriate narrative endpoint for the current identity.
     *
     * @param {string} scene The narrative scene key.
     * @returns {Promise} The Ajax promise.
     */
    function callNarrativeEndpoint(scene) {
        if (hasActivityIdentity()) {
            return call('local_stackmathgame_get_activity_narrative', getActivityArgs({scene: scene}));
        }
        return call('local_stackmathgame_get_narrative', {
            quizid: state.config.quizid,
            scene: scene
        });
    }

    /**
     * Call the appropriate prefetch endpoint for the current identity.
     *
     * @param {number} currentslot The current slot number.
     * @returns {Promise} The Ajax promise.
     */
    function callPrefetchEndpoint(currentslot) {
        if (hasActivityIdentity()) {
            return call(
                'local_stackmathgame_prefetch_next_activity_node',
                getActivityArgs({currentslot: currentslot})
            );
        }
        return call('local_stackmathgame_prefetch_next_node', {
            quizid: state.config.quizid,
            currentslot: currentslot
        });
    }

    /**
     * Safely parse a JSON string, returning fallback on error.
     *
     * @param {string|null} value The JSON string.
     * @param {*} fallback The fallback value.
     * @returns {*} The parsed value or fallback.
     */
    function parseJson(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    // ── Question refresh ───────────────────────────────────────────────────

    /**
     * Try to refresh the question HTML from the fragment endpoint.
     *
     * @returns {Promise<boolean>} Resolves true when the question was refreshed.
     */
    function refreshQuestionFromFragment() {
        const attemptid = getAttemptId();
        const slot = getCurrentSlot();
        if (!attemptid || !slot) {
            return Promise.resolve(false);
        }
        return call(
            'local_stackmathgame_get_question_fragment',
            {attemptid: attemptid, slot: slot}
        ).then(function(response) {
            if (!response || response.status !== 'ok' || !response.questionhtml) {
                return false;
            }
            const parser = new DOMParser();
            const doc = parser.parseFromString(response.questionhtml, 'text/html');
            const replacement = doc.querySelector('.que') || doc.body.firstElementChild;
            const current = getCurrentQuestion();
            if (replacement && current && current.parentNode) {
                current.parentNode.replaceChild(replacement, current);
                bindInputs();
                return true;
            }
            return false;
        }).catch(function() {
            return false;
        });
    }

    /**
     * Refresh the question by reloading the full page HTML.
     *
     * @returns {Promise<void>}
     */
    function refreshQuestionFromPage() {
        return fetch(window.location.href, {credentials: 'same-origin'})
            .then(function(response) {
                return response.text();
            })
            .then(function(html) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const slot = getCurrentSlot();
                const selector = slot ? '.que[data-smg-slot="' + slot + '"]' : '.que';
                const replacement = doc.querySelector(selector) || doc.querySelector('.que');
                const current = getCurrentQuestion();
                if (replacement && current && current.parentNode) {
                    current.parentNode.replaceChild(replacement, current);
                    bindInputs();
                }
            })
            .catch(Notification.exception);
    }

    /**
     * Attach focus listeners to question input fields.
     *
     * @returns {void}
     */
    function bindInputs() {
        document.querySelectorAll('.que input[type="text"], .que textarea').forEach(function(input) {
            input.addEventListener('focus', function() {
                state.activeInput = input;
            });
        });
    }

    // ── Game module routing ────────────────────────────────────────────────

    /**
     * Derive the AMD module name for the active mode subplugin.
     *
     * The convention is <modecomponent>/game, e.g. stackmathgamemode_rpg/game.
     * Falls back to stackmathgamemode_exitgames/game when the component is unknown.
     *
     * @param {string} modecomponent The design's modecomponent field.
     * @returns {string} AMD module name.
     */
    function resolveGameModuleName(modecomponent) {
        var known = [
            'stackmathgamemode_rpg',
            'stackmathgamemode_exitgames',
            'stackmathgamemode_wisewizzard'
        ];
        var component = (known.indexOf(modecomponent) !== -1)
            ? modecomponent
            : 'stackmathgamemode_exitgames';
        return component + '/game';
    }

    /**
     * Dynamically load and initialise the mode-specific game module.
     *
     * The game module receives the current state object so it can react to
     * answer results and update the game UI without coupling to AJAX logic.
     *
     * @param {string} modecomponent The design's modecomponent.
     * @returns {Promise<void>}
     */
    function loadGameModule(modecomponent) {
        return new Promise(function(resolve) {
            var moduleName = resolveGameModuleName(modecomponent);
            require([moduleName], function(gameModule) {
                if (gameModule && typeof gameModule.init === 'function') {
                    state.activeGame = gameModule.init({
                        config: state.config,
                        design: state.store.design,
                        profile: state.store.profile,
                        questionmap: state.store.questionmap,
                        narrative: state.store.narrative,
                        assetBaseUrl: state.config.themeAssetUrl || ''
                    });
                }
                resolve();
            });
        });
    }

    // ── Answer submission ──────────────────────────────────────────────────

    /**
     * Handle the game-check button click: submit answers and dispatch to game module.
     *
     * @returns {void}
     */
    function handleGameCheck() {
        const attemptid = getAttemptId();
        const slot = getCurrentSlot();
        const answers = collectAnswers();
        if (!attemptid || !slot) {
            return;
        }
        call('local_stackmathgame_submit_answer', {
            attemptid: attemptid,
            slot: slot,
            answers: answers
        }).then(function(response) {
            state.store.lastsubmit = response;
            if (response.profile) {
                state.store.profile = response.profile;
            }
            return refreshQuestionFromFragment().then(function(ok) {
                if (!ok) {
                    return refreshQuestionFromPage();
                }
                return null;
            }).then(function() {
                return Promise.all([
                    callNarrativeEndpoint(response.cannext ? 'victory' : 'defeat'),
                    callPrefetchEndpoint(slot),
                    callProfileStateEndpoint()
                ]);
            }).then(function(results) {
                state.store.narrative =
                    results[0] && results[0].lines ? results[0].lines : [];
                state.store.nextnode =
                    results[1] && results[1].nextnode ? results[1].nextnode : null;
                if (results[2] && results[2].profile) {
                    state.store.profile = results[2].profile;
                    state.store.design = results[2].design || state.store.design;
                }
                // Dispatch to the active game module.
                if (state.activeGame && typeof state.activeGame.onAnswer === 'function') {
                    state.activeGame.onAnswer(response, state.store);
                }
            });
        }).catch(Notification.exception);
    }

    // ── Bootstrap ──────────────────────────────────────────────────────────

    /**
     * Ensure the minimal game shell element exists and wire the check button.
     *
     * The subplugin game module may replace this shell with its own UI.
     *
     * @returns {Element} The shell element.
     */
    function ensureShell() {
        let shell = document.querySelector('.smg-runtime-shell');
        if (shell) {
            return shell;
        }
        const question = getCurrentQuestion();
        shell = document.createElement('div');
        shell.className = 'smg-runtime-shell';
        shell.innerHTML = [
            '<div class="smg-runtime-actions mt-2">',
            '  <button type="button" class="btn btn-primary btn-sm smg-action-check">',
            '    Antwort prüfen',
            '  </button> ',
            '  <button type="button" class="btn btn-secondary btn-sm smg-action-native">',
            '    Standard-Ansicht',
            '  </button>',
            '</div>'
        ].join('');
        if (question && question.parentNode) {
            question.parentNode.insertBefore(shell, question);
        } else {
            document.body.insertBefore(shell, document.body.firstChild);
        }
        shell.querySelector('.smg-action-check').addEventListener('click', handleGameCheck);
        shell.querySelector('.smg-action-native').addEventListener('click', function() {
            document.querySelectorAll('.smg-native-controls').forEach(function(node) {
                node.classList.toggle('smg-native-force-visible');
            });
        });
        return shell;
    }

    /**
     * Bootstrap all initial game data from the server, then load the game module.
     *
     * @returns {Promise<void>}
     */
    function bootstrapData() {
        return Promise.all([
            callConfigEndpoint(),
            callProfileStateEndpoint(),
            callNarrativeEndpoint('world_enter'),
            callPrefetchEndpoint(getCurrentSlot() || 0)
        ]).then(function(results) {
            var quizconfig = results[0] || {};
            state.store.design = quizconfig.design || null;
            state.store.questionmap = quizconfig.questionmap || [];
            state.runtime = parseJson(quizconfig.runtimejson || '{}', {});
            state.store.profile = results[1] ? results[1].profile : null;
            state.store.narrative = results[2] && results[2].lines ? results[2].lines : [];
            state.store.nextnode = results[3] && results[3].nextnode ? results[3].nextnode : null;

            ensureShell();
            bindInputs();

            var modecomponent = (state.store.design && state.store.design.modecomponent) || '';
            return loadGameModule(modecomponent);
        });
    }

    /**
     * Initialise the game engine for a quiz attempt page.
     *
     * Called by PHP via js_call_amd('local_stackmathgame/game_engine', 'init', [config]).
     *
     * @param {Object} config Configuration object from PHP (quizid, cmid, etc.).
     * @returns {void}
     */
    function init(config) {
        state.config = config || {};
        if (!state.config.quizid && state.config.instanceid && state.config.modname === 'quiz') {
            state.config.quizid = state.config.instanceid;
        }
        if (!state.config.instanceid && state.config.quizid) {
            state.config.instanceid = state.config.quizid;
        }
        if (!state.config.modname) {
            state.config.modname = 'quiz';
        }
        if (!state.config.quizid && !state.config.cmid && !state.config.instanceid) {
            return;
        }
        bootstrapData().catch(Notification.exception);
    }

    return {init: init};
});
