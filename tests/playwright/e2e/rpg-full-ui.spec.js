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
const course = require('./support/course');
const quiz = require('./support/quiz');
const stackQuestion = require('./support/stack-question');
const editor = require('./support/stackmathgame-editor');
const users = require('./support/users');
const diag = require('./support/artifact-summary');
const game = require('./support/games/rpg');

const ADMIN_USER = process.env.SMG_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.SMG_ADMIN_PASS || 'Admin!23';
const PLAYER_USER = process.env.SMG_PLAYER_USER || 'smgplayer';
const PLAYER_PASS = process.env.SMG_PLAYER_PASS || 'Smg-Play-Pass!1';
const PLAYER_FIRST = 'Pat';
const PLAYER_LAST = 'Player';
const BASE_URL = process.env.SMG_BASE_URL || 'http://127.0.0.1:8000';

/**
 * The questions, and the answers that solve them.
 *
 * Deliberately trivial. What is under test is the plugin's integration with STACK - the
 * authoring forms, the game configuration, the runtime - not STACK's ability to do algebra. A
 * hard question would only add ways for the test to fail for reasons that are nobody's fault.
 */
const QUESTIONS = [
  { name: 'Quest 1 – one and one', text: 'What is 1 + 1?', answer: '2' },
  { name: 'Quest 2 – two times three', text: 'What is 2 * 3?', answer: '6' },
  { name: 'Quest 3 – solve for x', text: 'If x + 2 = 5, what is x?', answer: '3' },
];

const LEVEL_TWO_HEADING = 'Level 2 – The deeper forest';

// Installing a course, authoring three STACK questions through forms and playing an attempt are
// all slower than a normal assertion, and a cold CAS is slower still.
test.setTimeout(20 * 60 * 1000);

test.describe('StackMathGame RPG, built and played through the interface', () => {
  test('an administrator builds an RPG and a participant plays it to the end', async ({ page }) => {
    const stamp = Date.now();
    const trouble = diag.watchForTrouble(page, BASE_URL);
    const done = [];
    let courseid = 0;
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

    await step('Log in as an administrator', async () => {
      await auth.login(page, ADMIN_USER, ADMIN_PASS);
    });

    await step('Create the participant account', async () => {
      // Created here, before the course, so the enrolment step below has somebody to enrol. Doing
      // it through Site administration rather than a CLI script is the point: an account conjured
      // outside the interface would not show that a real person can be given access.
      await users.createUser(page, {
        username: PLAYER_USER,
        password: PLAYER_PASS,
        firstname: PLAYER_FIRST,
        lastname: PLAYER_LAST,
        email: `${PLAYER_USER}@example.invalid`,
      });
    });

    await step('Create a course', async () => {
      courseid = await course.createCourse(page, `Math adventure ${stamp}`, `SMGRPG${stamp}`);
    });

    await step('Enrol the participant', async () => {
      await users.enrol(page, courseid, `${PLAYER_FIRST} ${PLAYER_LAST}`, 'Student');
    });

    await step('Create three STACK questions in the question bank', async () => {
      for (const question of QUESTIONS) {
        await stackQuestion.createStackQuestion(page, courseid, question);
      }
    });

    await step('Preview each question and confirm the CAS grades it', async () => {
      // Done before the questions reach the quiz. A question that cannot be graded would
      // otherwise surface much later, in the play-through, as a game that refuses to advance -
      // and the report would point at the game rather than at the question.
      for (const question of QUESTIONS) {
        await stackQuestion.previewAndVerify(page, courseid, question.name, question.answer);
      }
    });

    await step('Create the quiz with the STACK Math Game behaviour', async () => {
      cmid = await quiz.createQuiz(page, courseid, `The trial of ${stamp}`);
    });

    await step('Add the questions to the quiz', async () => {
      await quiz.addQuestionsFromBank(page, cmid, QUESTIONS.map((q) => q.name));
    });

    await step('Split the quiz into two levels', async () => {
      // A section heading is what the game reads as a level boundary, so this is also the setup
      // for the level change the play-through checks further down.
      await quiz.addSectionHeading(page, cmid, 3, LEVEL_TWO_HEADING);
    });

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
          sceneType: last ? game.finalSceneType : 'challenge',
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
      const form = page.locator('form[action*="startattempt.php"]');
      await expect(form.first(), 'The quiz offers no way to start an attempt').toBeVisible({ timeout: 30000 });
      await form.first().locator('button[type="submit"], input[type="submit"]').first().click();

      const confirm = page.locator('#id_submitbutton, .modal button:has-text("Continue")');
      if (await confirm.count()) {
        await confirm.first().click().catch(() => {});
      }

      await game.waitUntilReady(page);
    });

    await step('Play every quest to the end', async () => {
      const before = await game.readHud(page);

      for (let i = 0; i < QUESTIONS.length; i++) {
        const input = page.locator('.que input[id$="_ans1"]').first();
        await expect(
          input,
          `Quest ${i + 1} shows no STACK input - the game did not reach it`
        ).toBeVisible({ timeout: 60000 });

        // Twice: STACK grades only after the student has confirmed how the input was read.
        for (const pass of [1, 2]) {
          await input.fill(QUESTIONS[i].answer);
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
        page.locator('.smg-runtime-shell'),
        'The game shell disappeared before the end of the run'
      ).toBeAttached();

      const finish = page.locator(
        '.smg-nav, .smg-rpg-next, a[href*="summary.php"], a[href*="processattempt"]'
      ).first();
      await expect(
        finish,
        'The last quest offers no way to finish the run'
      ).toBeVisible({ timeout: 60000 });
      await finish.click();
    });

    await step('Moodle records the attempt', async () => {
      await page.goto(`/mod/quiz/view.php?id=${cmid}`);
      await expect(
        page.locator('table:has-text("Grade"), .quizattemptsummary, text=/Attempt|Versuch/i').first(),
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
