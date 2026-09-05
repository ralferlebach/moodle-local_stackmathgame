# local_stackmathgame — load tests

Two plans with different jobs. The read plan asks "does it stay fast", the race plan asks "does
it stay correct". Both need a live, seeded site.

## Seeding

```bash
make load-seed                 # seeds a course + quiz + token, stores tests/load/.load-env
# or directly:
eval "$(php tests/load/seed_large.php 8)"   # 8 = number of quiz slots
```

`seed_large.php` creates a course, a quiz using `preferredbehaviour = stackmathgame` with one
question per page, enables the game, builds the question map, and mints a REST token for an
enrolled student. It prints `BASE_URL`, `COURSEID`, `CMID`, `QUIZID`, `SLOTS` and `TOKEN`.

The token is unavoidable. The endpoints under test are web services rather than pages, and the
plans have to exercise the same calls `game_engine.js` makes — those all go through
`/webservice/rest/server.php`.

The seed fills the slots with plain short-answer questions. The measured endpoints read
configuration and profile state; they do not evaluate answers, so Maxima is not needed. For a
STACK-backed run, import your own STACK questions into the seeded quiz before starting the
plan — the plans do not care which question type sits in a slot.

## stackmathgame-read-endpoints.js / .jmx — read plateau

Keeps `VUS` users active for `DURATION`, reproducing the whole attempt-page bootstrap on every
iteration: `get_quiz_config`, `get_profile_state`, `get_narrative` and `prefetch_next_node`.

The bootstrap is reproduced as a whole rather than one endpoint at a time on purpose:
`game_engine.js` fires all four inside a single `Promise.all`, so measuring them separately
would report a latency the player never experiences.

```bash
k6 run -e BASE_URL="$BASE_URL" -e QUIZID="$QUIZID" -e TOKEN="$TOKEN" \
  -e VUS=25 -e DURATION=90s tests/load/stackmathgame-read-endpoints.js

make jmeter                    # JMeter twin, parameters from .load-env
```

A Moodle web service answers HTTP 200 even when it raises an exception, so both plans assert on
the absence of an `exception` key rather than on the status code alone.

`prefetch_next_node` calls `question_map_service::ensure_for_cmid()` on every request, so it is
the endpoint where a missing index shows up soonest. Watch its p95 separately.

## stackmathgame-capacity-race.js — concurrency / write gate

Many simultaneous submissions of the **same** answer to the **same** slot compete to be
rewarded. This is the complement to the PHPUnit tests for issue #5: those can prove that a
second, sequential submission grants nothing, but they cannot prove mutual exclusion. A single
PHP process reads and writes the profile inside one database session, so its read-modify-write
always sees its own earlier write. Only genuinely parallel requests can interleave between the
"already solved?" check and the XP increment.

The gate is the plugin's behaviour, not the latency: XP may grow by the reward of one solve,
never by a multiple of it. That is checked in `teardown()` against the profile, because every
parallel submission of an already-solved question is a legitimate HTTP 200 — the invariant does
not live in the response codes.

```bash
k6 run -e BASE_URL="$BASE_URL" -e TOKEN="$TOKEN" -e QUIZID="$QUIZID" \
  -e ATTEMPTID=<id> -e SLOT=1 -e ANSWER=2 -e EXPECTED_XP=10 -e VUS=30 \
  tests/load/stackmathgame-capacity-race.js
```

Prerequisites: an open attempt (`ATTEMPTID`) and a slot whose `configjson` carries a non-zero
`rewards.xp`. The seed leaves the schema default of `0`, so set a reward first — with `0` the
gate passes trivially and proves nothing.

## Gemessene Ergebnisse

Auf einem GitHub-Runner, September 2026.

### Leseplan (k6)

```
5750 Anfragen, 0 Fehler, 5748 von 5748 Checks bestanden
p95 363 ms, Median 105 ms, Maximum 1600 ms, 62,8 Anfragen/s
```

### Leseplan (JMeter)

```
6014 Samples, 0 Fehler
get_quiz_config      p95 577 ms
get_profile_state    p95 555 ms
prefetch_next_node   p95 583 ms
Quiz-Ansicht         p95 1007 ms  (enthaelt den Asset-Injection-Hook)
```

Die drei Webservices liegen dicht beieinander - kein Endpunkt faellt aus dem Rahmen, auch nicht
`prefetch_next_node`, das bei jedem Aufruf `question_map_service::ensure_for_cmid()` ausfuehrt.
Die 303er der Quiz-Ansicht sind Weiterleitungen, keine Fehler.

### Race-Plan: diesmal mit echter Gleichzeitigkeit

```
30 Iterationen, alle angenommen, 0 Fehler
mittlere Dauer 4,02 s, 5,81 Iterationen/s, Gesamtdauer 5,2 s
```

Aus Durchsatz mal Bearbeitungszeit folgt eine **mittlere Gleichzeitigkeit von rund 23 Anfragen**:
praktisch alle 30 waren zur selben Zeit in Bearbeitung. Das ist die Kontention, die lokal nie
zustande kam - und die XP stiegen trotzdem nur um die einfache Belohnung.

Damit ist die At-most-once-Garantie unter echter Last belegt. Welcher der beiden Schutzmechanismen
sie traegt - der Lock in `submit_answer` oder die Deduplizierung identischer Antworten durch die
Question-Engine - trennt auch dieser Lauf nicht; siehe den Abschnitt unten.

## Was der Race-Plan nicht zuordnet

Ein Kontrolllauf mit neutralisiertem Lock lieferte dasselbe Ergebnis. Der Grund steht in der
Datenbank: nach dreissig Absendungen hatte der Versuch **zwei** Antwortschritte, nicht dreissig.
Moodles Question-Engine verwirft eine identische Wiederholung derselben Antwort, es entsteht kein
neuer Schritt, die erreichte Wertung aendert sich nicht - und ohne Wertungsaenderung zahlt
`calculate_submit_deltas()` nichts aus.

Der Lock ist damit nicht widerlegt, aber auch nicht als tragend nachgewiesen. Um ihn gezielt zu
pruefen, muessten die parallelen Anfragen *verschiedene* richtige Antworten auf dieselbe Frage
schicken, sodass jede einen echten neuen Schritt erzeugt.

## Thresholds

Nicht mehr geraten, sondern aus den Messungen oben abgeleitet:

| Schwellwert | Wert | Begruendung |
|---|---|---|
| `http_req_failed` | < 1 % | gemessen 0 %; alles darueber ist eine echte Regression |
| `http_req_duration` p95 | < 1000 ms | rund das Dreifache der gemessenen 363 ms |
| `iteration_duration` p95 | < 2500 ms | der Bootstrap feuert vier Aufrufe gleichzeitig |
| JMeter-Dauerassertion | < 2000 ms | gemessen p95 583 ms |

Der alte Wert von 2000 ms fuer `http_req_duration` haette eine Verfuenffachung der Latenz
stillschweigend durchgelassen. Wer auf deutlich schwaecherer Hardware misst, sollte die Werte neu
bestimmen statt sie anzuheben - ein Schwellwert, der jede Messung besteht, ist keiner.
