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
