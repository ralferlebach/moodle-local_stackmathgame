/**
 * The RPG played like a student plays it, checked against what the question engine stored.
 *
 * re-audit N09. The UI journey (game-full-ui.spec.js) builds the game through the interface; this
 * one takes a seeded quiz and concentrates on the play itself, which the UI journey used to fake:
 * it wrote hidden inputs through evaluate(), clicked "check" twice whatever STACK said, and took an
 * attempt summary on the quiz page as proof of a result. Here:
 *
 *  - answers go into the visible field - the STACK Math Editor's MathQuill field with the editor
 *    on, STACK's own field with it off - by keyboard, as a student types;
 *  - a check is followed by what STACK shows: its validation of the input, its feedback, and only
 *    the checks the question's configuration calls for;
 *  - every result is compared with the question attempt steps, the grade and the attempt state
 *    the question engine stored, read through support/smg-e2e.php.
 *
 * Needs a site on SMG_BASE_URL whose code is at SMG_MOODLE_ROOT (the helper runs there with the
 * php on PATH, or SMG_PHP). The seed builds the course; nothing the student does is seeded.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { test, expect } = require('@playwright/test');
const {
  check, finishAttempt, goToQuestion, helper, next, stackField, typeAnswer, typeChain, waitForGame, watchLeaveDialogs,
  waitForValidation: waitForStack,
} = require('./support/play');
const system = require('./support/system');
const ui = require('./support/ui');

/** The fixture's questions, slot by slot (tests/fixtures/e2e_verified_questions.xml). */
const SLOT = { add: 1, expand: 2, cube: 3, equiv: 4 };

/**
 * Which inputs STACK makes the student confirm (mustverify in the fixture). Only those are
 * validated while typing - STACK initialises instant validation for nothing else - and only for
 * those does a check have to wait for it. The others are graded by the first check.
 */
const MUSTVERIFY = { [SLOT.add]: true, [SLOT.expand]: false, [SLOT.cube]: false, [SLOT.equiv]: true };

/** The equivalence chain the fixture's teacher answer uses. */
const CHAIN = ['1+2*3^3', '1+2*27', '1+54', '55'];

/**
 * Wait for STACK as this fixture's configuration requires for a slot.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} slot The slot.
 */
async function waitForValidation(page, slot) {
  await waitForStack(page, MUSTVERIFY[slot]);
}

/**
 * Sign in - a system function, not under test (support/system.js).
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} username The user.
 * @param {string} password The password.
 */
async function login(page, username, password) {
  await system.signIn(page, username, password);
}

/**
 * Open the quiz from its course page and start the attempt, as the participant does.
 *
 * Opening the course is a system function; from there on everything is clicked.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {Object} seed The seed: courseid and quizname.
 * @returns {Promise<number>} The attempt id, read from the page's address.
 */
async function startAttempt(page, seed) {
  await system.openCourse(page, seed.courseid);
  await ui.openActivity(page, seed.quizname);
  await page.locator('form[action*="startattempt.php"]').first()
    .locator('button[type="submit"], input[type="submit"]').first().click();
  await page.waitForURL(/attempt\.php/, { waitUntil: 'domcontentloaded' });
  await waitForGame(page);
  return Number(new URL(page.url()).searchParams.get('attempt'));
}

/**
 * Read the question engine's record of the attempt.
 *
 * @param {Object} seed The seed result.
 * @returns {Object} The state.
 */
function stored(seed) {
  return helper('state', seed.username, String(seed.cmid));
}

/**
 * Play slot 1 wrongly, then correctly, and check what was stored each time.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {Object} seed The seed.
 * @param {boolean} editor Whether the editor is on.
 */
async function wrongThenRight(page, seed, editor) {
  await typeAnswer(page, editor, ['6']);
  await waitForValidation(page, SLOT.add);
  expect(await check(page)).toBe('status_saved');
  await expect(page.locator('.smg-rpg-feedback')).toContainText('Not yet - try again.');
  await expect(page.locator('a.smg-rpg-next[href]'), 'A wrong answer opened the way on').toHaveCount(0);
  let state = stored(seed);
  let slot = state.slots[SLOT.add];
  expect(slot.vars.ans1).toBe('6');
  expect(slot.fraction).toBe(0);
  // Exactly one graded step for the wrong answer: the check was not doubled.
  expect(slot.steps.filter((s) => s.data.ans1 === '6').length).toBe(1);

  await typeAnswer(page, editor, ['5']);
  await waitForValidation(page, SLOT.add);
  expect(await stackField(page).inputValue()).toBe('5');
  expect(await check(page)).toBe('status_saved');
  await expect(page.locator('.smg-rpg-feedback')).toContainText('Correct, well done.');
  await expect(page.locator('a.smg-rpg-next[href]')).toHaveCount(1);
  state = stored(seed);
  slot = state.slots[SLOT.add];
  expect(slot.vars.ans1).toBe('5');
  expect(slot.vars.ans1_val).toBe('5');
  // Graded right; adaptivemultipart keeps the attempt open, so the state is "todo" with the mark.
  expect(slot.steps[slot.steps.length - 1].data.ans1).toBe('5');
  expect(slot.steps.map((s) => s.data.ans1).filter(Boolean)).toEqual(['6', '5']);
  expect(state.profile.progress.slots[String(SLOT.add)].solved).toBe(1);
}

/**
 * The equivalence question: first line left out, then the whole chain.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {Object} seed The seed.
 * @param {boolean} editor Whether the editor is on.
 */
async function equivalence(page, seed, editor) {
  // The first line deliberately left out. STACK refuses the argument with its own first-line
  // message; the game transfers the input unchanged - the stored step holds the three lines.
  await typeChain(page, editor, CHAIN.slice(1));
  await expect(page.locator('.smg-rpg-panel .stackinputfeedback').first())
    .toContainText(/wrong first line|first line in your argument must be/i, { timeout: 60000 });
  expect(await check(page)).toBe('status_saved');
  await expect(page.locator('.smg-rpg-panel').first())
    .toContainText(/wrong first line|first line in your argument must be/i);
  let slot = stored(seed).slots[SLOT.equiv];
  expect(slot.vars.ans1.split(/\r?\n/)).toEqual(CHAIN.slice(1));

  // The whole chain: transferred line by line and graded.
  await typeChain(page, editor, CHAIN);
  await expect.poll(async () => (await stackField(page).inputValue()).split(/\r?\n/),
    { message: 'The chain did not reach STACK\'s field', timeout: 10000 }).toEqual(CHAIN);
  await waitForValidation(page, SLOT.equiv);
  await expect(page.locator('.smg-rpg-panel .stackinputfeedback').first())
    .not.toContainText(/wrong first line/i, { timeout: 60000 });
  expect(await check(page)).toBe('status_saved');
  await expect(page.locator('.smg-rpg-feedback')).toContainText(/Excellent reasoning|Good: every line/);
  slot = stored(seed).slots[SLOT.equiv];
  expect(slot.vars.ans1.split(/\r?\n/)).toEqual(CHAIN);
  expect(slot.fraction).toBeGreaterThan(0.99);
}

test.describe.configure({ mode: 'serial' });
test.setTimeout(15 * 60 * 1000);

test.describe('the RPG, played and verified against the question engine (re-audit N09)', () => {
  test('editor on: MathQuill, wrong then right, back and forward, reload, chain, finish', async ({ page }) => {
    const editor = helper('editor', 'on');
    test.skip(editor.editor === 'absent', 'local_stackmatheditor is not installed on this site.');
    const seed = helper('seed');
    expect(seed.questions).toBe(4);
    expect(seed.blockers).toEqual([]);
    const dialogs = watchLeaveDialogs(page);

    await login(page, seed.username, seed.password);
    await startAttempt(page, seed);
    await expect(page.locator('.smg-rpg-panel .sme-toolbar').first()).toBeVisible();

    await wrongThenRight(page, seed, true);
    await next(page);

    // Slot 2: x^2, out of the exponent, +2x+1 - MathQuill writes it to STACK's field.
    // The multiplication typed as a student types it in the editor: "*" becomes the dot. The
    // fixture does not insert stars, so "2x" would be refused as STACK refuses it on paper.
    await typeAnswer(page, true, ['x^2', 'ArrowRight', '+2*x+1']);
    await waitForValidation(page, SLOT.expand);
    const visible = await stackField(page).inputValue();
    expect(await check(page)).toBe('status_saved');
    await expect(page.locator('.smg-rpg-feedback')).toContainText('Correct, well done.');
    expect(stored(seed).slots[SLOT.expand].vars.ans1).toBe(visible);

    // Back to the solved scene: it is shown solved, with its way on open.
    await page.locator('a.smg-rpg-arrow.is-back[href]').first().click();
    await page.waitForLoadState('domcontentloaded');
    await waitForGame(page);
    await expect(stackField(page)).toHaveValue('5');
    await expect(page.locator('a.smg-rpg-next[href]')).toHaveCount(1);
    await next(page);

    // A reload keeps the stored answer and the open way on, and asks nothing.
    await page.reload();
    await waitForGame(page);
    await expect(stackField(page)).toHaveValue(visible);
    await expect(page.locator('a.smg-rpg-next[href]')).toHaveCount(1);
    await next(page);

    await typeAnswer(page, true, ['27']);
    await waitForValidation(page, SLOT.cube);
    expect(await check(page)).toBe('status_saved');
    await next(page);

    await equivalence(page, seed, true);
    await finishAttempt(page);

    const state = stored(seed);
    expect(state.state).toBe('finished');
    expect(state.slots[SLOT.add].state).toBe('gradedright');
    expect(state.slots[SLOT.expand].state).toBe('gradedright');
    expect(state.slots[SLOT.cube].state).toBe('gradedright');
    expect(state.slots[SLOT.equiv].state).toBe('gradedright');
    // Slot 1 was answered wrongly once; adaptivemultipart's penalty applies to that slot only.
    expect(state.slots[SLOT.add].mark).toBeLessThan(1);
    expect(state.slots[SLOT.expand].mark).toBe(1);
    expect(state.sumgrades).toBeGreaterThan(3.5);
    expect(dialogs.filter((d) => d.type === 'beforeunload'), 'A saved scene asked whether to leave').toEqual([]);
  });

  test('editor off: native fields - the stored step holds exactly the visible value', async ({ page }) => {
    helper('editor', 'off');
    const seed = helper('seed');
    const dialogs = watchLeaveDialogs(page);
    await login(page, seed.username, seed.password);
    await startAttempt(page, seed);
    await expect(page.locator('.smg-rpg-panel .sme-toolbar')).toHaveCount(0);

    await wrongThenRight(page, seed, false);
    await next(page);

    // StackMathGame path of the editor issues #100/#101: change the visible input, check, and the
    // stored step must hold exactly what the field showed.
    await typeAnswer(page, false, ['x^2+2*x+1']);
    await stackField(page).fill('x^2+2*x+1 ');
    await stackField(page).fill('(x+1)^2');
    await waitForValidation(page, SLOT.expand);
    const visible = await stackField(page).inputValue();
    expect(await check(page)).toBe('status_saved');
    expect(stored(seed).slots[SLOT.expand].vars.ans1).toBe(visible);

    await next(page);
    await typeAnswer(page, false, ['27']);
    await waitForValidation(page, SLOT.cube);
    expect(await check(page)).toBe('status_saved');
    await next(page);
    await equivalence(page, seed, false);
    await finishAttempt(page);

    const state = stored(seed);
    expect(state.state).toBe('finished');
    expect(Object.values(state.slots).map((s) => s.state)).toEqual(['gradedright', 'gradedright', 'gradedright', 'gradedright']);
    expect(dialogs.filter((d) => d.type === 'beforeunload')).toEqual([]);
  });

  for (const layout of [
    { name: 'narrow viewport 390px', viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 },
    // 200 % zoom of a 1280x800 window: half the CSS pixels, at twice the density.
    { name: '200% zoom', viewport: { width: 640, height: 400 }, deviceScaleFactor: 2 },
  ]) {
    test(`${layout.name}: toolbar, full chain, feedback and navigation stay usable`, async ({ browser }) => {
      const editor = helper('editor', 'on');
      test.skip(editor.editor === 'absent', 'local_stackmatheditor is not installed on this site.');
      const seed = helper('seed');
      const context = await browser.newContext({ viewport: layout.viewport, deviceScaleFactor: layout.deviceScaleFactor });
      const page = await context.newPage();
      await login(page, seed.username, seed.password);
      const attempt = await startAttempt(page, seed);
      const width = layout.viewport.width;

      /**
       * Assert an element lies within the viewport's width - not clipped, not off to the side.
       *
       * @param {import('@playwright/test').Locator} locator The element.
       * @param {string} what What it is, for the message.
       */
      const withinWidth = async (locator, what) => {
        await locator.scrollIntoViewIfNeeded();
        const box = await locator.boundingBox();
        expect(box, `${what} has no box`).not.toBeNull();
        expect(box.x, `${what} starts left of the viewport`).toBeGreaterThanOrEqual(-1);
        expect(box.x + box.width, `${what} ends right of the viewport`).toBeLessThanOrEqual(width + 1);
      };

      // Free navigation: straight to the equivalence question.
      // Straight to the chain through Moodle's own quiz navigation, as a participant would jump.
      await goToQuestion(page, SLOT.equiv);
      const toolbar = page.locator('.smg-rpg-panel .sme-toolbar').first();
      await expect(toolbar).toBeVisible();
      for (const button of await toolbar.locator('.sme-tb-btn').all()) {
        if (await button.isVisible()) {
          await withinWidth(button, 'A toolbar button');
        }
      }

      await typeChain(page, true, CHAIN);
      const rows = page.locator('.smg-rpg-panel .sme-equiv-row');
      await expect(rows).toHaveCount(CHAIN.length);
      for (let i = 0; i < CHAIN.length; i++) {
        await expect(rows.nth(i)).toBeVisible();
        await withinWidth(rows.nth(i), `Line ${i + 1} of the chain`);
      }
      await expect.poll(async () => (await stackField(page).inputValue()).split(/\r?\n/),
        { timeout: 10000 }).toEqual(CHAIN);
      await waitForValidation(page, SLOT.equiv);
      await withinWidth(page.locator('.smg-action-check').first(), 'The check control');
      expect(await check(page)).toBe('status_saved');

      const feedback = page.locator('.smg-rpg-feedback');
      await expect(feedback).toContainText(/Excellent reasoning|Good: every line/);
      await withinWidth(feedback, 'The feedback');
      const finish = page.locator('a.smg-rpg-next[href]').first();
      await expect(finish).toBeVisible();
      await withinWidth(finish, 'The way on');
      expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth),
        'The page scrolls sideways').toBeLessThanOrEqual(1);
      // Nothing may cover the way on: the element under its centre must be the control itself.
      const box = await finish.boundingBox();
      const hit = await page.evaluate(([x, y]) => {
        const el = document.elementFromPoint(x, y);
        return el ? (el.closest('a.smg-rpg-next') ? 'the control' : el.outerHTML.slice(0, 160)) : 'nothing';
      }, [box.x + box.width / 2, box.y + box.height / 2]);
      expect(hit, 'Something covers the way on').toBe('the control');
      const dialogs = watchLeaveDialogs(page);
      await finish.click();
      await page.waitForURL(/summary\.php/, { waitUntil: 'domcontentloaded' });
      expect(dialogs, 'Leaving the solved scene asked whether to leave').toEqual([]);
      expect(stored(seed).slots[SLOT.equiv].state).toBe('complete');
      await context.close();
    });
  }
});
