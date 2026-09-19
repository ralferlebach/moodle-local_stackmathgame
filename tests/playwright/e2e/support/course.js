/**
 * Creating courses and sections through the interface.
 *
 * @module     local_stackmathgame/e2e/course
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

/**
 * Create a course through the course form and return its id.
 *
 * The id is read out of the URL Moodle lands on rather than assumed. Nothing in this suite may
 * depend on a database id being 2 because the site is fresh - the next change to Moodle's
 * installer would break every such assumption at once.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string} fullname The course name.
 * @param {string} shortname The short name, unique site-wide.
 * @returns {Promise<number>} The course id.
 */
async function createCourse(page, fullname, shortname) {
  await page.goto('/course/edit.php?category=1');
  await page.fill('#id_fullname', fullname);
  await page.fill('#id_shortname', shortname);
  await page.click('#id_saveanddisplay');

  await expect(page).toHaveURL(/course\/view\.php|user\/index\.php|enrol/, { timeout: 60000 });
  const url = new URL(page.url());
  const id = Number(url.searchParams.get('id') || url.searchParams.get('courseid') || 0);
  expect(id, 'The new course has no id in its URL').toBeGreaterThan(0);
  return id;
}

module.exports = { createCourse };
