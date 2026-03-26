define([], function() {
    const ensureShell = function() {
        let shell = document.querySelector('.smg-runtime-shell');
        if (shell) {
            return shell;
        }
        shell = document.createElement('section');
        shell.className = 'smg-runtime-shell card mb-3';
        shell.innerHTML = '' +
            '<div class="card-body">' +
                '<div class="smg-runtime-header d-flex justify-content-between align-items-center mb-2">' +
                    '<strong class="smg-runtime-title">STACK Math Game</strong>' +
                    '<span class="badge text-bg-secondary smg-runtime-badge">runtime</span>' +
                '</div>' +
                '<div class="smg-runtime-status small text-muted mb-2"></div>' +
                '<div class="smg-runtime-profile mb-2"></div>' +
                '<div class="smg-runtime-narrative alert alert-info d-none"></div>' +
                '<div class="smg-runtime-next small text-muted mb-2"></div>' +
                '<div class="smg-runtime-actions btn-group btn-group-sm" role="group">' +
                    '<button type="button" class="btn btn-primary" data-smg-action="check"></button>' +
                    '<button type="button" class="btn btn-outline-secondary" data-smg-action="native"></button>' +
                '</div>' +
            '</div>';
        const firstQuestion = document.querySelector('.que') || document.querySelector('#region-main');
        if (firstQuestion && firstQuestion.parentNode) {
            firstQuestion.parentNode.insertBefore(shell, firstQuestion);
        } else {
            document.body.prepend(shell);
        }
        return shell;
    };

    const render = function(state) {
        const shell = ensureShell();
        const strings = state.strings || {};
        shell.querySelector('[data-smg-action="check"]').textContent = strings.check || 'Game check';
        shell.querySelector('[data-smg-action="native"]').textContent = strings.native || 'Use native controls';

        const status = shell.querySelector('.smg-runtime-status');
        const profile = shell.querySelector('.smg-runtime-profile');
        const narrative = shell.querySelector('.smg-runtime-narrative');
        const next = shell.querySelector('.smg-runtime-next');

        if (state.loading) {
            status.textContent = strings.loading || 'Loading game layer...';
        } else if (state.error) {
            status.textContent = (strings.error || 'Runtime error') + ': ' + state.error;
        } else if (state.lastSubmit && state.lastSubmit.message) {
            status.textContent = state.lastSubmit.message;
        } else {
            status.textContent = strings.ready || 'Game runtime ready';
        }

        const profiledata = state.profile || {};
        profile.innerHTML = '' +
            '<div><strong>' + (strings.profile || 'Profile') + '</strong></div>' +
            '<div>Score: ' + (profiledata.score || 0) + ' · XP: ' + (profiledata.xp || 0) + ' · Level: ' + (profiledata.levelno || 1) + '</div>' +
            '<div>' + (strings.design || 'Design') + ': ' + ((state.design && state.design.name) || '—') + '</div>';

        const lines = state.narrativeLines || [];
        if (lines.length) {
            narrative.classList.remove('d-none');
            narrative.innerHTML = lines.map(function(line) {
                return '<div>' + String(line)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;') + '</div>';
            }).join('');
        } else {
            narrative.classList.add('d-none');
            narrative.innerHTML = '';
        }

        if (state.nextNodeDescription) {
            next.textContent = (strings.nextnode || 'Next node') + ': ' + state.nextNodeDescription;
        } else {
            next.textContent = '';
        }
    };

    return {
        ensureShell: ensureShell,
        render: render
    };
});
