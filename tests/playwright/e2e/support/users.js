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
const { clickVisible } = require('./visible');

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

  // Moodle wraps the password in its "passwordunmask" widget: the real input carries d-none and
  // stays hidden until the "Click to enter text" link beside it is used. The input is in the DOM
  // the whole time, which is why a fill() reports a timeout on an element it can plainly see -
  // and why unticking "Generate password and notify user", the obvious suspect, changed nothing.
  const generate = page.locator('#id_createpassword');
  if (await generate.count() && await generate.isChecked()) {
    await generate.uncheck();
  }

  const password = page.locator('#id_newpassword');
  if (!(await password.isVisible())) {
    const reveal = page
      .locator('a:has-text("Click to enter text"), [data-passwordunmask="edit"]')
      .first();
    await expect(
      reveal,
      'Neither the password field nor its "Click to enter text" link is available'
    ).toBeVisible({ timeout: 30000 });
    await reveal.click();
  }

  await expect(
    password,
    'The password field stayed hidden even after opening the passwordunmask widget'
  ).toBeVisible({ timeout: 30000 });
  await password.fill(user.password);
  await page.fill('#id_firstname', user.firstname);
  await page.fill('#id_lastname', user.lastname);
  await page.fill('#id_email', user.email);

  await page.click('#id_submitbutton');

  // Where Moodle lands after saving a user differs by version and by how the page was reached,
  // so the account is confirmed where it can always be found: the user list, filtered by the
  // username that was just used. Asserting on the landing page instead made the check depend on
  // a redirect that has nothing to do with whether the account exists.
  await expect(
    page.locator('#id_username'),
    'The user form did not save - it is still on screen, so something was rejected'
  ).toBeHidden({ timeout: 30000 });

  await page.goto(`/admin/user.php?search=${encodeURIComponent(user.username)}`);
  await expect(
    page.locator(`text=${user.firstname} ${user.lastname}`).first(),
    `The account ${user.username} is not in the user list`
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
async function enrol(page, courseid, fullname, role, identity) {
  // Searched and confirmed by a unique identity, not by the display name. Every run creates a
  // "Pat Player", so on any site that has seen a previous run the name matches several accounts:
  // the picker offered the oldest one, the dialog enrolled it, and the confirmation - which also
  // looked for "Pat Player" - found that old account in the list and passed. The participant who
  // then logged in was not enrolled at all, and the failure surfaced two steps later as "the
  // quiz offers no way to start an attempt".
  const needle = identity || fullname;
  await page.goto(`/user/index.php?id=${courseid}`);
  // By accessible name rather than by text content. Moodle wraps the label in spans and adds
  // icons, so :has-text() misses a control that the page plainly shows - the failure then reads
  // "0 elements matched" while the screenshot shows the button. getByRole matches what a screen
  // reader announces, which is what the error context reports too.
  await clickVisible(
    page.getByRole('button', { name: 'Enrol users' }),
    'Opening the enrolment dialog'
  );

  const dialog = page.locator('.modal-dialog').last();
  await expect(dialog, 'The enrolment dialog did not open').toBeVisible({ timeout: 30000 });

  // Moodle's autocomplete, by its own markup rather than by "the first text input": the dialog
  // also contains the role selector and the enrolment options, and which of them counts as first
  // depends on the version.
  const search = dialog.locator('input.form-autocomplete-input, input[role="combobox"]').first();
  await expect(search, 'The participant search box is not in the dialog').toBeVisible({ timeout: 30000 });
  await search.click();
  await search.fill(needle);

  // The suggestion list is rendered outside the input, and typing alone does not select anybody -
  // a dialog submitted without a selection enrols nobody and reports success.
  const suggestion = page
    .locator('.form-autocomplete-suggestions [role="option"], [role="listbox"] [role="option"]')
    .filter({ hasText: needle })
    .first();
  await expect(
    suggestion,
    `The participant search found nobody matching ${needle}`
  ).toBeVisible({ timeout: 30000 });
  await suggestion.click();

  const roleSelect = dialog.locator('select').first();
  if (await roleSelect.count()) {
    await roleSelect.selectOption({ label: role }).catch(() => {});
  }

  await dialog.locator('button:has-text("Enrol")').last().click();

  await page.goto(`/user/index.php?id=${courseid}&perpage=5000`, { waitUntil: 'domcontentloaded' });
  await expect(
    page.locator('table').filter({ hasText: needle }).first(),
    `${needle} was not enrolled - the dialog reported success but the participant list lacks them`
  ).toBeVisible({ timeout: 30000 });
}

module.exports = { createUser, enrol };
