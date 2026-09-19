/**
 * Creating STACK questions through the question bank form.
 *
 * No XML or GIFT import. Importing is a legitimate thing for a teacher to do, but it is not the
 * authoring workflow, and this suite exists to prove the authoring workflow works - a question
 * bank filled from a file says nothing about whether the form can produce a playable question.
 *
 * The questions are deliberately trivial (1+1, 2*3, x+2=5). What is under test is the plugin's
 * integration with STACK, not STACK's algebra.
 *
 * @module     local_stackmathgame/e2e/stack-question
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

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
async function createStackQuestion(page, courseid, question) {
  // Through the question bank and its chooser, not a constructed addquestion.php URL. The URL
  // form takes different parameters between Moodle versions, and guessing them would mean the
  // test fails on a version difference that a person clicking "Create a new question" would
  // never notice.
  await page.goto(`/question/edit.php?courseid=${courseid}`);

  await page.locator('button:has-text("Create a new question"), input[value*="Create a new question"]')
    .first().click();

  const chooser = page.locator('.modal-dialog, .qbank-chooser, form').last();
  const stack = chooser.locator('label:has-text("STACK"), input[value="stack"]').first();
  await expect(
    stack,
    'STACK is not offered in the question type chooser - is qtype_stack installed?'
  ).toBeVisible({ timeout: 30000 });
  await stack.click();

  const go = chooser.locator('button:has-text("Add"), input[value="Add"], button:has-text("Continue")').first();
  if (await go.count()) {
    await go.click();
  }

  await expect(
    page.locator('#id_name'),
    'The STACK question form did not open'
  ).toBeVisible({ timeout: 60000 });

  await page.fill('#id_name', question.name);

  // The three placeholders are what make a STACK question: the input to type into, the
  // validation line that echoes how STACK read it, and the feedback the tree produces.
  const body = `<p>${question.text}</p><p>[[input:ans1]] [[validation:ans1]]</p><div>[[feedback:prt1]]</div>`;
  await fillEditor(page, '#id_questiontext', body);

  await page.fill('#id_ans1modelans', question.answer);

  await page.click('#id_submitbutton');

  await expect(
    page.locator(`text=${question.name}`).first(),
    `The question "${question.name}" was not saved - check the form for validation errors`
  ).toBeVisible({ timeout: 60000 });
}

/**
 * Write into a Moodle editor field, whichever editor is configured.
 *
 * Atto and TinyMCE both replace the textarea with an iframe or a contenteditable div, so setting
 * the textarea's value alone is silently discarded on submit.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} selector The textarea selector.
 * @param {string} html The HTML to write.
 */
async function fillEditor(page, selector, html) {
  const editable = page.locator(`${selector}editable, [contenteditable="true"]`).first();
  if (await editable.count()) {
    await editable.click();
    await editable.evaluate((node, value) => {
      node.innerHTML = value;
      node.dispatchEvent(new Event('input', { bubbles: true }));
    }, html);
    // The editor copies its content back into the textarea on submit; nudging it here keeps the
    // two in step even when the editor is slow to react.
    await page.locator(selector).first().evaluate((node, value) => {
      node.value = value;
    }, html).catch(() => {});
    return;
  }
  await page.fill(selector, html);
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
  await page.goto(`/question/edit.php?courseid=${courseid}`);
  const row = page.locator(`tr:has-text("${name}")`).first();
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

module.exports = { createStackQuestion, previewAndVerify, fillEditor };
