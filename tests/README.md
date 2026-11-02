# Tests – local_stackmathgame

## Overview

| Suite | Tool | Coverage |
|---|---|---|
| `unit/` | PHPUnit | Service classes, bridges, shortcodes, validators, runtime endpoints |
| `behat/` | Behat + Selenium | Navigation, page groups, Studio access, quiz settings UI |
| `jest/` | Jest (jsdom) | `game_core`, `game_engine` (check flow under failure, unsaved state), RPG counter cache |
| `playwright/` | Playwright | Smoke specs; `e2e/`: the UI-built journey and the verified play-through |

---

## PHPUnit test inventory

| File | Group | Priority |
|---|---|---|
| `profile_service_test.php` | Pure (no DB) | 1 – Critical |
| `bridge_dispatcher_test.php` | Mixed | 1 – Critical |
| `capability_test.php` | `local_stackmathgame_db` | 1 – Critical |
| `api_helper_test.php` | Pure | 2 – High |
| `narrative_resolver_test.php` | Pure | 2 – High |
| `shortcode_test.php` | Mixed | 2 – High |
| `stash_bridge_test.php` | `local_stackmathgame_db` | 2 – High |
| `stash_mapping_service_test.php` | `local_stackmathgame_db` | 2 – High |
| `quiz_configurator_test.php` | `local_stackmathgame_db` | 2 – High |
| `design_validator_test.php` | Pure | 3 – Medium |
| `design_exporter_test.php` | `local_stackmathgame_db` | 3 – Medium |
| `runtime_endpoints_security_test.php` | `local_stackmathgame_db` | 1 – Critical (fragment refusals, logged payload, CSRF, quest stages) |
| `eventlog_redaction_test.php` | `local_stackmathgame_db` | 1 – Critical (session keys in historic payloads) |
| `config_manager_test.php` | Placeholder | — |
| `definitions_test.php` | Placeholder | — |
| `page_helper_test.php` | Placeholder | — |
| `quiz_helper_test.php` | Placeholder | — |

---

## Running PHPUnit

Run from the **Moodle root**:

```bash
# All plugin tests:
vendor/bin/phpunit --testsuite local_stackmathgame

# DB-dependent tests only:
vendor/bin/phpunit --testsuite local_stackmathgame \
    --group local_stackmathgame_db

# Single file:
vendor/bin/phpunit local/stackmathgame/tests/unit/shortcode_test.php

# With block_xp + block_stash (integration CI job):
# → See .github/workflows/moodle-ci.yml phpunit-with-integrations job
```

### Test groups

| Group | Requires DB | Notes |
|---|---|---|
| *(no group)* | No | Pure logic, fast |
| `local_stackmathgame_db` | Yes | DB read/write |

---

## Running Behat

```bash
php admin/tool/behat/cli/init.php

# All plugin scenarios:
vendor/bin/behat --config behat/behat.yml \
    --tags "@local_stackmathgame and not @broken"

# Studio access only:
vendor/bin/behat --config behat/behat.yml \
    --tags "@local_stackmathgame" \
    local/stackmathgame/tests/behat/studio_access.feature

# Quiz settings:
vendor/bin/behat --config behat/behat.yml \
    local/stackmathgame/tests/behat/quiz_game_settings.feature
```

---

## Silent-fail integration tests

Bridge tests that require optional plugins use `markTestSkipped` when the
plugin is absent. Both CI jobs (`phpunit` and `phpunit-with-integrations`)
run the same test files; the `markTestSkipped` calls ensure the standard
job stays green without the optional plugins installed.

| Test | Without block_xp/stash | With block_xp/stash |
|---|---|---|
| `test_xp_bridge_silent_fail_without_block_xp` | Runs (asserts available=false) | Skipped |
| `test_xp_bridge_fires_events_when_block_xp_installed` | Skipped | Runs |
| `test_dispatch_awards_real_stash_item_when_mapped` | Skipped | Runs |

---

## Import/Export validation tests

`design_validator_test.php` uses fixture ZIP files in `tests/fixtures/`:

| Fixture | Expected result |
|---|---|
| `demo_design_valid.zip` | 0 errors |
| `demo_design_invalid.zip` | Error: missing modecomponent |
| `demo_design_corrupt.zip` | Error: not a valid ZIP |

No Moodle DB required — fixture-based tests run fast.

---

## Running Jest

```bash
cd tests/jest && npm install && npx jest
```

The AMD sources are evaluated as they ship (`amd_loader.js`), against stubs of Moodle's AJAX
layer, the change checker and the mode loader, so every request can fail, succeed or hang on its
own.

---

## The verified play-through (Playwright)

`tests/playwright/e2e/rpg-verified-play.spec.js` plays a seeded quiz through the browser and
compares every result with what the question engine stored. It runs `e2e/support/smg-e2e.php`
with the `php` on the runner against the site's code:

```bash
cd tests/playwright && npm ci
SMG_BASE_URL=http://127.0.0.1:8000 SMG_MOODLE_ROOT=/path/to/moodle \
    npx playwright test --config=playwright.e2e.config.js rpg-verified-play
```

`SMG_CHROMIUM_PATH` points Playwright at an installed Chromium when the bundled one cannot be
downloaded. The spec switches the STACK Math Editor on and off site-wide (`smg-e2e.php editor`);
the editor tests are skipped, and say so, when the editor is not installed.

