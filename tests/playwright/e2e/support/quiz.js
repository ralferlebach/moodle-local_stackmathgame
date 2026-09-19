/**
 * Creating a quiz, shaping its pages and filling it from the question bank - all through forms.
 *
 * @module     local_stackmathgame/e2e/quiz
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

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

  await page.click('#id_submitbutton2');
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
  const expander = page.locator('a:has-text("Expand all"), .collapseexpand').first();
  if (await expander.count()) {
    await expander.click().catch(() => {});
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

  await page.locator('a:has-text("Add"), button:has-text("Add")').first().click();
  await page.locator('a:has-text("from question bank")').first().click();

  const dialog = page.locator('.modal-dialog').last();
  await expect(dialog, 'The question bank chooser did not open').toBeVisible({ timeout: 30000 });

  for (const name of names) {
    const row = dialog.locator(`tr:has-text("${name}")`).first();
    await expect(row, `"${name}" is not offered in the chooser`).toBeVisible({ timeout: 30000 });
    await row.locator('input[type="checkbox"]').first().check();
  }

  await dialog.locator('button:has-text("Add selected questions"), input[value*="Add selected"]')
    .first().click();

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
