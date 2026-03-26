define([], function() {
    const createBus = function() {
        const handlers = {};
        return {
            on: function(name, handler) {
                handlers[name] = handlers[name] || [];
                handlers[name].push(handler);
                return function() {
                    handlers[name] = (handlers[name] || []).filter(function(item) {
                        return item !== handler;
                    });
                };
            },
            emit: function(name, payload) {
                (handlers[name] || []).forEach(function(handler) {
                    handler(payload);
                });
            }
        };
    };

    return {createBus: createBus};
});
