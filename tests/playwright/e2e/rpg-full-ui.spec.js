/**
 * The whole RPG, built and played through the interface and nothing else.
 *
 * From the first login onwards nothing is seeded, and nothing is written through a CLI, a web
 * service or SQL. Everything a real administrator, teacher or participant would do is done by
 * clicking it - and typing: answers go into the visible field, MathQuill or STACK's own, never into
 * a hidden input by script (re-audit N09). At the very end the question engine's record is READ
 * through support/smg-e2e.php and compared with what the participant saw; that needs the site's
 * code at SMG_MOODLE_ROOT and php on the runner.
 *
 * One test rather than several. The steps depend on each other - a quiz cannot be configured
 * before it exists, a game cannot be played before it is configured - so separate tests would
 * rebuild the same state repeatedly and report the same failure several ways. The boundaries are
 * test.step() calls, which is what makes the report readable and the video navigable.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { test, expect } = require('@playwright/test');
const auth = require('./support/auth');
const quiz = require('./support/quiz');
const stackQuestion = require('./support/stack-question');
const editor = require('./support/stackmathgame-editor');
const diag = require('./support/artifact-summary');
// The mode under test. SMG_GAME=wisewizzard plays the Digital Mentoring tutor instead of the RPG;
// everything mode-specific - where the controls are, what the counters are - is in its profile.
const game = require(`./support/games/${process.env.SMG_GAME || 'rpg'}`);
const { clickVisible } = require('./support/visible');
const play = require('./support/play');
const fs = require('fs');
const path = require('path');

/**
 * Where reference images go, when asked for.
 *
 * Set SMG_REFERENCE_DIR to have the journey photograph each kind of scene as it plays: the
 * starting meadow, a monster before and after the answer, the validation box, the end. The images
 * are for comparing against the original by eye - a layout regression shows up there long before
 * it breaks an assertion.
 */
const REFERENCE_DIR = process.env.SMG_REFERENCE_DIR || '';

/**
 * Photograph the page for the reference set.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} name The file name, without extension.
 */
async function reference(page, name) {
  if (!REFERENCE_DIR) {
    return;
  }
  fs.mkdirSync(REFERENCE_DIR, { recursive: true });
  await page.waitForTimeout(1200);
  await page.screenshot({ path: path.join(REFERENCE_DIR, `${name}.png`), fullPage: false });
}

const ADMIN_USER = process.env.SMG_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.SMG_ADMIN_PASS || 'Admin!23';
// Created in the backend by .github/e2e-seed.php, which the workflow runs before this test.
// Participant, course and enrolment are site administration rather than game building, and
// driving them through the interface was the most fragile part of the journey.
const PLAYER_USER = process.env.SMG_PLAYER_USER;
const PLAYER_PASS = process.env.SMG_PLAYER_PASS || 'Smg-Play-Pass!1';
const COURSE_ID = Number(process.env.SMG_COURSE_ID || 0);
const BASE_URL = process.env.SMG_BASE_URL || 'http://127.0.0.1:8000';

/**
 * All five fixture questions, and what solves each.
 *
 * The answer is a list of lines. Four questions take a single line; the equivalence-reasoning
 * question takes one line per step, and its first line is already filled in by STACK
 * (the "firstline" option), so only the steps after it are typed.
 */
const QUESTIONS = [
  // A welcome page: its input is hidden by the question itself, so it is not answered but
  // left - which is what the instruction scene type is for.
  { name: 'SMG Fixture 01 - Welcome to the Game', answer: [], instruction: true },
  // mustverify is the fixture's own setting: whether STACK makes the student confirm the input.
  // Only then is there a validation to wait for; either way one check grades.
  { name: 'SMG Fixture 02 - One Plus One', answer: ['1+1=2'], mustverify: false },
  { name: 'SMG Fixture 03 - Two Times Two', answer: ['2*2=4'], mustverify: false },
  { name: 'SMG Fixture 04 - Three Cubed', answer: ['3^3=27'], mustverify: false },
  { name: 'SMG Fixture 05 - Equivalence Reasoning', answer: ['1+2*27', '1+54', '55'], appendToFirstLine: true,
    mustverify: true },
];

/** The fixture file the questions are imported from. */
const FIXTURE = require('path').resolve(__dirname, '..', '..', 'fixtures', 'e2e_stack_questions.xml');

// Installing a course, authoring three STACK questions through forms and playing an attempt are
// all slower than a normal assertion, and a cold CAS is slower still.
test.setTimeout(20 * 60 * 1000);

/**
 * Type an answer into the visible field of the presented question, as a participant does.
 *
 * With the STACK Math Editor in front of the field that is its MathQuill field (one row per line
 * for an equivalence chain); without it, STACK's own field. The previous version wrote the value
 * into the hidden original input by script, which tested nothing a participant does.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string[]} lines The answer, one entry per line.
 * @returns {Promise<string>} What STACK's field holds afterwards.
 */
async function answerVisibly(page, lines) {
  const editor = await page.locator(play.within('.sme-mq-container .mq-editable-field, .sme-equiv-row'))
    .first().isVisible().catch(() => false);
  if (lines.length > 1) {
    await play.typeChain(page, editor, lines);
  } else {
    await play.typeAnswer(page, editor, editor ? play.keysFor(lines[0]) : lines);
  }
  await expect.poll(async () => (await play.stackField(page).inputValue()).split(/\r?\n/),
    { message: 'The answer did not reach STACK\'s field', timeout: 10000 }).toEqual(lines);
  return play.stackField(page).inputValue();
}

test.describe('StackMathGame RPG, built and played through the interface', () => {
  test('an administrator builds an RPG and a participant plays it to the end', async ({ page }) => {
    const stamp = Date.now();
    const trouble = diag.watchForTrouble(page, BASE_URL);
    // Issue #16: moving on after a saved answer must not ask whether to leave the page, and
    // leaving with an unsaved edit must. Every leave-page dialog is recorded and judged at the end;
    // an unexpected one is accepted so the journey can go on and report it.
    const leaveWarnings = [];
    let expectLeaveWarning = false;
    let leaveWarningTested = false;
    page.on('dialog', async (dialog) => {
      if (dialog.type() !== 'beforeunload') {
        return;
      }
      leaveWarnings.push({ url: page.url(), expected: expectLeaveWarning });
      if (expectLeaveWarning) {
        await dialog.dismiss();
      } else {
        await dialog.accept();
      }
    });
    const done = [];
    // What the participant's visible field held at each check, by quest.
    const typed = [];
    const courseid = COURSE_ID;
    let cmid = 0;
    let scenes = [];

    /**
     * Run a step, remembering it for the summary and naming it if it fails.
     *
     * @param {string} title What the step does.
     * @param {Function} body The step.
     */
    const step = async (title, body) => {
      try {
        await test.step(title, body);
      } catch (error) {
        diag.writeStepSummary(done, title, error.message);
        throw error;
      }
      done.push(title);
    };

    await step('The backend prepared a participant and a course', async () => {
      expect(PLAYER_USER, 'SMG_PLAYER_USER is not set - run .github/e2e-seed.php first').toBeTruthy();
      expect(courseid, 'SMG_COURSE_ID is not set - run .github/e2e-seed.php first').toBeGreaterThan(0);
    });

    await step('Log in as an administrator', async () => {
      await auth.login(page, ADMIN_USER, ADMIN_PASS);
    });

    // Optional: a design package to import through the studio first. The licensed artwork is not
    // part of the plugin, so CI plays the bundled placeholders; a site that has the package - and
    // a local reference run - imports it here, by the same route an administrator would.
    if (process.env.SMG_DESIGN_PACKAGE) {
      await step('Import the design package through the Game Design Studio', async () => {
        await editor.importDesignPackage(page, process.env.SMG_DESIGN_PACKAGE, game.designName);
      });
    }

    await step('Import the STACK fixture questions', async () => {
      await stackQuestion.importQuestions(page, courseid, FIXTURE);
    });

    // The question bank preview step was dropped deliberately. It existed to prove the imported
    // questions are gradable, and it coupled this suite to the bank's own list markup - which
    // changes between Moodle versions and cost several runs to chase. The play-through proves the
    // same thing better: it answers each question through the game and requires STACK to accept
    // the answer. A question that cannot be graded fails there, in the step that matters.

    await step('Create the quiz with the STACK Math Game behaviour', async () => {
      cmid = await quiz.createQuiz(page, courseid, `The trial of ${stamp}`);
    });

    await step('Add the questions to the quiz', async () => {
      await quiz.addQuestionsFromBank(page, cmid, QUESTIONS.map((q) => q.name));
    });

    // No level split in this journey. Adding a section heading goes through Moodle's in-place
    // editor on the quiz structure page, which is its own piece of UI with its own quirks, and it
    // tests Moodle's quiz editor rather than this plugin. The level behaviour it would set up -
    // enterslevel, the chapter_start narrative - is covered where it is decided, in
    // page_group_resolver_test and navigation_resolver_test.

    await step('The quiz reports no prerequisite blockers', async () => {
      await editor.assertNoBlockers(page, cmid);
    });

    await step('Switch the game on and choose the RPG design', async () => {
      await editor.enableGame(page, cmid, game.designName);
    });

    await step('The flow editor lists one scene per question', async () => {
      scenes = await editor.listScenes(page, cmid);
      expect(
        scenes.length,
        `The flow lists ${scenes.length} scenes for ${QUESTIONS.length} questions`
      ).toBe(QUESTIONS.length);
    });

    await step('Configure every scene, ending on a boss', async () => {
      for (let i = 0; i < scenes.length; i++) {
        const last = i === scenes.length - 1;
        await editor.configureScene(page, cmid, scenes[i], {
          sceneType: QUESTIONS[i].instruction ? 'instruction' : (last ? game.finalSceneType : 'challenge'),
          score: 10 * (i + 1),
          xp: 5 * (i + 1),
          intro: `Quest ${i + 1} begins.`,
          success: `Quest ${i + 1} is won.`,
        });
      }
    });

    await step('The configuration survives a reload', async () => {
      await editor.assertScenePersisted(page, cmid, scenes[0], '#id_reward_score', '10');
      await editor.assertScenePersisted(page, cmid, scenes[0], '#id_narrative_intro', 'Quest 1 begins.');
    });

    await step('Hand over to the participant', async () => {
      await auth.logout(page);
      await auth.login(page, PLAYER_USER, PLAYER_PASS);
    });

    await step('Start the quiz and wait for the RPG to take over', async () => {
      await page.goto(`/mod/quiz/view.php?id=${cmid}`);
      const form = page.locator('form[action*="startattempt.php"]').first();
      await expect(form.first(), 'The quiz offers no way to start an attempt').toBeVisible({ timeout: 30000 });
      await form.first().locator('button[type="submit"], input[type="submit"]').first().click();

      const confirm = page.locator('#id_submitbutton, .modal button:has-text("Continue")').first();
      if (await confirm.count()) {
        await confirm.first().click().catch(() => {});
      }

      await game.waitUntilReady(page);
    });

    await step('Play every quest to the end', async () => {
      const before = await game.readHud(page);

      for (let i = 0; i < QUESTIONS.length; i++) {
        await reference(page, `q${i + 1}-a-scene`);

        if (QUESTIONS[i].instruction) {
          // Nothing to answer: the way forward has to be on offer the moment the scene loads.
          // If it is not, the game treats a briefing page like a challenge nobody can solve.
          const onward = game.nextControl(page);
          await expect(
            onward,
            `Quest ${i + 1} is an instruction scene but offers no way on`
          ).toBeVisible({ timeout: 60000 });
          await onward.click();
          await page.waitForLoadState('domcontentloaded').catch(() => {});
          await game.waitUntilReady(page);
          continue;
        }

        // Waiting for the field is what tells "the game reached this quest" from "the game is
        // stuck on the last one". The field is the original STACK input, which an editor may hide.
        await expect(
          play.stackField(page),
          `Quest ${i + 1} has no STACK input - the game did not reach it`
        ).toBeAttached({ timeout: 60000 });

        const question = QUESTIONS[i];
        let lines = question.answer;
        if (question.appendToFirstLine) {
          // STACK prefills the first line of an equivalence chain ("firstline"), and the steps
          // continue below it. Overwriting it would remove the line the chain starts from.
          const prefilled = (await play.stackField(page).inputValue()).split(/\r?\n/)[0].trim();
          lines = [prefilled, ...question.answer].filter(Boolean);
        }

        // One check, after STACK has validated where its configuration asks for it. The previous
        // version checked twice whatever STACK said.
        typed[i] = await answerVisibly(page, lines);
        await play.waitForValidation(page, question.mustverify);
        await reference(page, `q${i + 1}-b-validated`);
        expect(await play.check(page), `Quest ${i + 1} was not saved`).toBe('status_saved');
        await expect(
          page.locator('.smg-rpg-feedback .stackprtfeedback, .que[data-smg-controlled="1"] .stackprtfeedback').first(),
          `STACK's feedback for quest ${i + 1} is not shown`
        ).toBeVisible({ timeout: 60000 });
        await reference(page, `q${i + 1}-c-solved`);

        if (!leaveWarningTested && i < QUESTIONS.length - 1) {
          leaveWarningTested = true;
          // An edit that was not checked is protected: leaving asks first, and staying keeps it.
          const before = page.url();
          const edited = lines.length === 1 ? [`${lines[0]}+0`] : [...lines.slice(0, -1), `${lines[lines.length - 1]}+0`];
          await answerVisibly(page, edited);
          expectLeaveWarning = true;
          await game.nextControl(page).click();
          await page.waitForTimeout(1500);
          expectLeaveWarning = false;
          expect(page.url(), 'Leaving with an unchecked edit did not stay on the page').toBe(before);
          // Checked again, the edit is saved, and the way on is clear again.
          typed[i] = await answerVisibly(page, lines);
          await play.waitForValidation(page, question.mustverify);
          expect(await play.check(page)).toBe('status_saved');
        }

        if (i < QUESTIONS.length - 1) {
          const next = game.nextControl(page);
          await expect(
            next,
            `The game offers no way on after quest ${i + 1}`
          ).toBeVisible({ timeout: 60000 });
          await next.click();
          await page.waitForLoadState('domcontentloaded').catch(() => {});
          await game.waitUntilReady(page);
        }
      }

      const after = await game.readHud(page);
      // Only for a mode that counts: the tutor has no counters, and a journey that asserted them
      // regardless would fail for the wrong reason.
      if (game.hasCounters) {
        expect(
          after.fairies,
          `The counters did not move: ${JSON.stringify(before)} → ${JSON.stringify(after)}`
        ).toBeGreaterThan(before.fairies);
      }
    });

    await step('The run reaches its end and the attempt is finished', async () => {
      // "Finish" rather than "next": the last scene is configured to end the run, so a control
      // that still offers another scene would mean the branching never noticed the end.
      await expect(
        page.locator('.smg-runtime-shell').first(),
        'The game shell disappeared before the end of the run'
      ).toBeAttached();
      // By its label first: the RPG renders the same navigation element for "next" and "finish"
      // and changes only its text and target, while a hidden twin from the previous scene can
      // still sit in the DOM.
      await clickVisible(
        page.getByRole('link', { name: /Finish the run|Finish/i })
          .or(page.locator('a.smg-rpg-next[href*="summary.php"], a[href*="summary.php"]')),
        'The last quest offers no way to finish the run',
        60000
      );
      // The attempt is really finished, through the summary page, as a participant finishes it.
      // An attempt summary on the quiz page proved nothing: an unfinished attempt shows one too.
      await play.submitFromSummary(page);
    });

    await step('The question engine stored what the participant saw', async () => {
      const state = play.helper('state', PLAYER_USER, String(cmid));
      expect(state.state, 'The attempt is not finished').toBe('finished');
      for (let i = 0; i < QUESTIONS.length; i++) {
        const slot = state.slots[i + 1];
        expect(slot, `No question attempt for quest ${i + 1}`).toBeTruthy();
        if (QUESTIONS[i].instruction) {
          continue;
        }
        expect(slot.vars.ans1, `Quest ${i + 1}: the stored answer is not the visible one`).toBe(typed[i]);
        expect(slot.state, `Quest ${i + 1} is not graded right`).toBe('gradedright');
      }
      expect(state.sumgrades, 'The attempt has no grade').not.toBeNull();
    });

    await step('Nothing broke quietly along the way', async () => {
      // Collected across the whole journey. None of these raise an exception, and all of them
      // matter here: a 404 for a sprite is invisible in the page, and a broken AMD module simply
      // means nothing happens.
      const unexpected = leaveWarnings.filter((w) => !w.expected);
      expect(
        unexpected.map((w) => w.url),
        'The browser asked whether to leave a page whose answer was already saved'
      ).toEqual([]);
      expect(
        leaveWarnings.filter((w) => w.expected).length,
        'Leaving with an unchecked edit did not warn'
      ).toBe(1);
      const report = diag.describeTrouble(trouble);
      expect(report, `The browser reported problems during the journey:\n\n${report}`).toBe('');
    });

    diag.writeStepSummary(done);
  });
});
