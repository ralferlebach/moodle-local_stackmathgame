define([
    'core/notification',
    'local_stackmathgame/api_client',
    'local_stackmathgame/state_store',
    'local_stackmathgame/event_bus',
    'local_stackmathgame/question_controller',
    'local_stackmathgame/navigation_controller',
    'local_stackmathgame/ui_renderer'
], function(Notification, ApiClient, StateStore, EventBus, QuestionController, NavigationController, UiRenderer) {
    const buildStrings = function() {
        return {
            loading: M.util.get_string('gamelayerloading', 'local_stackmathgame'),
            ready: M.util.get_string('gamestatusready', 'local_stackmathgame'),
            error: M.util.get_string('gameruntimeerror', 'local_stackmathgame'),
            check: M.util.get_string('gamecheckanswer', 'local_stackmathgame'),
            native: M.util.get_string('gameusenative', 'local_stackmathgame'),
            profile: M.util.get_string('gameprofile', 'local_stackmathgame'),
            design: M.util.get_string('gamecurrentdesign', 'local_stackmathgame'),
            nextnode: M.util.get_string('gamenextnode', 'local_stackmathgame')
        };
    };

    const init = function(config) {
        const bus = EventBus.createBus();
        const store = StateStore.createStore({
            loading: true,
            config: config || {},
            strings: buildStrings(),
            profile: null,
            design: null,
            narrativeLines: [],
            nextNodeDescription: '',
            error: ''
        });

        UiRenderer.ensureShell();
        store.subscribe(UiRenderer.render);
        UiRenderer.render(store.getState());

        const wireActions = function() {
            const shell = UiRenderer.ensureShell();
            shell.addEventListener('click', function(e) {
                const button = e.target.closest('[data-smg-action]');
                if (!button) {
                    return;
                }
                const action = button.getAttribute('data-smg-action');
                if (action === 'native') {
                    QuestionController.triggerNativeSubmit();
                    return;
                }
                if (action === 'check') {
                    const attemptid = QuestionController.getAttemptId();
                    const slot = QuestionController.getCurrentSlot();
                    const answers = QuestionController.collectAnswers();
                    if (!attemptid || !slot) {
                        store.patch({error: 'Missing attempt or slot context.'});
                        return;
                    }
                    store.patch({loading: true, error: ''});
                    ApiClient.submitAnswer(attemptid, slot, answers)
                        .then(function(result) {
                            store.patch({
                                loading: false,
                                lastSubmit: result,
                                profile: result.profile || store.getState().profile,
                                design: result.design || store.getState().design,
                                narrativeLines: [result.message || ''],
                            });
                            bus.emit('submitted', result);
                            return ApiClient.saveProgress(Object.assign({
                                scoredelta: 0,
                                xpdelta: 0,
                                softcurrencydelta: 0,
                                hardcurrencydelta: 0,
                                progressjson: JSON.stringify({lastslot: slot, lastsubmitstatus: result.status || ''}),
                                flagsjson: JSON.stringify({lastquestionid: result.questionid || 0}),
                                statsjson: JSON.stringify({submissioncount: answers.length}),
                                eventtype: 'frontend_progress_sync'
                            }, config.cmid ? {
                                cmid: config.cmid,
                                modname: config.modname || 'quiz',
                                instanceid: config.instanceid || config.quizid
                            } : {
                                quizid: config.quizid
                            }));
                        })
                        .then(function(progressResult) {
                            store.patch({profile: progressResult.profile || store.getState().profile});
                            return ApiClient.prefetchNextNode(config, QuestionController.getCurrentSlot());
                        })
                        .then(function(nextNodeResult) {
                            store.patch({
                                nextNode: nextNodeResult.nextnode || null,
                                nextNodeDescription: NavigationController.describeNextNode(nextNodeResult.nextnode || null),
                                loading: false
                            });
                        })
                        .catch(function(error) {
                            store.patch({loading: false, error: error && error.message ? error.message : 'Request failed.'});
                            Notification.exception(error);
                        });
                }
            });
        };

        wireActions();

        Promise.all([
            ApiClient.getQuizConfig(config),
            ApiClient.getProfileState(config),
            ApiClient.getNarrative(config, 'world_enter'),
            ApiClient.prefetchNextNode(config, 0)
        ]).then(function(results) {
            const quizConfig = results[0];
            const profileState = results[1];
            const narrative = results[2];
            const nextNode = results[3];
            store.patch({
                loading: false,
                quizConfig: quizConfig,
                profile: (profileState && profileState.profile) || (quizConfig && quizConfig.profile) || null,
                design: (quizConfig && quizConfig.design) || (profileState && profileState.design) || null,
                narrativeLines: (narrative && narrative.lines) || [],
                nextNode: nextNode.nextnode || null,
                nextNodeDescription: NavigationController.describeNextNode(nextNode.nextnode || null)
            });
        }).catch(function(error) {
            store.patch({loading: false, error: error && error.message ? error.message : 'Initialisation failed.'});
            Notification.exception(error);
        });
    };

    return {init: init};
});
