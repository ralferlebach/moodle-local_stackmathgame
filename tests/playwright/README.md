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


## The UI-only acceptance journey (`e2e/`)

`e2e/rpg-full-ui.spec.js` is a different kind of test from the rest of this folder.

The other specs seed their fixture with `seed.php` and then check one thing. This one builds
everything through the interface: the participant account, the course, three STACK questions
written in the question bank form, the quiz, its behaviour, the section heading that makes a
level, the game settings, every direction card - and then plays the RPG to its end as that
participant.

That is slower and more brittle by nature, and it is the point. A seeded test proves the runtime
works; it cannot prove a teacher could ever have reached that state. Several defects in this
plugin were exactly that - a setting that worked when written to the database and could not be
reached through any form (issue #8, and the stash section that never rendered).

### The rule

After the first login, nothing is created outside the browser. No `seed.php`, no CLI script, no
web service, no SQL, no stored storage state, no imported question file. If a real person would
click it, Playwright clicks it.

The one thing that is still automated is the infrastructure: PostgreSQL, PHP, Node, Chromium,
Moodle core, the plugin ecosystem, Maxima and an empty Moodle installation. That is the site a
person would be handed, not the game they build on it.

### Running it

```bash
# Needs a Moodle with the plugin ecosystem installed and a working STACK CAS.
cd tests/playwright
npm install
SMG_BASE_URL=http://127.0.0.1:8000 SMG_ADMIN_PASS='Admin!23' npm run e2e:rpg
```

In CI it is the **Playwright E2E RPG (UI only)** workflow, started by hand from the Actions tab.
It installs its own Moodle, so nothing needs preparing.

### Output

Video, screenshots, a trace, an HTML report and a JSON result - on success as well as on failure,
and a step-by-step summary in the GitHub Actions run summary naming the last step that succeeded.

### Adding another game mode

Everything mode-specific lives in `e2e/support/games/rpg.js`: the design name, the scene type the
run ends on, how to tell the mode has taken over the page, how to read its counters, and which
control moves on. A second mode is a second file of that shape plus one line in the spec - the
journey itself knows nothing about the RPG.
