# Changelog

## Unreleased

### Added

* A game flow authoring page (issue #1): `flow.php` lists every quiz slot beside its direction
  card, `flow_service` reads and writes them through `slot_config_schema`, and the per-slot form
  covers scene type, narrative, all four branch outcomes, rewards and display flags. Includes a
  bulk apply that only touches the fields that were filled in, and an analysis that reports
  unreachable slots and dead ends.

* Full development and CI infrastructure adopted from `moodle-plugintemplate`: dev and main
  pipelines, Playwright browser journeys, k6 and JMeter load plans, makefile quality gates,
  coverage floor, release-artefact and stale-file gates.
* `docs/ENTWICKLUNGSUMGEBUNG.md` with the environment setup and the session prompts.

### Changed

* CI: every `moodle-plugin-ci` check command now passes the `plugin` argument. Version 4 requires
  it and `install` does not export it, so `codechecker`, `phpdoc`, `savepoints`, `validate`,
  `mustache`, `grunt`, `phpunit` and `behat` aborted before checking anything.
* CI: the development pipeline pins `qbehaviour_stackmathgame` to `main`; that repository has no
  `development` branch and `add-plugin` fails hard on a missing ref.
* All line endings normalised to LF, with an `eol=lf` policy in `.gitattributes`. CRLF made
  PHPCS report 291 errors across 68 files.
* PHPUnit test classes moved from the `local_stackmathgame\tests\unit` namespace to
  `local_stackmathgame\unit`, which is what `moodle.PHPUnit.TestCaseNames` expects for files in
  `tests/unit/`.
* The duplicated question-map upgrade block was extracted into
  `db/upgradelib.php::local_stackmathgame_upgrade_questionmap_cmid()`.
* `ui_renderer.render()` split into `statusText()`, `renderProfile()` and `renderNarrative()`;
  the promise chain in `game_engine.handleGameCheck()` flattened. Both were ESLint failures under
  `--max-lint-warnings 0`.
* Language packs gained the nine missing capability strings.

* CI now installs the real dependency set (`qtype_stack`, `qbehaviour_adaptivemultipart`,
  `qbehaviour_stackmathgame`, `filter_shortcodes`) plus the optional `block_xp` / `block_stash`
  integrations, so the bridges are exercised rather than skipped.
* CI runs Grunt and Mustache. The template assumed a PHP-only plugin; this one ships AMD
  modules in `amd/src/` and in every `mode/*/amd/src/`.
* The version lockstep gate now checks the bundled mode subplugins unconditionally and
  `qbehaviour_stackmathgame` only on a tag.

* Prerequisite validation per activity (issue #3): a `prerequisite_checker` service, a panel on
  the game settings page, form validation that refuses to enable an unplayable game, and a guard
  in the asset-injection hook.

* Branch navigation is now resolved on the server for every mode (issue #2): a
  `navigation_resolver` service, a `navigation` block on the submit and prefetch responses, and
  shared `navigationFrom` / `applyNavigation` helpers in `game_core`.

* Design assets now reach the game runtime (issue #4): `runtimeassets` is a structured field on
  the design export, `game_core.assetUrl()` addresses assets by manifest key, and the RPG and
  ExitGames modes render the sprites their packages declare.

### Fixed

* `theme_manager::asset_base_url()` accepted a design slug and ignored it, returning the generic
  shared directory for every design alike.
* `game_engine.js` read `runtimejson` from the quiz config instead of from the design, one level
  too high, so the runtime was always an empty object.
* `stackmathgamemode_wisewizzard` built an asset path by appending a filename to a base URL.

* `prefetch_next_node` never called `branch_resolver` at all: it returned the next unsolved slot
  in map order and ignored the branching configuration, so a prefetch and a submit could disagree
  about where the player was going.
* The three game modes only produced a navigation control for an explicit `slot` jump, leaving
  `linear` - the default every auto-created slot receives - and `end` with no way forward.
* Narrative text reached the DOM through `innerHTML` unescaped in all three game modes.

* `requiresbehaviour` was reset to `0` on every save of the quiz game settings, because the
  teacher form does not carry the field and the value was derived with `empty()`. It is now only
  written when a caller states a value.
* `behat_local_stackmathgame::i_navigate_to_smg_settings_for_quiz()` ignored its argument and
  read the cmid from the current page's DOM. It now resolves the quiz by name, and the context
  gained the standard `resolve_page_instance_url()` / `resolve_page_url()` methods.
* Both language files are now alphabetically ordered, as `moodle.Files.LangFilesOrdering`
  requires, and the German pack no longer misses the eight `stashmapping_*` strings.

### Removed

* `tests/behat/behat_local_stackmatheditor.php`, a Behat context belonging to a different
  plugin: it declared `@package local_stackmatheditor`, drove a MathQuill editor this plugin does
  not ship, and no feature file here referenced any of its fifteen steps.
* `amd/src/fantasy_quiz.js`, the standalone prototype module superseded by `game_engine`. Its
  HTML escaping was salvaged into `game_core`; its activity-aware endpoint dispatch already
  existed in `api_client`.
* `.github/workflows/moodle-ci.yml`, superseded by the dev and main pipelines. Its dependency
  knowledge — in particular that `qtype_stack` needs `qbehaviour_adaptivemultipart` — was
  carried over.

## 0.9.0

Scaffolding release: database schema, external API, AMD modules, hook registrations, privacy
provider, language packs, shortcodes, narrative renderer, Game Design Studio with ZIP
import/export, and the three bundled game mode subplugins.
