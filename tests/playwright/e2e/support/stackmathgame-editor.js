/**
 * The plugin's own authoring surfaces: game settings and the flow editor.
 *
 * @module     local_stackmathgame/e2e/stackmathgame-editor
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');
const { expandAll } = require('./quiz');
const { uploadThroughFilepicker } = require('./filepicker');
const ui = require('./ui');

/**
 * Open the game settings of the quiz on screen, through "More" in the quiz's navigation.
 *
 * @param {import('@playwright/test').Page} page The page, on any page of the quiz.
 */
async function openGameSettings(page) {
  await ui.secondaryNav(page, 'Game settings');
  await expect(page.locator('#id_enabled'), 'The game settings did not open').toBeVisible({ timeout: 30000 });
}

/**
 * Fail when the game settings report a prerequisite as unmet.
 *
 * @param {import('@playwright/test').Page} page The page, on any page of the quiz.
 */
async function assertNoBlockers(page) {
  await openGameSettings(page);

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
 * @param {string|RegExp} design The design, matched against the option text.
 */
async function enableGame(page, design) {
  if (!(await page.locator('#id_enabled').isVisible().catch(() => false))) {
    await openGameSettings(page);
  }

  const enabled = page.locator('#id_enabled');
  await expect(enabled, 'The game settings form has no enable switch').toBeVisible({ timeout: 30000 });
  await enabled.check();

  // The design picker is a radio group, one card per design - not a select. The first version
  // of this helper looked for #id_designid, found nothing and skipped the choice without a word,
  // so every journey ran with whatever design was preselected, whatever it had been asked for.
  // It now fails when the design is not on offer, and says what was.
  const radios = page.locator('input[type="radio"][name="designid"]');
  await expect(radios.first(), 'The game settings offer no design to choose').toBeAttached({ timeout: 30000 });
  const choices = await radios.evaluateAll((nodes) => nodes.map((n) => {
    const label = n.closest('label') || document.querySelector(`label[for="${n.id}"]`);
    const text = ((label && label.textContent) || n.parentElement.textContent || '').replace(/\s+/g, ' ').trim();
    return { id: n.id, value: n.value, text };
  }));
  // Anchored at the start, without a closing word boundary: the card's text runs the design name
  // straight into its component name ("RPG Fantasystackmathgamemode_rpg..."), so "\\bRPG Fantasy\\b"
  // never matched - and "RPG" alone would have matched two designs.
  const escaped = String(design).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const wanted = design instanceof RegExp ? design : new RegExp(`^${escaped}`, 'i');
  const match = choices.find((c) => wanted.test(c.text));
  expect(match, `No design matching "${design}" - offered: ${choices.map((c) => c.text).join(' | ')}`).toBeTruthy();
  await page.locator(`input[type="radio"][name="designid"][value="${match.value}"]`).check({ force: true });

  await page.click('#id_submitbutton');
  await expect(
    page.locator('text=/Changes saved|gespeichert/i').first(),
    'Saving the game settings produced no confirmation'
  ).toBeVisible({ timeout: 30000 });
}

/**
 * Open the game flow from the game settings and return the slot numbers it lists.
 *
 * @param {import('@playwright/test').Page} page The page, on any page of the quiz.
 * @returns {Promise<number[]>} The slot numbers, in the order shown.
 */
async function listScenes(page) {
  if (!(await page.locator('#id_enabled').isVisible().catch(() => false))) {
    await openGameSettings(page);
  }
  await page.getByRole('link', { name: ui.exactly('Game flow') }).first().click();
  await ui.settle(page);
  const rows = page.locator('table tbody tr[data-slot]');
  await expect(rows.first(), 'The flow editor lists no scenes').toBeVisible({ timeout: 30000 });

  return rows.evaluateAll((nodes) => nodes.map((n) => Number(n.getAttribute('data-slot'))));
}

/**
 * Open one scene's direction card from the flow list.
 *
 * @param {import('@playwright/test').Page} page The page, on the flow list.
 * @param {number} slot The slot number.
 */
async function openScene(page, slot) {
  const row = page.locator(`table tbody tr[data-slot="${slot}"]`).first();
  await expect(row, `The flow list has no row for slot ${slot}`).toBeVisible({ timeout: 30000 });
  await row.getByRole('link', { name: ui.exactly('Edit') }).first().click();
  await ui.settle(page);
  await expect(
    page.locator('#id_scenetype'),
    `The direction card for slot ${slot} did not open`
  ).toBeVisible({ timeout: 30000 });
}

/**
 * Configure one scene's direction card, starting from the flow list.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} slot The slot number.
 * @param {Object} settings What to set.
 * @param {string} [settings.sceneType] The scene type, by option value.
 * @param {number} [settings.score] The score reward.
 * @param {number} [settings.xp] The XP reward.
 * @param {string} [settings.intro] The opening narrative.
 * @param {string} [settings.success] The narrative after a correct answer.
 */
async function configureScene(page, slot, settings) {
  await openScene(page, slot);

  // Shared with the quiz form: "Expand all" is a button in some versions and a link in others,
  // and a helper that looked for only one of them left the narrative and reward sections closed.
  await expandAll(page);

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
 * @param {number} slot The slot number.
 * @param {string} selector The field to inspect.
 * @param {string} expected The expected value.
 */
async function assertScenePersisted(page, slot, selector, expected) {
  await openScene(page, slot);
  await expect(
    page.locator(selector).first(),
    `Slot ${slot} did not keep ${selector} across a reload`
  ).toHaveValue(expected, { timeout: 30000 });
  // Back to the list the way a teacher leaves a form unchanged.
  await page.locator('#id_cancel').click();
  await ui.settle(page);
}


/**
 * Import a design package through the Game Design Studio.
 *
 * The same route a site administrator takes: Studio, Import, the ZIP into the file picker, save.
 * The studio then opens the imported design, which is the confirmation that it was accepted.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} zip Absolute path of the design package.
 * @param {string} name The design name the package declares.
 */
async function importDesignPackage(page, zip, name) {
  await ui.openStudio(page);
  await page.locator('.nav-tabs a, .tabtree a').filter({ hasText: ui.exactly('Import') }).first().click();
  await ui.settle(page);
  await uploadThroughFilepicker(page, zip);
  await page.click('#id_submitbutton');
  await page.waitForLoadState('domcontentloaded').catch(() => {});

  const problem = page.locator('.alert-danger').first();
  if (await problem.isVisible().catch(() => false)) {
    throw new Error(`The studio rejected the package: ${(await problem.innerText()).trim()}`);
  }
  await expect(
    page.locator('input[name="name"]').first(),
    'The studio did not open the imported design'
  ).toHaveValue(new RegExp(name, 'i'), { timeout: 60000 });
}

module.exports = {
  importDesignPackage, assertNoBlockers, assertScenePersisted, configureScene, enableGame, listScenes,
  openGameSettings };
