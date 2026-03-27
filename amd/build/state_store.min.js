define([], function() {
    const createStore = function(initialState) {
        let state = Object.assign({}, initialState || {});
        let listeners = [];

        const notify = function() {
            listeners.forEach(function(listener) {
                listener(state);
            });
        };

        return {
            getState: function() {
                return state;
            },
            replace: function(nextState) {
                state = Object.assign({}, nextState || {});
                notify();
            },
            patch: function(partial) {
                state = Object.assign({}, state, partial || {});
                notify();
            },
            subscribe: function(listener) {
                listeners.push(listener);
                return function() {
                    listeners = listeners.filter(function(item) {
                        return item !== listener;
                    });
                };
            }
        };
    };

    return {createStore: createStore};
});
