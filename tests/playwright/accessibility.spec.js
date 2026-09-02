// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Accessibility checks for the pages local_stackmathgame contributes to Moodle.
 *
 * Scoped to this plugin's own markup: scanning a whole Moodle page reports core's violations
 * too, which no amount of work on this plugin can fix and which quickly turn the check into
 * noise that everyone learns to ignore.
 *
 * @module     local_stackmathgame/accessibility.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { loginAs, open, startGameAttempt } = require('./helpers');

const CMID = process.env.SMG_CMID;
const MANAGER = process.env.SMG_MANAGER_USER;
const MANAGER_PASS = process.env.SMG_MANAGER_PASS;
const STUDENT = process.env.SMG_STUDENT_USER;
const STUDENT_PASS = process.env.SMG_STUDENT_PASS;

test.skip(!CMID || !MANAGER, 'Nicht geseedet - tests/playwright/seed.php ausfuehren.');

/**
 * Run axe against one region of the page and assert it is clean.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @param {string} selector The region to analyse.
 * @returns {Promise<void>}
 */
async function expectAccessible(page, selector) {
  const results = await new AxeBuilder({ page })
    .include(selector)
    .withTags(['wcag2a', 'wcag2aa'])
    .analyze();
  const summary = results.violations
    .map((v) => `${v.id} (${v.nodes.length}x): ${v.help}`)
    .join('\n');
  expect(results.violations, `Barrierefreiheits-Verstoesse in ${selector}:\n${summary}`).toEqual([]);
}

test.describe('Accessibility', () => {
  test('the game settings form is accessible', async ({ page }) => {
    await loginAs(page, MANAGER, MANAGER_PASS);
    await open(page, `/local/stackmathgame/quiz_settings.php?cmid=${CMID}`);
    await expectAccessible(page, '[role="main"]');
  });

  test('the Game Design Studio is accessible', async ({ page }) => {
    await loginAs(page, MANAGER, MANAGER_PASS);
    await open(page, '/local/stackmathgame/studio.php');
    await expectAccessible(page, '[role="main"]');
  });

  test('the injected game shell is accessible', async ({ page }) => {
    test.skip(!STUDENT, 'Kein Lernenden-Account geseedet.');
    await loginAs(page, STUDENT, STUDENT_PASS);
    await startGameAttempt(page, CMID);
    // Only the plugin's own shell: the surrounding attempt page belongs to mod_quiz.
    await expectAccessible(page, '.smg-runtime-shell');
  });
});
