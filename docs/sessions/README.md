# Session records

One conversation is one session, and one session is one file: `session-NNN.md`, numbered
sequentially, never overwritten.

The file is written **during** the session rather than composed at the end. A record assembled
afterwards remembers what was changed but not why an alternative was rejected, and the "why" is
the part that is expensive to reconstruct months later.

What belongs in one:

* the objective, and which issues it maps to
* decisions taken and the reasoning behind them, including the options not chosen
* changed files, capabilities and entry points touched, API contracts used
* tests written or run — with skips recorded honestly, since a skipped STACK test is not a
  passing STACK test
* unresolved risks and anything deliberately left undone

If the session touched submit, profile or reward code, it must state explicitly whether the
at-most-once reward guarantee was re-tested under genuine concurrency.
