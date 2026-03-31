# Post-migration cleanup plan

## Purpose

This document collects the concrete cleanup work that should happen *after* the migration is considered complete.

## Cleanup buckets

### A. API cleanup

- remove deprecated quiz-wrapper services
- collapse duplicate export helpers kept only for wrapper compatibility
- reduce payload duplication where both quiz and activity responses still expose mirrored fields

### B. Persistence cleanup

- remove obsolete compatibility columns after verified backfill
- drop indexes that exist only for legacy access paths
- delete fallback code for old schema variants once no longer needed

### C. Integration cleanup

- simplify bridge dispatch signatures once all callers pass activity identity
- remove legacy quiz-based stash lookup helpers
- simplify event logging once `cmid` is universally available

### D. Test cleanup

- remove legacy wrapper assertions once wrappers are intentionally retired
- reduce duplicate wrapper-vs-activity test matrices where no longer useful
- keep one focused compatibility suite only if wrappers remain public

## Suggested release order

1. **Current state**: migration complete, compatibility retained
2. **Cleanup release 1**: deprecations and documentation
3. **Cleanup release 2**: wrapper removals and service simplification
4. **Cleanup release 3**: destructive schema cleanup, if still justified

## Operational note

Do not combine destructive cleanup with broad functional refactors. Keep cleanup releases narrow so that any rollback remains straightforward.

## Suggested verification after cleanup releases

- PHPUnit for service, integration, and wrapper coverage
- Behat for quiz editing and attempt flows
- reward-state and reward-history reporting via CLI
- direct DB spot checks for migrated rows and indexes
