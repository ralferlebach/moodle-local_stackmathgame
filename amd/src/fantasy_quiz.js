define(['jquery'], function($) {
    const selectors = {
        question: '.que',
        nativeSubmit: '.submitbtns, .submitbtn, .im-controls',
        feedback: '.stackprtfeedback, .outcome, .specificfeedback',
        input: 'input[type="text"], textarea'
    };

    const state = {
        config: null,
        activeInput: null
    };

    function wrapFeedback() {
        document.querySelectorAll(selectors.feedback).forEach((node) => {
            if (node.closest('.smg-feedback-bubble')) {
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.className = 'smg-feedback-bubble alert alert-info';
            wrapper.setAttribute('data-smg-role', 'feedback');
            node.parentNode.insertBefore(wrapper, node);
            wrapper.appendChild(node);
        });
    }

    function hideNativeSubmit() {
        document.querySelectorAll(selectors.nativeSubmit).forEach((node) => {
            node.classList.add('smg-native-hidden');
            node.setAttribute('aria-hidden', 'true');
        });
    }

    function installMathToolbar() {
        if (document.querySelector('.smg-math-toolbar')) {
            return;
        }

        const toolbar = document.createElement('div');
        toolbar.className = 'smg-math-toolbar';
        ['+', '-', '*', '/', '(', ')', '^', '='].forEach((symbol) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-secondary btn-sm';
            button.textContent = symbol;
            button.addEventListener('click', () => {
                if (!state.activeInput) {
                    return;
                }
                const input = state.activeInput;
                const start = input.selectionStart || input.value.length;
                const end = input.selectionEnd || input.value.length;
                input.value = input.value.slice(0, start) + symbol + input.value.slice(end);
                input.focus();
                input.selectionStart = input.selectionEnd = start + symbol.length;
                input.dispatchEvent(new Event('input', {bubbles: true}));
            });
            toolbar.appendChild(button);
        });

        document.body.appendChild(toolbar);
        document.querySelectorAll(selectors.input).forEach((input) => {
            input.addEventListener('focus', () => {
                state.activeInput = input;
            });
        });
    }

    function addStatusBanner() {
        if (document.querySelector('.smg-status-banner')) {
            return;
        }
        const target = document.querySelector('#page-header') || document.body.firstElementChild;
        if (!target || !target.parentNode) {
            return;
        }
        const banner = document.createElement('div');
        banner.className = 'smg-status-banner alert alert-secondary';
        banner.textContent = M.util.get_string('gamestatusready', 'local_stackmathgame');
        target.parentNode.insertBefore(banner, target.nextSibling);
    }

    function decorateQuestions() {
        document.querySelectorAll(selectors.question).forEach((question, index) => {
            question.dataset.smgControlled = 'true';
            question.dataset.smgIndex = String(index + 1);
        });
    }

    function init(config) {
        state.config = config || {};
        hideNativeSubmit();
        wrapFeedback();
        installMathToolbar();
        addStatusBanner();
        decorateQuestions();
        document.body.classList.add('smg-game-active');
    }

    return {init: init};
});
