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


## Bundled mode packages

This work-in-progress build ships bundled default design packages for the `exitgames`, `wisewizzard`, and `rpg` mode subplugins under `mode/*/packages/*`.
