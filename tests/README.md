# Tests – local_stackmatheditor

## Overview

| Suite | Tool | What it covers |
|---|---|---|
| `unit/` | PHPUnit | Pure logic: definitions, config_manager priority chain, enabled modes |
| `behat/` | Behat + Selenium | Browser: toolbar rendering, pre-fill, configure page, nav selector |

---

## Running PHPUnit tests

Run from the **Moodle root** (not from within the plugin):

```bash
# All plugin tests (no DB needed for unit/ suite):
vendor/bin/phpunit --testsuite local_stackmatheditor

# DB-dependent integration tests only:
vendor/bin/phpunit --testsuite local_stackmatheditor \
    --group local_stackmatheditor_db

# Single test class:
vendor/bin/phpunit local/stackmatheditor/tests/unit/definitions_test.php
```

The `--group local_stackmatheditor_db` tests require:

```bash
# Initialise the Moodle test DB first (once per environment):
php admin/tool/phpunit/cli/init.php
```

### Test groups

| Group | Requires DB | Notes |
|---|---|---|
| *(default / no group)* | No | Pure logic, fast, no fixtures |
| `local_stackmatheditor_db` | Yes | DB read/write via Moodle's test fixtures |

---

## Running Behat tests

```bash
# Initialise Behat (once per environment):
php admin/tool/behat/cli/init.php

# Run all plugin scenarios:
vendor/bin/behat --config behat/behat.yml \
    --tags @local_stackmatheditor

# Run only rendering scenarios:
vendor/bin/behat --config behat/behat.yml \
    --tags "@local_stackmatheditor and @javascript" \
    local/stackmatheditor/tests/behat/editor_rendering.feature

# Run toolbar configuration scenarios:
vendor/bin/behat --config behat/behat.yml \
    --tags "@local_stackmatheditor" \
    local/stackmatheditor/tests/behat/configure_toolbar.feature
```

Behat requires:
- A running Selenium/WebDriver instance (Chrome recommended).
- `qtype_stack` installed and at least one STACK question in the test DB.
- `$CFG->behat_prefix` configured in `config.php`.

---

## CI / GitHub Actions

A suggested workflow (`.github/workflows/ci.yml` in the Moodle root or as a
standalone `moodle-plugin-ci` run):

```yaml
- name: Run PHPUnit unit tests (no DB)
  run: vendor/bin/phpunit --testsuite local_stackmatheditor

- name: Run PHPUnit DB tests
  run: vendor/bin/phpunit --testsuite local_stackmatheditor \
       --group local_stackmatheditor_db

- name: Run Behat tests
  run: vendor/bin/behat --tags @local_stackmatheditor \
       --config behat/behat.yml
```

For `moodle-plugin-ci`, add to `.moodle-plugin-ci.yml`:

```yaml
phpunit:
  extra: "--testsuite local_stackmatheditor"
behat:
  extra: "--tags @local_stackmatheditor"
```
