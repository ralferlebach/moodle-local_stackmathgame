moodle-local_stackmathgame
==========================

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-local_stackmathgame/actions/workflows/moodle-plugin-ci-main.yml/badge.svg?branch=main)](https://github.com/ralferlebach/moodle-local_stackmathgame/actions?query=workflow%3A%22Moodle+Plugin+CI+Main%22+branch%3Amain)

Turns an ordinary Moodle quiz built from STACK questions into a playable game: each quiz slot
becomes a scene with its own narrative, rewards and branching, and a game mode subplugin
renders it as an RPG, an escape game or a mentor-guided tutorial.


Requirements
------------

This plugin requires Moodle 4.5+.

Hard dependencies, all enforced through `version.php`:

* **qtype_stack** — the question type this plugin is built around. It in turn requires
  `qbehaviour_adaptivemultipart` and a reachable Maxima installation.
* **qbehaviour_stackmathgame** — the question behaviour that makes the pageless answer flow
  possible. A quiz must actually be set to this behaviour, not merely have it installed.
* **filter_shortcodes** — carries the `[smg…]` shortcodes used in narrative text.

Optional integrations, wired through silent-fail bridges: **block_xp** and **block_stash**. If
they are absent nothing breaks and no error is shown; if they are present, solving a scene can
award experience points and stash items.


Motivation for this plugin
--------------------------

STACK questions are excellent at assessing mathematics and poor at motivating a student to
attempt the next one. The gap is not in the assessment, it is in everything around it: there is
no reason to continue, no consequence to a wrong answer beyond a lower mark, and no sense of
having got anywhere.

This plugin grew out of a standalone JavaScript prototype that solved that for one course by
wrapping a quiz in a fantasy narrative. The prototype worked but was unmaintainable — it
persisted game state by abusing a STACK `maxima-string` field, and it could not be configured
by anyone who was not willing to edit JavaScript. This plugin keeps the browser-side idea and
replaces every hack with a Moodle-native equivalent: real database tables, real web services,
real capabilities, and an authoring interface for teachers.


Installation
------------

Install the plugin like any other plugin to folder
`local/stackmathgame`

The three game modes ship inside this plugin as subplugins under `mode/` and need no separate
installation. `qtype_stack`, `qbehaviour_stackmathgame` and `filter_shortcodes` must be present
before the installation will complete.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.


Usage & Settings
----------------

After installing the plugin, it does not do anything to Moodle yet. A game is enabled per quiz.

**As a teacher**, on a quiz built from STACK questions:

1. Set the quiz's question behaviour to **STACK Math Game**. Any other behaviour leaves the
   game inactive — the pageless answer flow depends on it.
2. Open **Game settings** from the quiz's settings navigation.
3. Enable the game, pick a **label** and pick a **design**.
4. Configure each slot's scene, narrative, rewards and branching.

The **label** is what ties progress together. Profiles, experience points and achievements are
stored per label rather than per quiz, so several quizzes sharing one label form a single
campaign in which a student's progress carries over.

The **design** is a visual and narrative package bound to one game mode. A teacher chooses one
from a thumbnail picker and nothing else — designs themselves are authored elsewhere.

**Site administration → Plugins → Local plugins → STACK Math Game** holds the site-wide
settings and the label registry.

**Game Design Studio** (`/local/stackmathgame/studio.php`, linked from the navbar) is where
designs are created, edited, imported and exported. This is deliberately separate from the quiz
settings: authoring a game — narratives, sprites, mechanics — is a different job from running
one, and mixing the two put decisions in front of teachers that they had no basis to make.


Capabilities
------------

This plugin introduces these additional capabilities:

* **local/stackmathgame:play** — play a game in a quiz attempt. Granted to students by default.
* **local/stackmathgame:configurequiz** — enable and configure the game on a quiz, including the
  per-slot scenes. Granted to editing teachers and managers by default.
* **local/stackmathgame:viewstudio** — open the Game Design Studio read-only.
* **local/stackmathgame:managethemes** — create, edit, import and export designs. Intended for
  the dedicated Game Designer role and for administrators.
* **local/stackmathgame:managelabels** — create and assign the labels that group progress across
  quizzes. Because a label is site-wide, this is deliberately not given to teachers by default.


Subplugins
----------

Game modes are subplugins of type `stackmathgamemode`, installed under `mode/`:

* **stackmathgamemode_rpg** — fantasy RPG. Mana, freed fairies, teleport-style scene transitions.
* **stackmathgamemode_exitgames** — escape game. Locks, clues and a countdown towards an exit.
* **stackmathgamemode_wisewizzard** — mentor-guided tutorial. A wizard comments on each answer.

A mode owns its rendering and nothing else. It receives the resolved game state from the core
engine and returns an `onAnswer` handler; it does not talk to the server, and it does not decide
where the player goes next. That separation is what keeps a fourth mode a small piece of work.


Scheduled Tasks
---------------

This plugin does not add any additional scheduled tasks.


How this plugin works / Pitfalls
--------------------------------

The runtime is a chain, and every link matters:

1. On a quiz attempt page a `before_http_headers` hook checks whether a game is enabled for the
   activity and, if so, injects the AMD bootstrap. Note the detection is via `SCRIPT_FILENAME`,
   not `$PAGE->pagetype` — the latter is not yet populated when that hook fires.
2. `game_engine.js` fetches the configuration, the profile, the narrative and the next node,
   then loads the mode subplugin's AMD module and hands it the resolved state.
3. Answering calls a web service instead of submitting the page. The question is re-rendered
   from a fragment endpoint, so feedback appears without a full reload.
4. Branch resolution decides the next slot from the outcome and the slot's configuration.

**Pitfalls worth knowing before debugging:**

* `cmid`, not `quizid`, is the source of truth throughout the data model. It encodes the full
  context and leaves the door open for module types other than quiz.
* A quiz whose `preferredbehaviour` is not `stackmathgame` will not start a game, however
  complete its game settings look.
* Asset URLs come from the design's runtime asset map, resolved server-side from the package
  manifest. Modes must use asset **keys**; a mode that builds its own path will break the moment
  a design is imported rather than bundled.


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme. It should also work with Boost
child themes, including Moodle Core's Classic theme. However, we can't support any other theme
than Boost.

The game runtime injects its own markup into the quiz attempt page and styles it itself, so a
theme that heavily rewrites `mod_quiz` output may need adjustment.


Plugin repositories
-------------------

This plugin is not published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/ralferlebach/moodle-local_stackmathgame


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always
appear.

Please report bugs and problems on Github:
https://github.com/ralferlebach/moodle-local_stackmathgame/issues

We will do our best to solve your problems, but please note that due to limited resources we
can't always provide per-case support.


Feature proposals
-----------------

Due to limited resources, the functionality of this plugin is primarily implemented for our own
local needs and published as-is to the community. We are aware that members of the community
will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/ralferlebach/moodle-local_stackmathgame/issues

Please create pull requests on Github:
https://github.com/ralferlebach/moodle-local_stackmathgame/pulls

We are always interested to read about your feature proposals or even get a pull request from
you, but please accept that we can handle your issues only as feature _proposals_ and not as
feature _requests_.


Moodle release support
----------------------

Due to limited resources, this plugin is only maintained for the most recent major release of
Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS
release. However, new features and improvements are not necessarily backported to the LTS
release.

In practice the supported range is also bounded by `qtype_stack`: this plugin cannot support a
Moodle version that STACK itself does not.

There may be several weeks after a new major release of Moodle has been published until we can
do a compatibility check and fix problems if necessary. If you encounter problems with a new
major release of Moodle — or can confirm that this plugin still works with a new major release —
please let us know on Github.


Translating this plugin
-----------------------

This Moodle plugin is provided with English and German language packs only. Translations into
other languages must be managed through AMOS (https://lang.moodle.org), where they will become
part of Moodle's official language pack.

As the plugin creator, we continue to maintain the German translation. For all other languages,
we kindly ask you to contribute your translations directly in AMOS.

Note that narrative text inside a design package is content, not interface text, and is
translated by editing the design in the Game Design Studio rather than through AMOS.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to
send us a pull request on Github with modifications.


Development
-----------

See `docs/ENTWICKLUNGSUMGEBUNG.md` for the development environment, the local quality gates and
the session prompts. In short:

```bash
make check          # PHPCS, PHPDoc, ESLint, Mustache, PHPMD, PHPCPD, PHPUnit
make fix            # auto-fix style and PHPDoc, rebuild AMD
make playwright     # browser journeys against a live site
make load-seed      # seed a course + token for the load tests
```

`make check` is a fast local pre-check; GitHub CI is the authoritative release gate.


Maintainers
-----------

The plugin is maintained by\
Ralf Erlebach


Copyright
---------

The copyright of this plugin is held by\
Ralf Erlebach

The RPG mode is derived from `alquiz-fantasy-bg-ver3.js`, © 2022 Malte Neugebauer,
Hochschule Bochum, used under the MIT Licence.

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
