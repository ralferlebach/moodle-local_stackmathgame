# Tests – local_stackmathgame

## Overview

| Suite | Tool | Coverage |
|---|---|---|
| `unit/` | PHPUnit | Service classes, bridges, shortcodes, validators |
| `behat/` | Behat + Selenium | Navigation, Studio access, quiz settings UI |

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
