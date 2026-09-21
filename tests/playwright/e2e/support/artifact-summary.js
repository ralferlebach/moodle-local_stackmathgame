/**
 * Collecting browser trouble, and writing the run summary GitHub shows.
 *
 * @module     local_stackmathgame/e2e/artifact-summary
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const fs = require('fs');

/**
 * Watch a page for the failures that never raise an exception.
 *
 * An uncaught JavaScript error, a 404 for a sprite or a 500 from a web service all leave the test
 * green: Playwright only fails on what it was told to assert. In this plugin those are exactly
 * the failures that matter - a missing asset is invisible in the rendered page, and a broken AMD
 * module simply means nothing happens.
 *
 * Requests that fail for reasons outside the plugin - a favicon, an analytics beacon - would
 * make the suite flaky, so only same-origin requests are counted.
 *
 * @param {import('@playwright/test').Page} page The page to watch.
 * @param {string} baseurl The site's own origin.
 * @returns {{consoleErrors: string[], pageErrors: string[], failedRequests: string[], badResponses: string[]}}
 *          The collector, filled as the run proceeds.
 */
function watchForTrouble(page, baseurl) {
  const collected = {
    consoleErrors: [],
    pageErrors: [],
    failedRequests: [],
    badResponses: [],
  };

  page.on('console', (message) => {
    if (message.type() !== 'error') {
      return;
    }
    // "Failed to load resource" is Chromium echoing a network failure into the console, without
    // the URL. The network itself is judged below by requestfailed and response, which do know
    // the URL and count only the site's own requests. Counting the echo as well let every
    // third-party asset the sandbox could not reach - fonts and a CDN behind an intercepting
    // proxy, reported as ERR_CERT_AUTHORITY_INVALID - fail a journey whose own requests were all
    // fine. What this listener is for is script errors, and those it still catches.
    if (message.text().startsWith('Failed to load resource')) {
      return;
    }
    collected.consoleErrors.push(message.text());
  });

  page.on('pageerror', (error) => {
    collected.pageErrors.push(error.message);
  });

  page.on('requestfailed', (request) => {
    if (request.url().startsWith(baseurl)) {
      collected.failedRequests.push(`${request.url()} (${request.failure()?.errorText})`);
    }
  });

  page.on('response', (response) => {
    if (!response.url().startsWith(baseurl)) {
      return;
    }
    if (response.status() >= 400) {
      collected.badResponses.push(`${response.status()} ${response.url()}`);
    }
  });

  return collected;
}

/**
 * Turn the collector into a message, or an empty string when nothing went wrong.
 *
 * @param {Object} collected The collector from watchForTrouble().
 * @returns {string} A readable report, or ''.
 */
function describeTrouble(collected) {
  const parts = [];
  const add = (label, list) => {
    if (list.length) {
      parts.push(`${label}:\n  ${list.slice(0, 10).join('\n  ')}`);
    }
  };

  add('Uncaught JavaScript errors', collected.pageErrors);
  add('Console errors', collected.consoleErrors);
  add('Failed requests', collected.failedRequests);
  add('HTTP 4xx/5xx responses', collected.badResponses);

  return parts.join('\n\n');
}

/**
 * Append a summary to the GitHub Actions step summary, when running there.
 *
 * Written from the test rather than the workflow because only the test knows how far it got. A
 * workflow-level summary can say "failed"; this one can say which step was the last to succeed,
 * which is the first thing anybody wants to know.
 *
 * @param {string[]} completed The steps that finished.
 * @param {string} [failed] The step that failed, if any.
 * @param {string} [detail] Extra detail about the failure.
 */
function writeStepSummary(completed, failed, detail) {
  const target = process.env.GITHUB_STEP_SUMMARY;
  if (!target) {
    return;
  }

  const lines = ['# StackMathGame E2E – RPG', ''];
  completed.forEach((step) => lines.push(`✅ ${step}  `));
  if (failed) {
    lines.push(`❌ ${failed}  `);
    if (detail) {
      lines.push('', '```', detail.slice(0, 2000), '```');
    }
  }
  lines.push(
    '',
    'Artifacts:',
    '- HTML report',
    '- screenshots',
    '- trace',
    '- full video',
    '- result JSON',
    ''
  );

  fs.appendFileSync(target, lines.join('\n'));
}

module.exports = { describeTrouble, watchForTrouble, writeStepSummary };
