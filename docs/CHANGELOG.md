# Changelog

## 2026090250

### Fixed
- Behat: the branch-navigation scenarios pressed STACK's own "Check" button, which the game
  hides, instead of the game's "Check answer" button.
- "Game settings" was missing from the quiz's "More" menu (#13). The navigation callback read the
  activity from `$PAGE->cm`, which is still empty while the secondary navigation is built.
- Game settings (#14): a new label name typed while a label was selected was dropped although
  the page reported success. The typed name now wins; a name matching an active label joins it,
  one matching an archived label is refused. Names and display names are length-checked, label
  and configuration are saved in one transaction, and activation is checked against the
  submitted design. A draft can be saved without a design. Opening or cancelling the page writes
  nothing; contradictory `cmid`/`quizid` parameters are refused instead of falling back to the
  quizid, and the page no longer deletes anything. Design previews resolve like the runtime.
- Fresh installations lacked the unique index on the configuration's `cmid` (upgraded sites had
  it); duplicates are reduced to the latest row before the index is added.
- RPG (#15): question, inputs with or without an editor, validation, the check button and
  Moodle's outcome now stay together in the panel. In the scene they were clipped and the
  feedback was hidden. Navigation arrows carry visible captions and say why they are locked;
  locked controls stay reachable by keyboard. Artwork that fails to load is reported.
- Leaving a scene after a saved answer opened the browser's "leave page?" dialog (#16). The quiz
  form is marked clean once the answer is stored and the question shows the stored state;
  unsaved edits remain protected. A second click while a check is running is ignored; a failing
  follow-up request no longer blocks the way on; the native fallback submits the form instead of
  reloading it.

### Changed
- CI (#12): the release gate and the version-lockstep job are gone; `ci-complete` summarises the
  test jobs only.

### Added
- Label selector backed by a server-side search (`local_stackmathgame_search_labels`).

## 2026090248

### Fixed
- The question fragment endpoint never returned a fragment: it called a method that does not
  exist, passed render_question() its arguments in the wrong order, and fell back to an API Moodle
  reserves for unit tests. After every check the engine therefore reloaded the whole page instead
  of swapping the question. It now swaps only the question.
- RPG: the hidden textarea MathQuill uses to capture keystrokes was moved out of the editor into
  the scene, which lost the last character of an answer. Only STACK's own named fields move.
- Tutor and exit game: a scene solved on an earlier visit, or before a reload, offered no way on.
- The reported input names included STACK's language stamp (step_lang).

### Added
- With a maths editor that does not attach to replaced content (STACK Math Editor 1.2.2), the
  page reloads after a check so the editor initialises again.
- E2E workflow input `stackmatheditor` to run the journey with the editor installed.

## 2026090247

### Added
- Chapter landmarks in the quiz navigation for every mode: a house on the starting scene, a skull
  on a boss, a chequered flag on "Finish attempt". Font Awesome Free from Moodle core.
- Compatibility with the STACK Math Editor in every mode (see README, "Maths input editors").
- A warning on the game settings page when MathJax is loaded from an external server.
- Design packages with artwork are imported through the Game Design Studio.

### Changed
- All user-facing labels in the engine and the modes come from the language pack (en, de).
- RPG: the elf steps aside when a tall validation would cover him; validation ends above the
  arrows; the panel width no longer depends on the scene's.
- Digital Mentoring (wisewizzard) mode rebuilt after the original's layout; the operator keyboard
  is gone, the STACK Math Editor replaces it.

### Fixed
- The engine used game_core without declaring it as a dependency.
- Formulas in a question swapped in after a check stayed as raw TeX.

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
