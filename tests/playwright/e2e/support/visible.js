/**
 * Picking the element a person would actually click.
 *
 * Moodle renders the same control more than once on several pages - a button above and below a
 * table, a duplicate kept for narrow screens - and only one of them is visible at a time. A
 * locator's .first() takes whichever comes first in the DOM, which may be the hidden one, and
 * then the click waits for something that will never become visible: the report says "timeout",
 * the screenshot shows the button plainly on screen, and the two seem to contradict each other.
 *
 * @module     local_stackmathgame/e2e/visible
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

/**
 * Click the first visible match, waiting for one to appear.
 *
 * @param {import('@playwright/test').Locator} locator Candidates, possibly several.
 * @param {string} description What the caller was trying to click, for the failure message.
 * @param {number} [timeout] How long to wait for a visible candidate.
 */
async function clickVisible(locator, description, timeout = 30000) {
  const deadline = Date.now() + timeout;

  do {
    const count = await locator.count();
    for (let i = 0; i < count; i++) {
      const candidate = locator.nth(i);
      if (await candidate.isVisible()) {
        await candidate.click();
        return;
      }
    }
    await locator.page().waitForTimeout(250);
  } while (Date.now() < deadline);

  const total = await locator.count();
  expect(
    false,
    `${description}: ${total} element(s) matched but none was visible`
  ).toBe(true);
}

module.exports = { clickVisible };
