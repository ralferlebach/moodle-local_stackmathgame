/**
 * Playing a STACK question in the RPG the way a student does, for the browser journeys.
 *
 * Shared by rpg-verified-play.spec.js and game-full-ui.spec.js (re-audit N09): answers go into the
 * visible field by keyboard, a check waits for what STACK's configuration calls for and reads the
 * outcome the engine reports, and the question engine's record is read back through
 * smg-e2e.php.
 *
 * @module     local_stackmathgame/e2e/support/play
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const path = require('path');

const HELPER = path.resolve(__dirname, 'smg-e2e.php');

/**
 * Where the presented question's fields are: the RPG's panel, or the question itself for a mode
 * that leaves it in place.
 */
const ROOTS = ['.smg-rpg-panel', '.que[data-smg-controlled="1"]'];

/**
 * Build a selector for something inside the presented question, in every mode.
 *
 * @param {string} inner The selector within the question.
 * @returns {string} The selector.
 */
function within(inner) {
  return ROOTS.map((root) => inner.split(',').map((part) => `${root} ${part.trim()}`).join(', ')).join(', ');
}

/**
 * Run the helper and return its JSON.
 *
 * @param {...string} args The command and its arguments.
 * @returns {Object} The result.
 */
function helper(...args) {
  const out = execFileSync(process.env.SMG_PHP || 'php', [HELPER, ...args], {
    env: process.env,
    encoding: 'utf8',
    timeout: 300000,
  });
  return JSON.parse(out.trim().split('\n').pop());
}


/**
 * Wait until the RPG has taken over and the presented question is in its panel.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function waitForGame(page) {
  await expect(page.locator('.smg-rpg-stage').first()).toBeVisible({ timeout: 60000 });
  await expect(page.locator('.smg-rpg-panel .formulation').first()).toBeAttached({ timeout: 60000 });
}


/**
 * The original STACK field of the presented question - the interface every surface writes to.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {import('@playwright/test').Locator} The field.
 */
function stackField(page) {
  return page.locator(within('input[name$="_ans1"], textarea[name$="_ans1"]')).first();
}


/**
 * Type into the visible field like a student: MathQuill when the editor is on, STACK's own field
 * otherwise. Keys are typed one step at a time; "ArrowRight" and friends are pressed.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {boolean} editor Whether the editor stands in front of the field.
 * @param {string[]} keys Text to type and keys to press, in order.
 */
async function typeAnswer(page, editor, keys) {
  if (editor) {
    const mq = page.locator(within('.sme-mq-container .mq-editable-field')).first();
    await expect(mq, 'The MathQuill field is not visible').toBeVisible();
    await mq.click();
    await page.keyboard.press('Control+a');
    await page.keyboard.press('Backspace');
  } else {
    const field = stackField(page);
    await expect(field, 'STACK\'s own field is not visible with the editor off').toBeVisible();
    await field.click();
    await field.press('Control+a');
    await field.press('Backspace');
  }
  for (const key of keys) {
    if (/^(Arrow|End|Home|Enter|Backspace|Control\+)/.test(key)) {
      await page.keyboard.press(key);
    } else {
      await page.keyboard.type(key);
    }
  }
}


/**
 * Type an equivalence chain, one step per line.
 *
 * With the editor on, a line is added with Enter (which copies the current step) and replaced; with
 * it off, the lines are typed into STACK's textarea.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {boolean} editor Whether the editor is on.
 * @param {string[]} lines The steps.
 */
async function typeChain(page, editor, lines) {
  if (!editor) {
    const area = stackField(page);
    await expect(area).toBeVisible();
    await area.click();
    await area.press('Control+a');
    await area.press('Backspace');
    await page.keyboard.type(lines.join('\n'));
    return;
  }
  const rows = page.locator(within('.sme-equiv-row'));
  await expect(rows.first()).toBeVisible();
  // Surplus lines go the way a student removes them: emptied, then one more Backspace.
  for (let guard = 0; guard < 12 && await rows.count() > lines.length; guard++) {
    await rows.last().locator('.mq-editable-field').first().click();
    await page.keyboard.press('Control+a');
    await page.keyboard.press('Backspace');
    await page.keyboard.press('Backspace');
  }
  for (let i = 0; i < lines.length; i++) {
    if (i < await rows.count()) {
      await rows.nth(i).locator('.mq-editable-field').first().click();
    } else {
      // Enter in the last line adds a line below it, holding a copy of the step.
      await rows.nth(i - 1).locator('.mq-editable-field').first().click();
      await page.keyboard.press('End');
      await page.keyboard.press('Enter');
    }
    await page.keyboard.press('Control+a');
    await page.keyboard.type(lines[i]);
  }
  await expect(rows).toHaveCount(lines.length);
}


/**
 * Wait until STACK has validated what the field holds now.
 *
 * With JavaScript on, STACK validates an input while it is typed: its validation box is marked
 * "waiting" from the first keystroke, "loading" while the request runs, and shows the result -
 * with the validated value in a hidden "_val" field - when it is done. STACK's own check button
 * is disabled for exactly that time. A check sent while the box still waits is only validated,
 * not graded. The previous journey ignored this and pressed "check" twice regardless; here the
 * check waits for STACK, and one check grades.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {boolean} mustverify Whether STACK makes the student confirm this input.
 */
async function waitForValidation(page, mustverify) {
  if (!mustverify) {
    // Nothing to wait for, and nothing shown: STACK validates this input only on the check.
    await expect(page.locator(within('.stackinputfeedback.waiting, .stackinputfeedback.loading')))
      .toHaveCount(0);
    return;
  }
  await page.waitForTimeout(150);
  await expect.poll(async () => page.evaluate((selector) => {
    const field = document.querySelector(selector);
    const box = field ? document.getElementById(field.name + '_val') : null;
    if (!box) {
      return 'no validation box';
    }
    if (box.classList.contains('waiting') || box.classList.contains('loading')) {
      return 'validating';
    }
    return box.textContent.trim() === '' ? 'empty' : 'validated';
  }, within('input[name$="_ans1"], textarea[name$="_ans1"]')), { message: 'STACK never finished validating the current input', timeout: 60000 }).toBe('validated');
}


/**
 * Press the game's check control and wait for the outcome the engine reports.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Promise<string>} The status key (status_saved, ...).
 */
async function check(page) {
  await page.locator('.smg-action-check').first().click();
  const status = page.locator('.smg-runtime-status');
  await expect.poll(async () => status.getAttribute('data-smg-status').catch(() => null), {
    message: 'The check never finished',
    timeout: 90000,
  }).not.toMatch(/^(status_saving)?$/);
  return status.getAttribute('data-smg-status');
}


/**
 * Collect every leave-page dialog, so none can go unnoticed.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Array} The dialogs seen.
 */
function watchLeaveDialogs(page) {
  const seen = [];
  page.on('dialog', async (dialog) => {
    seen.push({ type: dialog.type(), url: page.url() });
    await dialog.accept();
  });
  return seen;
}


/**
 * Follow the open "next" arrow.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function next(page) {
  const arrow = page.locator('a.smg-rpg-next[href]').first();
  await expect(arrow).toBeVisible();
  await arrow.scrollIntoViewIfNeeded();
  await arrow.click();
  await page.waitForLoadState('domcontentloaded');
  await waitForGame(page);
}


/**
 * Go to a question through Moodle's quiz navigation, as a participant jumps between questions.
 *
 * On a narrow screen the navigation sits in the closed block drawer, which is opened first with
 * its own button.
 *
 * @param {import('@playwright/test').Page} page The page, on the attempt.
 * @param {number} slot The question's number in the navigation.
 */
async function goToQuestion(page, slot) {
  const button = page.locator(`#quiznavbutton${slot}`).first();
  if (!(await button.isVisible().catch(() => false))) {
    // By its name: the drawer also holds a "Close block drawer" button with the same target.
    const drawer = page.getByRole('button', { name: 'Open block drawer' }).first();
    await expect(drawer, 'The quiz navigation is neither shown nor behind a drawer button').toBeVisible();
    await drawer.click();
  }
  await expect(button, `The quiz navigation has no question ${slot}`).toBeVisible({ timeout: 15000 });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    button.click(),
  ]);
  await waitForGame(page);
}

/**
 * Submit the attempt from the quiz summary page, as a student does: "Submit all and finish" and
 * its confirmation.
 *
 * @param {import('@playwright/test').Page} page The page, on summary.php.
 */
async function submitFromSummary(page) {
  await page.waitForURL(/summary\.php/, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Submit all and finish' }).first().click();
  // Moodle asks for confirmation in a dialog - unless the quiz goes straight to the review.
  const confirm = page.locator('.modal-dialog').getByRole('button', { name: 'Submit all and finish' });
  await expect(confirm.or(page.locator('body#page-mod-quiz-review')).first()).toBeVisible({ timeout: 60000 });
  if (await confirm.isVisible().catch(() => false)) {
    await confirm.click();
  }
  await page.waitForURL(/review\.php/, { waitUntil: 'domcontentloaded' });
}

/**
 * Finish the attempt from the RPG's last scene.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function finishAttempt(page) {
  const finish = page.locator('a.smg-rpg-next[href*="summary.php"]').first();
  await expect(finish, 'The last scene offers no way to finish the run').toBeVisible({ timeout: 60000 });
  await finish.click();
  await submitFromSummary(page);
}

/**
 * Turn a linear answer into the keys a student types into MathQuill.
 *
 * MathQuill opens an exponent on "^" and stays in it; a student leaves it with the right arrow
 * before typing on. "3^3=27" therefore becomes "3^3", ArrowRight, "=27".
 *
 * @param {string} text The answer as written on paper.
 * @returns {string[]} Text runs and key presses for typeAnswer().
 */
function keysFor(text) {
  const keys = [];
  const re = /\^([A-Za-z0-9]+)/g;
  let last = 0;
  let match;
  while ((match = re.exec(text)) !== null) {
    keys.push(text.slice(last, match.index) + '^' + match[1]);
    keys.push('ArrowRight');
    last = match.index + match[0].length;
  }
  if (last < text.length) {
    keys.push(text.slice(last));
  }
  return keys.filter((key) => key !== '');
}

module.exports = {
  keysFor,
  check,
  finishAttempt,
  goToQuestion,
  helper,
  next,
  stackField,
  submitFromSummary,
  within,
  typeAnswer,
  typeChain,
  waitForGame,
  waitForValidation,
  watchLeaveDialogs,
};
