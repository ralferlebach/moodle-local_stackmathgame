# Upgrade notes

## Audience

These notes are for developers and operators upgrading an existing installation to the activity-based contract.

## Main changes

The plugin now uses activity identity as the canonical source of truth:

- `cmid`
- `modname`
- `instanceid`

Legacy `quizid` support remains available for compatibility, but new code should target the activity contract.

## Required upgrade procedure

Run the Moodle upgrade after deploying the updated plugin files:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

If AMD files changed in the deployment, rebuild them as usual.

## Data migrations included

The upgrade path may include additive schema and backfill steps for:

- question map rows
- stash map rows
- event log rows
- activity web-service registration

The migration intentionally prefers additive changes before any destructive cleanup.

## Recommended post-upgrade checks

### 1. Web-service registration

Verify that both activity and legacy wrapper services are registered in `mdl_external_functions`.

### 2. Question map integrity

Check that `local_stackmathgame_questionmap` rows exist for migrated activities and that `questionid` values are not zero.

### 3. Stash mapping backfill

If stash mappings existed before the migration, run the backfill/reporting tooling and confirm that `cmid` has been populated.

### 4. Runtime verification

Check at least one quiz attempt page and confirm that:

- the runtime shell appears
- config/profile/prefetch endpoints work
- the activity endpoints return the expected payloads

## Behavioural compatibility

Existing quiz-based consumers should continue to work because the plugin still exposes quiz wrapper APIs. New development should target the activity APIs instead.

## Risk management

The migration was designed to be reversible at the integration boundary by keeping wrappers and fallback logic in place. Destructive legacy removal should only happen after a dedicated cleanup release.
