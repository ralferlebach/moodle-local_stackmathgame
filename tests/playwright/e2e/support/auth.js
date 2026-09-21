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
 * Navigate, retrying a navigation the server did not answer.
 *
 * PHP's built-in web server, which the CI job runs Moodle on, occasionally leaves a worker busy
 * with the tail of a redirect chain - most reliably straight after a logout. The next navigation
 * then waits the full timeout for a worker that will never pick it up. A shorter timeout and a
 * retry turn a ninety-second hang into a two-second hiccup. A page that is genuinely broken still
 * fails, just three times instead of once.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} url The URL.
 */
async function gotoResilient(page, url) {
  let last;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      return;
    } catch (error) {
      last = error;
      await page.waitForTimeout(1000);
    }
  }
  throw last;
}

/**
 * Log in through the login form.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} username The username.
 * @param {string} password The password.
 */
async function login(page, username, password) {
  // Up to three attempts. Moodle's login form carries a one-shot token tied to the session, and
  // the form can be rejected with "Invalid login" while the credentials are perfectly correct.
  //
  // The retry has to recognise its own success. The first version did not: when an attempt had
  // in fact logged in and only the confirming assertion lost a race, the next attempt returned to
  // the login page and met "You are already logged in as ...", where there is no username field
  // at all - so the run failed with a sixty-second timeout on #username, which reads like a
  // broken login page rather than a session that was established all along.
  let notice = '';
  let landed = '';

  for (let attempt = 1; attempt <= 3; attempt++) {
    await gotoResilient(page, '/login/index.php');

    if (await isLoggedIn(page)) {
      return;
    }

    await page.fill('#username', username);
    await page.fill('#password', password);
    await page.click('#loginbtn');
    await page.waitForLoadState('domcontentloaded').catch(() => {});

    if (await isLoggedIn(page)) {
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
 * Report whether this browser already holds a Moodle session.
 *
 * Two signals, because Moodle offers two: the user menu on any ordinary page, and the "you are
 * already logged in" dialog that the login page itself shows to an authenticated visitor. The
 * second is the one a retry runs into, and taking it as a failure is what made the retry worse
 * than no retry.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Promise<boolean>} True when a session exists.
 */
async function isLoggedIn(page) {
  const menu = page.locator('#user-menu-toggle, .usermenu, #usermenu').first();
  if (await menu.isVisible().catch(() => false)) {
    return true;
  }

  const already = page.locator('text=/already logged in/i').first();
  return already.isVisible().catch(() => false);
}

/**
 * Navigations here wait for the document, not for every subresource.
 *
 * Playwright's default waits for "load", which includes every image, font and script. Straight
 * after a logout, one of those can be held by a PHP worker that is still finishing the redirect
 * chain, and the next goto then waits ninety seconds for a sprite rather than for the page.
 * Whether the page is usable is checked explicitly afterwards anyway.
 */

/**
 * Log out through the user menu.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function logout(page) {
  await gotoResilient(page, '/my/');
  const key = await page.evaluate(() => (window.M && window.M.cfg && window.M.cfg.sesskey) || '');

  // logout.php answers with a redirect chain, and Chromium reports the first hop as ERR_ABORTED
  // when the second one supersedes it. The logout itself has happened by then, so the navigation
  // error is expected and caught; what is checked is the outcome, not the transport.
  await page.goto(`/login/logout.php?sesskey=${key}`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.waitForLoadState('domcontentloaded').catch(() => {});

  // Moodle asks for confirmation when the key is stale.
  const confirm = page
    .locator('button:has-text("Log out"), input[value="Log out"], button:has-text("Continue")')
    .first();
  if (await confirm.count() && await confirm.isVisible()) {
    await confirm.click().catch(() => {});
    await page.waitForLoadState('domcontentloaded').catch(() => {});
  }

  await gotoResilient(page, '/login/index.php');
  await expect(
    page.locator('#username'),
    'Still logged in after logging out - the next login would act as the wrong user'
  ).toBeVisible({ timeout: 30000 });
}

module.exports = { login, logout };
