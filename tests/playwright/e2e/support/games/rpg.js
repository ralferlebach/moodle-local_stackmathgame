/**
 * What "playing the RPG" means, as opposed to playing any other mode.
 *
 * The journey itself knows nothing mode-specific. Everything that is true of the RPG and not of
 * ExitGames or WiseWizzard lives here, so a second mode is a second file of this shape and no
 * change to the journey.
 *
 * @module     local_stackmathgame/e2e/games/rpg
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

module.exports = {
  /** The name shown in the design selector. */
  designName: 'RPG',

  /** The scene type to give the final question, so the run ends on a boss. */
  finalSceneType: 'boss',

  /**
   * Wait until the RPG has taken over the page.
   *
   * The shell is what the AMD bootstrap injects, and the stage is what this mode draws on top of
   * it. Waiting for both distinguishes "the runtime started" from "the RPG started" - a
   * distinction that matters, because a broken mode module leaves the shell present and empty.
   *
   * @param {import('@playwright/test').Page} page The page.
   */
  async waitUntilReady(page) {
    await expect(
      page.locator('.smg-runtime-shell').first(),
      'The game shell was never injected - the AMD chain did not complete'
    ).toBeAttached({ timeout: 60000 });

    await expect(
      page.locator('.smg-rpg-stage, .smg-rpg-hud').first(),
      'The RPG interface never appeared, although the shell is there'
    ).toBeVisible({ timeout: 60000 });
  },

  /**
   * Read the RPG's own counters.
   *
   * @param {import('@playwright/test').Page} page The page.
   * @returns {Promise<{mana: number, fairies: number}>} What the HUD currently shows.
   */
  async readHud(page) {
    const text = await page.locator('.smg-rpg-hud').first().innerText().catch(() => '');
    const numbers = (text.match(/\d+/g) || []).map(Number);
    return { mana: numbers[0] || 0, fairies: numbers[1] || 0 };
  },

  /**
   * The control that moves to the next scene.
   *
   * @param {import('@playwright/test').Page} page The page.
   * @returns {import('@playwright/test').Locator} The control.
   */
  nextControl(page) {
    return page.locator('.smg-rpg-next, .smg-nav, a:has-text("→")').first();
  },
};
