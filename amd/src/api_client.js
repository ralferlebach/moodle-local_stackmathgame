define(['core/ajax'], function(Ajax) {
    const call = function(methodname, args) {
        return Ajax.call([{
            methodname: methodname,
            args: args
        }])[0];
    };

    return {
        getQuizConfig: function(quizid) {
            return call('local_stackmathgame_get_quiz_config', {quizid: quizid});
        },
        getProfileState: function(quizid) {
            return call('local_stackmathgame_get_profile_state', {quizid: quizid});
        },
        submitAnswer: function(attemptid, slot, answers) {
            return call('local_stackmathgame_submit_answer', {
                attemptid: attemptid,
                slot: slot,
                answers: answers
            });
        },
        saveProgress: function(payload) {
            return call('local_stackmathgame_save_progress', payload);
        },
        getNarrative: function(quizid, scene) {
            return call('local_stackmathgame_get_narrative', {quizid: quizid, scene: scene});
        },
        prefetchNextNode: function(quizid, currentslot) {
            return call('local_stackmathgame_prefetch_next_node', {
                quizid: quizid,
                currentslot: currentslot
            });
        }
    };
});
