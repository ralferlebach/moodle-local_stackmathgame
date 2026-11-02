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
 * Jest tests for the RPG counter cache (re-audit N07).
 *
 * sessionStorage belongs to the tab, not to the Moodle session. The cache key named only the
 * campaign, and a cached value won whenever it was higher than what the server reported - so after
 * a logout and a login as somebody else in the same tab, the new player saw the previous player's
 * fairies. These tests run the real mode in jsdom with a real sessionStorage.
 *
 * @module     local_stackmathgame/tests/rpg_hud_cache.test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { loadAmd } = require('./amd_loader');

const M = { util: { get_string: (key) => key } };

/**
 * Load the RPG mode from source.
 *
 * @returns {Object} The module.
 */
function loadRpg() {
  const GameCore = loadAmd('amd/src/game_core.js', {});
  return loadAmd('mode/rpg/amd/src/game.js', {
    'local_stackmathgame/game_core': GameCore,
    'core_filters/events': { notifyFilterContentUpdated: () => null },
  }, { M, URL, Image: window.Image });
}

/**
 * A profile with the given slots solved.
 *
 * @param {number[]} slots Solved slot numbers.
 * @returns {Object} The exported profile.
 */
function profileSolving(slots) {
  const progress = { slots: {} };
  slots.forEach((slot) => {
    progress.slots[String(slot)] = { solved: 1 };
  });
  return { progressjson: JSON.stringify(progress) };
}

/**
 * Start the mode on a one-question page.
 *
 * @param {Object} rpg The module.
 * @param {Object} config The engine configuration.
 * @param {Object|null} profile The server profile.
 * @returns {Object} The mode handle.
 */
function start(rpg, config, profile) {
  document.body.innerHTML = '<form id="responseform"><div id="question-9-1" class="que">'
    + '<div class="formulation"><input type="text" name="q9:1_ans1"></div></div></form>';
  return rpg.init({
    config,
    profile,
    questionmap: [{ slotnumber: 1, configjson: '{}' }, { slotnumber: 2, configjson: '{}' }],
    currentslot: 1,
    assets: {},
    assetlist: [],
  });
}

const fairies = () => document.querySelector('[data-smg-hud="fairies"]').textContent;

beforeEach(() => {
  sessionStorage.clear();
});

describe('RPG counter cache (re-audit N07)', () => {
  test('the key is scoped by user and campaign', () => {
    const rpg = loadRpg();
    const a = rpg.cacheKeyFor({ userid: 5, labelid: 2, quizid: 9 });
    const b = rpg.cacheKeyFor({ userid: 6, labelid: 2, quizid: 9 });
    const c = rpg.cacheKeyFor({ userid: 5, labelid: 3, quizid: 9 });

    expect(a).not.toBe(b);
    expect(a).not.toBe(c);
    expect(a).toContain('u5');
    // Without a label the quiz is the campaign.
    expect(rpg.cacheKeyFor({ userid: 5, quizid: 9 })).not.toBe(rpg.cacheKeyFor({ userid: 5, quizid: 8 }));
  });

  test('the server state wins over a higher cached value', () => {
    const rpg = loadRpg();
    const config = { userid: 5, labelid: 2, quizid: 9 };
    sessionStorage.setItem(rpg.cacheKeyFor(config), JSON.stringify({ mana: 90, fairies: 7 }));

    start(rpg, config, profileSolving([1]));

    expect(fairies()).toBe('1');
    expect(rpg.chooseScore({ mana: 60, fairies: 1 }, { mana: 90, fairies: 7 })).toEqual({ mana: 60, fairies: 1 });
  });

  test('another player in the same tab never sees the previous player\'s counters', () => {
    const rpg = loadRpg();
    // Player A plays - solves a scene - and leaves counters behind in the tab.
    const a = start(rpg, { userid: 5, labelid: 2, quizid: 9 }, profileSolving([1]));
    a.onAnswer({ slot: 2, cannext: true, firstsolve: true, profile: profileSolving([1, 2]) });
    expect(fairies()).toBe('2');

    // Logout, login as player B in the same tab: B's server profile has nothing solved.
    start(rpg, { userid: 6, labelid: 2, quizid: 9 }, profileSolving([]));

    expect(fairies()).toBe('0');
    // And A's entry is gone from the tab.
    const keys = [];
    for (let i = 0; i < sessionStorage.length; i++) {
      keys.push(sessionStorage.key(i));
    }
    expect(keys.some((key) => key.indexOf('u5:') !== -1)).toBe(false);
  });

  test('without any server profile the player\'s own cache stands in', () => {
    const rpg = loadRpg();
    const config = { userid: 5, labelid: 2, quizid: 9 };
    sessionStorage.setItem(rpg.cacheKeyFor(config), JSON.stringify({ mana: 70, fairies: 3 }));
    sessionStorage.setItem(rpg.cacheKeyFor({ userid: 6, labelid: 2 }), JSON.stringify({ mana: 99, fairies: 9 }));

    start(rpg, config, null);

    expect(fairies()).toBe('3');
  });

  test('an answer rebuilds the counters from the profile the server returned', () => {
    const rpg = loadRpg();
    const config = { userid: 5, labelid: 2, quizid: 9 };
    const mode = start(rpg, config, profileSolving([]));
    expect(fairies()).toBe('0');

    mode.onAnswer({ slot: 1, cannext: true, firstsolve: true, profile: profileSolving([1]) });
    expect(fairies()).toBe('1');

    // A repeated first-solve flag cannot push the counters past the server's record.
    mode.onAnswer({ slot: 1, cannext: true, firstsolve: true, profile: profileSolving([1]) });
    expect(fairies()).toBe('1');
  });
});
