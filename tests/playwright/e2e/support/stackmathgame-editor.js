/**
 * The plugin's own authoring surfaces: game settings and the flow editor.
 *
 * @module     local_stackmathgame/e2e/stackmathgame-editor
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

/**
 * Open the game settings and read the prerequisite panel before changing anything.
 *
 * The panel is the plugin's own verdict on whether this quiz can be played. Reading it here means
 * a misconfiguration fails with the plugin's own explanation, at the step that caused it, instead
 * of three pages later as a missing element in the runtime.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} cmid The quiz course-module id.
 */
async function assertNoBlockers(page, cmid) {
  await page.goto(`/local/stackmathgame/quiz_settings.php?cmid=${cmid}`);

  const blockers = page.locator('.smg-prereq-error, tr:has(.badge-danger), .alert-danger').first();
  if (await blockers.count()) {
    const text = (await blockers.first().innerText()).trim();
    expect(text, `The quiz reports a prerequisite blocker: ${text}`).toBe('');
  }
}

/**
 * Switch the game on and choose a design.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} cmid The quiz course-module id.
 * @param {string|RegExp} design The design, matched against the option text.
 */
async function enableGame(page, cmid, design) {
  await page.goto(`/local/stackmathgame/quiz_settings.php?cmid=${cmid}`);

  const enabled = page.locator('#id_enabled');
  await expect(enabled, 'The game settings form has no enable switch').toBeVisible({ timeout: 30000 });
  await enabled.check();

  const designSelect = page.locator('#id_designid');
  if (await designSelect.count()) {
    await designSelect.selectOption({ label: design instanceof RegExp ? design : new RegExp(design, 'i') });
  }

  await page.click('#id_submitbutton');
  await expect(
    page.locator('text=/Changes saved|gespeichert/i').first(),
    'Saving the game settings produced no confirmation'
  ).toBeVisible({ timeout: 30000 });
}

/**
 * Open the flow editor and return the slot numbers it lists.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} cmid The quiz course-module id.
 * @returns {Promise<number[]>} The slot numbers, in the order shown.
 */
async function listScenes(page, cmid) {
  await page.goto(`/local/stackmathgame/flow.php?cmid=${cmid}`);
  const rows = page.locator('table tbody tr[data-slot]');
  await expect(rows.first(), 'The flow editor lists no scenes').toBeVisible({ timeout: 30000 });

  return rows.evaluateAll((nodes) => nodes.map((n) => Number(n.getAttribute('data-slot'))));
}

/**
 * Configure one scene's direction card.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} cmid The quiz course-module id.
 * @param {number} slot The slot number.
 * @param {Object} settings What to set.
 * @param {string} [settings.sceneType] The scene type, by option value.
 * @param {number} [settings.score] The score reward.
 * @param {number} [settings.xp] The XP reward.
 * @param {string} [settings.intro] The opening narrative.
 * @param {string} [settings.success] The narrative after a correct answer.
 */
async function configureScene(page, cmid, slot, settings) {
  await page.goto(`/local/stackmathgame/flow.php?cmid=${cmid}&slot=${slot}&action=editslot`);
  await expect(
    page.locator('#id_scenetype'),
    `The direction card for slot ${slot} did not open`
  ).toBeVisible({ timeout: 30000 });

  const expander = page.locator('a:has-text("Expand all"), .collapseexpand').first();
  if (await expander.count()) {
    await expander.click().catch(() => {});
  }

  if (settings.sceneType) {
    await page.selectOption('#id_scenetype', settings.sceneType);
  }
  if (settings.intro !== undefined) {
    await page.fill('#id_narrative_intro', settings.intro);
  }
  if (settings.success !== undefined) {
    await page.fill('#id_narrative_success', settings.success);
  }
  if (settings.score !== undefined) {
    await page.fill('#id_reward_score', String(settings.score));
  }
  if (settings.xp !== undefined) {
    await page.fill('#id_reward_xp', String(settings.xp));
  }

  await page.click('#id_submitbutton');
  await expect(
    page.locator('text=/Changes saved|gespeichert/i').first(),
    `Saving the direction card for slot ${slot} produced no confirmation`
  ).toBeVisible({ timeout: 30000 });
}

/**
 * Re-open a scene and confirm a stored value survived the round trip.
 *
 * Persistence is worth checking separately because the failure mode is silent: the form saves,
 * says so, and the value is gone when the page is next opened. Two of this plugin's real defects
 * looked exactly like that.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} cmid The quiz course-module id.
 * @param {number} slot The slot number.
 * @param {string} selector The field to inspect.
 * @param {string} expected The expected value.
 */
async function assertScenePersisted(page, cmid, slot, selector, expected) {
  await page.goto(`/local/stackmathgame/flow.php?cmid=${cmid}&slot=${slot}&action=editslot`);
  await expect(
    page.locator(selector).first(),
    `Slot ${slot} did not keep ${selector} across a reload`
  ).toHaveValue(expected, { timeout: 30000 });
}

module.exports = { assertNoBlockers, assertScenePersisted, configureScene, enableGame, listScenes };
