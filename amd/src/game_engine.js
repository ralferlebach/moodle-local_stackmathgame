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
define(['core/ajax', 'core/notification', 'local_stackmathgame/game_core', 'core_filters/events',
    'core_form/changechecker', 'core/templates', 'core/fragment'],
    function(Ajax, Notification, GameCore, FilterEvents, ChangeChecker, Templates, Fragment) {

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
            navigation: null,
            assets: {},
            lastsubmit: null,
            // Whether the question on the page shows the state after the last answer. False when
            // both refreshes failed: the answer is saved, the page still shows it as typed.
            questionrefreshed: true
        },
        activeInput: null,
        submitting: false,
        // True once the page is being left; a pending re-initialisation must not reload it then.
        leaving: false,
        // What the server holds for every answer field on the page, by field key. Fields whose
        // value differs from this are unsaved; the form is clean only when none does.
        baseline: {}
    };

    /** Input types that are page bookkeeping rather than something a player types. */
    const NON_ANSWER_TYPES = ['hidden', 'submit', 'button', 'image', 'reset', 'file'];

    /** Name pattern of a question type's own response field. */
    const ANSWER_FIELD = /^q\d+:\d+_[^:-]/;

    // ── Utility functions ──────────────────────────────────────────────────

    /**
     * Return the quiz attempt id from the URL or page inputs.
     *
     * @returns {number} The attempt id, or 0 if not found.
     */
    function getAttemptId() {
        const params = new URLSearchParams(window.location.search);
        const field = document.querySelector('input[name="attempt"]');
        const raw = params.get('attempt') || (field ? field.value : '') || '0';
        return parseInt(raw, 10) || 0;
    }

    /**
     * Return the question the game presents.
     *
     * @returns {Element|null} The question DOM element.
     */
    function getCurrentQuestion() {
        return GameCore.currentQuestion();
    }

    /**
     * Read the slot number of a question element.
     *
     * @param {Element|null} question The question element.
     * @returns {number} The slot number, or 0.
     */
    function slotOfQuestion(question) {
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
     * Return the slot number of the current question.
     *
     * @returns {number} The slot number, or 0.
     */
    function getCurrentSlot() {
        return slotOfQuestion(getCurrentQuestion());
    }

    /**
     * Mark the question the server resolved for this page as the one the game presents.
     *
     * A quiz page can hold several questions - the stages of a quest, a set of alternatives. The
     * runtime used to take the first of them whatever the server had resolved (re-audit N06). The
     * presented question is marked, the others are taken out of view: they belong to the attempt,
     * so they stay in the form, but they are not on offer now.
     *
     * @param {number} activeslot The slot the server resolved, 0 when unknown.
     * @returns {void}
     */
    function markActiveQuestion(activeslot) {
        if (!activeslot) {
            return;
        }
        const questions = Array.from(document.querySelectorAll('.que'));
        const active = questions.find(function(question) {
            return slotOfQuestion(question) === activeslot;
        });
        if (!active) {
            return;
        }
        questions.forEach(function(question) {
            if (question === active) {
                question.setAttribute('data-smg-controlled', '1');
                question.setAttribute('data-smg-slot', String(activeslot));
                question.hidden = false;
                question.classList.remove('smg-que-inactive');
            } else {
                question.removeAttribute('data-smg-controlled');
                question.hidden = true;
                question.classList.add('smg-que-inactive');
            }
        });
    }

    /**
     * Return the elements that hold the current question's fields.
     *
     * The question, plus any part of it a mode has moved elsewhere on the page. A mode that lays
     * the question out as a scene moves the answer field and the question's hidden inputs out of
     * .que; looking in .que alone would then find nothing at all.
     *
     * @returns {Element[]} The roots.
     */
    function questionRoots() {
        const question = getCurrentQuestion();
        return (question ? [question] : []).concat(Array.from(document.querySelectorAll('[data-smg-question-part]')));
    }

    /**
     * Return every element that holds answer fields on the page, whichever question they belong to.
     *
     * @returns {Element[]} The roots.
     */
    function pageRoots() {
        return Array.from(document.querySelectorAll('.que')).concat(
            Array.from(document.querySelectorAll('[data-smg-question-part]'))
        );
    }

    /**
     * Report whether a form control is something the player answers with.
     *
     * @param {Element} node The control.
     * @returns {boolean} True for named, enabled answer fields of a question.
     */
    function isAnswerField(node) {
        // A question type's own field: q{usage}:{slot}_{name}. Names starting with ":" or "-"
        // belong to the question engine or the behaviour - the flag, the sequence check, the
        // submit - and are not answers; the flag in particular is saved on its own.
        if (!node.name || node.disabled || !ANSWER_FIELD.test(node.name)) {
            return false;
        }
        const type = (node.type || '').toLowerCase();
        return NON_ANSWER_TYPES.indexOf(type) === -1;
    }

    /**
     * Build the key a field is tracked under: its name, and for a choice also its value.
     *
     * @param {Element} node The control.
     * @returns {string} The key.
     */
    function fieldKey(node) {
        return (node.type === 'checkbox' || node.type === 'radio') ? node.name + '#' + node.value : node.name;
    }

    /**
     * Read what a field currently holds.
     *
     * @param {Element} node The control.
     * @returns {string} The value; "1"/"0" for a choice.
     */
    function fieldValue(node) {
        if (node.type === 'checkbox' || node.type === 'radio') {
            return node.checked ? '1' : '0';
        }
        return node.value || '';
    }

    /**
     * Write a value into a field.
     *
     * @param {Element} node The control.
     * @param {string} value The value from fieldValue().
     * @returns {void}
     */
    function setFieldValue(node, value) {
        if (node.type === 'checkbox' || node.type === 'radio') {
            node.checked = value === '1';
        } else {
            node.value = value;
        }
    }

    /**
     * Collect the answer fields under some roots.
     *
     * @param {Element[]} roots Where to look.
     * @returns {Element[]} The fields, without duplicates.
     */
    function answerFields(roots) {
        const fields = [];
        roots.forEach(function(root) {
            root.querySelectorAll('input, textarea, select').forEach(function(node) {
                if (isAnswerField(node) && fields.indexOf(node) === -1) {
                    fields.push(node);
                }
            });
        });
        return fields;
    }

    /**
     * Take a snapshot of answer fields.
     *
     * @param {Element[]} roots Where to look.
     * @returns {Object} Field key to value.
     */
    function snapshotFields(roots) {
        const snapshot = {};
        answerFields(roots).forEach(function(node) {
            snapshot[fieldKey(node)] = fieldValue(node);
        });
        return snapshot;
    }

    /**
     * List the answer fields whose value the server does not hold.
     *
     * @returns {string[]} The keys of unsaved fields.
     */
    function unsavedFields() {
        const now = snapshotFields(pageRoots());
        return Object.keys(now).filter(function(key) {
            const saved = Object.prototype.hasOwnProperty.call(state.baseline, key) ? state.baseline[key] : '';
            return now[key] !== saved;
        });
    }

    /**
     * Collect all answer field values from the current question.
     *
     * @returns {Array<{name: string, value: string}>} Array of name/value pairs.
     */
    function collectAnswers() {
        const result = [];
        if (!getCurrentQuestion()) {
            return result;
        }
        const seen = new Set();
        // Collected from the question and from every part a mode moved out of it - not even the
        // sequence check would be sent otherwise, and Moodle rejects the attempt without it.
        const nodes = [];
        questionRoots().forEach(function(root) {
            root.querySelectorAll('input, textarea, select').forEach(function(node) {
                nodes.push(node);
            });
        });
        nodes.forEach(function(node) {
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
        // The session key is deliberately not among these. The request carries it as a parameter
        // of the AJAX call, which is where require_sesskey() reads it; a copy among the answers
        // only ever ended up in the event log (re-audit N01).
        ['attempt', 'thispage', 'nextpage', 'slots'].forEach(function(name) {
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
        return Promise.resolve(Ajax.call([{methodname: methodname, args: args}])[0]);
    }

    /**
     * Run a request whose failure must not stop the game.
     *
     * @param {string} methodname The web service name.
     * @param {Object} args The call arguments.
     * @returns {Promise} Resolves to the response, or to null on failure.
     */
    function optionalCall(methodname, args) {
        return call(methodname, args).catch(function(error) {
            window.console.warn('[stackmathgame] ' + methodname + ' failed; continuing without it', error);
            return null;
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

    // ── Status ─────────────────────────────────────────────────────────────

    /**
     * Show what happened to the player's answer, next to the check control.
     *
     * A live region, so a screen reader announces it. Without it a failed save or a question
     * that could not be refreshed looked exactly like success (re-audit N03).
     *
     * @param {string} key The language string key (status_*).
     * @param {string} kind "info" or "error".
     * @returns {void}
     */
    function showStatus(key, kind) {
        let status = document.querySelector('.smg-runtime-status');
        if (!status) {
            status = document.createElement('div');
            status.className = 'smg-runtime-status small mt-1';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
        }
        const check = document.querySelector('.smg-action-check');
        if (check && check.parentNode && check.nextSibling !== status) {
            check.parentNode.insertBefore(status, check.nextSibling);
        } else if (!status.parentNode) {
            document.body.appendChild(status);
        }
        status.setAttribute('data-smg-status', key);
        status.classList.toggle('text-danger', kind === 'error');
        status.textContent = M.util.get_string(key, 'local_stackmathgame');
    }

    // ── Unsaved-changes state ──────────────────────────────────────────────

    /**
     * Bring Moodle's change checker in line with what the server holds.
     *
     * The quiz watches #responseform and warns before leaving a page with unsaved changes. The
     * game saves through a web service, so it has to tell the checker - but only what is true. The
     * whole form used to be reset after every saved answer, although an answer saves one slot:
     * another slot's edit, or an edit made while the request was running, then left without a
     * warning and was lost (re-audit N04). Moodle 4.5's checker knows one flag per form, not per
     * field, so the per-field truth is kept here and the flag follows it: clean only when every
     * answer field on the page holds what the server holds.
     *
     * @returns {boolean} True when the form is clean.
     */
    function syncDirtyState() {
        const form = document.getElementById('responseform');
        const clean = unsavedFields().length === 0;
        if (form) {
            if (clean) {
                ChangeChecker.resetFormDirtyState(form);
            } else {
                ChangeChecker.markFormAsDirty(form);
            }
        }
        return clean;
    }

    // ── Question refresh ───────────────────────────────────────────────────

    /**
     * Carry edits made while a check was running into the freshly rendered question.
     *
     * The fragment shows the state of the request that was sent. A value typed after it was sent
     * is newer than that, and swapping the question in used to overwrite it with the older answer
     * (re-audit N04). Such a value is put back into the replacement before it goes on the page,
     * so an editor attaching to it starts from it, and it stays unsaved.
     *
     * @param {Element} replacement The freshly rendered question.
     * @param {Object} sent The snapshot of the fields at the time the answer was sent.
     * @returns {number} How many fields carried a newer value.
     */
    function carryNewerEdits(replacement, sent) {
        if (!sent) {
            return 0;
        }
        const live = snapshotFields(questionRoots());
        let carried = 0;
        answerFields([replacement]).forEach(function(node) {
            const key = fieldKey(node);
            if (!Object.prototype.hasOwnProperty.call(sent, key) || !Object.prototype.hasOwnProperty.call(live, key)) {
                return;
            }
            if (live[key] !== sent[key]) {
                setFieldValue(node, live[key]);
                carried++;
            }
        });
        return carried;
    }

    /**
     * Put a freshly rendered question in place of the current one.
     *
     * One route for both refresh paths, so both announce the new content the same way: through
     * Moodle's filter event, which is how MathJax learns there is maths to typeset and how any
     * input surface that follows Moodle's conventions learns there are new fields to enhance.
     *
     * @param {Element} current The question on the page.
     * @param {Element} replacement The freshly rendered question.
     * @param {Object} flow The check this refresh belongs to.
     * @returns {void}
     */
    function swapQuestion(current, replacement, flow) {
        const hadSurface = GameCore.hasInputSurface(current);
        // As rendered, the replacement is what the server holds for this slot.
        Object.assign(state.baseline, snapshotFields([replacement]));
        flow.carried = carryNewerEdits(replacement, flow.sent);
        // A mode may have moved parts of the old question out of it - the RPG shows the fields in
        // its panel - and those parts stay on the page until the mode mounts the new question.
        // Until then the page holds every id twice, and getElementById() finds the old copy: the
        // question's scripts bound STACK's validation to the old, soon removed, validation box,
        // and the visible one never updated. The old copies give up their ids.
        const freshIds = new Set(Array.from(replacement.querySelectorAll('[id]')).map(function(node) {
            return node.id;
        }));
        document.querySelectorAll('[data-smg-question-part] [id]').forEach(function(node) {
            if (freshIds.has(node.id)) {
                node.setAttribute('data-smg-stale-id', node.id);
                node.removeAttribute('id');
            }
        });
        // The markers go with the question: without them the next lookup falls back to the first
        // question of the page, which on a quest is not this one.
        replacement.setAttribute('data-smg-controlled', '1');
        replacement.setAttribute('data-smg-slot', String(slotOfQuestion(current) || flow.slot));
        current.parentNode.replaceChild(replacement, current);
        bindInputs();
        FilterEvents.notifyFilterContentUpdated([replacement]);

        // An input surface that does not follow replaced content - the STACK Math Editor before
        // release 1.3 is one - leaves the fresh question with bare fields. The page is reloaded so
        // every surface initialises again, but only when that loses nothing: every field holds
        // what the server holds and no check is running. Otherwise the reload would throw away an
        // edit, so the field stays as it is and the player is told (re-audit N04).
        if (hadSurface) {
            watchForSurface(replacement, SURFACE_POLLS);
        }
    }

    /** How often a replaced question is checked for its input surface, 200 ms apart (3 s). */
    const SURFACE_POLLS = 15;

    /**
     * Wait for an input surface to attach to a replaced question; reload only as a last resort.
     *
     * Checked repeatedly rather than once after a fixed delay: an editor that attaches a little
     * later than that delay used to trigger the reload anyway, and a reload that fires while the
     * player is already following the way on cancels their navigation - the browser journey caught
     * exactly that on a narrow screen. The reload is skipped when the page is being left, while a
     * check runs, or when an answer field holds something the server does not.
     *
     * @param {Element} replacement The question that was swapped in.
     * @param {number} remaining How many checks are left.
     * @returns {void}
     */
    function watchForSurface(replacement, remaining) {
        window.setTimeout(function() {
            const now = getCurrentQuestion();
            if (state.leaving || now !== replacement || GameCore.hasInputSurface(now)) {
                return;
            }
            // Not while the check that swapped the question is still running: its answer is not
            // fully handled yet, and a reload then would cut it short. The wait starts counting
            // once the check has finished.
            if (state.submitting) {
                watchForSurface(replacement, remaining);
                return;
            }
            if (remaining > 1) {
                watchForSurface(replacement, remaining - 1);
                return;
            }
            if (syncDirtyState()) {
                window.location.reload();
            } else {
                showStatus('status_editorlost', 'error');
            }
        }, 200);
    }

    /**
     * Try to refresh the question HTML from the fragment endpoint.
     *
     * @param {Object} flow The check this refresh belongs to.
     * @returns {Promise<boolean>} Resolves true when the question was refreshed.
     */
    function refreshQuestionFromFragment(flow) {
        if (!flow.attemptid || !flow.slot) {
            return Promise.resolve(false);
        }
        return call(
            'local_stackmathgame_get_question_fragment',
            {attemptid: flow.attemptid, slot: flow.slot}
        ).then(function(response) {
            if (!response || response.status !== 'ok' || !response.questionhtml) {
                return false;
            }
            const doc = new DOMParser().parseFromString(response.questionhtml, 'text/html');
            const replacement = doc.querySelector('.que') || doc.body.firstElementChild;
            const current = getCurrentQuestion();
            if (replacement && current && current.parentNode) {
                swapQuestion(current, replacement, flow);
                return runQuestionScripts(response.javascript || '');
            }
            return false;
        }).catch(function() {
            return false;
        });
    }

    /**
     * Run the scripts a refreshed question asked for, and wait until they have run.
     *
     * STACK binds its instant validation to the input elements inside the question's own div
     * when it initialises. A swapped-in question has new elements and, without its scripts, no
     * validation at all - every answer then needed a second check (re-audit N09). The wait
     * matters as much: a mode may move the fields out of the question as soon as it is told
     * about the answer, and STACK, initialising after that, would find nothing to bind to. The
     * scripts announce themselves through M.util.js_pending; the wait ends when they have
     * completed, or after a few seconds at most.
     *
     * @param {string} javascript The collected scripts, as script tags.
     * @returns {Promise<boolean>} Resolves true once they have run.
     */
    function runQuestionScripts(javascript) {
        if (!javascript) {
            return Promise.resolve(true);
        }
        // Collected as script tags, like Moodle's fragment service returns them; turned into one
        // runnable block the same way core/fragment does.
        Templates.runTemplateJS(Fragment.processCollectedJavascript(javascript));
        return waitForPageScripts().then(function() {
            return true;
        });
    }

    /**
     * Wait until the scripts Moodle has queued for the page have run, a few seconds at most.
     *
     * Scripts announce themselves through M.util.js_pending and finish with js_complete. The
     * question types initialise there - STACK binds its instant validation to the inputs inside
     * the question - and a mode that moves the fields out of the question before that has
     * happened leaves them without it. On a page load the game's own bootstrap used to win that
     * race now and then, and STACK's validation was missing on exactly those pages (re-audit N09).
     *
     * @returns {Promise<void>} Resolves when nothing is pending, or after the cap.
     */
    function waitForPageScripts() {
        return new Promise(function(resolve) {
            const started = Date.now();
            const poll = function() {
                const pending = (typeof M !== 'undefined' && M.util && M.util.pending_js) || [];
                const others = pending.filter(function(key) {
                    return String(key).indexOf('local_stackmathgame') !== 0;
                });
                if (!others.length || Date.now() - started > 4000) {
                    resolve();
                    return;
                }
                window.setTimeout(poll, 50);
            };
            poll();
        });
    }

    /**
     * Refresh the question by reloading the full page HTML.
     *
     * @param {Object} flow The check this refresh belongs to.
     * @returns {Promise<boolean>} Resolves true when the question was refreshed.
     */
    function refreshQuestionFromPage(flow) {
        return fetch(window.location.href, {credentials: 'same-origin'})
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                // Found by slot: the page's markup knows nothing of the game's markers, and the
                // first question of a page with several is not necessarily this one.
                const replacement = Array.from(doc.querySelectorAll('.que')).find(function(question) {
                    return slotOfQuestion(question) === flow.slot;
                });
                const current = getCurrentQuestion();
                if (replacement && current && current.parentNode) {
                    swapQuestion(current, replacement, flow);
                    return true;
                }
                return false;
            })
            .catch(function(error) {
                window.console.warn('[stackmathgame] the question could not be refreshed', error);
                return false;
            });
    }

    /**
     * Refresh the question after a saved answer: fragment first, then the whole page.
     *
     * @param {Object} flow The check.
     * @returns {Promise<boolean>} Resolves true when the page shows the stored state.
     */
    function refreshQuestion(flow) {
        return refreshQuestionFromFragment(flow).then(function(ok) {
            return ok ? true : refreshQuestionFromPage(flow);
        });
    }

    /**
     * Attach focus listeners to question input fields.
     *
     * @returns {void}
     */
    function bindInputs() {
        var selector = '.que input[type="text"], .que textarea, '
            + '[data-smg-question-part] input[type="text"], [data-smg-question-part] textarea';
        document.querySelectorAll(selector).forEach(function(input) {
            input.addEventListener('focus', function() {
                state.activeInput = input;
            });
        });
    }

    /**
     * Submit the answer the ordinary way, through the question's own check button.
     *
     * Used when the server cannot process the answer without a page load. A native submission
     * saves the typed answer, and Moodle's change checker knows a submitted form is not being
     * abandoned.
     *
     * @param {number} slot The slot being answered.
     * @returns {boolean} False when no native check button exists.
     */
    function submitNatively(slot) {
        var form = document.getElementById('responseform');
        var selector = 'input[type=submit][name$="-submit"]:not(.smg-action-check)';
        var candidates = Array.from(document.querySelectorAll(selector));
        var button = candidates.find(function(node) {
            return !slot || node.name.indexOf(':' + slot + '_') !== -1;
        }) || candidates[0];
        if (!form || !button || typeof form.requestSubmit !== 'function') {
            return false;
        }
        form.requestSubmit(button);
        return true;
    }

    /**
     * Mark the check control as busy or ready.
     *
     * @param {boolean} busy Whether an answer is being checked.
     * @returns {void}
     */
    function setChecking(busy) {
        state.submitting = busy;
        document.querySelectorAll('.smg-action-check').forEach(function(button) {
            button.disabled = busy;
            button.setAttribute('aria-busy', busy ? 'true' : 'false');
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
                        // The navigation the server resolved for this scene at load time. A mode
                        // needs it before any answer exists: an instruction scene asks for nothing
                        // and must offer its way forward immediately.
                        navigation: state.store.navigation,
                        // Resolved once, here, from the question the server says this page
                        // presents.
                        currentslot: getCurrentSlot(),
                        // Assets are addressed by key, from the map the server resolved out of
                        // the design's package manifest. A mode must not build a path: doing so
                        // works only for bundled packages and breaks the moment a design is
                        // imported, and it fails invisibly because a broken src renders empty.
                        assets: state.store.assets,
                        // The same assets as a list, each with the sprite geometry its package
                        // declares. "assets" stays a plain key-to-URL map because the other modes
                        // read it that way; a mode that animates sheets reads this one.
                        assetlist: (state.store.design && state.store.design.runtimeassets) || [],
                        // Kept for the shared fallback directory only. Not a place to append
                        // filenames to - use assets[key].
                        assetBaseUrl: state.config.themeAssetUrl || ''
                    });
                }
                resolve();
            });
        });
    }

    // ── Answer submission ──────────────────────────────────────────────────

    /**
     * Take the submit response: decide whether the answer was saved.
     *
     * @param {Object} flow The check.
     * @param {Object} response The submit_answer response.
     * @returns {boolean} True when the answer was recorded and the game continues.
     */
    function acceptSubmit(flow, response) {
        if (!response || response.requiresnativefallback) {
            // The server could not process the answer through the pageless path. The answer is
            // submitted the ordinary way instead. Where the page has no native check button there
            // is nothing to submit with, and the reload that used to follow threw the typed answer
            // away (re-audit N03); the answer now stays in the field and the player is told.
            if (submitNatively(flow.slot)) {
                flow.native = true;
                return false;
            }
            showStatus('status_nofallback', 'error');
            syncDirtyState();
            return false;
        }
        flow.response = response;
        state.store.lastsubmit = response;
        if (response.profile) {
            state.store.profile = response.profile;
        }
        // What was sent is what the server now holds for this slot. Only this slot: other fields
        // on the page keep their own saved state.
        Object.assign(state.baseline, flow.sent);
        return true;
    }

    /**
     * Report a failed submit request: nothing was saved.
     *
     * @param {Object} flow The check.
     * @param {*} error The failure.
     * @returns {boolean} False, the game does not continue.
     */
    function rejectSubmit(flow, error) {
        window.console.warn('[stackmathgame] submit_answer failed', error);
        flow.failed = true;
        showStatus('status_notsaved', 'error');
        syncDirtyState();
        return false;
    }

    /**
     * Hand a recorded answer to the mode.
     *
     * A failure in the mode's rendering is reported and contained. It must not look like a failed
     * save: the answer is recorded, and a retry from here would submit it a second time.
     *
     * @param {Object} flow The check.
     * @returns {boolean} True when the mode took the answer.
     */
    function dispatchAnswer(flow) {
        state.store.questionrefreshed = !!flow.refreshed;
        if (!state.activeGame || typeof state.activeGame.onAnswer !== 'function') {
            return false;
        }
        try {
            state.activeGame.onAnswer(flow.response, state.store);
            return true;
        } catch (error) {
            window.console.error('[stackmathgame] the game mode failed to show the answer', error);
            flow.renderfailed = true;
            return false;
        }
    }

    /**
     * Tell the player what became of a recorded answer, once everything else has run.
     *
     * @param {Object} flow The check.
     * @returns {void}
     */
    function reportRecorded(flow) {
        const clean = syncDirtyState();
        if (!flow.refreshed || flow.renderfailed) {
            showStatus('status_refreshfailed', 'error');
        } else if (flow.carried || !clean) {
            showStatus('status_changedduringcheck', 'info');
        } else {
            showStatus('status_saved', 'info');
        }
    }

    /**
     * Handle the game-check button click: submit answers and dispatch to game module.
     *
     * @param {Event} [event] The click on the check control.
     * @returns {Promise<Object|null>} Resolves to the submit response once everything has run.
     */
    function handleGameCheck(event) {
        // A submit button named like STACK's own Check buttons ("...-submit"), so that input
        // surfaces that follow STACK's check - the STACK Math Editor among them - treat it as one:
        // they write every visible editor into its original STACK input and announce the check
        // before this handler runs. The native submission is prevented here; the answer goes to
        // the server through submit_answer, exactly once.
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        // One check at a time: a second click while the first is on its way would submit the
        // same answer twice against a sequence check that only the first can satisfy.
        if (state.submitting) {
            return Promise.resolve(null);
        }
        const flow = {
            attemptid: getAttemptId(),
            slot: getCurrentSlot(),
            response: null,
            refreshed: false,
            carried: 0
        };
        if (!flow.attemptid || !flow.slot) {
            return Promise.resolve(null);
        }
        // The fields as they are sent. Edits made while the request runs differ from this, and
        // that difference is what keeps them unsaved.
        flow.sent = snapshotFields(questionRoots());
        const answers = collectAnswers();
        setChecking(true);
        showStatus('status_saving', 'info');
        return call('local_stackmathgame_submit_answer', {
            attemptid: flow.attemptid,
            slot: flow.slot,
            answers: answers
        }).then(function(response) {
            return acceptSubmit(flow, response);
        }, function(error) {
            return rejectSubmit(flow, error);
        }).then(function(accepted) {
            return accepted ? refreshQuestion(flow) : false;
        }).then(function(refreshed) {
            flow.refreshed = !!refreshed;
            return flow.response ? followUp(flow.response, flow.slot, flow.attemptid) : null;
        }).then(function() {
            if (flow.response) {
                dispatchAnswer(flow);
                reportRecorded(flow);
            }
            return flow.response;
        }).catch(function(error) {
            // Whatever failed here failed after the answer was recorded, or while telling the
            // player it was not. Reported, never retried.
            window.console.error('[stackmathgame] check failed', error);
            if (flow.response) {
                showStatus('status_refreshfailed', 'error');
            } else {
                Notification.exception(error);
            }
            return flow.response;
        }).then(function(result) {
            if (!flow.native) {
                setChecking(false);
            }
            return result;
        });
    }

    /**
     * Fetch what the mode needs after a recorded answer.
     *
     * Each request may fail on its own without taking the others, or the answer, with it: the
     * submit response already carries the authoritative navigation, so the way on opens even
     * when the narrative, the prefetch or the profile is unavailable.
     *
     * @param {Object} response The submit_answer response.
     * @param {number} slot The slot that was answered.
     * @param {number} attemptid The attempt.
     * @returns {Promise<boolean>}
     */
    function followUp(response, slot, attemptid) {
        return Promise.all([
            optionalCall('local_stackmathgame_get_narrative', {
                quizid: state.config.quizid,
                scene: response.cannext ? 'victory' : 'defeat'
            }),
            optionalCall('local_stackmathgame_prefetch_next_node', {
                quizid: state.config.quizid,
                currentslot: slot,
                // The outcome and the attempt are what let the server resolve the branch and
                // build a usable URL. Without them the endpoint fell back to "next unsolved
                // slot in map order" and ignored the branching entirely.
                outcome: response.cannext ? 'gradedright' : 'gradedwrong',
                attemptid: attemptid
            }),
            optionalCall('local_stackmathgame_get_profile_state', {
                quizid: state.config.quizid
            })
        ]).then(function(results) {
            state.store.narrative =
                results[0] && results[0].lines ? results[0].lines : [];

            state.store.nextnode =
                results[1] && results[1].nextnode ? results[1].nextnode : null;
            // The submit response is the authority on where the player goes next, because it
            // saw the actual outcome. The prefetch only fills in when submit predates the
            // navigation field.
            state.store.navigation = response.navigation
                || (results[1] && results[1].navigation)
                || null;
            if (results[2] && results[2].profile) {
                state.store.profile = results[2].profile;
                state.store.design = results[2].design || state.store.design;
            }
            // A new level opens: the chapter narrative is fetched once, here, rather than by each
            // mode, and before the mode is dispatched to, so it is dispatched to exactly once.
            var nav = state.store.navigation;
            if (!nav || !nav.enterslevel) {
                return null;
            }
            return optionalCall('local_stackmathgame_get_narrative', {
                quizid: state.config.quizid,
                scene: 'chapter_start'
            });
        }).then(function(chapter) {
            if (chapter && chapter.lines && chapter.lines.length) {
                // Prepended, so the level intro reads before the outcome text.
                state.store.narrative = chapter.lines.concat(state.store.narrative);
            }
            return true;
        });
    }

    // ── Bootstrap ──────────────────────────────────────────────────────────

    /**
     * Turn the server's resolved asset list into a lookup by key.
     *
     * The web service sends a list of key/url pairs rather than an object, because Moodle's
     * external API cannot describe a structure with arbitrary keys - and the keys come from a
     * package manifest, which a design author may extend.
     *
     * @param {Object} design The exported design record.
     * @returns {Object} Map of asset key to URL.
     */
    function buildAssetMap(design) {
        var map = {};
        var list = (design && design.runtimeassets) || [];
        list.forEach(function(entry) {
            if (entry && entry.key) {
                map[entry.key] = entry.url || '';
            }
        });
        return map;
    }

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
            '  <button type="submit" name="smg-game-check-submit" class="btn btn-primary btn-sm smg-action-check">',
            '  </button> ',
            '  <button type="button" class="btn btn-secondary btn-sm smg-action-native"></button>',
            '</div>'
        ].join('');
        if (question && question.parentNode) {
            question.parentNode.insertBefore(shell, question);
        } else {
            document.body.insertBefore(shell, document.body.firstChild);
        }
        // Labels from the language pack, set as text: they were German literals in the markup,
        // shown as such to every English-speaking student.
        shell.querySelector('.smg-action-check').textContent = M.util.get_string('checkanswer', 'local_stackmathgame');
        shell.querySelector('.smg-action-native').textContent = M.util.get_string('standardview', 'local_stackmathgame');
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
     * Only the configuration is required. The profile, the opening narrative and the prefetch
     * each make the game richer, and each used to be able to stop it from starting at all: one
     * failing request rejected the whole bootstrap (re-audit N03).
     *
     * @returns {Promise<void>}
     */
    function bootstrapData() {
        return Promise.all([
            call('local_stackmathgame_get_quiz_config', {quizid: state.config.quizid}),
            optionalCall('local_stackmathgame_get_profile_state', {quizid: state.config.quizid}),
            optionalCall('local_stackmathgame_get_narrative', {
                quizid: state.config.quizid,
                scene: 'world_enter'
            }),
            optionalCall('local_stackmathgame_prefetch_next_node', {
                quizid: state.config.quizid,
                currentslot: getCurrentSlot() || 0,
                attemptid: getAttemptId()
            })
        ]).then(function(results) {
            var quizconfig = results[0] || {};
            state.store.design = quizconfig.design || null;
            state.store.assets = buildAssetMap(state.store.design);
            state.store.questionmap = quizconfig.questionmap || [];
            // The runtimejson field lives on the design, not on the quiz config. Reading it one
            // level too high is why the runtime was always an empty object and no design data
            // ever reached the modes - and {} is a valid value, so nothing ever complained.
            state.runtime = parseJson(
                (state.store.design && state.store.design.runtimejson) || '{}',
                {}
            );
            state.store.profile = results[1] ? results[1].profile : null;
            state.store.narrative = results[2] && results[2].lines ? results[2].lines : [];
            state.store.nextnode = results[3] && results[3].nextnode ? results[3].nextnode : null;
            state.store.navigation = results[3] && results[3].navigation
                ? results[3].navigation
                : null;
            // The question types initialise first; the mode may rearrange their markup.
            return waitForPageScripts();
        }).then(function() {
            ensureShell();
            bindInputs();

            // The chapter landmarks in Moodle's quiz navigation are the same for every mode.
            GameCore.decorateQuizNavigation(state.store.questionmap);

            var modecomponent = (state.store.design && state.store.design.modecomponent) || '';
            return loadGameModule(modecomponent);
        });
    }

    /**
     * Initialise the game engine for a quiz attempt page.
     *
     * Called by PHP via js_call_amd('local_stackmathgame/game_engine', 'init', [config]).
     *
     * @param {Object} config Configuration object from PHP (quizid, cmid, activeslot, etc.).
     * @returns {Promise<void>} Resolves once the mode is loaded; rejections are already reported.
     */
    function init(config) {
        state.config = config || {};
        if (!state.config.quizid && state.config.instanceid) {
            state.config.quizid = state.config.instanceid;
        }
        if (!state.config.quizid) {
            return Promise.resolve();
        }
        markActiveQuestion(parseInt(state.config.activeslot || 0, 10) || 0);
        // Once the page is being left, nothing may reload it any more.
        if (typeof window.addEventListener === 'function') {
            window.addEventListener('pagehide', function() {
                state.leaving = true;
            });
            window.addEventListener('beforeunload', function() {
                state.leaving = true;
            });
        }
        // The page as the server rendered it is the saved state of every field on it.
        state.baseline = snapshotFields(pageRoots());
        return bootstrapData().catch(Notification.exception);
    }

    return {
        init: init,
        // The check as a promise, for a caller that needs to know when it has finished - the
        // unit tests do. The check control calls the same function.
        check: handleGameCheck
    };
});
