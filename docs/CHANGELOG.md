# Changelog

## Unreleased

### Added

* Full development and CI infrastructure adopted from `moodle-plugintemplate`: dev and main
  pipelines, Playwright browser journeys, k6 and JMeter load plans, makefile quality gates,
  coverage floor, release-artefact and stale-file gates.
* `docs/ENTWICKLUNGSUMGEBUNG.md` with the environment setup and the session prompts.

### Changed

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

### Fixed

* `requiresbehaviour` was reset to `0` on every save of the quiz game settings, because the
  teacher form does not carry the field and the value was derived with `empty()`. It is now only
  written when a caller states a value.
* `behat_local_stackmathgame::i_navigate_to_smg_settings_for_quiz()` ignored its argument and
  read the cmid from the current page's DOM. It now resolves the quiz by name, and the context
  gained the standard `resolve_page_instance_url()` / `resolve_page_url()` methods.
* Both language files are now alphabetically ordered, as `moodle.Files.LangFilesOrdering`
  requires, and the German pack no longer misses the eight `stashmapping_*` strings.

### Removed

* `.github/workflows/moodle-ci.yml`, superseded by the dev and main pipelines. Its dependency
  knowledge — in particular that `qtype_stack` needs `qbehaviour_adaptivemultipart` — was
  carried over.

## 0.9.0

Scaffolding release: database schema, external API, AMD modules, hook registrations, privacy
provider, language packs, shortcodes, narrative renderer, Game Design Studio with ZIP
import/export, and the three bundled game mode subplugins.
