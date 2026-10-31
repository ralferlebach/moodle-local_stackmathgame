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

  await page.locator('input[type="file"]').first().setInputFiles(file);
  await page.getByRole('button', { name: /Upload this file/i }).first().click();

  await expect(
    page.locator('.filepicker-filename, .fp-filename').first(),
    'The uploaded file does not appear on the form'
  ).toBeVisible({ timeout: 120000 });
}

module.exports = { uploadThroughFilepicker };
