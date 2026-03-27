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


## Shortcodes

The plugin registers the shortcodes `smgscore`, `smgxp`, `smglevel`, `smgprogress`, `smgnarrative`, `smgavatar`, and `smgleaderboard`. Outside a quiz context the attribute `label="..."` is required.


## Optional integration behaviour

- `block_xp` is integrated softly via Moodle events (`progress_updated`, `question_solved`).
- `block_stash` is integrated softly via a local inventory fallback plus `stash_item_granted` event.
- Both integrations fail silently when the companion plugins are not installed.
