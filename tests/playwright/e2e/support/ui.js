/**
 * Ways through Moodle's interface: the menus, buttons and links a person uses.
 *
 * Every helper here starts from the page the journey is on and reaches the next one by clicking,
 * typing or choosing - never by typing an address. Addresses are what a person cannot see, and
 * several defects in this plugin were pages that worked when opened by URL and could not be
 * reached from any menu (issue #13 was one). Signing in and opening the course are the only
 * exceptions; they live in system.js and are not under test.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

/**
 * A pattern matching exactly the given text, ignoring surrounding whitespace.
 *
 * @param {string} text The text.
 * @returns {RegExp} The pattern.
 */
function exactly(text) {
  return new RegExp(`^\\s*${text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*$`);
}

/**
 * Wait until the page has settled: loaded, and Moodle's own scripts no longer busy.
 *
 * Moodle attaches most controls from AMD modules after the page has loaded. A click that lands
 * before that is swallowed - the add-activity button and the form's "Expand all" both did so.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Promise<void>}
 */
async function settle(page) {
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
  await page.waitForFunction(
    () => !window.M || !M.util || !M.util.pending_js || M.util.pending_js.length === 0,
    null,
    { timeout: 30000 }
  ).catch(() => {});
}

/**
 * Close a guided tour if one has opened over the page.
 *
 * Tours are switched off by the backend seed; this covers a tour added later or by another
 * plugin, which would otherwise take the next click.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Promise<void>}
 */
async function dismissTour(page) {
  const skip = page.getByRole('button', { name: /^(Skip tour|End tour)$/ }).first();
  if (await skip.isVisible().catch(() => false)) {
    await skip.click();
  }
}

/**
 * Open an entry of the page's secondary navigation - the row of tabs under the heading - looking
 * in the "More" menu when the entry is not shown directly.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} label The entry's text.
 * @returns {Promise<void>}
 */
async function secondaryNav(page, label) {
  await settle(page);
  await dismissTour(page);
  const nav = page.locator('.secondary-navigation').first();
  await expect(nav, 'The page has no secondary navigation').toBeVisible({ timeout: 30000 });
  const direct = nav.locator('a.nav-link').filter({ hasText: exactly(label) });
  for (let i = 0; i < await direct.count(); i++) {
    if (await direct.nth(i).isVisible()) {
      await direct.nth(i).click();
      await settle(page);
      return;
    }
  }
  await nav.locator('a.dropdown-toggle').filter({ hasText: exactly('More') }).first().click();
  const entry = nav.locator('.dropdown-menu a').filter({ hasText: exactly(label) }).first();
  await expect(entry, `"${label}" is neither in the navigation nor under "More"`).toBeVisible({ timeout: 15000 });
  await entry.click();
  await settle(page);
}

/**
 * Choose an entry of a page's tertiary navigation - the selector above the content.
 *
 * Moodle up to 5.0 renders it as a select that navigates when the choice changes; from 5.1 it is
 * a dropdown of links. Both are operated the way a person operates them.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} label The entry's text.
 * @returns {Promise<void>}
 */
async function tertiaryNav(page, label) {
  await settle(page);
  const select = page.locator('.tertiary-navigation select').first();
  if (await select.isVisible().catch(() => false)) {
    // Moodle's select navigates on its "accessible change": after a mouse choice at once, after a
    // keyboard choice only once it is confirmed with Enter. Choosing without a mouse, as here,
    // therefore ends with Enter, exactly as a keyboard user confirms.
    const before = page.url();
    await select.focus();
    await select.selectOption({ label });
    await Promise.all([
      page.waitForURL((url) => url.toString() !== before, { waitUntil: 'domcontentloaded', timeout: 30000 }),
      select.press('Enter'),
    ]);
    await settle(page);
    return;
  }
  const toggle = page.locator('.tertiary-navigation .dropdown-toggle').first();
  await toggle.click();
  await page.locator('.tertiary-navigation .dropdown-menu a, .tertiary-navigation [role="option"]')
    .filter({ hasText: exactly(label) }).first().click();
  await settle(page);
}

/**
 * Switch the course's edit mode on or off with the switch in the top bar.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {boolean} on The wanted state.
 * @returns {Promise<void>}
 */
async function setEditMode(page, on) {
  await settle(page);
  const editing = await page.evaluate(() => document.body.classList.contains('editing'));
  if (editing === on) {
    return;
  }
  const toggle = page.locator('.editmode-switch-form input[name="setmode"]').first();
  await expect(toggle, 'The page has no edit mode switch').toBeVisible({ timeout: 30000 });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
    toggle.click(),
  ]);
  await settle(page);
  await dismissTour(page);
}

/**
 * Open an activity from the course page by its name.
 *
 * @param {import('@playwright/test').Page} page The page, on the course.
 * @param {string} name The activity's name.
 * @returns {Promise<void>}
 */
async function openActivity(page, name) {
  await settle(page);
  await dismissTour(page);
  const link = page.locator('.activityname a, a.aalink').filter({ hasText: name }).first();
  await expect(link, `The course page does not list "${name}"`).toBeVisible({ timeout: 30000 });
  await link.click();
  await settle(page);
}

/**
 * Open the Game Design Studio from the icon in the top bar.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Promise<void>}
 */
async function openStudio(page) {
  await settle(page);
  const link = page.getByRole('link', { name: /Game Design Studio/i }).first();
  await expect(link, 'The top bar has no Game Design Studio entry').toBeVisible({ timeout: 30000 });
  await link.click();
  await settle(page);
}

/**
 * The course-module id of the activity the page belongs to, read from the page itself.
 *
 * Read, not navigated to: the journeys keep it only to ask the backend afterwards what was stored.
 *
 * @param {import('@playwright/test').Page} page The page, on an activity.
 * @returns {Promise<number>} The course-module id.
 */
async function currentCmid(page) {
  const fromBody = await page.evaluate(() => {
    const match = (document.body.className || '').match(/\bcmid-(\d+)\b/);
    return match ? Number(match[1]) : 0;
  });
  if (fromBody) {
    return fromBody;
  }
  const url = new URL(page.url());
  return Number(url.searchParams.get('cmid') || url.searchParams.get('id') || 0);
}

module.exports = {
  currentCmid,
  dismissTour,
  exactly,
  openActivity,
  openStudio,
  secondaryNav,
  setEditMode,
  settle,
  tertiaryNav,
};
