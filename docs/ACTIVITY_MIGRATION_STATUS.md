# Activity migration status

## Summary

The migration from quiz-centric identifiers to activity-centric identifiers is complete for the supported runtime and configuration flows.

Canonical activity identity is now:

- `cmid`
- `modname`
- `instanceid`

Legacy quiz compatibility remains available through `quizid` wrappers and fallback lookups where required.

## Completed areas

### Runtime contract

The runtime paths are activity-aware and expose activity identity through the public API contract.

Covered areas:

- activity config
- profile state
- reward state
- reward history
- progress save
- prefetch
- answer submission

### Persistence and mapping

The following persistence layers are migrated or migration-ready:

- `local_stackmathgame` configuration rows use `cmid`
- `local_stackmathgame_questionmap` is rebuilt and backfilled by `cmid`
- `local_stackmathgame_stashmap` supports `cmid` with legacy `quizid` compatibility
- `local_stackmathgame_eventlog` supports `cmid` with additive backfill

### Integrations

The bridge and reward paths have been migrated to prefer activity identity:

- bridge dispatcher
- stash bridge
- inventory lookup
- reward state exports
- reward history exports

### Tooling and verification

The migration includes operational tooling and tests:

- question-map rebuild and backfill scripts
- stash-map backfill script
- reward-state reporting script
- PHPUnit coverage for activity and legacy wrapper flows
- Behat fixes for tertiary navigation and shared steps

## Compatibility posture

The current codebase is intentionally in a **compatibility phase**:

- activity-first code paths are canonical
- legacy quiz wrappers remain supported
- legacy fields are not yet removed
- fallback logic is still present where it reduces upgrade risk

This is the recommended state until all connected consumers have moved to the activity contract.

## What is not part of the migration anymore

The following work is explicitly considered post-migration cleanup rather than migration itself:

- removing `quizid` compatibility fields
- deleting quiz-wrapper web services
- removing fallback branches for old schema variants
- slimming bridge payloads once no legacy clients remain

## Exit criteria for the migration phase

The migration phase can be considered complete when all of the following are true:

- runtime and configuration APIs work through the activity contract
- legacy quiz wrappers produce equivalent results
- activity-aware mappings and logs are persisted correctly
- CI is green for linting, PHPUnit, and Behat
- operators have at least one rebuild/backfill path for migrated data

Those conditions are now satisfied for the current scope.
