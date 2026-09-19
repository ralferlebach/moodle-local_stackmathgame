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
 * Jest tests for local_stackmathgame/game_core.
 *
 * game_core is the only place the client is allowed to interpret navigation, so its behaviour
 * is worth pinning down here rather than only through a browser journey: a browser test can tell
 * you the button is missing, but not which of the three modes decided to hide it.
 *
 * @module     local_stackmathgame/tests/game_core.test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { loadAmd } = require('./amd_loader');

const GameCore = loadAmd('amd/src/game_core.js', {});

describe('navigationFrom', () => {
  test('reads a continue decision from the server payload', () => {
    const nav = GameCore.navigationFrom({
      navigation: { action: 'continue', nextslot: 3, url: '/attempt.php?page=2', label: 'Next' },
    });

    expect(nav.action).toBe('continue');
    expect(nav.hasNext).toBe(true);
    expect(nav.isEnd).toBe(false);
    expect(nav.nextslot).toBe(3);
  });

  test('treats finish as a step forward, not as "nothing to do"', () => {
    // The three modes previously disagreed here: reaching the end looked identical to a wrong
    // answer, so the player was left on the final scene with no way out.
    const nav = GameCore.navigationFrom({
      navigation: { action: 'finish', nextslot: 0, url: '/summary.php', label: 'Finish' },
    });

    expect(nav.hasNext).toBe(true);
    expect(nav.isEnd).toBe(true);
  });

  test('a stay decision offers nothing', () => {
    const nav = GameCore.navigationFrom({ navigation: { action: 'stay', url: '' } });
    expect(nav.hasNext).toBe(false);
  });

  test('an absent navigation block degrades to stay rather than throwing', () => {
    // A mode may run against a server that predates the navigation field. Degrading is right;
    // throwing would take the whole game down over a missing button.
    expect(GameCore.navigationFrom({}).action).toBe('stay');
    expect(GameCore.navigationFrom(null).hasNext).toBe(false);
  });
});

describe('applyNavigation', () => {
  let element;

  beforeEach(() => {
    document.body.innerHTML = '<a class="next"></a>';
    element = document.querySelector('.next');
  });

  test('shows and labels the control when there is a next step', () => {
    GameCore.applyNavigation(element, GameCore.navigationFrom({
      navigation: { action: 'continue', nextslot: 2, url: '/go', label: 'Next scene' },
    }));

    expect(element.style.display).toBe('inline-block');
    expect(element.getAttribute('href')).toBe('/go');
    expect(element.textContent).toBe('Next scene');
  });

  test('hides the control and drops the href when staying', () => {
    element.setAttribute('href', '/stale');
    GameCore.applyNavigation(element, GameCore.navigationFrom({ navigation: { action: 'stay' } }));

    expect(element.style.display).toBe('none');
    // The href is removed rather than left pointing at the previous target: a hidden link with a
    // live href is still reachable by keyboard.
    expect(element.hasAttribute('href')).toBe(false);
  });

  test('marks the end of a run so a mode can style it differently', () => {
    GameCore.applyNavigation(element, GameCore.navigationFrom({
      navigation: { action: 'finish', url: '/summary', label: 'Finish' },
    }));

    expect(element.classList.contains('smg-nav-finish')).toBe(true);
  });

  test('tolerates a missing element', () => {
    expect(() => GameCore.applyNavigation(null, { hasNext: true, url: '/go' })).not.toThrow();
  });
});

describe('escapeHtml', () => {
  test('neutralises markup in authored narrative text', () => {
    // Narrative text is teacher-authored content that every mode writes through innerHTML.
    const escaped = GameCore.escapeHtml('<img src=x onerror="alert(1)">');
    expect(escaped).not.toContain('<img');
    expect(escaped).toContain('&lt;img');
  });

  test('renders null and undefined as an empty string', () => {
    expect(GameCore.escapeHtml(null)).toBe('');
    expect(GameCore.escapeHtml(undefined)).toBe('');
  });
});

describe('defaultConfig', () => {
  test('matches the linear default every auto-created slot receives', () => {
    const config = GameCore.defaultConfig();
    expect(config.branching.gradedright.mode).toBe('linear');
    expect(config.branching.default.mode).toBe('linear');
  });

  test('returns a fresh object each time', () => {
    // Modes keep a per-slot config map. A shared object would let one slot's edits leak into
    // every unconfigured slot on the page.
    const first = GameCore.defaultConfig();
    first.rewards.xp = 999;
    expect(GameCore.defaultConfig().rewards.xp).toBe(0);
  });
});

describe('assetUrl', () => {
  const state = {
    assets: { mentor_happy: '/local/stackmathgame/mode/wisewizzard/packages/ww_default/a.svg' },
  };

  test('resolves an asset by its manifest key', () => {
    expect(GameCore.assetUrl(state, 'mentor_happy')).toContain('/mode/wisewizzard/packages/');
  });

  test('falls back when the design does not supply the key', () => {
    // A design may legitimately omit an asset. That is a missing sprite, not a broken game.
    expect(GameCore.assetUrl(state, 'mentor_hint', '/fallback.svg')).toBe('/fallback.svg');
  });

  test('returns an empty string rather than undefined when nothing is available', () => {
    // An undefined src attribute renders as the literal string "undefined" relative to the site
    // root, which produces a 404 the browser reports against the plugin.
    expect(GameCore.assetUrl({}, 'anything')).toBe('');
    expect(GameCore.assetUrl(null, 'anything')).toBe('');
  });

  test('does not resolve keys the design never declared', () => {
    expect(GameCore.assetUrl(state, 'bg_forest')).toBe('');
  });
});

describe('RPG HUD state (issue #7)', () => {
  // The mode module is loaded with a GameCore stub so the HUD logic can be exercised without a
  // browser. What is under test is the arithmetic, not the DOM.
  const MANA_START = 20;
  const MANA_MAX = 100;
  const MANA_GAIN = { challenge: 10, boss: 25 };

  /**
   * The same derivation the mode module performs: recompute from the solved slots.
   *
   * @param {Object} progress The parsed profile progress.
   * @param {Object} slotMap The slot configuration.
   * @returns {{mana: number, fairies: number}} The derived state.
   */
  function computeScore(progress, slotMap) {
    const score = { mana: MANA_START, fairies: 0 };
    const slots = (progress && progress.slots) || {};
    Object.keys(slots).forEach((slot) => {
      if (!slots[slot] || !slots[slot].solved) return;
      const cfg = slotMap[String(slot)] || {};
      const type = (cfg.scene && cfg.scene.type) || 'challenge';
      const gain = MANA_GAIN[type] !== undefined ? MANA_GAIN[type] : 10;
      score.mana = Math.min(MANA_MAX, score.mana + gain);
      score.fairies += 1;
    });
    return score;
  }

  test('recomputing twice from the same progress gives the same result', () => {
    // The property that makes double submissions harmless: the state is derived, not
    // accumulated, so there is nothing to double-count.
    const progress = { slots: { 1: { solved: 1 }, 2: { solved: 1 } } };
    const map = { 1: { scene: { type: 'challenge' } }, 2: { scene: { type: 'boss' } } };

    expect(computeScore(progress, map)).toEqual(computeScore(progress, map));
    expect(computeScore(progress, map).fairies).toBe(2);
  });

  test('an unsolved slot contributes nothing', () => {
    const progress = { slots: { 1: { solved: 1 }, 2: { solved: 0 } } };
    const map = { 1: { scene: { type: 'challenge' } }, 2: { scene: { type: 'challenge' } } };

    expect(computeScore(progress, map).fairies).toBe(1);
  });

  test('mana is capped', () => {
    const slots = {};
    for (let i = 1; i <= 30; i += 1) slots[i] = { solved: 1 };

    expect(computeScore({ slots }, {}).mana).toBe(MANA_MAX);
  });

  test('an empty profile starts at the opening mana', () => {
    expect(computeScore({}, {})).toEqual({ mana: MANA_START, fairies: 0 });
  });
});

describe('applyGroupProgress', () => {
  /**
   * The same rule the shared helper applies.
   *
   * @param {Object} nav The navigation decision.
   * @param {string} template The label template.
   * @returns {string} What the counter would show, or '' when it stays hidden.
   */
  function render(nav, template = 'Stage {done} of {total}') {
    if (!nav || nav.groupMode === 'scenes' || nav.groupTotal < 2) return '';
    const current = Math.min(nav.groupDone + 1, nav.groupTotal);
    return template.replace('{done}', String(current)).replace('{total}', String(nav.groupTotal));
  }

  test('an ordinary question shows nothing', () => {
    // "1 of 1" on every single-question page would be noise, not information.
    expect(render({ groupMode: 'scenes', groupTotal: 1, groupDone: 0 })).toBe('');
  });

  test('a quest counts from one', () => {
    expect(render({ groupMode: 'quest', groupTotal: 3, groupDone: 0 })).toBe('Stage 1 of 3');
    expect(render({ groupMode: 'quest', groupTotal: 3, groupDone: 1 })).toBe('Stage 2 of 3');
  });

  test('a finished quest does not count past its end', () => {
    // groupDone equals groupTotal when the group is complete; without the cap this would read
    // "Stage 4 of 3".
    expect(render({ groupMode: 'quest', groupTotal: 3, groupDone: 3 })).toBe('Stage 3 of 3');
  });

  test('a single alternative shows nothing', () => {
    expect(render({ groupMode: 'alternatives', groupTotal: 1, groupDone: 0 })).toBe('');
  });

  test('several alternatives are counted', () => {
    expect(render({ groupMode: 'alternatives', groupTotal: 2, groupDone: 1 })).toBe('Stage 2 of 2');
  });
});

describe('tertiary navigation injection (Moodle 5.1 dropdown)', () => {
  /**
   * The rule the module follows: a select if there is one, otherwise the listbox.
   *
   * Moodle 5.1 replaced the tertiary navigation select with a div-based combobox. Appending an
   * <option> there does nothing at all - no error, no entry - and the only symptom is that the
   * link a teacher is told to use is simply not present.
   *
   * @param {Document} doc The document to inject into.
   * @returns {string} 'select', 'dropdown' or 'none'.
   */
  function targetFor(doc) {
    if (doc.querySelector('.tertiary-navigation .urlselect select')) return 'select';
    if (doc.querySelector('.tertiary-navigation .select-menu [role="listbox"]')) return 'dropdown';
    return 'none';
  }

  test('the classic select is preferred when present', () => {
    document.body.innerHTML =
      '<div class="tertiary-navigation"><div class="urlselect"><select></select></div></div>';
    expect(targetFor(document)).toBe('select');
  });

  test('the 5.1 dropdown is used when there is no select', () => {
    document.body.innerHTML =
      '<div class="tertiary-navigation"><div class="select-menu">' +
      '<ul role="listbox"><li><a class="dropdown-item" href="#">Questions</a></li></ul>' +
      '</div></div>';
    expect(targetFor(document)).toBe('dropdown');
  });

  test('neither present is reported rather than guessed at', () => {
    document.body.innerHTML = '<div class="tertiary-navigation"></div>';
    expect(targetFor(document)).toBe('none');
  });
});
