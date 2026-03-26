define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    const state = {
        config: null,
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

    function ensureShell() {
        let shell = document.querySelector('.smg-runtime-shell');
        if (shell) {
            return shell;
        }
        const question = getCurrentQuestion();
        shell = document.createElement('div');
        shell.className = 'smg-runtime-shell alert alert-secondary';
        shell.innerHTML = [
            '<div class="smg-runtime-header"><strong>STACK Math Game</strong></div>',
            '<div class="smg-runtime-meta"></div>',
            '<div class="smg-runtime-narrative"></div>',
            '<div class="smg-runtime-feedback"></div>',
            '<div class="smg-runtime-actions mt-2">',
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
            document.querySelectorAll('.smg-native-controls').forEach((node) => {
                node.classList.toggle('smg-native-force-visible');
            });
        });
        return shell;
    }

    function renderShell() {
        const shell = ensureShell();
        const profile = state.store.profile || {};
        const design = state.store.design || {};
        const nextnode = state.store.nextnode || {};
        const narrative = Array.isArray(state.store.narrative) ? state.store.narrative : [];
        shell.querySelector('.smg-runtime-meta').innerHTML = [
            '<div><strong>Score:</strong> ' + (profile.score || 0) + '</div>',
            '<div><strong>XP:</strong> ' + (profile.xp || 0) + '</div>',
            '<div><strong>Level:</strong> ' + (profile.levelno || 1) + '</div>',
            '<div><strong>Design:</strong> ' + (design.name || '-') + '</div>',
            '<div><strong>Next:</strong> ' + (nextnode.nodekey || '-') + '</div>'
        ].join('');
        shell.querySelector('.smg-runtime-narrative').innerHTML = narrative.map((line) => '<div>' + $('<div>').text(line).html() + '</div>').join('');
        const feedback = state.store.lastsubmit;
        if (feedback) {
            shell.querySelector('.smg-runtime-feedback').innerHTML = [
                '<div><strong>Status:</strong> ' + $('<div>').text(feedback.state || '').html() + '</div>',
                '<div><strong>Message:</strong> ' + $('<div>').text(feedback.message || '').html() + '</div>',
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
        return call('local_stackmathgame_get_question_fragment', {
            attemptid: attemptid,
            slot: slot
        }).then((response) => {
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
            input.addEventListener('focus', () => {
                state.activeInput = input;
            });
        });
    }

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
        }).then((response) => {
            state.store.lastsubmit = response;
            if (response.profile) {
                state.store.profile = response.profile;
            }
            return refreshQuestionFromFragment().then((ok) => {
                if (!ok) {
                    return refreshQuestionFromPage();
                }
                return null;
            }).then(() => {
                return Promise.all([
                    call('local_stackmathgame_get_narrative', {quizid: state.config.quizid, scene: response.cannext ? 'victory' : 'defeat'}),
                    call('local_stackmathgame_prefetch_next_node', {quizid: state.config.quizid, currentslot: slot})
                ]).then((results) => {
                    state.store.narrative = results[0] && results[0].lines ? results[0].lines : [];
                    state.store.nextnode = results[1] && results[1].nextnode ? results[1].nextnode : null;
                    renderShell();
                });
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
            state.store.profile = results[1] ? results[1].profile : null;
            state.store.narrative = results[2] && results[2].lines ? results[2].lines : [];
            state.store.nextnode = results[3] && results[3].nextnode ? results[3].nextnode : null;
            renderShell();
        });
    }

    function init(config) {
        state.config = config || {};
        document.body.classList.add('smg-game-active');
        bindInputs();
        bootstrapData().catch(Notification.exception);
    }

    return {init: init};
});
