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

`e2e/game-full-ui.spec.js` is a different kind of test from the rest of this folder.

The other specs seed their fixture with `seed.php` and then check one thing. This one builds each
of the three games - RPG, Exit Game and Digital Mentor - through the interface: it imports the
STACK fixture questions through the question bank, adds the quiz from the course page, fills it
from the bank, switches the game on in its settings, configures every direction card - and then
plays the game to its end as a participant and finishes the attempt.

That is slower and more brittle by nature, and it is the point. A seeded test proves the runtime
works; it cannot prove a teacher could ever have reached that state. Several defects in this
plugin were exactly that - a setting that worked when written to the database and could not be
reached through any form (issue #8, the stash section that never rendered, and the game settings
missing from the quiz's "More" menu, issue #13).

### The rule

Everything a teacher or participant does is done through the interface: clicked, typed, chosen -
from the page the journey is on. No address is ever typed into the browser to reach a page
(`e2e/support/ui.js`), and answers are typed into the visible field.

Two things are deliberately **not** done through the interface, because they are core Moodle and
not under test (`e2e/support/system.js`):

* **Signing in and switching user** - the login is posted over HTTP into the browser's cookie jar.
* **Opening the course** - by its address.

Before the journey, `.github/e2e-seed.php` creates the participant, one course per game and the
enrolments, and switches Moodle's guided tours off. Afterwards the question engine's record is
read back through `e2e/support/smg-e2e.php` and compared with what the participant saw - read
only, it changes nothing.

### Running it

```bash
# Needs a Moodle with the plugin ecosystem installed and a working STACK CAS.
cd tests/playwright
npm install
eval "$(cd /path/to/moodle && php local/stackmathgame/.github/e2e-seed.php)"
SMG_BASE_URL=http://127.0.0.1:8000 SMG_ADMIN_PASS='Admin!23' SMG_MOODLE_ROOT=/path/to/moodle \
  SMG_GAMES=rpg,exitgames,wisewizzard npm run e2e:rpg
```

`SMG_GAMES` picks the games; the default is all three.

In CI it is the **Playwright E2E games (UI only)** workflow, started by hand from the Actions tab.
It installs its own Moodle, so nothing needs preparing.

### Output

Two artefacts, on success as well as on failure, plus a step-by-step summary in the GitHub Actions
run summary naming the last step that succeeded.

* **`e2e-rpg-recording`** - the video, the screenshots and `results.json`. This is the one to
  download normally.
* **`e2e-rpg-trace`** - the Playwright trace and the full HTML report, for investigating a
  failure.

They are separate because of the second one. A Playwright HTML report bundles the trace viewer -
a 600 kB minified JavaScript application - and the trace itself is a zip inside the artefact zip.
Heuristic virus scanners flag that combination: obfuscated-looking script inside a nested archive
is also what real malware looks like, so the shape alone is enough to trigger a warning without
anything being wrong.

Nothing in either artefact is executed by downloading it, and the trace's contents are just the
pages the browser visited: in a typical run, 155 screenshots plus Moodle's own CSS and JavaScript,
captured from the site the test drove. If your scanner objects, it will be to `e2e-rpg-trace`, and
you can ignore that artefact unless you are diagnosing a failure.

If you want to check rather than take this on trust: look at which file the scanner actually names
(it will be `trace/assets/*.js` from the report, or the nested `trace.zip`), and run that single
file through VirusTotal. A single heuristic engine objecting to a minified bundle is a false
positive; several engines naming a specific family is not, and then it is worth telling me.

### The game profiles

Everything mode-specific lives in `e2e/support/games/<mode>.js` - `rpg.js`, `exitgames.js`,
`wisewizzard.js`: the design name, the scene type the run ends on, how to tell the mode has taken
over the page, how to read its counters, and which control moves on. The journey itself knows
nothing about any one game; a further mode is a further file of that shape plus one entry in the
spec's `GAMES` list and a course in the seed.
