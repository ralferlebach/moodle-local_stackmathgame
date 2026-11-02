/**
 * Uploading a file through Moodle's file picker.
 *
 * Moodle has no plain file field on its forms: a drop area opens the repository dialog, and only
 * inside that dialog is there a real <input type="file">. Setting files on the page directly waits
 * for an element that is never rendered.
 *
 * @module     local_stackmathgame/e2e/filepicker
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { expect } = require('@playwright/test');

/**
 * Put a file into the form's file picker.
 *
 * @param {import('@playwright/test').Page} page The page with the form.
 * @param {string} file Absolute path of the file to upload.
 */
async function uploadThroughFilepicker(page, file) {
  await page.getByRole('button', { name: /Choose a file|Add\.\.\./i }).first().click();

  // Addressed on the page rather than inside a scoped container: the picker is Moodle's YUI
  // dialogue and its wrapper class has changed more than once.
  await expect(
    page.getByRole('heading', { name: /File picker/i }).first(),
    'The file picker did not open'
  ).toBeVisible({ timeout: 30000 });

  const uploadRepo = page.getByRole('link', { name: /Upload a file/i }).first();
  if (await uploadRepo.count()) {
    await uploadRepo.click();
  }

  // The upload pane is built after the repository link is clicked, and an earlier, hidden pane
  // can still hold a file input of its own. Filled too early, or into that one, the upload is
  // sent empty and Moodle answers "No files attached". So: the input inside the visible upload
  // form, checked to hold the file, and the upload repeated if Moodle still saw none.
  for (let attempt = 1; attempt <= 3; attempt++) {
    const form = page.locator('.fp-upload-form:visible').first();
    await expect(form, 'The upload pane of the file picker did not open').toBeVisible({ timeout: 30000 });
    const input = form.locator('input[type="file"]').first();
    await input.setInputFiles(file);
    await expect.poll(
      () => input.evaluate((el) => (el.files ? el.files.length : 0)),
      { message: 'The file did not reach the file picker', timeout: 10000 }
    ).toBe(1);
    await page.getByRole('button', { name: /Upload this file/i }).first().click();

    const nofile = page.getByText(/No files attached/i).first();
    const done = page.locator('.filepicker-filename, .fp-filename').first();
    await expect(nofile.or(done)).toBeVisible({ timeout: 120000 });
    if (!(await nofile.isVisible().catch(() => false))) {
      break;
    }
    await page.getByRole('button', { name: /^OK$/ }).first().click().catch(() => {});
  }

  await expect(
    page.locator('.filepicker-filename, .fp-filename').first(),
    'The uploaded file does not appear on the form'
  ).toBeVisible({ timeout: 120000 });
}

module.exports = { uploadThroughFilepicker };
