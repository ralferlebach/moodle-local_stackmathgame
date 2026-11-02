// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Jest tests for local_stackmathgame/game_engine: the check flow under failure (re-audit N03),
 * the unsaved-changes state (N04), the presented slot (N06) and the session key (N01).
 *
 * The engine runs against stubs of Moodle's AJAX layer, the change checker and the mode loader,
 * so every request can be made to fail, to succeed or to hang on its own. game_core and, where
 * the panel matters, the real RPG mode are loaded from source: the bugs these tests pin down lived
 * in the interplay between the engine and the mode.
 *
 * @module     local_stackmathgame/tests/game_engine.test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { loadAmd } = require('./amd_loader');

const M = { util: { get_string: (key) => key } };

/** The core_form/changechecker API of Moodle 4.5, as far as the engine uses it. */
const ChangeChecker = {
  resetFormDirtyState: (form) => {
    form.dataset.formSubmitted = 'false';
    form.dataset.formDirty = 'false';
  },
  markFormAsDirty: (form) => {
    form.dataset.formDirty = 'true';
  },
};

/**
 * A promise that can be settled from outside: a request that is "still running".
 *
 * @returns {{promise: Promise, resolve: Function, reject: Function}}
 */
function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

/**
 * Let every pending promise callback run.
 *
 * @returns {Promise<void>}
 */
async function settle() {
  // Microtasks only, so this also works under fake timers: every stub here settles through
  // promises, never through a timer.
  for (let i = 0; i < 200; i++) {
    await Promise.resolve();
  }
}

/**
 * Markup of one STACK question as Moodle renders it.
 *
 * @param {number} slot The slot.
 * @param {Object} values Field suffix to value.
 * @param {Object} options editor: true to hide the fields behind an input surface; outcome text.
 * @returns {string} HTML.
 */
function questionHtml(slot, values = {}, options = {}) {
  const hide = options.editor ? ' style="position:absolute;left:-10000px;width:1px;height:1px"' : '';
  const fields = Object.keys(values).map((name) =>
    `<input type="text" name="q9:${slot}_${name}" id="q9:${slot}_${name}" value="${values[name]}"${hide}>`
  ).join('');
  const editor = options.editor ? '<span class="fake-editor">editor</span>' : '';
  return `<div id="question-9-${slot}" class="que stack">`
    + '<div class="content"><div class="formulation">'
    + `<input type="hidden" name="q9:${slot}_:sequencecheck" value="${options.seq || 1}">`
    + `<p>Question ${slot}</p>${fields}${editor}`
    + `<input type="submit" name="q9:${slot}_-submit" value="Check" class="native-check">`
    + `</div><div class="outcome">${options.outcome || ''}</div></div></div>`;
}

/**
 * Build the attempt page.
 *
 * @param {string} questions The questions' markup.
 * @returns {HTMLFormElement} The response form.
 */
function page(questions) {
  document.body.innerHTML = '<form id="responseform" data-form-dirty="false">'
    + '<input type="hidden" name="attempt" value="7">'
    + '<input type="hidden" name="thispage" value="0">'
    + '<input type="hidden" name="slots" value="1,2">'
    + '<input type="hidden" name="sesskey" value="REALSESSKY">'
    + questions + '</form>';
  return document.getElementById('responseform');
}

/**
 * Start an engine against stubbed services.
 *
 * @param {Object} options handlers: methodname => function(args) returning a promise;
 *     mode: the mode module to load (default: a recording stub); fetch: the page fetch stub.
 * @returns {Object} The harness.
 */
function startEngine(options = {}) {
  const calls = [];
  const handlers = Object.assign({
    local_stackmathgame_get_quiz_config: () => Promise.resolve({ design: { modecomponent: 'stackmathgamemode_rpg' }, questionmap: [] }),
    local_stackmathgame_get_profile_state: () => Promise.resolve({ profile: { progressjson: '{}' } }),
    local_stackmathgame_get_narrative: () => Promise.resolve({ lines: [] }),
    local_stackmathgame_prefetch_next_node: () => Promise.resolve({ nextnode: null, navigation: null }),
  }, options.handlers || {});
  const Ajax = {
    call: (requests) => requests.map((request) => {
      calls.push(request);
      const handler = handlers[request.methodname];
      return handler ? handler(request.args) : Promise.reject(new Error('unexpected ' + request.methodname));
    }),
  };
  const reload = jest.fn();
  const windowListeners = {};
  const fakeWindow = {
    addEventListener: (type, fn) => {
      (windowListeners[type] = windowListeners[type] || []).push(fn);
    },
    location: {
      search: '?attempt=7&page=0',
      href: 'http://localhost/mod/quiz/attempt.php?attempt=7&page=0',
      reload,
    },
    setTimeout: (fn, ms) => setTimeout(fn, ms),
    console: { warn: () => null, error: () => null, log: () => null },
  };
  const onAnswer = options.onAnswer || jest.fn();
  const mode = options.mode || { init: jest.fn(() => ({ onAnswer })) };
  const GameCore = loadAmd('amd/src/game_core.js', {});
  const FilterEvents = { notifyFilterContentUpdated: jest.fn() };
  const Notification = { exception: jest.fn() };
  const Templates = { runTemplateJS: jest.fn() };
  const fetchStub = options.fetch || jest.fn(() => Promise.reject(new Error('offline')));
  // Only the release before the re-audit used jQuery here; the stub lets the same tests run
  // against it, so they can be shown to fail there.
  const jquery = (selector) => ({
    val: () => {
      const node = document.querySelector(selector);
      return node ? node.value : undefined;
    },
  });
  const engine = loadAmd('amd/src/game_engine.js', {
    jquery,
    'core/ajax': Ajax,
    'core/notification': Notification,
    'local_stackmathgame/game_core': GameCore,
    'core_filters/events': FilterEvents,
    'core_form/changechecker': ChangeChecker,
    'core/templates': Templates,
    'core/fragment': { processCollectedJavascript: (js) => js.replace(/<\/?script[^>]*>/g, '') },
  }, {
    window: fakeWindow,
    M,
    fetch: fetchStub,
    DOMParser: window.DOMParser,
    URLSearchParams,
    require: (names, callback) => callback(mode),
  });
  if (typeof engine.check !== 'function') {
    // The release before the re-audit had no promise-returning check; drive it through the button.
    engine.check = async () => {
      const button = document.querySelector('.smg-action-check');
      if (button) {
        button.click();
      }
      await settle();
    };
  }
  const setHandler = (name, fn) => {
    handlers[name] = fn;
  };
  const fireWindow = (type) => (windowListeners[type] || []).forEach((fn) => fn({ type }));
  return { setHandler, fireWindow, Templates, engine, calls, reload, onAnswer, mode, Notification, fetchStub, GameCore, FilterEvents,
    submits: () => calls.filter((c) => c.methodname === 'local_stackmathgame_submit_answer') };
}

/**
 * A processed submit response.
 *
 * @param {Object} extra Fields to override.
 * @returns {Object} The response.
 */
function processed(extra = {}) {
  return Object.assign({
    status: 'processed', processed: true, requiresnativefallback: false, slot: 1,
    cannext: true, firstsolve: true,
    navigation: { action: 'continue', nextslot: 2, url: '/next', label: 'Next scene' },
  }, extra);
}

/**
 * The fragment response for a question.
 *
 * @param {string} html The question markup.
 * @returns {Promise<Object>} The response.
 */
function fragment(html) {
  return Promise.resolve({ status: 'ok', questionhtml: html });
}

const status = () => document.querySelector('.smg-runtime-status');
const field = (name) => {
  const all = Array.from(document.querySelectorAll(`[name="${name}"]`));
  return all[all.length - 1];
};

afterEach(() => {
  jest.useRealTimers();
  document.body.innerHTML = '';
});

describe('the check under failure (re-audit N03)', () => {
  test('both refreshes failing keeps the question, the last input and a readable status', async () => {
    // The real RPG: it moves the formulation into its panel, and used to clear the panel when the
    // refreshed question never arrived.
    const GameCore = loadAmd('amd/src/game_core.js', {});
    const rpg = loadAmd('mode/rpg/amd/src/game.js', {
      'local_stackmathgame/game_core': GameCore,
      'core_filters/events': { notifyFilterContentUpdated: () => null },
    }, { M, URL, Image: window.Image });
    page(questionHtml(1, { ans1: '' }));
    const h = startEngine({
      mode: rpg,
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => Promise.reject(new Error('fragment down')),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1, userid: 5 });
    await settle();

    const input = field('q9:1_ans1');
    expect(input.closest('.smg-rpg-panel')).not.toBeNull();
    input.value = '1+2*3^3';
    await h.engine.check();
    await settle();

    const kept = field('q9:1_ans1');
    expect(kept).not.toBeNull();
    expect(kept.value).toBe('1+2*3^3');
    expect(document.querySelector('.smg-rpg-panel').textContent).toContain('Question 1');
    expect(status().getAttribute('data-smg-status')).toBe('status_refreshfailed');
    expect(h.fetchStub).toHaveBeenCalledTimes(1);
    expect(h.submits()).toHaveLength(1);
    expect(h.reload).not.toHaveBeenCalled();
    // The check control is usable again.
    expect(document.querySelector('.smg-action-check').disabled).toBe(false);
  });

  test('a rendering error after a recorded answer never submits it a second time', async () => {
    const form = page(questionHtml(1, { ans1: '' }));
    form.requestSubmit = jest.fn();
    const h = startEngine({
      onAnswer: () => {
        throw new Error('mode broke');
      },
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' }, { seq: 2 })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';

    await h.engine.check();
    await settle();

    expect(h.submits()).toHaveLength(1);
    expect(form.requestSubmit).not.toHaveBeenCalled();
    expect(h.reload).not.toHaveBeenCalled();
    expect(status().getAttribute('data-smg-status')).toBe('status_refreshfailed');
    expect(document.querySelector('.smg-action-check').disabled).toBe(false);
    // The answer is saved and shown as stored: leaving loses nothing.
    expect(form.dataset.formDirty).toBe('false');
  });

  test('without a native submitter an unsaved answer is kept rather than reloaded away', async () => {
    const form = page(questionHtml(1, { ans1: '' }));
    document.querySelectorAll('.native-check').forEach((node) => node.remove());
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve({ processed: false, requiresnativefallback: true }),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';
    form.dataset.formDirty = 'true';

    await h.engine.check();
    await settle();

    expect(h.reload).not.toHaveBeenCalled();
    expect(field('q9:1_ans1').value).toBe('x^3');
    expect(form.dataset.formDirty).toBe('true');
    expect(status().getAttribute('data-smg-status')).toBe('status_nofallback');
    expect(h.onAnswer).not.toHaveBeenCalled();
  });

  test('with a native submitter the fallback submits the form natively, once', async () => {
    const form = page(questionHtml(1, { ans1: '' }));
    form.requestSubmit = jest.fn();
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve({ processed: false, requiresnativefallback: true }),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';

    await h.engine.check();
    await settle();

    expect(form.requestSubmit).toHaveBeenCalledTimes(1);
    expect(form.requestSubmit.mock.calls[0][0].name).toBe('q9:1_-submit');
    expect(h.reload).not.toHaveBeenCalled();
    expect(h.onAnswer).not.toHaveBeenCalled();
  });

  test('a failed submit keeps the input and says it was not saved', async () => {
    const form = page(questionHtml(1, { ans1: '' }));
    const h = startEngine({
      handlers: { local_stackmathgame_submit_answer: () => Promise.reject(new Error('server down')) },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';

    await h.engine.check();
    await settle();

    expect(field('q9:1_ans1').value).toBe('x^3');
    expect(form.dataset.formDirty).toBe('true');
    expect(status().getAttribute('data-smg-status')).toBe('status_notsaved');
    expect(h.onAnswer).not.toHaveBeenCalled();
    expect(h.reload).not.toHaveBeenCalled();
    expect(document.querySelector('.smg-action-check').disabled).toBe(false);
  });

  test('failing optional profile, narrative and prefetch calls do not block the game', async () => {
    page(questionHtml(1, { ans1: '' }));
    const failing = () => Promise.reject(new Error('optional down'));
    const h = startEngine({
      handlers: {
        local_stackmathgame_get_profile_state: failing,
        local_stackmathgame_get_narrative: failing,
        local_stackmathgame_prefetch_next_node: failing,
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    await settle();
    // The mode starts although three of the four bootstrap requests failed.
    expect(h.mode.init).toHaveBeenCalledTimes(1);

    field('q9:1_ans1').value = 'x^3';
    await h.engine.check();
    await settle();

    expect(h.onAnswer).toHaveBeenCalledTimes(1);
    const store = h.onAnswer.mock.calls[0][1];
    // The way on comes from the submit response and opens without the prefetch.
    expect(store.navigation.action).toBe('continue');
    expect(status().getAttribute('data-smg-status')).toBe('status_saved');
  });

  test('a second click while the check runs is ignored', async () => {
    page(questionHtml(1, { ans1: '' }));
    const pending = deferred();
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => pending.promise,
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';

    const first = h.engine.check();
    await h.engine.check();
    pending.resolve(processed());
    await first;

    expect(h.submits()).toHaveLength(1);
    expect(h.onAnswer).toHaveBeenCalledTimes(1);
  });
});

describe('the unsaved-changes state (re-audit N04)', () => {
  test('saving one slot does not mark an edit in another slot as saved', async () => {
    const form = page(questionHtml(1, { ans1: '' }) + questionHtml(2, { ans1: '' }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    // Scenes mode with two questions on one page: slot 1 presented, slot 2 also on the page.
    await h.engine.init({ quizid: 3 });
    field('q9:1_ans1').value = 'x^3';
    field('q9:2_ans1').value = 'y^2';
    form.dataset.formDirty = 'true';

    await h.engine.check();
    await settle();

    expect(h.submits()[0].args.slot).toBe(1);
    expect(form.dataset.formDirty).toBe('true');
    expect(field('q9:2_ans1').value).toBe('y^2');
  });

  test('several saved and unchanged inputs leave the form clean', async () => {
    const form = page(questionHtml(1, { ans1: '', ans2: '' }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'a', ans2: 'b' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'a';
    field('q9:1_ans2').value = 'b';
    form.dataset.formDirty = 'true';

    await h.engine.check();
    await settle();

    expect(form.dataset.formDirty).toBe('false');
    expect(status().getAttribute('data-smg-status')).toBe('status_saved');
  });

  test('an edit made while the check is running survives the swap and stays unsaved', async () => {
    const form = page(questionHtml(1, { ans1: '', ans2: '' }));
    const pending = deferred();
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => pending.promise,
        // The fragment shows the state of the request that was sent: the older answer.
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^2', ans2: 'k' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^2';
    field('q9:1_ans2').value = 'k';

    const running = h.engine.check();
    // The field stays editable during the request, and the player corrects the answer.
    expect(field('q9:1_ans1').disabled).toBe(false);
    field('q9:1_ans1').value = 'x^3';
    pending.resolve(processed({ cannext: false, firstsolve: false }));
    await running;
    await settle();

    expect(field('q9:1_ans1').value).toBe('x^3');
    expect(field('q9:1_ans2').value).toBe('k');
    expect(form.dataset.formDirty).toBe('true');
    expect(status().getAttribute('data-smg-status')).toBe('status_changedduringcheck');
    expect(h.submits()).toHaveLength(1);
  });

  test('editor on: the delayed re-initialisation does not reload over an unsaved edit', async () => {
    jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
    const form = page(questionHtml(1, { ans1: '' }, { editor: true }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        // The refreshed question comes back with bare fields: an editor that does not follow
        // replaced content has not attached to it.
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';
    await h.engine.check();
    await settle();
    field('q9:1_ans1').value = 'x^3+1';

    jest.advanceTimersByTime(3500);

    expect(h.reload).not.toHaveBeenCalled();
    expect(field('q9:1_ans1').value).toBe('x^3+1');
    expect(status().getAttribute('data-smg-status')).toBe('status_editorlost');
    expect(form.dataset.formDirty).toBe('true');
  });

  test('editor on: the delayed re-initialisation reloads when nothing would be lost', async () => {
    jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
    const form = page(questionHtml(1, { ans1: '' }, { editor: true }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';
    await h.engine.check();
    await settle();

    jest.advanceTimersByTime(3500);

    expect(h.reload).toHaveBeenCalledTimes(1);
    expect(form.dataset.formDirty).toBe('false');
  });

  test('editor on: no reload once the player is leaving the page', async () => {
    jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
    page(questionHtml(1, { ans1: '' }, { editor: true }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';
    await h.engine.check();
    await settle();
    // The player follows the way on before the editor has had its chance.
    h.fireWindow('pagehide');

    jest.advanceTimersByTime(3500);

    expect(h.reload).not.toHaveBeenCalled();
  });

  test('editor on: an editor attaching late is waited for, not reloaded away', async () => {
    jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
    page(questionHtml(1, { ans1: '' }, { editor: true }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';
    await h.engine.check();
    await settle();

    jest.advanceTimersByTime(1500);
    // The editor attaches 1.5 s after the swap: its field is hidden behind it now.
    field('q9:1_ans1').setAttribute('style', 'position:absolute;left:-10000px;width:1px;height:1px');
    jest.advanceTimersByTime(3000);

    expect(h.reload).not.toHaveBeenCalled();
  });

  test('editor on: the wait for the editor does not run out while the check is still running', async () => {
    jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
    page(questionHtml(1, { ans1: '' }, { editor: true }));
    const narrative = deferred();
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    // The follow-up after the swap hangs: the check is still running.
    h.setHandler('local_stackmathgame_get_narrative', () => narrative.promise);
    field('q9:1_ans1').value = 'x^3';
    const running = h.engine.check();
    await settle();

    jest.advanceTimersByTime(10000);
    expect(h.reload).not.toHaveBeenCalled();

    narrative.resolve({ lines: [] });
    await running;
    await settle();
    expect(status().getAttribute('data-smg-status')).toBe('status_saved');
    jest.advanceTimersByTime(3500);
    // Only now, with the answer handled and nothing unsaved, is the page reloaded for the editor.
    expect(h.reload).toHaveBeenCalledTimes(1);
  });

  test('editor off: a refresh never reloads the page', async () => {
    jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
    page(questionHtml(1, { ans1: '' }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';
    await h.engine.check();
    await settle();

    jest.advanceTimersByTime(5000);

    expect(h.reload).not.toHaveBeenCalled();
  });

  test('editor on: a request that never returns causes no reload and keeps the input', async () => {
    jest.useFakeTimers({ doNotFake: ['nextTick', 'queueMicrotask'] });
    const form = page(questionHtml(1, { ans1: '' }, { editor: true }));
    const h = startEngine({
      handlers: { local_stackmathgame_submit_answer: () => new Promise(() => null) },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';
    form.dataset.formDirty = 'true';
    h.engine.check();
    await settle();

    jest.advanceTimersByTime(60000);

    expect(h.reload).not.toHaveBeenCalled();
    expect(field('q9:1_ans1').value).toBe('x^3');
    expect(form.dataset.formDirty).toBe('true');
    expect(status().getAttribute('data-smg-status')).toBe('status_saving');
  });
});

describe('the refreshed question\'s own scripts (re-audit N09)', () => {
  test('run once the refreshed question is on the page', async () => {
    page(questionHtml(1, { ans1: '' }));
    let presentAtRun = null;
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => Promise.resolve({
          status: 'ok',
          questionhtml: questionHtml(1, { ans1: 'x^3' }, { outcome: 'fresh' }),
          javascript: '<script>initStackInputs();</script>',
        }),
      },
    });
    h.Templates.runTemplateJS.mockImplementation(() => {
      presentAtRun = document.querySelector('.que[data-smg-controlled="1"]').textContent.includes('fresh');
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';

    await h.engine.check();
    await settle();

    expect(h.Templates.runTemplateJS).toHaveBeenCalledTimes(1);
    expect(h.Templates.runTemplateJS).toHaveBeenCalledWith('initStackInputs();');
    expect(presentAtRun).toBe(true);
  });
});

describe('ids moved out of the question by a mode (re-audit N09)', () => {
  test('the old copies give up their ids, so the refreshed question\'s scripts find the new ones', async () => {
    page(questionHtml(1, { ans1: '' }));
    // What the RPG does: the formulation lives in a part outside .que.
    const part = document.createElement('div');
    part.setAttribute('data-smg-question-part', '');
    part.appendChild(document.querySelector('#question-9-1 .formulation'));
    document.getElementById('responseform').appendChild(part);
    let foundAtRun = null;
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => Promise.resolve({
          status: 'ok', questionhtml: questionHtml(1, { ans1: 'x^3' }), javascript: '<script>x();</script>',
        }),
      },
    });
    h.Templates.runTemplateJS.mockImplementation(() => {
      foundAtRun = document.getElementById('q9:1_ans1').closest('.que') !== null;
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';

    await h.engine.check();
    await settle();

    expect(foundAtRun).toBe(true);
    expect(part.querySelector('[data-smg-stale-id="q9:1_ans1"]')).not.toBeNull();
  });
});

describe('the presented question (re-audit N06)', () => {
  test('the slot the server resolved is presented and answered, not the first on the page', async () => {
    page(questionHtml(1, { ans1: '' }) + questionHtml(2, { ans1: '' }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed({ slot: 2 })),
        local_stackmathgame_get_question_fragment: () => Promise.reject(new Error('down')),
      },
      // The page fallback returns the whole page; the question is found by its slot.
      fetch: jest.fn(() => Promise.resolve({
        ok: true,
        text: () => Promise.resolve('<html><body>' + questionHtml(1, { ans1: '' })
          + questionHtml(2, { ans1: 'z' }, { outcome: 'graded two' }) + '</body></html>'),
      })),
    });
    await h.engine.init({ quizid: 3, activeslot: 2 });

    expect(document.getElementById('question-9-2').getAttribute('data-smg-controlled')).toBe('1');
    expect(document.getElementById('question-9-1').hidden).toBe(true);
    expect(h.mode.init.mock.calls[0][0].currentslot).toBe(2);

    field('q9:2_ans1').value = 'z';
    await h.engine.check();
    await settle();

    expect(h.submits()[0].args.slot).toBe(2);
    expect(h.calls.find((c) => c.methodname === 'local_stackmathgame_get_question_fragment').args.slot).toBe(2);
    // The refreshed question is slot 2's, and it stays the presented one.
    const presented = document.querySelector('.que[data-smg-controlled="1"]');
    expect(presented.id).toBe('question-9-2');
    expect(presented.textContent).toContain('graded two');
    expect(document.getElementById('question-9-1').hidden).toBe(true);
  });

  test('without a resolved slot the first question is used, as before', async () => {
    page(questionHtml(1, { ans1: '' }) + questionHtml(2, { ans1: '' }));
    const h = startEngine();
    await h.engine.init({ quizid: 3 });

    expect(h.mode.init.mock.calls[0][0].currentslot).toBe(1);
    expect(document.getElementById('question-9-2').hidden).toBe(false);
  });
});

describe('the session key (re-audit N01)', () => {
  test('is not sent among the answers', async () => {
    page(questionHtml(1, { ans1: '' }));
    const h = startEngine({
      handlers: {
        local_stackmathgame_submit_answer: () => Promise.resolve(processed()),
        local_stackmathgame_get_question_fragment: () => fragment(questionHtml(1, { ans1: 'x^3' })),
      },
    });
    await h.engine.init({ quizid: 3, activeslot: 1 });
    field('q9:1_ans1').value = 'x^3';

    await h.engine.check();

    const answers = h.submits()[0].args.answers;
    expect(answers.map((a) => a.name)).not.toContain('sesskey');
    expect(JSON.stringify(answers)).not.toContain('REALSESSKY');
    expect(answers).toContainEqual({ name: 'q9:1_ans1', value: 'x^3' });
    expect(answers).toContainEqual({ name: 'q9:1_:sequencecheck', value: '1' });
  });
});
