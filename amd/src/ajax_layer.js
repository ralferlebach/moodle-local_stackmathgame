// This file is part of Moodle - http://moodle.org/
//
// local_stackmathgame/ajax_layer.js
//
// Encapsulates the browser-side STACK answer submission AJAX chain.
//
// Architecture:
//  1. StackSubmitter  – Handles the 2–3 step STACK submission chain (submit → validate → feedback).
//                       Keeps sequencecheck in sync. Returns a normalised FeedbackResult.
//  2. GameStateSyncer – Sends game-state deltas to the plugin PHP endpoint (non-blocking queue).
//  3. RequestQueue    – Async queue with exponential backoff for resilient saves.
//
// @package    local_stackmathgame
// @copyright  2025 Your Institution
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define(['core/ajax', 'core/notification'], function (Ajax, Notification) {

    'use strict';

    // =========================================================================
    // Constants
    // =========================================================================

    const DUMMY_NUMERIC = '429876543210';
    const MAX_RETRIES   = 4;
    const BASE_DELAY_MS = 800;

    // =========================================================================
    // RequestQueue — non-blocking save queue with exponential backoff
    // =========================================================================

    class RequestQueue {
        constructor() {
            this._queue      = [];
            this._processing = false;
        }

        /** Enqueue a no-arg async function. */
        enqueue(fn) {
            this._queue.push(fn);
            if (!this._processing) {
                this._flush();
            }
        }

        async _flush() {
            this._processing = true;
            while (this._queue.length > 0) {
                const fn = this._queue.shift();
                let attempt = 0;
                while (attempt < MAX_RETRIES) {
                    try {
                        await fn();
                        break;
                    } catch (err) {
                        attempt++;
                        if (attempt >= MAX_RETRIES) {
                            // Log but don't block. State will be re-synced on next save.
                            console.warn('[stackmathgame] Save queue exhausted retries:', err);
                        } else {
                            const delay = BASE_DELAY_MS * Math.pow(2, attempt - 1);
                            await new Promise(r => setTimeout(r, delay));
                        }
                    }
                }
            }
            this._processing = false;
        }
    }

    // =========================================================================
    // SequenceCheckManager — tracks Moodle's sequencecheck counter
    // =========================================================================

    class SequenceCheckManager {
        constructor(form) {
            this._form = form;
        }

        /** Get all sequencecheck fields in the form. */
        _fields() {
            return Array.from(this._form.querySelectorAll('input[name$=sequencecheck]'));
        }

        /** Reset to value 1 (correct starting state for a fresh attempt). */
        reset() {
            this._fields().forEach(f => { f.value = '1'; });
        }

        /** Sync sequencecheck value from a fetched page's form. */
        syncFrom(fetchedForm) {
            if (!fetchedForm) return;
            fetchedForm.querySelectorAll('input[name$=sequencecheck]').forEach(fetchedField => {
                const localField = this._form.querySelector(`input[name="${fetchedField.name}"]`);
                if (localField) {
                    localField.value = fetchedField.value;
                }
            });
        }

        /** Raise all sequence checks by delta (used after validation-error retry). */
        raise(delta = 1) {
            this._fields().forEach(f => {
                f.value = String(parseInt(f.value, 10) + delta);
            });
        }
    }

    // =========================================================================
    // StackSubmitter — the encapsulated STACK submission chain
    // =========================================================================

    /**
     * @typedef {Object} FeedbackResult
     * @property {'correct'|'partial'|'incorrect'} outcome
     * @property {string}  feedbackHtml     Raw HTML from .stackprtfeedback
     * @property {number}  targetEnemyIndex Index of the targeted enemy (multi-input questions)
     * @property {boolean} hasStackInputError
     */

    class StackSubmitter {
        /**
         * @param {HTMLFormElement}       form          The #responseform
         * @param {HTMLInputElement}      inputElement  The spell input field
         * @param {Object|null}           targetedEnemy The currently targeted enemy object
         * @param {number}                enemyIndex    Index in enemies array
         */
        constructor(form, inputElement, targetedEnemy, enemyIndex) {
            this._form          = form;
            this._input         = inputElement;
            this._targetEnemy   = targetedEnemy;
            this._enemyIndex    = enemyIndex;
            this._seqMgr        = new SequenceCheckManager(form);
            this._parser        = new DOMParser();
        }

        /**
         * Submit the answer through the STACK chain.
         * Returns a FeedbackResult promise.
         *
         * Flow:
         *   POST answer → check for validation page → re-POST if needed →
         *   check for redo button → parse feedback
         *
         * @returns {Promise<FeedbackResult>}
         */
        async submit() {
            const formData = this._buildFormData();
            const raw1     = await this._post(this._form.action, formData);
            const page1    = this._parse(raw1);

            // --- Handle validation error (no redo button yet) ---
            if (this._hasValidationError(page1)) {
                this._seqMgr.raise(2);
                throw new SubmissionError('validation_error', 'Server returned a validation error.');
            }

            // --- Handle intermediate validation page (no redo, no error) ---
            const page1form = page1.getElementById('responseform');
            if (!this._hasRedoButton(page1)) {
                const validationFix = await this._resubmitValidationPage(page1, page1form);
                return this._parseResult(validationFix);
            }

            return this._parseResult(page1);
        }

        // ------------------------------------------------------------------
        // Private helpers
        // ------------------------------------------------------------------

        _buildFormData() {
            const fd   = new FormData(this._form);
            const submitBtn = this._form.querySelector('input.submit, button.submit');

            if (!submitBtn) {
                throw new SubmissionError('no_submit_button', 'Submit button not found in form.');
            }
            fd.append(submitBtn.name, submitBtn.value);

            // Multi-input question: fill targeted enemy's input, dummy for others.
            const textInputs = this._form.querySelectorAll(
                '.formulation.clearfix input[type=text], .formulation.clearfix textarea'
            );

            if (textInputs.length > 1 && this._targetEnemy) {
                textInputs.forEach(inp => {
                    const isTargeted = (
                        this._targetEnemy.container.dataset.refer === inp.name
                    );
                    if (isTargeted) {
                        fd.set(inp.name, this._input.value);
                    } else {
                        const correct = inp.dataset.correctAnswer;
                        fd.set(inp.name, correct !== undefined ? correct : DUMMY_NUMERIC);
                    }
                });
            } else if (textInputs.length === 1) {
                fd.set(textInputs[0].name, this._input.value);
            }

            return fd;
        }

        async _resubmitValidationPage(parsedPage, parsedForm) {
            if (!parsedForm) {
                throw new SubmissionError('no_form', 'No responseform on validation page.');
            }

            // Check for stack input error on validation page.
            if (parsedPage.querySelector('.stackinputerror')) {
                this._seqMgr.raise(1);
                throw new SubmissionError('stack_input_error', 'Syntax error in input.');
            }

            const submitBtn = parsedPage.querySelector('input.submit, button.submit');
            if (!submitBtn) {
                throw new SubmissionError('no_submit_button_v2', 'No submit on validation page.');
            }

            // Sync sequencecheck from fetched page before re-posting.
            this._seqMgr.syncFrom(parsedForm);

            const fd2 = new FormData(parsedForm);
            fd2.append(submitBtn.name, submitBtn.value);

            const raw2 = await this._post(parsedForm.action, fd2);
            return this._parse(raw2);
        }

        _parseResult(parsedPage) {
            const feedbackFields = parsedPage.querySelectorAll('.stackprtfeedback');

            let outcome     = 'incorrect';
            let feedbackHtml = '';

            if (feedbackFields.length > 1 && this._enemyIndex >= 0) {
                // Multi-input: check feedback for the specific targeted enemy.
                const targetFeedback = feedbackFields[this._enemyIndex];
                if (targetFeedback) {
                    feedbackHtml = targetFeedback.innerHTML;
                    const evalNode = targetFeedback.querySelector('.correct, .incorrect, .partiallycorrect');
                    outcome = this._classToOutcome(evalNode);
                }
            } else if (feedbackFields.length > 0) {
                feedbackHtml = feedbackFields[0].innerHTML;
                const evalNode = feedbackFields[0].querySelector('.correct, .incorrect, .partiallycorrect');
                outcome = this._classToOutcome(evalNode);
            }

            // After a successful result, reset sequencecheck to maintain sync.
            this._seqMgr.reset();

            return {
                outcome,
                feedbackHtml,
                targetEnemyIndex: this._enemyIndex,
                hasStackInputError: false,
                redoButtonName:  this._getRedoButtonName(parsedPage),
                redoButtonValue: this._getRedoButtonValue(parsedPage),
                fetchedFormAction: parsedPage.getElementById('responseform')?.action ?? '',
            };
        }

        _classToOutcome(node) {
            if (!node) return 'incorrect';
            if (node.classList.contains('correct'))        return 'correct';
            if (node.classList.contains('partiallycorrect')) return 'partial';
            return 'incorrect';
        }

        _hasRedoButton(parsedPage) {
            return !!parsedPage.querySelector('.mod_quiz-redo_question_button');
        }

        _hasValidationError(parsedPage) {
            return !!parsedPage.querySelector('.validationerror');
        }

        _getRedoButtonName(parsedPage) {
            return parsedPage.querySelector('.mod_quiz-redo_question_button')?.name ?? '';
        }

        _getRedoButtonValue(parsedPage) {
            return parsedPage.querySelector('.mod_quiz-redo_question_button')?.value ?? '';
        }

        async _post(url, formData) {
            const response = await fetch(url, { method: 'POST', body: formData });
            if (!response.ok) {
                throw new SubmissionError('http_error', `HTTP ${response.status}`);
            }
            return response.text();
        }

        _parse(html) {
            return this._parser.parseFromString(html, 'text/html');
        }
    }

    // =========================================================================
    // SubmissionError — typed error for the chain
    // =========================================================================

    class SubmissionError extends Error {
        constructor(code, message) {
            super(message);
            this.name = 'SubmissionError';
            this.code = code;
        }
    }

    // =========================================================================
    // GameStateSyncer — sends state deltas to the Moodle plugin endpoint
    // =========================================================================

    class GameStateSyncer {
        constructor(config) {
            this._config = config; // { labelid, cmid, sesskey }
            this._queue  = new RequestQueue();
        }

        /**
         * Notify the server of a question outcome.
         * Non-blocking: queued and retried on failure.
         *
         * @param {string}  questionid
         * @param {number}  variantpage
         * @param {string}  outcome     'correct'|'partial'|'incorrect'
         */
        notifyOutcome(questionid, variantpage, outcome) {
            this._queue.enqueue(() => Ajax.call([{
                methodname: 'local_stackmathgame_submit_answer',
                args: {
                    attemptid:   this._config.attemptid,
                    cmid:        this._config.cmid,
                    labelid:     this._config.labelid,
                    questionid:  questionid,
                    variantpage: variantpage,
                    outcome:     outcome,
                    sesskey:     this._config.sesskey,
                },
            }])[0]);
        }

        /**
         * Bulk-save the full game state (e.g. before page unload).
         * Returns a promise (awaitable).
         *
         * @param {string[]} solved
         * @param {number[]} solvedVariants
         * @param {Object}   scores  { fairies, mana }
         * @returns {Promise}
         */
        saveFullState(solved, solvedVariants, scores) {
            return Ajax.call([{
                methodname: 'local_stackmathgame_save_gamestate',
                args: {
                    labelid:          this._config.labelid,
                    cmid:             this._config.cmid,
                    solved:           JSON.stringify(solved),
                    solved_variants:  JSON.stringify(solvedVariants),
                    score_fairies:    scores.fairies,
                    score_mana:       scores.mana,
                    sesskey:          this._config.sesskey,
                },
            }])[0];
        }
    }

    // =========================================================================
    // Public API
    // =========================================================================

    return {
        StackSubmitter,
        GameStateSyncer,
        SubmissionError,
        RequestQueue,
    };
});
