# Legacy removal plan

## Goal

Remove the remaining quiz-centric compatibility layer after all known consumers have switched to the activity-based contract.

## Scope of future removal

The following areas are candidates for removal in a later cleanup release:

- quiz-wrapper external functions that only delegate to activity endpoints
- legacy `quizid` fallback branches in service code
- legacy `quizid` columns that are no longer required for compatibility
- duplicated export code kept only for wrapper payload parity

## Preconditions

Do not start legacy removal until all of the following are true:

- no internal UI or external client still depends on quiz-only endpoints
- reward, mapping, and history APIs are consumed through activity identity
- upgrade tooling has backfilled historical data successfully
- the legacy wrappers have remained stable for at least one release cycle
- CI covers the activity contract comprehensively

## Removal strategy

### Step 1. Measure usage

Confirm whether any wrappers are still used in:

- Behat scenarios
- PHPUnit fixtures
- custom site integrations
- operational scripts

### Step 2. Mark as deprecated

Document deprecated quiz-wrapper APIs and helper methods in developer documentation before removal.

### Step 3. Remove wrappers first

Delete wrapper-only external functions and helper methods once no callers remain.

### Step 4. Remove legacy persistence fields

Only after wrapper removal and verified backfill:

- drop obsolete `quizid` compatibility fields where safe
- remove old indexes kept only for compatibility
- simplify service queries to activity-only resolution

### Step 5. Remove fallback logic

Delete old-schema fallback paths after supported upgrade paths no longer need them.

## Success criteria

Legacy removal is complete when:

- activity identity is the only public contract
- no compatibility wrappers remain
- no legacy-only database fields remain
- service and integration code no longer special-cases quiz identity except where quiz domain logic is still inherently required
