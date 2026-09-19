/**
 * Creating a quiz, shaping its pages and filling it from the question bank - all through forms.
 *
 * @module     local_stackmathgame/e2e/quiz
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');
const { clickVisible } = require('./visible');
const { CATEGORY, selectCategory } = require('./stack-question');

/**
 * Add a quiz to a course with the game behaviour set.
 *
 * The behaviour is the one setting without which nothing else matters: the game does not start at
 * all, however complete the rest of the configuration looks. The plugin's prerequisite panel says
 * so, and this is where it is decided.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} courseid The course id.
 * @param {string} name The quiz name.
 * @returns {Promise<number>} The course-module id.
 */
async function createQuiz(page, courseid, name) {
  await page.goto(`/course/modedit.php?add=quiz&course=${courseid}&section=1`);
  await expect(page.locator('#id_name')).toBeVisible({ timeout: 30000 });
  await page.fill('#id_name', name);

  await expandAll(page);
  await page.selectOption('#id_preferredbehaviour', 'stackmathgame');
  // One question per page: the branching navigation moves between pages, so anything else makes
  // the game play only the first question of each page.
  await page.selectOption('#id_questionsperpage', '1');

  // "Save and display", not "Save and return to course": the second button lands on the course
  // page, where there is no course-module id to read, so the step afterwards had nothing to work
  // with. The two ids differ by a single character and do opposite things.
  await page.click('#id_submitbutton');
  await expect(page).toHaveURL(/mod\/quiz\//, { timeout: 60000 });

  const cmid = Number(new URL(page.url()).searchParams.get('id')
    || new URL(page.url()).searchParams.get('cmid') || 0);
  expect(cmid, 'The new quiz has no course-module id in its URL').toBeGreaterThan(0);
  return cmid;
}

/**
 * Expand every collapsed section of the form on screen.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function expandAll(page) {
  // By role, and both roles: Moodle renders "Expand all" as a link in some versions and a button
  // in others. The selector that only looked for a link left the form collapsed, and the field
  // it was meant to reveal - the question behaviour - then timed out as though it did not exist.
  const byRole = page.getByRole('button', { name: /Expand all/i })
    .or(page.getByRole('link', { name: /Expand all/i }));
  if (await byRole.count()) {
    await byRole.first().click().catch(() => {});
    return;
  }
  const legacy = page.locator('.collapseexpand').first();
  if (await legacy.count()) {
    await legacy.click().catch(() => {});
  }
}

/**
 * Add named questions from the course bank to the quiz.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} cmid The quiz course-module id.
 * @param {string[]} names The question names to add.
 */
async function addQuestionsFromBank(page, cmid, names) {
  await page.goto(`/mod/quiz/edit.php?cmid=${cmid}`);

  await clickVisible(
    page.getByRole('button', { name: /^Add$/ }).or(page.getByRole('link', { name: /^Add$/ })),
    'Opening the add-question menu'
  );

  // The menu entries are rendered as anchors inside a dropdown and reported with varying roles
  // between Moodle versions, so they are matched by their container and their text rather than by
  // a role - a role-only locator found nothing while the menu was open in front of it.
  await clickVisible(
    page.locator('.dropdown-menu, [role="menu"], .menu')
      .locator('a, button, [role="menuitem"]')
      .filter({ hasText: /from question bank/i }),
    'Choosing "from question bank"'
  );

  // Scoped to the page, not to a .modal-dialog. Moodle 4.5 renders the question chooser inline
  // rather than in a modal, so everything scoped to a dialog matched nothing while the chooser
  // was plainly on screen - and every failure said the question had not been imported.
  await expect(
    page.getByRole('combobox', { name: /Category/i }).first(),
    'The question chooser did not open'
  ).toBeVisible({ timeout: 30000 });

  await page.waitForLoadState('networkidle').catch(() => {});
  await selectCategory(page, CATEGORY, page);

  for (const name of names) {
    const entry = page.getByText(name, { exact: false }).first();
    await expect(entry, `"${name}" is not offered in the chooser`).toBeVisible({ timeout: 30000 });

    const row = entry
      .locator('xpath=ancestor::*[self::tr or self::li or contains(@class,"row")][1]')
      .first();
    const box = (await row.count())
      ? row.locator('input[type="checkbox"]').first()
      : page.locator('input[type="checkbox"]').first();
    await box.check();
  }

  await clickVisible(
    page.getByRole('button', { name: /Add selected questions/i })
      .or(page.locator('input[value*="Add selected"]')),
    'Adding the selected questions'
  );

  for (const name of names) {
    await expect(
      page.locator(`text=${name}`).first(),
      `"${name}" did not land in the quiz`
    ).toBeVisible({ timeout: 30000 });
  }
}

/**
 * Add a section heading before a slot, which the game reads as a level boundary.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} cmid The quiz course-module id.
 * @param {number} slot The slot the new section starts at, 1-based.
 * @param {string} heading The level name.
 */
async function addSectionHeading(page, cmid, slot, heading) {
  await page.goto(`/mod/quiz/edit.php?cmid=${cmid}`);

  // The control sits on the page break above the slot it starts at.
  const adder = page.locator('a:has-text("Add"), button:has-text("Add")').nth(slot - 1);
  await adder.click();
  await page.locator('a:has-text("new section heading")').first().click();

  const input = page.locator('input[name="heading"], .inplaceeditable input').first();
  await expect(input, 'The section heading field did not appear').toBeVisible({ timeout: 30000 });
  await input.fill(heading);
  await input.press('Enter');

  await expect(
    page.locator(`text=${heading}`).first(),
    `The section heading "${heading}" was not saved`
  ).toBeVisible({ timeout: 30000 });
}

module.exports = { addQuestionsFromBank, addSectionHeading, createQuiz, expandAll };
