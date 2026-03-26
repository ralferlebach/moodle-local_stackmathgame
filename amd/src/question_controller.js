define([], function() {
    const getAttemptId = function() {
        const params = new URLSearchParams(window.location.search);
        return parseInt(params.get('attempt') || '0', 10);
    };

    const getCurrentQuestion = function() {
        return document.querySelector('.que[data-smg-controlled="1"]') || document.querySelector('.que');
    };

    const getCurrentSlot = function() {
        const question = getCurrentQuestion();
        if (!question) {
            return 0;
        }
        return parseInt(question.getAttribute('data-smg-slot') || '0', 10);
    };

    const collectAnswers = function() {
        const question = getCurrentQuestion();
        if (!question) {
            return [];
        }
        const fields = question.querySelectorAll('input, textarea, select');
        const answers = [];
        fields.forEach(function(field) {
            if (!field.name || field.disabled || field.type === 'hidden') {
                return;
            }
            if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                return;
            }
            answers.push({name: field.name, value: field.value});
        });
        return answers;
    };

    const triggerNativeSubmit = function() {
        const submit = document.querySelector('.smg-native-controls input[type="submit"], .smg-native-controls button[type="submit"], .submitbtns input[type="submit"], .submitbtns button[type="submit"]');
        if (submit) {
            submit.click();
            return true;
        }
        return false;
    };

    return {
        getAttemptId: getAttemptId,
        getCurrentSlot: getCurrentSlot,
        collectAnswers: collectAnswers,
        triggerNativeSubmit: triggerNativeSubmit
    };
});
