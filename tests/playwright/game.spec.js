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
 * Browser journeys for the local_stackmathgame runtime.
 *
 * These cover what Behat structurally cannot: the game only exists after game_engine.js has
 * loaded a mode subplugin over AMD and replaced parts of the quiz DOM, so the assertions have
 * to run in a real browser with real network traffic.
 *
 * Run against a live site seeded by tests/playwright/seed.php.
 *
 * @module     local_stackmathgame/game.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { test, expect } = require('@playwright/test');
const { loginAs, open, startGameAttempt, collectAssetRequests } = require('./helpers');

const CMID = process.env.SMG_CMID;
const STUDENT = process.env.SMG_STUDENT_USER;
const STUDENT_PASS = process.env.SMG_STUDENT_PASS;
const TEACHER = process.env.SMG_TEACHER_USER;
const TEACHER_PASS = process.env.SMG_TEACHER_PASS;

// Skipping loudly rather than failing: an unseeded site is a missing prerequisite, not a defect
// in the plugin, and a red run for that reason trains people to ignore red runs.
test.skip(!CMID || !STUDENT, 'Nicht geseedet - tests/playwright/seed.php ausfuehren.');

test.describe('Game runtime', () => {
  test('the game shell replaces the plain quiz view for a student', async ({ page }) => {
    await loginAs(page, STUDENT, STUDENT_PASS);
    await startGameAttempt(page, CMID);

    // The engine injects this shell only after get_quiz_config has confirmed the game is
    // enabled for this activity, so its presence proves the whole bootstrap chain ran.
    await expect(page.locator('.smg-runtime-shell')).toBeAttached();
    await expect(page.locator('.smg-action-check')).toBeVisible();
  });

  test('the active design loads its own assets without a 404', async ({ page }) => {
    const assets = collectAssetRequests(page);
    await loginAs(page, STUDENT, STUDENT_PASS);
    await startGameAttempt(page, CMID);
    // The mode module renders after init resolves, so give the asset requests a moment to land.
    await page.waitForTimeout(2000);

    expect(assets.failed, `Fehlende Assets: ${assets.failed.join(', ')}`).toEqual([]);

    // Issue #4: the seeded quiz uses the RPG design, so its assets must come from the RPG
    // package - not from the generic shared directory that asset_base_url() returned for every
    // design alike. Asserting that at least one package asset was actually fetched is the point:
    // a wrong path is invisible in the DOM, the element simply renders empty.
    const packageAssets = assets.urls.filter((url) =>
      url.includes('/mode/rpg/packages/')
    );
    expect(
      packageAssets.length,
      `Kein Asset aus dem RPG-Paket angefordert. Gesehen: ${assets.urls.join(', ')}`
    ).toBeGreaterThan(0);

    // The sprites the manifest declares must be on screen, not merely downloaded.
    await expect(page.locator('.smg-rpg-stage')).toBeVisible();
  });

  test('a teacher reaches the game settings for the quiz', async ({ page }) => {
    test.skip(!TEACHER, 'Kein Lehrenden-Account geseedet.');
    await loginAs(page, TEACHER, TEACHER_PASS);
    await open(page, `/local/stackmathgame/quiz_settings.php?cmid=${CMID}`);

    await expect(page.locator('#id_submitbutton')).toBeVisible();
    // The design picker is the one control a teacher is expected to use; if it is missing the
    // page rendered but the form did not build.
    await expect(page.locator('input[name="designid"]').first()).toBeAttached();
  });
});
