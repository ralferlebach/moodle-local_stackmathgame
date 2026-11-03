/**
 * System functions: signing in, switching user and opening a course.
 *
 * These are not under test. Moodle's login form and its course listing are core, they are the
 * same for every plugin, and driving them through the browser only added ways for a journey to
 * fail before it reached the game. So they are done the way a system would do them: the login is
 * posted over HTTP into the browser context's own cookie jar, and the course is opened by its
 * address. Everything after that point - building the game and playing it - happens through the
 * interface only (see ui.js).
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

const BASE_URL = (process.env.SMG_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');

/**
 * Sign in as a user, replacing whoever was signed in before.
 *
 * The browser context's request client shares its cookies with the pages, so the session it
 * creates is the one every page of this context uses from then on.
 *
 * @param {import('@playwright/test').Page} page The page whose context signs in.
 * @param {string} username The user name.
 * @param {string} password The password.
 * @returns {Promise<void>}
 */
async function signIn(page, username, password) {
  const context = page.context();
  await context.clearCookies();
  const request = context.request;

  let landed = '';
  for (let attempt = 1; attempt <= 3; attempt++) {
    const form = await request.get(`${BASE_URL}/login/index.php`);
    const html = await form.text();
    const token = (html.match(/name="logintoken"\s+value="([^"]*)"/) || [])[1] || '';
    await request.post(`${BASE_URL}/login/index.php`, {
      form: { username, password, logintoken: token, anchor: '' },
    });
    // Signed in when a page that needs a login is served instead of redirecting to the form.
    const check = await request.get(`${BASE_URL}/user/profile.php`);
    landed = check.url();
    if (check.ok() && !/\/login\/index\.php/.test(landed)) {
      return;
    }
  }
  expect(false, `Signing in as ${username} failed three times (ended on ${landed}).`).toBe(true);
}

/**
 * Open a course's main page.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} courseid The course id.
 * @returns {Promise<void>}
 */
async function openCourse(page, courseid) {
  let last;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      await page.goto(`/course/view.php?id=${courseid}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await expect(page.locator('#page-header, .page-header-headings').first()).toBeVisible({ timeout: 30000 });
      return;
    } catch (error) {
      last = error;
      await page.waitForTimeout(1000);
    }
  }
  throw last;
}

module.exports = { BASE_URL, openCourse, signIn };
