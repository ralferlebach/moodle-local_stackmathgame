/**
 * Logging in and out through the real login form.
 *
 * Deliberately not a stored storageState: the issue's rule is that everything from the first
 * login onwards happens through the interface, and a restored session is precisely a way of
 * skipping that. It would also hide a broken login, which is the first thing a user meets.
 *
 * @module     local_stackmathgame/e2e/auth
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

/**
 * Log in through the login form.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} username The username.
 * @param {string} password The password.
 */
async function login(page, username, password) {
  // Up to three attempts. Moodle's login form carries a one-shot token tied to the session, and
  // the session cookie is written by the request that renders the form - so a form fetched from
  // one PHP worker and submitted to another can be rejected with "Invalid login" while the
  // credentials are perfectly correct. It is a property of the throwaway web server the test
  // runs against, not of Moodle and not of this plugin, and retrying a fresh form is the honest
  // response: it neither hides a wrong password (which fails all three times) nor lets an
  // infrastructure race fail the whole journey.
  let notice = '';
  let landed = '';

  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.goto('/login/index.php');
    await page.fill('#username', username);
    await page.fill('#password', password);
    await page.click('#loginbtn');
    await page.waitForLoadState('domcontentloaded').catch(() => {});

    const menu = page.locator('#user-menu-toggle, .usermenu, #usermenu').first();
    if (await menu.isVisible().catch(() => false)) {
      return;
    }

    landed = page.url();
    notice = (await page.locator('.loginerrors, .alert-danger').allTextContents())
      .join(' ').trim();
  }

  // The landing URL and the page's own message are in the failure, because "element not found"
  // on its own cannot distinguish wrong credentials from a changed theme from a redirect to a
  // site policy page.
  expect(
    false,
    `Login as ${username} failed three times. Landed on ${landed}.`
      + (notice ? ` The page says: ${notice}` : '')
  ).toBe(true);
}

/**
 * Log out through the user menu.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function logout(page) {
  await page.goto('/my/');
  const key = await page.evaluate(() => (window.M && window.M.cfg && window.M.cfg.sesskey) || '');
  await page.goto(`/login/logout.php?sesskey=${key}`);

  // Moodle asks for confirmation when the key is stale.
  const confirm = page
    .locator('button:has-text("Log out"), input[value="Log out"], button:has-text("Continue")')
    .first();
  if (await confirm.count()) {
    await confirm.click().catch(() => {});
  }
  await expect(page.locator('#username, a:has-text("Log in")').first()).toBeVisible({ timeout: 30000 });
}

module.exports = { login, logout };
