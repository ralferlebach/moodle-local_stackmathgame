define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    const state = {
        config: null,
        runtime: {},
        store: {
            profile: null,
            design: null,
            narrative: [],
            nextnode: null,
            lastsubmit: null
        },
        activeInput: null
    };

    function getAttemptId() {
        const params = new URLSearchParams(window.location.search);
        const raw = params.get('attempt') || $('input[name="attempt"]').val() || '0';
        return parseInt(raw, 10) || 0;
    }

    function getCurrentQuestion() {
        return document.querySelector('.que[data-smg-controlled="1"]') || document.querySelector('.que');
    }

    function getCurrentSlot() {
        const question = getCurrentQuestion();
        return question ? parseInt(question.getAttribute('data-smg-slot') || '0', 10) || 0 : 0;
    }

    function collectAnswers() {
        const question = getCurrentQuestion();
        const result = [];
        if (!question) {
            return result;
        }
        const seen = new Set();
        question.querySelectorAll('input, textarea, select').forEach((node) => {
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
        ['attempt', 'thispage', 'nextpage', 'slots', 'sesskey'].forEach((name) => {
            const field = document.querySelector('input[name="' + name + '"]');
            if (field) {
                result.push({name: name, value: field.value || ''});
            }
        });
        return result;
    }

    function call(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    }

    function parseJson(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function ensureShell() {
        let shell = document.querySelector('.smg-runtime-shell');
        if (shell) {
            return shell;
        }
        const question = getCurrentQuestion();
        shell = document.createElement('div');
        shell.className = 'smg-runtime-shell alert alert-secondary';
        shell.innerHTML = [
            '<div class="smg-runtime-header d-flex align-items-center gap-3">',
            '  <div class="smg-runtime-thumb"></div>',
            '  <div class="smg-runtime-headings"><strong>STACK Math Game</strong><div class="smg-runtime-mode small text-muted"></div></div>',
            '</div>',
            '<div class="smg-runtime-meta mt-2"></div>',
            '<div class="smg-runtime-narrative mt-2"></div>',
            '<div class="smg-runtime-feedback mt-2"></div>',
            '<div class="smg-runtime-actions mt-3">',
            '  <button type="button" class="btn btn-primary btn-sm smg-action-check">Game check</button> ',
            '  <button type="button" class="btn btn-secondary btn-sm smg-action-native">Use native controls</button>',
            '</div>'
        ].join('');
        if (question && question.parentNode) {
            question.parentNode.insertBefore(shell, question);
        } else {
            document.body.insertBefore(shell, document.body.firstChild);
        }
        shell.querySelector('.smg-action-check').addEventListener('click', handleGameCheck);
        shell.querySelector('.smg-action-native').addEventListener('click', function() {
            document.querySelectorAll('.smg-native-controls').forEach((node) => node.classList.toggle('smg-native-force-visible'));
        });
        return shell;
    }

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function renderShell() {
        const shell = ensureShell();
        const profile = state.store.profile || {};
        const design = state.store.design || {};
        const summary = parseJson(profile.summaryjson || '{}', {});
        const runtime = state.runtime || {};
        const nextnode = state.store.nextnode || {};
        const narrative = Array.isArray(state.store.narrative) ? state.store.narrative : [];
        shell.className = 'smg-runtime-shell alert alert-secondary ' + (runtime.themeclass || '');
        shell.querySelector('.smg-runtime-mode').innerHTML = esc(runtime.modekey || design.modecomponent || '-');
        const thumb = shell.querySelector('.smg-runtime-thumb');
        if (runtime.thumbnailurl) {
            thumb.innerHTML = '<img src="' + esc(runtime.thumbnailurl) + '" alt="" style="max-width:64px;max-height:64px;" />';
        } else {
            thumb.innerHTML = '';
        }
        shell.querySelector('.smg-runtime-meta').innerHTML = [
            '<div><strong>Score:</strong> ' + (profile.score || 0) + '</div>',
            '<div><strong>XP:</strong> ' + (profile.xp || 0) + ' <span class="text-muted">(' + (summary.levelprogress || 0) + '/100)</span></div>',
            '<div><strong>Level:</strong> ' + (profile.levelno || 1) + '</div>',
            '<div><strong>Design:</strong> ' + esc(design.name || '-') + '</div>',
            '<div><strong>Mode:</strong> ' + esc(runtime.modekey || '-') + '</div>',
            '<div><strong>Solved:</strong> ' + (summary.solvedcount || 0) + ' / <strong>Partial:</strong> ' + (summary.partialcount || 0) + ' / <strong>Tracked:</strong> ' + (summary.trackedslots || 0) + '</div>',
            '<div><strong>Next:</strong> ' + esc(nextnode.nodekey || '-') + '</div>'
        ].join('');
        shell.querySelector('.smg-runtime-narrative').innerHTML = narrative.map((line) => '<div>' + esc(line) + '</div>').join('');
        const feedback = state.store.lastsubmit;
        if (feedback) {
            shell.querySelector('.smg-runtime-feedback').innerHTML = [
                '<div><strong>Status:</strong> ' + esc(feedback.state || '') + ' <span class="text-muted">(prev: ' + esc(feedback.previousstate || '-') + ')</span></div>',
                '<div><strong>Message:</strong> ' + esc(feedback.message || '') + '</div>',
                '<div><strong>Score delta:</strong> ' + (feedback.scoredelta || 0) + ' / <strong>XP delta:</strong> ' + (feedback.xpdelta || 0) + '</div>'
            ].join('');
        }
    }

    function refreshQuestionFromFragment() {
        const attemptid = getAttemptId();
        const slot = getCurrentSlot();
        if (!attemptid || !slot) {
            return Promise.resolve(false);
        }
        return call('local_stackmathgame_get_question_fragment', {attemptid: attemptid, slot: slot}).then((response) => {
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
        }).catch(() => false);
    }

    function refreshQuestionFromPage() {
        return fetch(window.location.href, {credentials: 'same-origin'}).then((response) => response.text()).then((html) => {
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
        }).catch(Notification.exception);
    }

    function bindInputs() {
        document.querySelectorAll('.que input[type="text"], .que textarea').forEach((input) => {
            input.addEventListener('focus', () => { state.activeInput = input; });
        });
    }

    function handleGameCheck() {
        const attemptid = getAttemptId();
        const slot = getCurrentSlot();
        const answers = collectAnswers();
        if (!attemptid || !slot) {
            return;
        }
        call('local_stackmathgame_submit_answer', {attemptid: attemptid, slot: slot, answers: answers}).then((response) => {
            state.store.lastsubmit = response;
            if (response.profile) {
                state.store.profile = response.profile;
            }
            return refreshQuestionFromFragment().then((ok) => {
                if (!ok) {
                    return refreshQuestionFromPage();
                }
                return null;
            }).then(() => Promise.all([
                call('local_stackmathgame_get_narrative', {quizid: state.config.quizid, scene: response.cannext ? 'victory' : 'defeat'}),
                call('local_stackmathgame_prefetch_next_node', {quizid: state.config.quizid, currentslot: slot}),
                call('local_stackmathgame_get_profile_state', {quizid: state.config.quizid})
            ])).then((results) => {
                state.store.narrative = results[0] && results[0].lines ? results[0].lines : [];
                state.store.nextnode = results[1] && results[1].nextnode ? results[1].nextnode : null;
                if (results[2] && results[2].profile) {
                    state.store.profile = results[2].profile;
                    state.store.design = results[2].design || state.store.design;
                }
                renderShell();
            });
        }).catch(Notification.exception);
    }

    function bootstrapData() {
        return Promise.all([
            call('local_stackmathgame_get_quiz_config', {quizid: state.config.quizid}),
            call('local_stackmathgame_get_profile_state', {quizid: state.config.quizid}),
            call('local_stackmathgame_get_narrative', {quizid: state.config.quizid, scene: 'world_enter'}),
            call('local_stackmathgame_prefetch_next_node', {quizid: state.config.quizid, currentslot: getCurrentSlot() || 0})
        ]).then((results) => {
            state.store.design = results[0] ? results[0].design : null;
            state.runtime = parseJson(results[0] && results[0].runtimejson ? results[0].runtimejson : '{}', {});
            state.store.profile = results[1] ? results[1].profile : null;
            state.store.narrative = results[2] && results[2].lines ? results[2].lines : [];
            state.store.nextnode = results[3] && results[3].nextnode ? results[3].nextnode : null;
            renderShell();
            bindInputs();
        });
    }

    function init(config) {
        state.config = config || {};
        if (!state.config.quizid) {
            return;
        }
        bootstrapData().catch(Notification.exception);
    }

    return {init: init};
});
