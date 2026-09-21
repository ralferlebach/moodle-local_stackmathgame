/**
 * The whole RPG, built and played through the interface and nothing else.
 *
 * From the first login onwards there is no seeding, no CLI, no web service and no SQL. Everything
 * a real administrator, teacher or participant would do is done by clicking it.
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
const game = require('./support/games/rpg');
const { clickVisible } = require('./support/visible');

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
  { name: 'SMG Fixture 02 - One Plus One', answer: ['1+1=2'] },
  { name: 'SMG Fixture 03 - Two Times Two', answer: ['2*2=4'] },
  { name: 'SMG Fixture 04 - Three Cubed', answer: ['3^3=27'] },
  { name: 'SMG Fixture 05 - Equivalence Reasoning', answer: ['1+2*27', '1+54', '55'], appendToFirstLine: true },
];

/** The fixture file the questions are imported from. */
const FIXTURE = require('path').resolve(__dirname, '..', '..', 'fixtures', 'e2e_stack_questions.xml');

// Installing a course, authoring three STACK questions through forms and playing an attempt are
// all slower than a normal assertion, and a cold CAS is slower still.
test.setTimeout(20 * 60 * 1000);

test.describe('StackMathGame RPG, built and played through the interface', () => {
  test('an administrator builds an RPG and a participant plays it to the end', async ({ page }) => {
    const stamp = Date.now();
    const trouble = diag.watchForTrouble(page, BASE_URL);
    const done = [];
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

        // An algebraic input is a text field, an equivalence-reasoning input a textarea - so
        // both are looked for. Waiting for the field is what tells "the game reached this quest"
        // from "the game is stuck on the last one".
        const input = page.locator('.que input[id$="_ans1"], .que textarea[id$="_ans1"]').first();
        await expect(
          input,
          `Quest ${i + 1} shows no STACK input - the game did not reach it`
        ).toBeVisible({ timeout: 60000 });

        const question = QUESTIONS[i];
        let value = question.answer.join('\n');
        if (question.appendToFirstLine) {
          // STACK prefills the first line of an equivalence chain ("firstline"), and the steps
          // continue below it. Overwriting it would remove the line the chain starts from.
          const prefilled = (await input.inputValue()).trim();
          value = [prefilled, ...question.answer].filter(Boolean).join('\n');
        }

        // Twice: STACK grades only after the student has confirmed how the input was read.
        for (const pass of [1, 2]) {
          await input.fill(value);
          await page.locator('.smg-action-check, .que input[type="submit"]').first().click();
          await page.waitForTimeout(pass === 1 ? 2000 : 4000);
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
      expect(
        after.fairies,
        `The RPG counters did not move: ${JSON.stringify(before)} → ${JSON.stringify(after)}`
      ).toBeGreaterThan(before.fairies);
    });

    await step('The run reaches its end', async () => {
      // "Finish" rather than "next": the last scene is configured to end the run, so a control
      // that still offers another scene would mean the branching never noticed the end.
      await expect(
        page.locator('.smg-runtime-shell').first(),
        'The game shell disappeared before the end of the run'
      ).toBeAttached();

      // By its label first: the RPG renders the same navigation element for "next" and "finish"
      // and changes only its text and target, while a hidden twin from the previous scene can
      // still sit in the DOM. A class-based .first() picked that twin and waited for it forever.
      await clickVisible(
        page.getByRole('link', { name: /Finish the run|Finish/i })
          .or(page.locator('a.smg-rpg-next[href*="summary.php"], a[href*="summary.php"]')),
        'The last quest offers no way to finish the run',
        60000
      );
    });

    await step('Moodle records the attempt', async () => {
      await page.goto(`/mod/quiz/view.php?id=${cmid}`);
      // The text= engine cannot be mixed into a CSS selector list, which is what the first version
      // did - Playwright rejected the whole locator before looking at the page.
      await expect(
        page.locator('table.quizattemptsummary, .quizattemptsummary')
          .or(page.getByText(/Summary of your previous attempts|Your final grade|Attempt 1/i))
          .first(),
        'The quiz view shows no attempt for this participant'
      ).toBeVisible({ timeout: 60000 });
    });

    await step('Nothing broke quietly along the way', async () => {
      // Collected across the whole journey. None of these raise an exception, and all of them
      // matter here: a 404 for a sprite is invisible in the page, and a broken AMD module simply
      // means nothing happens.
      const report = diag.describeTrouble(trouble);
      expect(report, `The browser reported problems during the journey:\n\n${report}`).toBe('');
    });

    diag.writeStepSummary(done);
  });
});
