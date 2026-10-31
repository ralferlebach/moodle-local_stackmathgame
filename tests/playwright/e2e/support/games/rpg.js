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
  /**
   * The design to play, by the name shown in the selector.
   *
   * "RPG Default" ships with the plugin and has placeholder artwork. A site that has installed
   * the licensed package through the Game Design Studio plays "RPG Fantasy" instead.
   */
  designName: process.env.SMG_RPG_DESIGN || 'RPG Default',

  /** The scene type to give the final question, so the run ends on a boss. */
  finalSceneType: 'boss',

  /** The RPG counts fairies and mana; the journey checks that solving moves them. */
  hasCounters: true,

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
      page.locator('.smg-rpg-stage').first(),
      'The RPG stage never appeared, although the shell is there'
    ).toBeVisible({ timeout: 60000 });
  },

  /**
   * Read the RPG's own counters.
   *
   * @param {import('@playwright/test').Page} page The page.
   * @returns {Promise<{mana: number, fairies: number}>} What the HUD currently shows.
   */
  async readHud(page) {
    // By data attribute, not by position in the text: the counters sit side by side in an order
    // that follows the original's layout, and reading "the first number" was a guess about it.
    const read = async (name) => Number(
      (await page.locator(`[data-smg-hud="${name}"]`).first().innerText().catch(() => '0')) || 0
    );
    return { mana: await read('mana'), fairies: await read('fairies') };
  },

  /**
   * The control that moves to the next scene.
   *
   * @param {import('@playwright/test').Page} page The page.
   * @returns {import('@playwright/test').Locator} The control.
   */
  nextControl(page) {
    // Only an open way on is a link. The locked arrow stays on screen - grey - so visibility
    // alone would accept a control that goes nowhere.
    return page.locator('a.smg-rpg-next[href]').first();
  },
};
