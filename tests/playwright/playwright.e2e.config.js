/**
 * Playwright configuration for the UI-only acceptance journey.
 *
 * Separate from playwright.config.js because the two suites want opposite things. The seeded
 * specs are short, independent and parallel; this one is a single long journey where each step
 * depends on the last.
 *
 * No retries, deliberately. A journey that only works the second time is not working, and a retry
 * would restart against a half-built course and fail for a different reason than the first
 * attempt - which makes the report harder to read, not easier.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e',
  testMatch: '**/*.spec.js',
  timeout: 20 * 60 * 1000,
  expect: { timeout: 30000 },
  retries: 0,
  workers: 1,
  use: {
    baseURL: process.env.SMG_BASE_URL || 'http://127.0.0.1:8000',
    headless: true,
    // Recorded on success as well as on failure. A green run is the deliverable here: a watchable
    // record of the finished game, usable for documentation and for showing the plugin to people
    // who will never read a test report.
    video: process.env.SMG_NO_RECORD ? 'off' : 'on',
    screenshot: 'on',
    trace: process.env.SMG_NO_RECORD ? 'off' : 'on',
    actionTimeout: 60000,
    navigationTimeout: 90000,
  },
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'e2e-report' }],
    ['json', { outputFile: 'e2e-report/results.json' }],
  ],
  outputDir: 'e2e-artifacts',
});
