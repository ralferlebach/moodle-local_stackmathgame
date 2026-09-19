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
  await page.goto('/login/index.php');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.click('#loginbtn');

  await expect(
    page.locator('#user-menu-toggle, .usermenu, #usermenu'),
    `Login as ${username} did not reach a logged-in page`
  ).toBeVisible({ timeout: 30000 });
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
  const confirm = page.locator('button:has-text("Log out"), input[value="Log out"], button:has-text("Continue")');
  if (await confirm.count()) {
    await confirm.first().click().catch(() => {});
  }
  await expect(page.locator('#username, a:has-text("Log in")').first()).toBeVisible({ timeout: 30000 });
}

module.exports = { login, logout };
