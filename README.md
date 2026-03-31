# local_stackmathgame

Installable Moodle local plugin scaffold for integrating the analysed STACK math game capabilities.

Implemented in this package:
- fixed hook registration without the missing `navigation_hooks` callback class;
- hook-based AMD injection for quiz attempt pages;
- generated language packs (`en`, `de`);
- seeded built-in fantasy theme and cache purge helper;
- added missing studio/privacy/helper classes required for installation.


## Dependencies

Required plugins:
- qtype_stack
- qbehaviour_stackmathgame
- filter_shortcodes

Optional integrations:
- block_xp
- block_stash

Subplugin support:
- stackmathgamemode subplugins live in `mode/` and are declared in `db/subplugins.json`.



## Migration and maintenance docs

The activity-based migration is documented here:

- `docs/ACTIVITY_MIGRATION_STATUS.md`
- `docs/UPGRADE_NOTES.md`
- `docs/LEGACY_REMOVAL_PLAN.md`
- `docs/POST_MIGRATION_CLEANUP.md`

These documents describe the completed migration state, upgrade expectations, the future legacy-removal strategy, and the planned post-migration cleanup work.

## Shortcodes

The plugin registers the shortcodes `smgscore`, `smgxp`, `smglevel`, `smgprogress`, `smgnarrative`, `smgavatar`, and `smgleaderboard`. Outside a quiz context the attribute `label="..."` is required.
