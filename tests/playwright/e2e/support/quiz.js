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
  await page.waitForLoadState('networkidle').catch(() => {});

  await clickVisible(
    page.getByRole('button', { name: /^Add$/ }).or(page.getByRole('link', { name: /^Add$/ })),
    'Opening the add-question menu'
  );

  // The menu entries are anchors whose reported role varies between versions, so they are matched
  // by text. "Add" itself appears in several menus on this page, hence the anchored /^Add$/ above.
  await clickVisible(
    page.locator('a').filter({ hasText: /from question bank/i }),
    'Choosing "from question bank"'
  );

  const modal = page.locator('.modal-dialog').last();
  await expect(modal, 'The question chooser did not open').toBeVisible({ timeout: 30000 });
  // The modal renders its shell first and fills in the filter row and the question table after.
  await page.waitForTimeout(3000);
  // The modal fetches its question list over AJAX; measuring before that finishes finds an empty
  // filter row and an empty table.
  await page.waitForLoadState('networkidle').catch(() => {});

  // The chooser opens on the course default category, which is empty here - the fixture brings
  // its own. Without this the questions are genuinely absent from the modal, which is exactly
  // what "not offered in the chooser" was reporting, correctly.
  await selectCategory(modal, CATEGORY, page);

  for (const name of names) {
    const row = modal.locator('tr').filter({ hasText: name }).first();
    await expect(row, `"${name}" is not offered in the chooser`).toBeVisible({ timeout: 30000 });
    await row.locator('input[type="checkbox"]').first().check();
  }

  await clickVisible(
    modal.getByRole('button', { name: /Add selected questions/i })
      .or(modal.locator('input[value*="Add selected"]')),
    'Adding the selected questions'
  );

  for (const name of names) {
    await expect(
      page.locator('tr, li').filter({ hasText: name }).first(),
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
  await page.waitForLoadState('networkidle').catch(() => {});

  // The "Add" control sits on the page break above the slot the new section starts at, so the
  // nth one is the one that matters. Matched by role, like the others: :has-text("Add") also
  // matches "Add from the question bank", which opens the wrong menu entirely.
  const adders = page.getByRole('button', { name: /^Add$/ })
    .or(page.getByRole('link', { name: /^Add$/ }));
  await adders.nth(slot - 1).click();
  await page.waitForTimeout(600);

  await clickVisible(
    page.locator('a').filter({ hasText: /new section heading/i }),
    'Choosing "a new section heading"'
  );
  await page.waitForTimeout(1500);

  // Moodle inserts the section under a placeholder name and does not open the editor: the
  // placeholder is an inplaceeditable that has to be clicked first. Waiting for the input without
  // that click waits for something no version has ever shown on its own.
  const editable = page
    .locator('.inplaceeditable')
    .filter({ hasText: /Untitled|New section|Section/i })
    .last();
  if (await editable.count()) {
    await editable.locator('a, [role="button"]').first().click().catch(async () => {
      await editable.click().catch(() => {});
    });
    await page.waitForTimeout(800);
  }

  const input = page.locator('.inplaceeditable input, input[name="heading"]').first();
  await expect(input, 'The section heading field did not appear').toBeVisible({ timeout: 30000 });
  await input.fill(heading);
  await input.press('Enter');

  await expect(
    page.getByText(heading, { exact: false }).first(),
    `The section heading "${heading}" was not saved`
  ).toBeVisible({ timeout: 30000 });
}

module.exports = { addQuestionsFromBank, addSectionHeading, createQuiz, expandAll };
