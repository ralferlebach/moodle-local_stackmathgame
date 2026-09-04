# Session 002 — Code-Analyse und Anforderungsprüfung

**Status:** abgeschlossen
**Quelle:** rekonstruiert in Sitzung 003 aus dem Verlauf
„[STACKGame] 002: Code-Analyse und Anforderungsprüfung".

> **Hinweis zur Rekonstruktion.** Nachträglich aus dem Gesprächsverlauf zusammengetragen, nicht
> zeitnah geführt. Belegbar sind die Befunde und die Architekturkorrektur; der Verlauf der
> Untersuchung und alles, was unterwegs verworfen wurde, fehlt.

---

## Ziel

Das in Sitzung 001 erzeugte Skelett gegen die ursprüngliche Planung prüfen: Stimmt die
Architektur, und was fehlt bis zu einem spielbaren Stand?

## Die zentrale Architekturkorrektur

Der schwerwiegendste Befund war ein eigener Fehler aus der vorigen Sitzung: **die Spiellogik der
drei Modi lag in `local_stackmathgame/amd/src/`** — als `game_rpg.js`, `game_exitgame.js`,
`game_tutor.js`.

Das widerspricht der Subplugin-Architektur. Die drei Modi sind eigenständige Moodle-Komponenten
vom Typ `stackmathgamemode`, deklariert in `db/subplugins.php`, und ihre AMD-Module gehören in
`mode/*/amd/src/game.js`. Nur der Router und die gemeinsame Engine (`game_core.js`) gehören ins
Elternplugin.

Ralf ergänzte dabei eine Namenskorrektur, die bis heute gilt: **der Router darf nicht nach einem
Spielmodus heißen.** `fantasy_quiz.js` wurde damit zum Auslaufmodell zugunsten von
`game_engine.js`. (Die Datei überlebte noch bis Sitzung 003 und wurde dort entfernt.)

## Grunt für Subplugins: Option B

Für den AMD-Build der drei Subplugins standen zwei Wege zur Wahl:

* **Option A** — je Subplugin eine eigene `package.json` und `Gruntfile.js`
* **Option B** — Moodles Grunt im Moodle-Root mit gezieltem Subpluginpfad

**Ralf entschied sich für Option B**, mit der Vorgabe, sie „ab dem kommenden Paket
mitauszuliefern". Dazu kamen `.eslintrc`-Dateien je Subplugin, die die Kernkonfiguration über
einen relativen Pfad erweitern.

> Nachtrag aus Sitzung 003: Genau diese `.eslintrc`-Dateien brachen unter Moodle 5.1+, weil die
> feste Pfadtiefe den Wechsel des Webroots nach `public/` nicht überlebt. Sie wurden entfernt —
> Moodles Gruntfile liefert die Konfiguration für jede Komponente ohnehin. Die Entscheidung für
> Option B als solche gilt weiter und steckt heute im makefile und im CI-Schritt, der über
> `mode/*/` iteriert.

## Befunde P1 bis P4

**P1 — Question-Map wird nie erzeugt.** Ohne Zeilen in `local_stackmathgame_questionmap` liefert
`get_quiz_config` ein leeres Array; alle drei Modi bekommen leere Slot-Maps, und es gibt kein
Branching, kein Narrativ, keine Navigation. Fix: `question_map_service::ensure_for_cmid($cmid)`
in `quiz_settings.php`.

**P2 — Kein Formularfeld für die Regiekarte.** `slot_config_schema.php` war mit allen Konstanten
fertig und wartete auf Verwendung; das Formular zeigte nur Enable, Design, Label und Stash.
Vorgeschlagen wurde ein Akkordeon je Slot im bestehenden Formular.

> Nachtrag aus Sitzung 003: Daraus wurde Issue #1, und die Umsetzung ging bewusst einen anderen
> Weg — eine eigene Seite `flow.php` statt eines Akkordeons, weil zwanzig Slots mit je fünf
> Abschnitten das Einstellungsformular unbenutzbar machen.

**P3 — `nextslot` fehlt in der Submit-Antwort.** `branch_resolver::resolve_next_slot()` existierte
vollständig, wurde aber nirgends aufgerufen. Die Antwort enthielt `cannext`, aber keine
Zielslot-Nummer, und keines der drei Mode-Module konnte navigieren.

> Nachtrag aus Sitzung 003: Daraus wurde Issue #2. Der dort vorgeschlagene Fix — im Client den
> Moodle-Navigationsbutton anklicken — wurde nicht übernommen; stattdessen löst der Server die
> Navigation vollständig auf und der Client rendert sie nur noch.

**P4 — Studio ohne visuellen Editor.** `design_edit_form.php` zeigte rohe JSON-Textareas für
`narrativejson`, `uijson` und `mechanicsjson`. Mittelfristig sollte ein visueller Editor auf
Basis der Schema-Konstanten entstehen. **Bis heute offen.**

## Kleiner, aber folgenreicher Befund

Der Haupttabelle `local_stackmathgame` fehlte ein Unique-Index auf `cmid`. Ohne ihn kann
`ensure_default()` bei gleichzeitigen Aufrufen Duplikate erzeugen.

## Ergebnis

Ralf beauftragte die Umsetzung von P1 bis P3. Die daraus entstandenen GitHub-Issues #1 bis #5
wurden in Sitzung 003 abgearbeitet.
