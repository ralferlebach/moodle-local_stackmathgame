# Documentation

Keep the documents that outlive a single change here.

* `ENTWICKLUNGSUMGEBUNG.md` — how to set up a working development environment for this plugin,
  which quality gates exist and how to run them, plus the session prompts.
* `prompt-templates/` — the prompts used to open and close a working session. The session-end
  prompt is the useful one: it forces a written record before the session is over, while the
  reasoning is still available.
* `sessions/` — one file per session, never overwritten. What changed, which API contracts were
  used, what was tested, and what remains open. The value shows up months later, when nobody
  remembers why something was decided.
* `STEP16_TESTING_GUIDE.md` — the manual test walkthrough inherited from the scaffolding phase.
  Superseded piece by piece as the automated suites grow; keep it until they cover the same
  ground.

Suggested further documents: a requirements document, a technical blueprint describing the
boundaries between core, mode subplugins and designs, and a backlog. The blueprint matters more
here than in a single-component plugin, because the mode subplugin contract
(`init(gameState) → {onAnswer}`) is a public API that three implementations depend on.
