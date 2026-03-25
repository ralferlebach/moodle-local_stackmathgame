// local_stackmathgame/game_engine.js
//
// Core game engine: refactored and modularised GamifiedQuiz + FantasyQuiz logic.
//
// Responsibilities:
//  - Quiz structure (groups, questions, navigation graph)
//  - Coordinating sprite animations
//  - Processing answer results from the AJAX layer
//  - Driving scene transitions
//
// @package    local_stackmathgame

define([
    'local_stackmathgame/sprite_engine',
    'local_stackmathgame/state_manager',
    'local_stackmathgame/ajax_layer',
], function (SpriteEngine, StateManager, AjaxLayer) {

    'use strict';

    // =========================================================================
    // Data model
    // =========================================================================

    class QuestionGroup {
        constructor(id, description) {
            this.id          = id;
            this.description = description;
            this.questions   = {}; // id → Question
        }
        addQuestion(q) { this.questions[q.id] = q; }
    }

    class Question {
        constructor(data) {
            this.id           = data.id;
            this.name         = data.name;
            this.page         = data.page;
            this.slot         = data.slot;
            this.needs        = data.needs        ?? 1;
            this.variants     = data.variants     ?? 1;
            this.onsuccess    = data.onsuccess    ?? null;
            this.onfailure    = data.onfailure    ?? null;
            this.askBeforeSkip = data.askBeforeSkip ?? false;
            this.BubbleInfo   = data.BubbleInfo   ?? null;
            this.color        = data.color        ?? null;
            this.filter       = data.filter       ?? null;
            this.group        = data.group;
        }
    }

    // =========================================================================
    // NavigationGraph — builds and queries the question chain
    // =========================================================================

    class NavigationGraph {
        constructor(groups, questionsConfig) {
            this.groups    = {};
            this.questions = {}; // flat lookup by id

            // Build groups.
            Object.entries(groups).forEach(([gid, gcfg]) => {
                this.groups[gid] = new QuestionGroup(gid, gcfg.description);
            });

            // Build questions.
            Object.entries(questionsConfig).forEach(([qid, qcfg]) => {
                const q = new Question({ id: qid, ...qcfg });
                const gid = qcfg.group || 'unsorted';
                if (!this.groups[gid]) {
                    this.groups[gid] = new QuestionGroup(gid, 'Unsorted');
                }
                this.groups[gid].addQuestion(q);
                this.questions[qid] = q;
            });

            // Auto-wire onsuccess / onfailure where not set.
            this._autoWire();
        }

        _autoWire() {
            const groupKeys = Object.keys(this.groups);
            groupKeys.forEach((gid, gi) => {
                const qkeys = Object.keys(this.groups[gid].questions);
                qkeys.forEach((qid, qi) => {
                    const q = this.groups[gid].questions[qid];
                    if (!q.onsuccess) {
                        q.onsuccess = (qi < qkeys.length - 1)
                            ? qkeys[qi + 1]
                            : (gi < groupKeys.length - 1)
                                ? Object.keys(this.groups[groupKeys[gi + 1]].questions)[0]
                                : '_finish';
                    }
                    if (!q.onfailure) {
                        q.onfailure = q.onsuccess; // default: same path
                    }
                });
            });
        }

        getQuestion(id) {
            return this.questions[id] || null;
        }

        getNextQuestionId(questionId, isSolved) {
            const q = this.questions[questionId];
            if (!q) return null;
            const nextId = isSolved ? q.onsuccess : q.onfailure;
            return nextId === '_finish' ? null : nextId;
        }

        /** Pick a random unsolved variant page for a question. */
        pickVariantPage(question, solvedVariants) {
            const solved = solvedVariants.getSolvedVariantIndicesFor(
                question.page, question.variants
            );
            let unsolved = [];
            for (let i = 0; i < question.variants; i++) {
                if (!solved.includes(i)) unsolved.push(i);
            }
            if (unsolved.length === 0) {
                // All solved: pick any except current page.
                unsolved = Array.from({ length: question.variants }, (_, i) => i);
            }
            const idx = unsolved[Math.floor(Math.random() * unsolved.length)];
            return question.page + idx;
        }

        /** Build Moodle page URL. */
        getPageUrl(baseSanitizedUrl, pageno) {
            return `${baseSanitizedUrl}&page=${pageno}`;
        }
    }

    // =========================================================================
    // GameEngine — main coordinator
    // =========================================================================

    class GameEngine {
        /**
         * @param {Object}       config        Full compiled config from get_quizconfig
         * @param {Object}       themeConfig   Theme JSON
         * @param {StateManager} stateMgr
         * @param {Object}       initParams    { attemptid, cmid, labelid, sesskey, … }
         */
        constructor(config, themeConfig, stateMgr, initParams) {
            this._config    = config;
            this._theme     = themeConfig;
            this._state     = stateMgr;
            this._params    = initParams;

            this._nav       = new NavigationGraph(config.groups, config.questions);
            this._syncer    = new AjaxLayer.GameStateSyncer(initParams);

            this._currentQuestionId  = null;
            this._targetedEnemy      = null;
            this._enemies            = [];
            this._player             = null;
            this._helper             = null;

            // DOM refs set during init().
            this._battleground       = null;
            this._monstersCamp       = null;
            this._inputElement       = null;
            this._speechBubble       = null;
            this._fader              = null;
            this._preInputField      = null;
            this._postInputField     = null;

            this._videoAnimationLock = false;
        }

        // ------------------------------------------------------------------
        // Init
        // ------------------------------------------------------------------

        init() {
            this._buildGameDOM();
            this._bindInput();
            this._loadCurrentQuestion();
            this._bindBeforeUnload();

            // Start validation polling.
            this._validationTimer = setInterval(
                () => this._updateValidationIndicator(), 1500
            );
        }

        // ------------------------------------------------------------------
        // DOM construction
        // ------------------------------------------------------------------

        _buildGameDOM() {
            const assetBase = this._params.themeAssetUrl;

            // Player.
            this._player = SpriteEngine.buildFromTheme(
                this._theme.player, assetBase, false, false
            );
            this._player.container.classList.add('smg-player-container');
            this._player.container.style.cssText += ';left:40%;bottom:0;';

            // Enemies pool (up to 6, visibility toggled per question).
            this._enemies = this._theme.enemies
                .slice(0, 6)
                .map(ecfg => {
                    const el = SpriteEngine.buildFromTheme(ecfg, assetBase, true, true);
                    el.container.classList.add('smg-enemy-container', 'smg-invisible');
                    el.container.style.position = 'relative';
                    this._bindEnemyEvents(el);
                    return el;
                });

            // Fairy helper.
            const fairyUrl = assetBase + 'ui/' + this._theme.ui.fairy;
            this._helper   = this._buildFairy(fairyUrl);

            // Battleground.
            const bg = document.createElement('div');
            bg.classList.add('smg-battleground');

            const camp = document.createElement('div');
            camp.classList.add('smg-monsters-camp');
            this._enemies.forEach(e => camp.appendChild(e.container));

            const speechBubble = this._buildSpeechBubble();

            bg.appendChild(this._player.container);
            bg.appendChild(camp);
            bg.appendChild(speechBubble);

            this._battleground = bg;
            this._monstersCamp = camp;
            this._speechBubble = speechBubble;

            // Fader.
            const fader = document.createElement('div');
            fader.classList.add('smg-fader');
            this._fader = fader;

            // Enter-spell container.
            const input   = document.querySelector(
                '.formulation.clearfix textarea, .formulation.clearfix input[type=text]'
            );
            input.removeAttribute('readonly');
            this._inputElement = input;

            const pre  = document.createElement('div');
            pre.classList.add('smg-input-surrounding-math');
            const post = document.createElement('div');
            post.classList.add('smg-input-surrounding-math');
            this._preInputField  = pre;
            this._postInputField = post;

            const spellContainer = document.createElement('div');
            spellContainer.classList.add('smg-enter-spell-container');
            spellContainer.appendChild(pre);
            spellContainer.appendChild(input);
            spellContainer.appendChild(post);

            // Mount into Moodle content area.
            const contentNode = document.querySelector('.que .content');
            contentNode.insertBefore(fader, contentNode.firstChild);
            contentNode.appendChild(spellContainer);
            contentNode.appendChild(bg);
        }

        _buildFairy(url) {
            const container = document.createElement('div');
            container.classList.add('smg-fairy-home');
            const img = document.createElement('img');
            img.src = url;
            img.classList.add('smg-fairy-img');
            img.style.cssText = 'height:40px;position:absolute;';
            container.appendChild(img);
            container.onclick = () => this._showNotification();
            return { container, img };
        }

        _buildSpeechBubble() {
            const bubble = document.createElement('div');
            bubble.classList.add('smg-bubble', 'smg-spell', 'smg-spell-in-progress');
            bubble.onclick = () => this._onSpellCast();
            return bubble;
        }

        _bindEnemyEvents(enemy) {
            enemy.container.addEventListener('click', () => {
                this._enemies.forEach(e => e.container.classList.remove('smg-targeted'));
                enemy.container.classList.add('smg-targeted');
                this._targetedEnemy = enemy;
                this._updateInputSurroundingMath(enemy);
            });
            enemy.container.addEventListener('mouseover', () => {
                this._helper.container.classList.add('smg-at-enemy');
            });
            enemy.container.addEventListener('mouseout', () => {
                this._helper.container.classList.remove('smg-at-enemy');
            });
        }

        _bindInput() {
            this._inputElement.addEventListener('keypress', e => {
                if (e.key === 'Enter') { this._speechBubble.click(); }
            });
        }

        _bindBeforeUnload() {
            window.addEventListener('beforeunload', () => {
                const snap = this._state.toJSON();
                // Best-effort sync on unload (sendBeacon fallback possible).
                this._syncer.saveFullState(
                    snap.solved, snap.solved_variants, snap.scores
                );
            });
        }

        // ------------------------------------------------------------------
        // Spell cast → AJAX submission
        // ------------------------------------------------------------------

        async _onSpellCast() {
            if (this._videoAnimationLock) return;
            if (!this._speechBubble.classList.contains('smg-spell-in-progress')) return;

            this._speechBubble.classList.remove('smg-spell-in-progress');

            const form = document.getElementById('responseform');
            if (!form) {
                this._speechBubble.classList.add('smg-spell-in-progress');
                return;
            }

            const enemyIndex = this._targetedEnemy
                ? this._enemies.indexOf(this._targetedEnemy)
                : 0;

            const submitter = new AjaxLayer.StackSubmitter(
                form,
                this._inputElement,
                this._targetedEnemy,
                enemyIndex
            );

            try {
                const result = await submitter.submit();
                this._processResult(result);
            } catch (err) {
                console.error('[GameEngine] Submission error:', err.code, err.message);
                this._speechBubble.classList.add('smg-spell-in-progress');
                // Show error indicator.
                document.querySelector('.smg-enter-spell-container')?.classList.add('smg-error');
            }
        }

        _processResult(result) {
            const currentQ = this._nav.getQuestion(this._currentQuestionId);
            if (!currentQ) return;

            const variantPage = this._getCurrentVariantPage();

            if (result.outcome === 'correct') {
                const wasNew = this._state.markSolved(this._currentQuestionId, variantPage);

                // Notify server (non-blocking).
                this._syncer.notifyOutcome(
                    this._currentQuestionId, variantPage, 'correct'
                );

                if (wasNew) {
                    this._animateVictory(() => this._goToNextScene(true));
                } else {
                    this._showNotification(this._randomVictoryText());
                    setTimeout(() => this._goToNextScene(true), 2500);
                }
            } else {
                this._syncer.notifyOutcome(
                    this._currentQuestionId, variantPage, result.outcome
                );
                this._animateAttack(false);
                this._showNotification(result.feedbackHtml);
                this._speechBubble.classList.add('smg-spell-in-progress');
            }
        }

        // ------------------------------------------------------------------
        // Scene management (delegated to scene_manager in full implementation)
        // ------------------------------------------------------------------

        _loadCurrentQuestion() {
            // Detect current page from URL.
            const match = window.location.href.match(/[?&]page=(\d+)/);
            const page  = match ? parseInt(match[1], 10) : 0;

            // Find question by page number.
            const q = Object.values(this._nav.questions)
                .find(q => q.page <= page && page < q.page + q.variants);

            if (q) {
                this._currentQuestionId = q.id;
                this._renderQuestion(q, page);
            }
        }

        _renderQuestion(question, page) {
            // Show/hide enemies based on question's input count.
            // (Full implementation would read slot info and show/hide accordingly.)
            this._enemies.forEach((enemy, i) => {
                enemy.container.classList.toggle('smg-invisible', i >= 1);
            });
        }

        _goToNextScene(proceed) {
            const nextId = this._nav.getNextQuestionId(
                this._currentQuestionId,
                this._state.isSolved(this._currentQuestionId)
            );

            if (!nextId) {
                this._handleFinish();
                return;
            }

            const nextQ   = this._nav.getQuestion(nextId);
            const page    = this._nav.pickVariantPage(nextQ, this._state);
            const baseUrl = this._getBaseUrl();
            const url     = this._nav.getPageUrl(baseUrl, page);

            this._fadeToUrl(url);
        }

        _fadeToUrl(url) {
            this._videoAnimationLock = true;
            this._fader.classList.add('smg-fade-out');
            this._fader.addEventListener('transitionend', () => {
                window.location.href = url;
            }, { once: true });
        }

        _handleFinish() {
            const finishLink = document.querySelector('.endtestlink.aalink');
            const url = finishLink?.href || 'summary.php';
            this._fadeToUrl(url);
        }

        _getCurrentVariantPage() {
            const match = window.location.href.match(/[?&]page=(\d+)/);
            return match ? parseInt(match[1], 10) : 0;
        }

        _getBaseUrl() {
            let url = window.location.href;
            const cut = Math.min(
                url.indexOf('&page=')     > -1 ? url.indexOf('&page=')     : Infinity,
                url.indexOf('&scrollpos=') > -1 ? url.indexOf('&scrollpos=') : Infinity,
                url.indexOf('#')           > -1 ? url.indexOf('#')           : Infinity
            );
            return cut < Infinity ? url.slice(0, cut) : url;
        }

        // ------------------------------------------------------------------
        // Animations (stubs — full implementations use CSS transitions)
        // ------------------------------------------------------------------

        _animateVictory(callback) {
            this._videoAnimationLock = true;
            this._player.setState('attack', 'once', 'idle', () => {
                this._enemies.forEach(e => {
                    if (!e.container.classList.contains('smg-invisible')) {
                        e.setState('die', 'toend', 'idle', () => {
                            this._videoAnimationLock = false;
                            if (callback) callback();
                        });
                    }
                });
            });
        }

        _animateAttack(lastStroke) {
            this._player.setState('attack', 'once', 'idle');
            if (!lastStroke) {
                this._enemies.forEach(e => {
                    if (!e.container.classList.contains('smg-invisible')) {
                        e.setState('hurt', 'once', 'idle');
                    }
                });
            }
        }

        _updateInputSurroundingMath(enemy) {
            // Clear.
            while (this._preInputField.firstChild)  this._preInputField.removeChild(this._preInputField.firstChild);
            while (this._postInputField.firstChild) this._postInputField.removeChild(this._postInputField.firstChild);

            if (!enemy) return;

            const qmark = enemy.container.querySelector('.smg-input-replacer');
            enemy.container.querySelectorAll('.smg-formula-container .nolink').forEach(mathNode => {
                const clone = mathNode.cloneNode(true);
                const questionMarkIsBefore = qmark
                    && (mathNode.compareDocumentPosition(qmark) & Node.DOCUMENT_POSITION_PRECEDING);
                if (questionMarkIsBefore) {
                    this._postInputField.appendChild(clone);
                } else {
                    this._preInputField.appendChild(clone);
                }
            });
        }

        _updateValidationIndicator() {
            const feedback = document.querySelector('.stackinputfeedback');
            if (!feedback) return;
            const hasError = feedback.querySelector('.stackinputerror');
            document.querySelector('.smg-enter-spell-container')
                ?.classList.toggle('smg-error', !!hasError);
        }

        _showNotification(html = '') {
            // Simplified: in full implementation, shows speech bubble.
            console.log('[GameEngine] Notification:', html);
        }

        _randomVictoryText() {
            const texts = [
                'You have made it! Keep up the good work!',
                'Super! That was right!',
                'Wonderful! You\'ve made it!',
            ];
            return texts[Math.floor(Math.random() * texts.length)];
        }
    }

    return { GameEngine, NavigationGraph, QuestionGroup, Question };
});
