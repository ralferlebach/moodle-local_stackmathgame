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
const ui = require('./ui');

/**
 * Add a quiz to the course with the game behaviour set, the way a teacher adds an activity.
 *
 * Starts on the course page: edit mode on, "Add an activity or resource", "Quiz" in the chooser,
 * then the settings form. The behaviour is the one setting without which nothing else matters:
 * the game does not start at all, however complete the rest of the configuration looks.
 *
 * @param {import('@playwright/test').Page} page The page, on the course.
 * @param {string} name The quiz name.
 * @returns {Promise<number>} The course-module id, read from the new quiz's page.
 */
async function createQuiz(page, name) {
  await ui.setEditMode(page, true);

  // Opened from the keyboard: the button shows a loading indicator over itself while the chooser
  // loads, and a pointer click arriving then is refused as covered. Focus and Enter is how a
  // keyboard user operates the same button.
  const chooser = page.locator('[data-region="chooser-options-container"]:visible').first();
  const add = page.locator('button.section-modchooser-link:visible, button[data-action="open-chooser"]:visible').last();
  // The section's own button at its end; the ones between activities only show on hover.
  await expect(add, 'The course offers no "Add an activity or resource"').toBeVisible({ timeout: 30000 });
  // Pressed again only while the chooser is visibly not open - a press before Moodle has wired
  // the button does nothing. Once the chooser is open it takes focus on its first entry, and a
  // further Enter would add that activity, an assignment, instead.
  for (let attempt = 0; attempt < 3 && !(await chooser.isVisible().catch(() => false)); attempt++) {
    await add.focus();
    await page.keyboard.press('Enter');
    await chooser.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
  }
  await expect(chooser, 'The activity chooser did not open').toBeVisible({ timeout: 30000 });
  const option = page.locator('[data-region="chooser-option-container"][data-internal="quiz"]:visible').first();
  await expect(option, 'The activity chooser offers no quiz').toBeVisible({ timeout: 30000 });
  await option.locator('a').first().click();
  await ui.settle(page);

  await expect(page.locator('#id_name'), 'The quiz settings form did not open').toBeVisible({ timeout: 60000 });
  await page.fill('#id_name', name);

  await expandAll(page);
  await page.selectOption('#id_preferredbehaviour', 'stackmathgame');
  // One question per page: the branching navigation moves between pages, so anything else makes
  // the game play only the first question of each page.
  await page.selectOption('#id_questionsperpage', '1');

  // "Save and display", not "Save and return to course": the second button lands on the course
  // page, where there is no course-module id to read, so the step afterwards had nothing to work
  // with. The two ids differ by a single character and do opposite things.
  // "Save and display": the teacher lands on the new quiz.
  await page.click('#id_submitbutton');
  await expect(page).toHaveURL(/mod\/quiz\//, { timeout: 60000 });
  await ui.settle(page);

  const cmid = await ui.currentCmid(page);
  expect(cmid, 'The new quiz page does not say which activity it is').toBeGreaterThan(0);
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
    // Clicked until it has worked: a click that lands before Moodle's form script has bound the
    // control does nothing, and the sections stay closed. Worked means the control now offers
    // "Collapse all".
    const collapse = page.getByRole('button', { name: /Collapse all/i })
      .or(page.getByRole('link', { name: /Collapse all/i }));
    for (let attempt = 0; attempt < 10; attempt++) {
      await byRole.first().click().catch(() => {});
      if (await collapse.first().isVisible({ timeout: 1000 }).catch(() => false)) {
        return;
      }
      await page.waitForTimeout(500);
    }
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
 * @param {import('@playwright/test').Page} page The page, on the quiz.
 * @param {string[]} names The question names to add.
 */
async function addQuestionsFromBank(page, names) {
  await ui.secondaryNav(page, 'Questions');
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

  // Confirmed on a freshly loaded structure page, not on the page the modal leaves behind. On a
  // slow runner that page was still rendering - its heading showed the literal template
  // placeholder "${heading}" - and the questions were reported missing although they had been
  // added.
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.reload({ waitUntil: 'domcontentloaded' });
  await ui.settle(page);
  for (const name of names) {
    await expect(
      page.locator('tr, li').filter({ hasText: name }).first(),
      `"${name}" did not land in the quiz`
    ).toBeVisible({ timeout: 60000 });
  }
}

module.exports = { addQuestionsFromBank, createQuiz, expandAll };
