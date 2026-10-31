/**
 * The Digital Mentoring (instant tutoring) mode, as the journey sees it.
 *
 * @module     local_stackmathgame/e2e/games/wisewizzard
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

module.exports = {
  /**
   * The design to play, by the name shown in the selector.
   *
   * "WiseWizzard Default" ships with the plugin and has placeholder faces. A site that has
   * imported the Digital Mentoring package through the studio plays "Digital Mentoring".
   */
  designName: process.env.SMG_WW_DESIGN || 'WiseWizzard Default',

  /** The tutor has no boss; the last scene is an ordinary one. */
  finalSceneType: 'challenge',

  /** The tutor talks rather than counts. */
  hasCounters: false,

  /**
   * Wait until the tutor has taken over the page.
   *
   * @param {import('@playwright/test').Page} page The page.
   */
  async waitUntilReady(page) {
    await expect(
      page.locator('.smg-runtime-shell').first(),
      'The game shell was never injected - the AMD chain did not complete'
    ).toBeAttached({ timeout: 60000 });
    await expect(
      page.locator('.smg-ww-chat').first(),
      'The tutor never appeared, although the shell is there'
    ).toBeVisible({ timeout: 60000 });
  },

  /**
   * The tutor keeps no counters.
   *
   * @returns {Promise<{mana: number, fairies: number}>} Always zero.
   */
  async readHud() {
    return { mana: 0, fairies: 0 };
  },

  /**
   * The way on: the button in the tutor's bubble, once it is a link.
   *
   * @param {import('@playwright/test').Page} page The page.
   * @returns {import('@playwright/test').Locator} The control.
   */
  nextControl(page) {
    return page.locator('a.smg-ww-next[href]').first();
  },
};
