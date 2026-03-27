define([], function() {
    const describeNextNode = function(nextnode) {
        if (!nextnode || !nextnode.nodetype) {
            return '';
        }
        if (nextnode.nodetype === 'end') {
            return 'end';
        }
        return [nextnode.nodetype, nextnode.nodekey || ('slot_' + nextnode.slotnumber)].join(':');
    };

    return {
        describeNextNode: describeNextNode
    };
});
