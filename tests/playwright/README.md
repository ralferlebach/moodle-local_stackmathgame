# local_stackmathgame — Playwright browser tests

These cover what Behat structurally cannot. The game only exists after `game_engine.js` has
loaded a mode subplugin over AMD and rewritten parts of the quiz DOM, so the assertions need a
real browser, real AMD loading and real network traffic — in particular for the asset
resolution of issue #4, where a wrong path is invisible in the DOM and only shows up as a 404.

## Run

```bash
cd tests/playwright
npm install && npm run install-browsers   # first run only
eval "$(php seed.php)"                    # exports SMG_BASE_URL, SMG_CMID, accounts
npm test                                  # or: npm run test:a11y
```

From the plugin root `make playwright` does all of the above in one step.

## What the seed creates

A course with a quiz that uses `preferredbehaviour = stackmathgame`, one question per page and
the RPG design enabled, plus a teacher, a student and a manager account. The RPG design is
chosen deliberately: it has the richest asset manifest, so a broken asset path fails loudly
instead of merely leaving a decoration blank.

Questions come from `tests/fixtures/stack_playwright.xml` when that file exists, so the
journeys run against real STACK questions. Without it the seed falls back to short-answer
questions and prints `SMG_HAS_STACK=0`; the navigation, settings, asset and accessibility
journeys still run, only the STACK-specific feedback assertions skip themselves.

## Specs

* `game.spec.js` — the game shell replaces the plain quiz view, the active design loads its own
  assets without a 404, and a teacher reaches the game settings.
* `accessibility.spec.js` — axe against **this plugin's** regions only. Scanning a whole Moodle
  page also reports core's violations, which no work here can fix and which turn the check into
  noise everyone learns to ignore.

## Environment

`SMG_BASE_URL` overrides the site. All other variables come from `seed.php`. Every spec skips
itself when its prerequisites are absent: an unseeded site is a missing prerequisite, not a
defect, and a red run for that reason trains people to ignore red runs.
