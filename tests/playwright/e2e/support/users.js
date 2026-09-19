/**
 * Creating and enrolling participants through the interface.
 *
 * The account is created in Site administration, exactly as an administrator would, rather than
 * by a CLI script. That was the first version of this suite and it broke the rule the whole test
 * exists to enforce: if the participant is conjured outside the interface, the test no longer
 * shows that a real person could have been given access.
 *
 * @module     local_stackmathgame/e2e/users
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

/**
 * Create a user account through Site administration.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {Object} user The account to create.
 * @param {string} user.username The username.
 * @param {string} user.password The password.
 * @param {string} user.firstname The first name.
 * @param {string} user.lastname The surname.
 * @param {string} user.email The email address.
 */
async function createUser(page, user) {
  await page.goto('/user/editadvanced.php?id=-1');

  await page.fill('#id_username', user.username);
  // The password field is behind "Choose an authentication method"; on a fresh site manual
  // authentication is the default and the field is present.
  await page.fill('#id_newpassword', user.password);
  await page.fill('#id_firstname', user.firstname);
  await page.fill('#id_lastname', user.lastname);
  await page.fill('#id_email', user.email);

  await page.click('#id_submitbutton');

  await expect(
    page.locator(`text=${user.firstname} ${user.lastname}`).first(),
    'The new account does not appear in the user list'
  ).toBeVisible({ timeout: 30000 });
}

/**
 * Enrol an existing user into a course through the participants page.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {number} courseid The course id.
 * @param {string} fullname The display name, as the picker shows it.
 * @param {string} role The role to give, e.g. "Student".
 */
async function enrol(page, courseid, fullname, role) {
  await page.goto(`/user/index.php?id=${courseid}`);
  await page.locator('button:has-text("Enrol users"), a:has-text("Enrol users")').first().click();

  const dialog = page.locator('.modal-dialog').last();
  const search = dialog.locator('input[type="text"], input[role="combobox"]').first();
  await search.fill(fullname);
  await dialog.locator(`[role="option"]:has-text("${fullname}"), li:has-text("${fullname}")`)
    .first().click();

  const roleSelect = dialog.locator('select').first();
  if (await roleSelect.count()) {
    await roleSelect.selectOption({ label: role }).catch(() => {});
  }
  await dialog.locator('button:has-text("Enrol")').last().click();

  await expect(
    page.locator(`table td:has-text("${fullname}")`).first(),
    `${fullname} was not enrolled`
  ).toBeVisible({ timeout: 30000 });
}

module.exports = { createUser, enrol };
