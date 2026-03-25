// local_stackmathgame/state_manager.js
//
// Client-side game state management.
//
// Single source of truth for the current session's solved questions,
// solved variants, and score values. Emits custom events so UI modules
// can react without coupling.
//
// @package    local_stackmathgame

define([], function () {

    'use strict';

    class StateManager {
        /**
         * @param {Object} initialState   Loaded from server on init
         * @param {string[]} initialState.solved
         * @param {number[]} initialState.solved_variants
         * @param {Object}   initialState.scores   { fairies, mana }
         */
        constructor(initialState) {
            this._solved         = new Set(initialState.solved          || []);
            this._solvedVariants = new Set(initialState.solved_variants || []);
            this._scores         = Object.assign({ fairies: 0, mana: 20 }, initialState.scores || {});
            this._listeners      = {};
        }

        // ------------------------------------------------------------------
        // Solved questions
        // ------------------------------------------------------------------

        isSolved(questionId) {
            return this._solved.has(questionId);
        }

        isVariantSolved(variantPage) {
            return this._solvedVariants.has(variantPage);
        }

        markSolved(questionId, variantPage) {
            const wasNew = !this._solved.has(questionId);
            this._solved.add(questionId);

            if (variantPage >= 0) {
                this._solvedVariants.add(variantPage);
            }

            if (wasNew) {
                this._emit('question_solved', { questionId, variantPage });
            }

            return wasNew;
        }

        getSolvedArray()         { return Array.from(this._solved); }
        getSolvedVariantsArray() { return Array.from(this._solvedVariants); }

        getSolvedVariantIndicesFor(questionBasePage, variantCount) {
            const result = [];
            for (let i = 0; i < variantCount; i++) {
                if (this._solvedVariants.has(questionBasePage + i)) {
                    result.push(i);
                }
            }
            return result;
        }

        // ------------------------------------------------------------------
        // Scores
        // ------------------------------------------------------------------

        getScore(type) {
            return this._scores[type] ?? 0;
        }

        setScore(type, value) {
            const prev = this._scores[type] ?? 0;
            this._scores[type] = value;
            if (prev !== value) {
                this._emit('score_changed', { type, prev, value, delta: value - prev });
            }
        }

        applyScoreDelta(scoreDelta) {
            Object.entries(scoreDelta).forEach(([type, delta]) => {
                const prev  = this._scores[type] ?? 0;
                const value = Math.max(0, prev + delta);
                this._scores[type] = value;
                if (delta !== 0) {
                    this._emit('score_changed', { type, prev, value, delta });
                }
            });
        }

        getScoreSnapshot() {
            return Object.assign({}, this._scores);
        }

        // ------------------------------------------------------------------
        // Serialisation
        // ------------------------------------------------------------------

        toJSON() {
            return {
                solved:          this.getSolvedArray(),
                solved_variants: this.getSolvedVariantsArray(),
                scores:          this.getScoreSnapshot(),
            };
        }

        // ------------------------------------------------------------------
        // Events
        // ------------------------------------------------------------------

        on(event, fn) {
            (this._listeners[event] = this._listeners[event] || []).push(fn);
            return this; // chainable
        }

        off(event, fn) {
            if (this._listeners[event]) {
                this._listeners[event] = this._listeners[event].filter(f => f !== fn);
            }
        }

        _emit(event, data) {
            (this._listeners[event] || []).forEach(fn => {
                try { fn(data); } catch (e) { console.error('[StateManager] listener error:', e); }
            });
        }
    }

    return StateManager;
});
