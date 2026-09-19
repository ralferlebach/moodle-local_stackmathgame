/**
 * Getting STACK questions into a course's question bank, through the import form.
 *
 * Ralf supplies the questions as a fixture rather than having the test author them field by
 * field. That is a deliberate narrowing of scope: what this suite is for is the game - its
 * settings, its flow editor, its runtime - and typing a STACK question into Moodle's question
 * form tests Moodle's question form. The import screen is still the interface; it is what a
 * teacher does with questions they were given.
 *
 * The questions themselves stay trivial (1+1=2, 2*2=4, 3^3=27) so that a failure means the game
 * is wrong, never that the algebra was hard.
 *
 * @module     local_stackmathgame/e2e/stack-question
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

/** The category the fixture file declares for its questions. */
const CATEGORY = 'StackMathGame E2E Fixtures';

/**
 * Create one STACK question in a course's question bank.
 *
 * Only the fields a working STACK question actually needs are filled. STACK derives an input and
 * a potential response tree from the placeholders in the question text and gives them sensible
 * defaults - the input is compared to its own model answer with AlgEquiv - so a question with one
 * input and one tree needs no further configuration. The preview afterwards is what proves that
 * held: if the defaults ever change, the preview fails and names the question.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} courseid The course id.
 * @param {Object} question The question to create.
 * @param {string} question.name The question name.
 * @param {string} question.text The prompt, without the placeholders.
 * @param {string} question.answer The correct answer, as a Maxima expression.
 */
async function importQuestions(page, courseid, fixture) {
  await page.goto(`/question/bank/importquestions/import.php?courseid=${courseid}`);

  const format = page.locator('#id_format_xml');
  await expect(
    format,
    'The import form has no Moodle XML option - is the question bank reachable?'
  ).toBeVisible({ timeout: 30000 });
  await format.check();

  // "Get category from file" off, so everything lands in the course default category. The fixture
  // declares its own category, and Moodle 4.5's question bank filters by category through an
  // autocomplete widget rather than a plain select - reproducing that UI in the test would be a
  // page of clicks that says nothing about this plugin. One checkbox removes the problem: the
  // questions are then exactly where the bank and the quiz chooser already look.
  // It is an advanced element, hidden until "Show more..." is used - so unchecking it without
  // expanding first waits on something that is in the DOM and not on screen.
  const showMore = page.locator('a.moreless-toggler, a:has-text("Show more")').first();
  if (await showMore.count() && await showMore.isVisible()) {
    await showMore.click();
  }
  const fromFile = page.locator('#id_catfromfile');
  if (await fromFile.count() && await fromFile.isVisible() && await fromFile.isChecked()) {
    await fromFile.uncheck();
  }

  // Moodle's file picker, not a plain file field. The import form shows a drop area whose real
  // <input type="file"> only exists inside the repository dialog, so setting files on the page
  // directly waits for an element that is never rendered.
  await page.getByRole('button', { name: /Choose a file|Add\.\.\./i }).first().click();

  // Addressed on the page rather than inside a scoped container: the picker is Moodle's YUI
  // dialogue and its wrapper class has changed more than once, so scoping to it made the step
  // fail with "the picker did not open" while the picker was plainly open.
  await expect(
    page.getByRole('heading', { name: /File picker/i }).first(),
    'The file picker did not open'
  ).toBeVisible({ timeout: 30000 });

  const uploadRepo = page.getByRole('link', { name: /Upload a file/i }).first();
  if (await uploadRepo.count()) {
    await uploadRepo.click();
  }

  await page.locator('input[type="file"]').first().setInputFiles(fixture);
  await page.getByRole('button', { name: /Upload this file/i }).first().click();

  await expect(
    page.locator('.filepicker-filename, .fp-filename').first(),
    'The uploaded file does not appear on the import form'
  ).toBeVisible({ timeout: 60000 });

  await page.click('#id_submitbutton');

  await expect(
    page.locator('text=/importing|questions? from file|Fragen/i').first(),
    'The import produced no progress report'
  ).toBeVisible({ timeout: 120000 });

  const proceed = page.locator('button:has-text("Continue"), input[value="Continue"]').first();
  if (await proceed.count()) {
    await proceed.click();
  }
}

/**
 * Switch a question bank view to the category the fixture created.
 *
 * The fixture declares its own category ("$course$/top/StackMathGame E2E Fixtures"), and both the
 * bank list and the quiz's question chooser open on the course default instead. The questions are
 * imported and correct; they are simply on another page - which reads as "the import silently did
 * nothing" and sends you looking in the wrong place.
 *
 * @param {import('@playwright/test').Locator|import('@playwright/test').Page} scope Page or dialog.
 * @param {string} fragment Part of the category name.
 * @param {import('@playwright/test').Page} page The page, for the suggestion list, which the
 *        autocomplete renders outside the scope it belongs to.
 */
async function selectCategory(scope, fragment, page) {
  const trace = (msg) => {
    if (process.env.SMG_DEBUG_FILTER) {
      console.log('[filter]', msg);
    }
  };
  // Three renderings, because Moodle has changed this twice and the differences are not cosmetic.
  //
  //  * up to 4.3    a plain <select name="category"> above the list;
  //  * 4.4 and 4.5  a filter row, whose category value is still a <select>, but named
  //                 #filter-value-category and only applied when "Apply filters" is pressed;
  //  * 5.1 onwards  the bank was reworked again and the control is an autocomplete.
  //
  // Guessing between them cost several runs: the helper looked for the 4.3 markup, found nothing
  // and returned quietly, so the questions appeared never to have been imported when they were
  // simply on another page.
  // Visibility decides, not existence. From 4.4 the category filter keeps a <select> in the DOM
  // purely as the backing store for an autocomplete - it carries class="hidden" and
  // aria-hidden="true" - so a branch that only asked "is there a select?" always took the wrong
  // path and timed out trying to choose an option in an element no one can see.
  const legacy = scope.locator('select[name="category"], #id_selectacategory').first();
  trace(`legacy count=${await legacy.count()}`);
  if (await legacy.count() && await legacy.isVisible()) {
    await selectByText(legacy, fragment);
    return;
  }

  const filterValue = scope.locator('#filter-value-category, select[id^="filter-value-category"]')
    .first();
  trace(`filterValue count=${await filterValue.count()} visible=${await filterValue.isVisible().catch(() => 'n/a')}`);
  if (await filterValue.count() && await filterValue.isVisible()) {
    await selectByText(filterValue, fragment);
    await applyFilters(scope, page);
    return;
  }

  // 5.1+ autocomplete. The suggestion list is attached to the document rather than to the filter
  // row, so it is looked for on the page.
  // The visible half of the autocomplete. Moodle gives it a generated id
  // (form_autocomplete_input-<timestamp>), so it is found by role instead.
  const auto = scope.locator('input[role="combobox"], input[id^="form_autocomplete_input"]').last();
  trace(`auto count=${await auto.count()}`);
  if (!(await auto.count())) {
    trace('no autocomplete found - returning without filtering');
    return;
  }
  await auto.click();
  await auto.fill(fragment);
  // Plain [role="option"]: Moodle renders the suggestions in a container whose class has changed
  // between versions, and scoping to it found nothing while the options were plainly there. The
  // role is what the widget guarantees.
  const suggestion = page.locator('[role="option"]').filter({ hasText: fragment }).first();
  await expect(
    suggestion,
    `The category filter found nothing called "${fragment}"`
  ).toBeVisible({ timeout: 30000 });
  await suggestion.click();

  // The hidden <select> behind the autocomplete is the authority on what was actually chosen.
  // Checking it turns a silent miss - the click landing on nothing, the filter staying empty -
  // into a failure that says so, instead of an empty question list three assertions later.
  const backing = scope.locator('#filter-value-category, select[data-field-name="category"]').first();
  if (await backing.count()) {
    await expect(
      backing,
      `The category filter did not take "${fragment}" - the suggestion was clicked but nothing was selected`
    ).not.toHaveValue('', { timeout: 10000 });
  }

  await applyFilters(scope, page);
}

/**
 * Choose the option of a select whose text contains a fragment.
 *
 * @param {import('@playwright/test').Locator} select The select.
 * @param {string} fragment Part of the option's text.
 */
async function selectByText(select, fragment) {
  const option = select.locator(`option:has-text("${fragment}")`).first();
  if (!(await option.count())) {
    return;
  }
  await select.selectOption({ value: await option.getAttribute('value') });
}

/**
 * Apply the question bank filters and wait for the list to be rebuilt.
 *
 * @param {import('@playwright/test').Locator|import('@playwright/test').Page} scope Page or dialog.
 * @param {import('@playwright/test').Page} page The page.
 */
async function applyFilters(scope, page) {
  const apply = scope.getByRole('button', { name: /Apply filters/i }).first();
  if (await apply.count()) {
    await apply.click();
    // The list is rebuilt over AJAX, so waiting for navigation would wait forever.
    await page.waitForLoadState('networkidle').catch(() => {});
  }
}

/**
 * Preview a question and confirm the known correct answer is marked correct.
 *
 * This is the check that makes the authoring step meaningful. A question can save cleanly and
 * still be ungradable - a missing tree, a CAS that never answers - and without a preview the
 * failure would surface much later, in the play-through, as a game that refuses to progress.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} courseid The course id.
 * @param {string} name The question name.
 * @param {string} answer The known correct answer.
 */
async function previewAndVerify(page, courseid, name, answer) {
  // Without qperpage: that parameter made Moodle render a page with no filter controls at
  // all, so every branch below found nothing and the helper returned without filtering - which
  // looked exactly like the import having failed.
  await page.goto(`/question/edit.php?courseid=${courseid}`);
  // The filter row is built by an AMD module after the page loads, so measuring straight after
  // goto() finds no controls at all - which reads as "this page has no filter" rather than "the
  // filter is not there yet", and sends you looking for the wrong Moodle version.
  await page.waitForLoadState('networkidle').catch(() => {});
  await selectCategory(page, CATEGORY, page);
  // filter({ hasText }) rather than the :has-text() pseudo-class: the latter takes the name as a
  // CSS-ish argument, and these names contain spaces and hyphens that it does not handle the way
  // it looks like it should. The question was on the page every time; the selector was not.
  const row = page.locator('tr').filter({ hasText: name }).first();
  await expect(row, `"${name}" is not in the question bank`).toBeVisible({ timeout: 30000 });

  const [preview] = await Promise.all([
    page.context().waitForEvent('page'),
    row.locator('a[title*="Preview" i], a:has-text("Preview")').first().click(),
  ]);
  await preview.waitForLoadState();

  const input = preview.locator('input[id$="_ans1"]').first();
  await expect(input, `The preview of "${name}" shows no input`).toBeVisible({ timeout: 30000 });

  // STACK grades only after the student has confirmed how their input was read, so the answer is
  // submitted twice: the first pass validates, the second is graded.
  for (const pass of [1, 2]) {
    await input.fill(answer);
    await preview.locator('input[type="submit"][name$="submit"], button:has-text("Check")')
      .first().click();
    await preview.waitForTimeout(pass === 1 ? 1500 : 3000);
  }

  await expect(
    preview.locator('text=/correct|richtig/i').first(),
    `The known correct answer to "${name}" was not graded correct - the CAS or the question is wrong`
  ).toBeVisible({ timeout: 30000 });

  await preview.close();
}

module.exports = { CATEGORY, importQuestions, previewAndVerify, selectCategory };
