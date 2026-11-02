# Changelog

## 2026090251

Re-audit of 2 November 2026 (findings N01, N03-N09) and the red CI run.

### Security
- Session keys are no longer stored (N01). The browser no longer sends its session key among the
  answers, the server drops one that is sent anyway, and the event log keeps a positive list of
  diagnostic fields only: the question's own response fields (with STACK's validation echoes) and
  the sequence check, keyed by name. The request itself stays protected by `require_sesskey()`.
  An upgrade step removes the session keys already logged: the old payloads lost their field
  names, so every value of exactly the session-key shape (ten characters `[A-Za-z0-9]`) is removed
  from logged answer lists; named shapes lose their `sesskey` entry. Rewritten rows carry
  `sesskeyredacted: 1`. A genuine answer of that exact shape is removed as well; the authoritative
  copy of every answer is the question attempt step.
- The question fragment endpoint serves running attempts only (N08). A finished attempt is refused
  (`attemptalreadyclosed`) - review is Moodle's review page, which applies the review options - and
  so is a slot the player was not given (a hidden alternative, a future quest stage).
- Game settings (N05): the service checks `local/stackmathgame:configurequiz` itself and refuses a
  context that is not the activity's.

### Fixed
- Teachers on a fresh site could not save the game settings (N05, the two failing Behat
  scenarios): the default label was only ever created lazily by the first configuration write,
  and since opening the settings page writes nothing, a teacher - who may select but not create
  labels - had no label to choose. The installer now creates it, and an upgrade step adds it to
  existing sites.
- Game settings (N05): an unchanged label is checked again (archived or deleted since -> field
  error); keep, clear and invalid design values are told apart (absent keeps, `0` clears a draft,
  negative or garbled is an error); the first save stores exactly the submitted values instead of
  the defaults `ensure_default()` added; the retry after a lost race validates again; designs for
  the picker are loaded bounded (50, plus the current one).
- Navigation (N06): a rule the author configured for a wrong answer (jump to a help scene, early
  end) is carried out; the default still keeps the player on the scene. Future quest stages are
  no longer playable or writable. A finished group is left past its page instead of landing on an
  alternative never offered. The server's presented slot is used at first render, on reload and
  after a stage change; navigation URLs carry it as `smgslot`, honoured only when playable.
- Check flow (N03): when both question refreshes fail, the question, the last input and a status
  message stay; a rendering error in a mode never resubmits; the native fallback without a native
  submit button keeps the answer instead of reloading the page; failing profile, narrative or
  prefetch requests no longer stop the game from starting.
- Unsaved changes (N04): the form is marked clean only when every answer field on the page holds
  what the server holds - per field, not the whole form after one slot's answer. Edits made while a
  check runs survive the refresh and stay unsaved. The re-initialisation reload for editors that
  do not follow replaced content waits for the editor, never runs while a check is running or the
  page is being left, and never discards an unsaved value.
- RPG counters (N07): the cache is scoped by user and campaign, server state wins over a higher
  cached value, and other players' entries are dropped from the tab.
- STACK's instant validation after a check (found by the browser journey, N09): the fragment now
  returns the question's own JavaScript, the engine runs it before the mode moves the fields, and
  stale duplicate ids of the moved fields are dropped first. Without this every answer after the
  first check needed a second check. On page load the engine also waits for the question types'
  scripts before the mode rearranges the question.
- ESLint (CI red): three warnings in `game_engine.js` (promise nesting, unreturned promise).

### Added
- A status line next to the check control (`status_*` strings): saving, saved, not saved, saved
  but not refreshed, no fallback, editor lost, changed during the check.
- Tests: Jest for the engine (failure injection, per-field dirty state, two slots, delayed
  requests, editor on/off, presented slot) and for the RPG counter cache; PHPUnit for the fragment
  endpoint, the event log payload, the upgrade clean-up, the settings service, wrong-answer rules,
  quest stages and the presented slot; Behat: the three former `@smg_navtiming` scenarios run again
  (they pressed "Check answer" before STACK had validated the input), plus a wrong-answer help path
  and `page_groups.feature` (three-stage quest with reload, URL manipulation, alternatives).
- `tests/playwright/e2e/rpg-verified-play.spec.js` with `support/smg-e2e.php` and `support/play.js`:
  the RPG played by keyboard in MathQuill (editor on) and in STACK's own fields (editor off), wrong
  then right, back and forward, reload, a four-line equivalence chain and the omitted first line,
  a real attempt finish, every result compared with the stored question attempt steps; narrow
  viewport and 200 % zoom. `rpg-full-ui.spec.js` answers through the visible field, checks once as
  STACK's configuration requires, finishes the attempt for real and compares the stored answers.

### Changed
- CI: the `@smg_navtiming` exclusion is gone from both Behat runs.

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
