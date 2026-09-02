# Session 003 — Infrastruktur und MVP-Blocker

**Status:** laufend
**Ziel der Sitzung:** Entwicklungs- und CI-Infrastruktur aus `moodle-plugintemplate` übernehmen,
danach die Issues #1–#5 abarbeiten, sodass die drei bereits angelegten Spielmechaniken in der
Testumgebung tatsächlich spielbar werden.

> Dieses Dokument wird während der gesamten Sitzung fortgeschrieben, nicht am Ende einmal
> geschrieben. Ein Chatverlauf entspricht einer Sitzung.

---

## Ausgangslage

Die Codebasis war ein vollständiges technisches Scaffold: Datenmodell, externe API, AMD-Module,
Hooks, Privacy-Provider, Sprachdateien, Shortcodes, Narrative-Renderer, GameDesign-Studio mit
ZIP-Import/-Export und drei gebündelte `stackmathgamemode`-Subplugins. Was fehlte, war die
Verbindung zwischen den Teilen — und genau darum drehen sich die fünf offenen Issues.

Alle fünf Issue-Befunde ließen sich im Code belegen:

| Issue | Belegstelle |
|---|---|
| #1 Regiekarten-UI fehlt | `quiz_settings_form.php` kennt nur enabled/label/design/stash; `question_map_service` schreibt ausschließlich `slot_config_schema::defaults()` |
| #2 Branching unvollständig | `mode/*/amd/src/game.js`: `(rule.mode === 'slot' && rule.target) ? … : null` — `linear` und `end` erzeugen nie einen Weiter-Link, obwohl `branch_resolver` serverseitig vollständig ist |
| #3 `requiresbehaviour` | `quiz_configurator.php:165` liest `$data['requiresbehaviour']`, das Formular liefert den Schlüssel nie → jedes Speichern setzt 1 → 0; `quiz.preferredbehaviour` wird nirgends geprüft |
| #4 Assets | `theme_manager::asset_base_url()` ignoriert `$slug`; `game_engine.js` liest `quizconfig.runtimejson` statt `design.runtimejson` |
| #5 Submit | `get_question_fragment.php:127` nutzt `get_state()->get_name()`; `submit_answer.php:141` nutzt bereits korrekt `(string)$qa->get_state()` |

**Nebenbefund:** `docs/sessions/` enthielt im hochgeladenen Stand nur `README.md`. Die Protokolle
der Sitzungen 001 und 002 liegen also nicht im Repository. Sie sollten dort nachgetragen werden,
sonst beginnt die Historie mit dieser Sitzung.

---

## Entscheidungen

| Frage | Entscheidung |
|---|---|
| (a) Testmatrix | Moodle 4.5–5.2, PHP 8.2–8.4; **kein** PHP 8.4 für Moodle 4.5 |
| (b) `requiresbehaviour` | Feld bleibt erhalten, wird aber nie aus dem Lehrenden-Formular geschrieben; es ist ein Durchsetzungs-Flag, kein Formularfeld |
| (c) Regiekarte | eigene Unterseite `flow.php?cmid=…`; React zulässig, falls die Oberfläche komplex wird (angelehnt an Moodle 5.3) |
| (d) `fantasy_quiz.js` | wird entfernt — **Brauchbares vorher in die Game-Engine übernehmen** |
| (e) STACK-Fixtures | Ralf sucht Fragen heraus, ggf. die Original-Fragen; der Playwright-Seed importiert sie automatisch, sobald `tests/fixtures/stack_playwright.xml` existiert |

---

## Patch 01 — Infrastruktur (abgeschlossen)

`smg_patch_2026090101.zip`

### Geänderte Dateien

```
.github/workflows/moodle-plugin-ci-main.yml   (neu, aus Template)
.github/workflows/moodle-plugin-ci-dev.yml    (neu, aus Template)
.github/workflows/playwright.yml              (neu, aus Template)
.github/workflows/load-k6.yml                 (neu, aus Template)
.github/workflows/load-jmeter.yml             (neu, aus Template)
.github/workflows/moodle-ci.yml               (entfernt)
makefile, phpcs.xml, phpmd.xml, .gitattributes, .gitignore, .phpcsignore, LICENSE
README.md
docs/ENTWICKLUNGSUMGEBUNG.md, docs/README.md, docs/DOCUMENTATION.md, docs/CHANGELOG.md
docs/prompt-templates/sessionstart.txt, docs/prompt-templates/sessionende.txt
tools/coverage_gate.php, tools/fix_phpdoc.php, tools/mustache_check.php
tests/coverage.php
tests/load/*  (seed_large.php, k6 ×2, JMX, README)
tests/playwright/*  (seed.php, helpers.js, game.spec.js, accessibility.spec.js, README)
db/removed_files.txt
```

### Angepasste Einstellungen (Struktur unverändert)

* **Abhängigkeiten.** Aus der alten `moodle-ci.yml` übernommen, dass `qtype_stack` seinerseits
  `qbehaviour_adaptivemultipart` braucht. Fehlt es, bricht die Moodle-Installation ab, bevor
  `local_stackmathgame` überhaupt erreicht wird — ein Fehlerbild, das leicht dem falschen Plugin
  zugeschrieben wird. Vollständiger Satz: qtype_stack, adaptivemultipart,
  qbehaviour_stackmathgame, filter_shortcodes (hart) sowie block_xp und block_stash (weich,
  damit die Bridges durch Testfälle laufen statt sich zu überspringen). Alle sechs Repo-Slugs
  gegen GitHub verifiziert.
* **Grunt und Mustache ergänzt.** Das Template war für ein PHP-only-Plugin geschrieben; dieses
  Plugin hat AMD in `amd/src/` *und* in `mode/*/amd/src/`. Das ist eine Erweiterung der Gates,
  keine Reduktion.
* **`ecosystem-lockstep` umgeschrieben.** Statt vier Geschwisterrepos: die drei gebündelten
  Mode-Subplugins werden unbedingt geprüft (sie liegen im selben ZIP, eine Versionsdrift ist
  dort nie ein Rollout-Artefakt), `qbehaviour_stackmathgame` dagegen nur auf einem Tag — zwei
  Repositories lassen sich nur nacheinander pushen, ein harter Gleichstand würde den ersten Push
  jedes Mal rot färben und wäre damit Lärm statt Gate.
* **Matrix** auf 4.5/5.0/5.2 × PHP 8.2–8.4 gesetzt, ohne 8.4-Zellen für 4.5.
* **makefile:** `amd` und `lint-js` iterieren explizit über `mode/*/`. Grunt bestimmt die
  Komponente aus dem Arbeitsverzeichnis; ein Lauf im Plugin-Root baut `amd/build/` und lässt
  `mode/*/amd/build/` veraltet zurück, während `amd/src` korrekt aussieht.

### Testinfrastruktur

* **k6-Read-Plan** bildet den kompletten Bootstrap ab (alle vier Webservices je Iteration), weil
  `game_engine.js` sie in einem `Promise.all` feuert — einzeln gemessen ergäbe das eine Latenz,
  die kein Spieler erlebt. Beide Pläne prüfen auf das Fehlen eines `exception`-Keys, nicht auf
  HTTP 200: ein Moodle-Webservice antwortet auch bei Exception mit 200.
* **k6-Race-Plan** auf die Reward-Farming-Invariante aus Issue #5 umgebaut. Der Gate sitzt im
  `teardown()` gegen das Profil, nicht in den Statuscodes: parallele Submits einer bereits
  gelösten Frage sind alle legitime 200er.
* **Playwright** prüft unter anderem, ob Assets aus dem Design-Paket oder nur aus dem generischen
  `shared/`-Pfad kommen — genau der Issue-#4-Fehler, der im DOM unsichtbar ist.

### Verifiziert

Alle PHP-Dateien linten sauber (PHP 8.3), alle Workflows sind gültiges YAML, der JMX-Plan ist
wohlgeformt, alle `make`-Targets laufen im Dry-Run, und das Release-Artefakt-Gate ist lokal grün
(253 Einträge, keine Entwicklerwerkzeuge enthalten, alle Pflichtinhalte vorhanden).

### Bewusst nicht getan

`version.php` nicht angehoben. Außer der README ändert sich nichts am ausgelieferten Paket; ein
erzwungenes Upgrade wäre Lärm.

---

## Patch 02 — Issue #3: Voraussetzungen verbindlich validieren (abgeschlossen, ungetestet)

Reihenfolge-Begründung: #3 ist der kleinste Blocker und reine Serverlogik. Es muss vor #1
kommen, weil die Regiekarte auf einem validierten Quiz aufsetzt — eine Szene für ein Quiz zu
authorieren, das das Spiel gar nicht starten kann, ist verlorene Arbeit.

### Entwurf

Neuer Service `local_stackmathgame\local\service\prerequisite_checker` mit drei Schweregraden
(`ok` / `warning` / `error`) und sieben Prüfungen:

1. `qbehaviour_stackmathgame` installiert — blockierend
2. `qtype_stack` installiert — blockierend
3. `filter_shortcodes` installiert — blockierend
4. `quiz.preferredbehaviour === 'stackmathgame'` — blockierend
5. STACK-Fragen im Quiz vorhanden — blockierend; gemischte Fragetypen nur Warnung
6. Question-Map deckt alle Slots ab — nur Warnung, weil die Map sich bei Bedarf selbst
   neu aufbaut; die Meldung ist trotzdem nützlich, wenn gerade Fragen bearbeitet wurden
7. Aktives Design zugewiesen — blockierend

`requiresbehaviour` bekommt damit die Bedeutung „die Verhaltensprüfung wird für diese Aktivität
durchgesetzt". Steht es auf 0, wird Prüfung 4 zur Warnung herabgestuft. Das Feld ist bewusst
**nicht** Teil des Lehrenden-Formulars: dass es sich stillschweigend abschalten ließ, war die
Ursache des ursprünglichen Fehlers.

### Geänderte Dateien

```
classes/local/service/prerequisite_checker.php   (neu)
classes/output/prerequisite_panel.php            (neu)
templates/prerequisite_panel.mustache            (neu, erstes Template im Plugin)
classes/game/quiz_configurator.php               (Bugfix save_for_quiz)
classes/form/quiz_settings_form.php              (Panel + Validierung)
classes/hook/output_hooks.php                    (Guard in inject_game_assets)
quiz_settings.php                                (Panel rendern)
lang/en|de/local_stackmathgame.php               (34 neue Strings, komplett sortiert)
tests/unit/prerequisite_checker_test.php         (neu)
tests/unit/requires_behaviour_test.php           (neu)
tests/behat/quiz_prerequisites.feature           (neu)
tests/behat/behat_local_stackmathgame.php        (Page-Resolver + Bugfix)
version.php, mode/*/version.php                  (2026090202, im Gleichstand)
```

### Der eigentliche Bugfix

`save_for_quiz()` schreibt `requiresbehaviour` jetzt nur noch, wenn der Schlüssel tatsächlich
übergeben wurde (`array_key_exists` statt `empty`). Vorher setzte jeder Speichervorgang des
Lehrenden-Formulars — das den Schlüssel nie enthält — die gespeicherte 1 auf 0 zurück und
schaltete die Durchsetzung genau in dem Moment ab, in dem jemand das Spiel konfigurierte.

Wichtig ist, dass das Feld schreibbar **bleibt**: würde es nur noch bewahrt, könnte eine
Administration die Durchsetzung nie mehr übersteuern. Beides ist getestet.

### Durchsetzung an drei Stellen

1. **Panel** ganz oben im Formular, vor jedem Bedienelement — wer ein Spiel aktivieren will,
   muss vorher wissen, dass es nicht starten kann.
2. **Validierung**: „Spiel aktivieren" ist bei blockierendem Befund nicht speicherbar, der Rest
   des Formulars aber schon. Das ganze Formular abzulehnen würde verhindern, dass die
   Konfiguration vorbereitet wird, während der Blocker noch besteht.
3. **`inject_game_assets()`** injiziert nicht mehr, wenn `is_playable()` false ist. Ohne das
   bootet die Engine, die vier Webservices antworten, und der Versuch verhält sich wie ein
   gewöhnlicher Test — was wie ein defektes Plugin aussieht, nicht wie eine falsch
   konfigurierte Aktivität.

### Nebenbefunde, die dabei behoben wurden

* **Sprachdateien waren unsortiert.** `moodle.Files.LangFilesOrdering` wäre also schon vor
  diesem Patch rot gewesen. Beide Dateien sind jetzt vollständig alphabetisch sortiert; die
  bisherigen Abschnittskommentare konnten dabei nicht erhalten bleiben.
* **Acht Strings fehlten im deutschen Sprachpaket** (`stashmapping_*`). Nachgetragen; die
  Schlüsselmengen von EN und DE sind jetzt identisch.
* **`behat_local_stackmathgame::i_navigate_to_smg_settings_for_quiz()` war defekt.** Der Schritt
  baute ein SQL-Statement, benutzte es nie, las die cmid stattdessen aus dem DOM der aktuellen
  Seite und ignorierte sein eigenes Argument — er öffnete also die Einstellungen irgendeines
  Tests oder scheiterte auf jeder Seite ohne `cmid`-Feld. Ersetzt durch eine Auflösung über die
  Datenbank plus die Moodle-üblichen `resolve_page_instance_url()` / `resolve_page_url()`.

### Tests

* `prerequisite_checker_test.php` — sieben Fälle: falsches Verhalten blockiert und nennt das
  tatsächlich verwendete; korrektes Verhalten besteht; `requiresbehaviour = 0` stuft auf Warnung
  herab statt die Prüfung zu entfernen; fehlende STACK-Fragen blockieren; ein leerer Test meldet
  die Leere und nicht „keine STACK-Fragen"; `get_blockers()` liefert nur Fehler; jede Prüfung ist
  vollständig befüllt.
* `requires_behaviour_test.php` — vier Regressionsfälle, inklusive dreifachem Speichern
  hintereinander und dem expliziten Überschreiben in beide Richtungen.
* `quiz_prerequisites.feature` — vier Szenarien, darunter ausdrücklich, dass die übrigen
  Einstellungen trotz Blocker speicherbar bleiben.

**Noch nicht ausgeführt.** In dieser Umgebung steht kein Moodle-Baum zur Verfügung; geprüft sind
bisher nur PHP-Syntax (alle Dateien, PHP 8.3), die Gültigkeit des Mustache-Beispielkontexts als
JSON und der Gleichstand der Sprachschlüssel. PHPUnit und Behat müssen lokal beziehungsweise in
der CI laufen.

---

## Offene Risiken

* **Maxima in der CI.** Ohne funktionierende Maxima-Anbindung überspringen sich STACK-Testfälle
  und melden trotzdem „passed". Für Issue #5 ist das der teuerste Posten im Plan; bis dahin muss
  jede Skip-Meldung ehrlich protokolliert werden.
* **Sitzungen 001 und 002** fehlen im Repository (siehe Nebenbefund oben).
* **Schwellenwerte der Lasttests** (`p95 < 2s`, `http_req_failed < 1%`) sind ein Startwert, kein
  gemessenes Ziel. Sie müssen nach dem ersten echten Lauf auf repräsentativer Hardware
  nachgezogen werden.

---

## Reward-Logik

Diese Sitzung hat Submit-, Profil- oder Reward-Code bisher **nicht** verändert. Die
At-most-once-Garantie wurde daher nicht neu unter Parallelität getestet. Sobald Issue #5
angefasst wird, ist das hier ausdrücklich zu vermerken.
