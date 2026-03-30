define(['core/ajax'], function(Ajax) {
    const call = function(methodname, args) {
        return Ajax.call([{
            methodname: methodname,
            args: args
        }])[0];
    };

    const hasActivityIdentity = function(config) {
        return !!(config && parseInt(config.cmid, 10));
    };

    const getActivityArgs = function(config, extra) {
        const args = {
            cmid: parseInt(config && config.cmid, 10) || 0,
            modname: (config && config.modname) || 'quiz',
            instanceid: parseInt(
                (config && (config.instanceid || config.quizid)) || 0,
                10
            ) || 0
        };
        return Object.assign(args, extra || {});
    };

    return {
        getQuizConfig: function(configorquizid) {
            if (typeof configorquizid === 'object' && hasActivityIdentity(configorquizid)) {
                return call('local_stackmathgame_get_activity_config', getActivityArgs(configorquizid));
            }
            return call('local_stackmathgame_get_quiz_config', {quizid: configorquizid});
        },
        getProfileState: function(configorquizid) {
            if (typeof configorquizid === 'object' && hasActivityIdentity(configorquizid)) {
                return call('local_stackmathgame_get_activity_profile_state', getActivityArgs(configorquizid));
            }
            return call('local_stackmathgame_get_profile_state', {quizid: configorquizid});
        },
        submitAnswer: function(attemptid, slot, answers) {
            return call('local_stackmathgame_submit_answer', {
                attemptid: attemptid,
                slot: slot,
                answers: answers
            });
        },
        saveProgress: function(payload) {
            if (hasActivityIdentity(payload)) {
                return call('local_stackmathgame_save_activity_progress', payload);
            }
            return call('local_stackmathgame_save_progress', payload);
        },
        getNarrative: function(configorquizid, scene) {
            if (typeof configorquizid === 'object' && hasActivityIdentity(configorquizid)) {
                return call(
                    'local_stackmathgame_get_activity_narrative',
                    getActivityArgs(configorquizid, {scene: scene})
                );
            }
            return call('local_stackmathgame_get_narrative', {quizid: configorquizid, scene: scene});
        },
        prefetchNextNode: function(configorquizid, currentslot) {
            if (typeof configorquizid === 'object' && hasActivityIdentity(configorquizid)) {
                return call(
                    'local_stackmathgame_prefetch_next_activity_node',
                    getActivityArgs(configorquizid, {currentslot: currentslot})
                );
            }
            return call('local_stackmathgame_prefetch_next_node', {
                quizid: configorquizid,
                currentslot: currentslot
            });
        }
    };
});
