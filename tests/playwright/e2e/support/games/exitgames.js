/**
 * The Exit Game, as the journey sees it: where it draws, and where its way on is.
 *
 * The Exit Game leaves the question where Moodle renders it and adds a guide with a speech
 * bubble above it and a "next" button to the game shell. It keeps no counters.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

module.exports = {
  /** The design to play, by the name shown in the selector. Ships with the plugin. */
  designName: process.env.SMG_EXIT_DESIGN || 'ExitGame Default',

  /** The last room is the final challenge. */
  finalSceneType: 'boss',

  /** The Exit Game tells a story; it does not count. */
  hasCounters: false,

  /**
   * Wait until the Exit Game has taken over the page.
   *
   * The shell is the engine's; the speech bubble is this mode's, and it shows the scene's intro,
   * which every scene of the journey has. A shell without a bubble means the mode module did not
   * start.
   *
   * @param {import('@playwright/test').Page} page The page.
   */
  async waitUntilReady(page) {
    await expect(
      page.locator('.smg-runtime-shell').first(),
      'The game shell was never injected - the AMD chain did not complete'
    ).toBeAttached({ timeout: 60000 });
    await expect(
      page.locator('.smg-eg-bubble').first(),
      'The Exit Game never showed its guide, although the shell is there'
    ).toBeVisible({ timeout: 60000 });
    await expect(page.locator('.que[data-smg-controlled="1"]').first()).toBeVisible({ timeout: 60000 });
  },

  /**
   * The Exit Game keeps no counters.
   *
   * @returns {Promise<{mana: number, fairies: number}>} Always zero.
   */
  async readHud() {
    return { mana: 0, fairies: 0 };
  },

  /**
   * The way on: the button in the game shell, once it is a link.
   *
   * @param {import('@playwright/test').Page} page The page.
   * @returns {import('@playwright/test').Locator} The control.
   */
  nextControl(page) {
    return page.locator('a.smg-eg-next[href]').first();
  },
};
