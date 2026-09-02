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

## Patch 03 — Issue #2: Branching vollständig (abgeschlossen)

### Der Befund war schärfer als im Issue beschrieben

Das Issue sagt, die Mode-Module erzeugten den Weiter-Button nur für einen expliziten
`slot`-Sprung. Das stimmt. Beim Nachlesen kam aber ein zweiter, gravierenderer Punkt dazu:

**`prefetch_next_node` ruft `branch_resolver` überhaupt nicht auf.** Der Endpunkt lieferte
schlicht „den nächsten ungelösten Slot in Map-Reihenfolge" und ignorierte die
Verzweigungskonfiguration vollständig. Damit konnten Prefetch und Submit unterschiedlicher
Meinung darüber sein, wohin die Spielenden gehen — und beim Seitenaufbau gewann immer der
Prefetch. Es gab also nicht zwei, sondern drei Interpretationen desselben Schemas.

### Lösung

Ein einziger Interpret, serverseitig: `navigation_resolver`. Er ruft `branch_resolver` auf und
liefert eine fertige Navigation aus `action` (`continue` / `finish` / `stay`), `nextslot`,
`nextpage`, `url` und `label`. Der Client entscheidet nichts mehr.

Drei Entscheidungen, die ich bewusst so getroffen habe:

* **`gradedwrong` ergibt immer `stay`.** Eine falsche Antwort löst keine Verzweigung auf. Täte
  sie es, bekämen die Spielenden in dem Moment einen Weg nach vorn, in dem die Antwort als
  falsch bewertet wird — der Wiederholungsversuch ist aber der Sinn der Szene.
* **`finish` ist ein Schritt, kein Nichts.** Vorher sah das Ende eines Durchlaufs für alle drei
  Module genauso aus wie eine falsche Antwort: kein Button. Die Spielenden blieben auf der
  letzten Szene stehen.
* **Das Label kommt vom Server.** Ein Modul, das die Beschriftung selbst erfindet, trifft eine
  Entscheidung über einen Zustand, der ihm nicht gehört — und die drei Module waren sich schon
  darüber uneins, was „kein nächster Slot" überhaupt bedeutet.

`quiz_slots.page` ist einsbasiert, der `page`-Parameter von `attempt.php` nullbasiert. Die
Umrechnung liegt in `page_for_slot()` und ist getestet: ein Fehler dort ist unsichtbar, die
Spielenden landen einfach auf der falschen Frage.

### Geänderte Dateien

```
classes/local/service/navigation_resolver.php   (neu)
classes/external/submit_answer.php              (navigation im Response)
classes/external/prefetch_next_activity_node.php (outcome/attemptid, branch_resolver)
classes/external/prefetch_next_node.php         (durchgereicht)
amd/src/game_core.js                            (navigationFrom, applyNavigation, escapeHtml)
amd/src/game_engine.js                          (outcome + attemptid an prefetch, store.navigation)
amd/src/fantasy_quiz.js                         (entfernt)
mode/rpg|exitgames|wisewizzard/amd/src/game.js  (Branch-Interpretation entfernt)
lang/en|de/local_stackmathgame.php              (nav_continue/finish/stay)
tests/unit/navigation_resolver_test.php         (neu)
tests/jest/*                                    (neu: Harness + 12 Tests)
tests/behat/branch_navigation.feature           (neu)
tests/behat/behat_local_stackmathgame.php       (Steps für Branch-Konfiguration)
makefile, .github/workflows/*                   (test-amd-Target und CI-Schritt)
```

### Entscheidung (d): `fantasy_quiz.js` entfernt

Vorher geprüft, was brauchbar war:

* **Aktivitätsbewusste Endpunktauswahl** (`cmid` statt nur `quizid`) — lag bereits vollständig in
  `api_client.js`. Nichts zu übernehmen.
* **HTML-Escaping** — fehlte in der Engine und in allen drei Modulen. Als `GameCore.escapeHtml()`
  übernommen und an allen drei `innerHTML`-Stellen der Module eingesetzt. Narrativtext ist von
  Lehrenden verfasster Inhalt, der ungefiltert ins DOM ging.
* Fragment-Refresh, `collectAnswers`, `bindInputs` — bereits identisch in `game_engine.js`.

Eingetragen in `db/removed_files.txt`.

### Tests

* **Jest: 12 Tests, ausgeführt, grün.** Der Harness (`tests/jest/amd_loader.js`) lädt die
  AMD-Quellen so, wie sie ausgeliefert werden, statt sie für den Runner umzubauen — ein Test
  gegen ein umgeschriebenes Modul sagt nichts über das aus, das der Browser bekommt.
  Als `make test-amd` und als CI-Schritt eingebunden.
* **PHPUnit:** acht Fälle für `navigation_resolver`, darunter der lineare Standardfall, `end`,
  der letzte Slot, ein ungültiges Sprungziel und die Nullbasierung der Seitenzahl.
* **Behat:** vier Szenarien plus vier neue Steps zum Setzen von Verzweigungsregeln. Die Steps
  schreiben durch `slot_config_schema`, damit eine Feature-Datei keine Konfigurationsform
  einschleusen kann, die der Resolver gar nicht akzeptiert.

### Zwischenfall beim Refactoring

Ein Regex zum Entfernen von `getSlotUrl()` hat in `mode/wisewizzard/amd/src/game.js` rund 120
Zeilen zu viel gelöscht (`injectStyles`, `buildSlotMap`, `buildChatUI`). ESLint hat es als drei
`no-undef` gemeldet; die Datei wurde aus dem Ausgangs-ZIP wiederhergestellt und die Änderung
zeilengenau statt per Regex wiederholt. Anlass, künftig nach jedem strukturellen Eingriff in JS
sofort zu linten statt erst am Ende.

### Nicht ausgeführt

`amd/build/` und `mode/*/amd/build/` sind **nicht** neu gebaut — dafür braucht es Grunt aus einem
Moodle-Baum. Vor dem Testen im Browser ist `make amd` zwingend, sonst liefert der Browser die
alten Module aus, während `amd/src` aktuell aussieht. PHPUnit und Behat stehen ebenfalls aus.

---

## Patch 04 — Issue #4: Design-Assets mit der Runtime verdrahten (abgeschlossen)

### Vier Fehler in einer Kette

Serverseitig war fast alles richtig — Manifeste, `package_registry`, `get_theme_config` liefern
korrekte URLs. Auf dem Weg zum Bildschirm ging es an vier Stellen verloren:

1. **`theme_manager::asset_base_url($slug)` verwarf sein Argument** und gab konstant
   `/pix/packages/shared/` zurück, für jedes Design gleich.
2. **`game_engine.js` las `quizconfig.runtimejson`** — das Feld liegt aber auf dem Design, nicht
   auf der Quiz-Konfiguration. Eine Ebene zu hoch gelesen, Ergebnis immer `{}`. Und weil `{}` ein
   vollkommen gültiger Wert ist, hat sich nie etwas beschwert.
3. **Die Engine reichte `assetBaseUrl` (einen Pfad) statt der Assetmap** an die Module weiter.
4. **`wisewizzard` baute daraus `assetBaseUrl + '/mentor_happy.svg'`** — ein handgebauter Pfad,
   der selbst bei korrekter Basis nur für gebündelte Pakete funktioniert hätte.

### Fünfter Befund: die Sprites wurden nie angefordert

Beim Verdrahten fiel auf, dass `rpg` und `exitgames` **überhaupt keine** Manifest-Assets
verwenden. Die Manifeste deklarieren `player_idle`, `enemy_idle`, `bg_forest`, `guide_happy`,
`guide_think` — die Module zeichneten stattdessen CSS-Verläufe. Nur `wisewizzard` hat es
überhaupt versucht.

Das ist mehr als ein kosmetischer Mangel: solange kein Modul ein Asset anfordert, ist die ganze
Auflösungskette nicht prüfbar. Ein Resolver, der eine falsche URL liefert, sieht exakt aus wie
einer, der die richtige liefert. Deshalb rendert `rpg` jetzt eine Bühne mit Hintergrund und zwei
Sprites, `exitgames` einen Guide, der auf richtig/falsch reagiert. Alle Assets sind optional —
ein Design ohne `bg_forest` hat schlicht keinen Hintergrund statt eines kaputten Bildes. Die
Sprites sind als `role="presentation"` mit leerem `alt` ausgezeichnet: der mathematische Inhalt
ist die Frage, nicht die Kulisse.

### Assetmap als eigenes Feld, nicht als verschachteltes JSON

`runtimeassets` ist jetzt ein strukturiertes Feld der Design-Struktur, nicht mehr ein
JSON-String in einem JSON-String. Als **Liste von key/url-Paaren**, nicht als Objekt: Moodles
External API kann keine Struktur mit beliebigen Schlüsseln beschreiben, und die Schlüssel kommen
aus einem Paketmanifest, das Design-Schaffende erweitern dürfen.

`runtimejson` bleibt aus Kompatibilitätsgründen erhalten, aber nichts liest dort noch Assets
heraus. Genau diese Form — JSON in JSON — hat es ermöglicht, die falsche Ebene zu lesen und
kommentarlos eine leere Map zu bekommen.

Module adressieren Assets ausschließlich über `GameCore.assetUrl(gameState, key, fallback)`.

### Geänderte Dateien

```
classes/game/theme_manager.php                  (asset_base_url respektiert den Slug)
classes/external/api.php                        (export_design + export_runtime_assets)
classes/external/get_quiz_config.php            (runtimeassets/thumbnailurl/themeclass)
amd/src/game_engine.js                          (buildAssetMap, runtimejson-Ebene korrigiert)
amd/src/game_core.js                            (assetUrl)
mode/rpg/amd/src/game.js                        (Bühne mit Hintergrund und Sprites)
mode/exitgames/amd/src/game.js                  (Guide-Sprite)
mode/wisewizzard/amd/src/game.js                (Asset per Schlüssel statt per Pfad)
tests/unit/design_assets_test.php               (neu)
tests/jest/game_core.test.js                    (assetUrl-Tests)
tests/playwright/game.spec.js                   (Assertion verschärft)
```

### Tests

* **Jest: 16 Tests, ausgeführt, grün** (12 aus Patch 03 plus 4 für `assetUrl`). Darunter, dass
  ein fehlender Schlüssel `''` liefert und nicht `undefined` — ein `undefined` im `src` wird vom
  Browser als Literal relativ zum Site-Root angefordert und erzeugt einen 404, den man dem Plugin
  anlastet.
* **PHPUnit:** sechs Fälle, darunter dass zwei Designs nie auf dasselbe Verzeichnis auflösen und
  dass **jede im Manifest deklarierte Datei tatsächlich existiert**. Eine fehlende Datei ist zur
  Laufzeit unsichtbar.
* **Playwright:** prüft jetzt, dass mindestens ein Asset aus `/mode/rpg/packages/` angefordert
  wurde und die Bühne sichtbar ist — nicht mehr nur, dass nichts 404 liefert.

---

## Patch 05 — CI-Pipeline reparieren (abgeschlossen, verifiziert)

Ralf hat die Logs des ersten Laufs geliefert: fünf von sechs Jobs rot. Für diesen Patch habe ich
eine echte Moodle-4.5-Umgebung aufgebaut — geklont, `npm install`, PostgreSQL, PHPUnit-Init,
`moodle-plugin-ci` v4.5.11 — und **jedes Gate tatsächlich ausgeführt** statt es zu vermuten.

### Ursache 1 — `Not enough arguments (missing: "plugin")`, 19 Aufrufe

`moodle-plugin-ci` v4 verlangt das `<plugin>`-Argument bei *jedem* Prüfbefehl; `install`
exportiert es nicht in die Umgebung. Die Template-Vorlage ging vom Gegenteil aus: `phplint`,
`phpmd` und `phpcpd` hatten es, alle übrigen nicht. `codechecker`, `phpdoc`, `savepoints`,
`validate`, `mustache`, `grunt`, `phpunit` und `behat` brachen ab, bevor sie irgendetwas
geprüft hatten. Das ist der Grund, warum der erste Lauf so breit rot war und gleichzeitig so
wenig aussagte.

### Ursache 2 — `--branch development` existiert nicht

`moodle-qbehaviour_stackmathgame` hat nur `main`. `add-plugin` scheitert hart auf einer
fehlenden Referenz statt auf den Default zurückzufallen; das riss die gesamte PHPUnit- und
Behat-Matrix mit. Auf `main` gesetzt, mit Begründung im Workflow-Kommentar.

### Ursache 3 — die gesamte Codebasis hatte CRLF

67 PHP-Dateien, dazu JS, SVG, Markdown und XML. PHPCS meldete daraufhin **291 Fehler**:
67-mal `LineEndings` plus 201-mal „Boilerplate comment wrong line", weil der Sniff Kopfzeilen
zählt und CRLF sie verschiebt. Das war schon vor dieser Sitzung rot — es kam nur nie zur
Anzeige, weil `codechecker` an Ursache 1 abbrach.

Alles auf LF normalisiert (130 Dateien) und eine `eol=lf`-Regel in `.gitattributes` ergänzt.
Ohne die holt ein Windows-Checkout die CRLF zurück und die CI wird rot aus einem Grund, den
niemand im Diff sieht.

### Ursache 4 — neun Capabilities ohne Sprachstring

`validate` wäre daran gescheitert, sobald es überhaupt gelaufen wäre. EN und DE nachgetragen.

### Ursache 5 — der `stale-files`-Gate hatte recht

`.github/workflows/moodle-ci.yml`, `amd/src/fantasy_quiz.js` und zwei Build-Artefakte liegen
noch im Repository. **Muss Ralf von Hand löschen** — ein ZIP-Overlay fügt hinzu und
überschreibt, löscht aber nie. Genau dafür existiert `db/removed_files.txt`.

### Was erst *im Baum* sichtbar wurde

Die Doku behauptet, `moodle.PHPUnit.*` schweige außerhalb eines Moodle-Baums. Das hat sich
bestätigt — beide Sniffs traten erst auf, nachdem ich das Plugin nach `moodle/local/` kopiert
hatte:

* **Alle 20 Testklassen hatten den falschen Namespace.** `local_stackmathgame\tests\unit`
  bildet auf `tests/tests/unit` ab; erwartet ist `local_stackmathgame\unit`.
* **`tests/behat/behat_local_stackmatheditor.php` gehört zu einem anderen Plugin.** Klasse
  `behat_local_stackmatheditor`, `@package local_stackmatheditor`, fünfzehn Schritte für einen
  MathQuill-Editor, den dieses Plugin nicht ausliefert — inklusive `set_config()`-Aufrufen gegen
  die fremde Komponente. Keine einzige Feature-Datei hier referenzierte einen dieser Schritte.
  Entfernt und in `db/removed_files.txt` eingetragen.
* `capability_test` hatte keine Coverage-Angabe. Gegenstand ist `db/access.php`, das keine
  Klasse deklariert — jetzt explizit `@coversNothing`, denn eine annotationsfreie Testklasse ist
  von einer vergessenen Annotation nicht zu unterscheiden.

### Weitere echte Befunde

* **PHPCPD:** 40 Zeilen doppelt in `db/upgrade.php` (Schritte 2026032832 und 2026032840). In
  `db/upgradelib.php` als `local_stackmathgame_upgrade_questionmap_cmid()` extrahiert. Sicher,
  weil die Routine durchgängig idempotent ist. Gefährlich war die Kopie, nicht die Länge: eine
  Korrektur an einer Stelle lässt die andere falsch, und nur Instanzen, die über die jeweils
  andere Version aktualisieren, merken es.
* **PHPDoc:** `ensure_default()` dokumentierte einen von zwei Parametern; zweimal wurde `@group`
  bzw. `@covers` mitten im Fließtext als Inline-Tag gelesen.
* **ESLint (Moodle-Konfiguration, `--max-lint-warnings 0`):** 15 Warnungen. Statt den
  Schwellwert zu senken behoben: reservierte Wörter als Objektschlüssel quotiert, Ausrichtungs-
  Leerzeichen entfernt, fehlende `return` in `then()`-Callbacks ergänzt, die verschachtelte
  Promise-Kette in `handleGameCheck()` flachgezogen (die Verschachtelung existierte nur, um
  `response` im Scope zu halten — eine Variable in der äußeren Funktion tut dasselbe), und
  `ui_renderer.render()` mit zyklomatischer Komplexität 23 in drei Helfer zerlegt.
* **`MOODLE_INTERNAL` in `db/upgradelib.php`** war überflüssig: die Datei deklariert nur eine
  Funktion, hat also keine Seiteneffekte.

### AMD-Build erledigt

Moodle 4.5 geklont, `npm install`, `grunt amd` für das Elternplugin **und** alle drei
Mode-Subplugins. Die Artefakte liegen aktualisiert im Patch. Damit ist der Blocker weg, den ich
seit Patch 03 mitschleppe — Patch 03 und 04 sind im Browser ab sofort wirksam.

### Verifiziert, nicht behauptet

| Gate | Ergebnis |
|---|---|
| phplint | PASS |
| phpcpd | PASS |
| phpmd | PASS |
| codechecker | PASS (vorher 291 Fehler) |
| phpdoc | PASS |
| validate | PASS |
| savepoints | PASS |
| mustache | PASS |
| grunt (Build + ESLint) | PASS |
| **PHPUnit** | **134 Tests, 413 Assertions, 0 Fehler, 9 übersprungen** |
| Jest | 16 Tests, grün (auch in Ralfs CI-Lauf) |
| Release-Artefakt | 262 Einträge, keine Entwicklerwerkzeuge |

Alle 25 Tests aus den Patches 02–04 laufen: `prerequisite_checker` (7), `requires_behaviour` (4),
`navigation_resolver` (8), `design_assets` (6).

### Ehrlich zu den Lücken

Die neun Skips sind Platzhalter und Bridge-Tests, die `block_xp` bzw. `block_stash` verlangen.

**Der Testbaum hat die harten Abhängigkeiten nicht.** `qtype_stack`,
`qbehaviour_stackmathgame` und `filter_shortcodes` fehlen, und die PHPUnit-Initialisierung hat
das Plugin trotzdem installiert. Für `prerequisite_checker` heißt das: die drei
Plugin-Präsenz-Prüfungen melden hier `error`, `is_playable()` ist immer `false`. Die Tests
bestehen trotzdem, weil sie gezielt einzelne Prüfschlüssel abfragen — aber **der Positivfall von
`is_playable()` ist damit ungetestet**. Das gehört zu Issue #5 nachgeholt, wenn Maxima und die
Abhängigkeiten in der CI stehen.

**Behat ist nicht gelaufen** — braucht Selenium und Chrome. Die Feature-Dateien bestehen
`gherkinlint` als Teil von `grunt`, mehr nicht.

---

## Patch 06 — Issue #1, Teil 1: Regiekarten-Oberfläche (abgeschlossen, verifiziert)

Entscheidung (c) umgesetzt: eigene Unterseite `flow.php?cmid=…`. **Kein React.** Für eine
Tabelle mit zehn bis dreißig Zeilen und ein Formular pro Zeile bringt es nichts, kostet aber eine
zweite Build-Kette — die makefile-Targets `lint-react` und `test-react` existieren zwar, aber
nichts sonst im Plugin nutzt sie, und ein ungenutzter Toolchain-Zweig verrottet.

### Warum eine eigene Seite

Zwanzig Slots mit je fünf Abschnitten würden das Einstellungsformular unbenutzbar machen. Die
beiden Aufgaben sind außerdem verschieden: Einstellungen wählt man einmal, den Ablauf bearbeitet
man wiederholt, während ein Test Gestalt annimmt.

### Kein paralleles Datenmodell

Alles liest und schreibt `local_stackmathgame_questionmap.configjson` über
`slot_config_schema`. Beim Speichern geht die Konfiguration durch `parse()` und wird erst dann
validiert — geprüft wird also exakt das, was gespeichert wird. Etwas anderes zu validieren als
zu speichern ist genau der Weg, auf dem beide auseinanderlaufen.

### Was gebaut wurde

```
classes/local/service/flow_service.php   (neu) Lesen, Schreiben, Bulk, Erreichbarkeitsanalyse
classes/form/slot_config_form.php        (neu) Die Regiekarte
classes/output/flow_list.php             (neu) Renderable der Slot-Liste
templates/flow_list.mustache             (neu)
flow.php                                 (neu) Liste, Editor, Sync, Bulk
quiz_settings.php                        Link auf den Ablauf
lang/en|de/local_stackmathgame.php       68 neue Strings je Sprache (248 gesamt)
tests/unit/flow_service_test.php         (neu) 11 Fälle
tests/behat/flow_editor.feature          (neu) 6 Szenarien
tests/behat/behat_local_stackmathgame.php  Page-Resolver für den Ablauf
```

### Drei Entscheidungen mit Begründung

* **Bulk-Anwendung ist partiell.** Nur ausgefüllte Felder werden übernommen. Ein
  Komplett-Überschreiben würde beim Setzen eines Szenentyps auf zwanzig Slots zwanzig Narrative
  löschen — genau das, was man beim Massenbearbeiten am wenigsten erwartet.
* **Erreichbarkeitsanalyse als eigene Prüfung.** Unerreichbare Slots und Sackgassen sind
  Autorenfehler, bei denen *keine einzelne Regiekarte falsch ist*. Jede ist für sich gültig; erst
  der Graph zeigt das Problem. Ohne die Analyse zeigt es sich als feststeckende Spielende
  mitten im Test — die teuerste Stelle, es zu finden.
* **Der letzte Slot bei linearer Verzweigung ist ein Ende, keine Sackgasse.** Ihn zu melden
  hieße, die Warnung bei jedem korrekt konfigurierten Test auszulösen, und eine Warnung, die
  immer erscheint, liest niemand.

### Akzeptanzkriterien aus Issue #1

Erfüllt: Slot-Liste in Quiz-Reihenfolge mit Slotnummer, Titel, Fragetyp und Question-ID;
STACK-Kennzeichnung; explizite und automatische Synchronisierung; keine verwaisten Regiekarten;
Szenentyp, Narrative, alle vier Verzweigungen, Score/XP, Achievement-Schlüssel und Display-Flags
editierbar; Zielslot-Auswahl nur aus tatsächlich existierenden Slots; `mode = end` verlangt kein
Ziel; serverseitige Validierung über `slot_config_schema`; kein JSON von Hand; Sackgassen- und
Erreichbarkeitsprüfung; erreichbar aus den Spieleinstellungen; Defaults auf mehrere markierte
Slots anwendbar.

**Offen aus dem Issue:** die Einbindung vorhandener Stash-/Achievement-Mappings ist nur zur
Hälfte da. Achievement-Schlüssel sind editierbar, die Stash-Zuordnung liegt weiterhin im
bestehenden Formular unter `stashmapping_*`. Zusammenführen wäre sinnvoll, gehört aber in
Teil 2.

### Nebenbefund

`question_map_service::ensure_for_cmid()` bewahrte vorhandenes `configjson` bereits korrekt und
löschte verwaiste Zeilen — das war kein Mangel, sondern ungetestet. Jetzt durch zwei Fälle
abgesichert, darunter ein doppelter Rebuild hintereinander.

### Verifiziert

| Gate | Ergebnis |
|---|---|
| phplint, phpcpd, phpmd | PASS |
| codechecker, phpdoc, validate, savepoints, mustache | PASS |
| grunt (Build + ESLint) | PASS |
| **PHPUnit** | **145 Tests, 455 Assertions, 0 Fehler, 9 Skips** |

Die elf neuen `flow_service`-Tests liefen im ersten Anlauf grün. PHPCS meldete beim ersten
Durchgang 20 Formatierungsfehler an mehrzeiligen Kontrollstrukturen, die `phpcbf` behoben hat,
plus Bannerkommentare (`// -----`), die an „Inline comments must end in full-stops" scheitern —
bei `--max-warnings 0` ein Fehlschlag. Entfernt; die Abschnittsüberschriften darunter tragen die
Bedeutung ohnehin.

**Behat weiterhin nicht ausgeführt** — die sechs neuen Szenarien sind ungeprüft.

---

## Patch 07 — CI-Lauf 2: die eigentliche Ursache (abgeschlossen, verifiziert)

### Was wirklich passiert ist

Drei Jobs rot, mit drei völlig verschiedenen Fehlermeldungen — und alle drei waren Folgefehler
**einer** Ursache:

```
Mustache Lint:  Could not load core_component from dirroot: <workspace>
Grunt:          Unable to find Gruntfile / Failed to find <workspace>/version.php
PHPUnit:        <workspace>/vendor/bin/phpunit: not found
```

Die Ursache steht im Install-Schritt darüber:

```
sh: 1: maxima: not found
* line 96 of /question/type/stack/db/install.php: coding_exception thrown
* line 494 of /lib/installlib.php: call to upgrade_noncore()
```

**`qtype_stack` führt in seiner `install.php` einen echten CAS-Healthcheck aus.** Ohne
Maxima-Binary wirft es eine `coding_exception`, `install_database.php` bricht ab — und damit
schreibt `moodle-plugin-ci install` nie `MOODLE_DIR` in die Job-Umgebung. Die Option `--moodle`
fällt dann auf `getenv('MOODLE_DIR') ?: '.'` zurück, also auf das Workspace-Root. Jeder folgende
Schritt sucht Moodle an der falschen Stelle und meldet etwas, das mit der Ursache nichts zu tun
hat.

Der `quality`-Job blieb grün, weil er `--no-init` verwendet: ohne Datenbankinstallation läuft
`qtype_stack/db/install.php` nie.

### Behoben

* **Maxima wird installiert**, in jedem Job, der eine vollständige Moodle-Installation macht
  (PHPUnit ×3, Behat, Coverage) sowie in den Playwright- und Lastworkflows.
  **Lokal verifiziert:** mit `maxima maxima-share gnuplot-nox` installiert `qtype_stack` sauber
  (`qtype_stack|2026080600` in `phpu_config_plugins`), und die vollständige Abhängigkeitskette
  steht.

* **Prüfbefehle nach dem Install zeigen jetzt auf `moodle/local/stackmathgame`.** Das war mein
  Fehler aus Patch 05: dort hatte ich pauschal `plugin` an *jeden* Befehl gehängt. Für Jobs ohne
  Install ist das richtig, für Jobs danach falsch — `install --plugin ./plugin` kopiert das
  Plugin in den Moodle-Baum, und ein Prüflauf gegen das ursprüngliche Checkout ist genau der
  „grün außerhalb des Baums"-Fall, vor dem die Doku warnt. Die eigentliche Ursache in Lauf 1 war
  nicht das fehlende Argument, sondern der abgebrochene Install (falscher Branch), der die
  Umgebung nie geschrieben hat.

* **`codeanalysis` lief ohne Moodle-Baum.** Damit waren `moodle.PHPUnit.TestCaseNames` und
  `TestCaseCovers` dort stumm — der Job meldete grün, während beide Sniffs nichts prüften. Genau
  so überlebten zwanzig falsche Test-Namespaces. Der Job bekommt jetzt denselben leichten
  `--no-init`-Install wie `quality` und prüft im Baum.

### Zur Frage nach js/mustache/jest

**Nichts davon wurde entfernt.** Die Schritte stehen im Log des Laufs als
`9_Mustache Lint`, `10_Grunt (AMD build + ESLint)` und `11_Jest (AMD unit tests)`.
Jest lief durch: 16 Tests grün. Mustache und Grunt scheiterten an der oben beschriebenen
Folgekette — ihre Meldungen („kein Gruntfile", „core_component nicht ladbar") lesen sich, als
sei der Schritt selbst falsch konfiguriert, deshalb der Eindruck. Verifizierbar in
`PHPUnit _ MOODLE_405_STABLE _ PHP 8.2 _ pgsql/` des hochgeladenen Log-Archivs.

### Lücke aus Patch 05 geschlossen

Mit installierbarem `qtype_stack` ist der **Positivfall von `is_playable()`** endlich testbar
und getestet. Bis dahin waren alle acht Fälle in `prerequisite_checker_test` Ablehnungen — ein
Prüfer, der alles ablehnt, hätte sie sämtlich bestanden. Der Test überspringt sich sauber, wenn
`qtype_stack` fehlt.

### Verifiziert

Alle neun Gates PASS. **146 Tests, 454 Assertions, 0 Fehler, 9 Skips** — diesmal gegen einen
Baum mit vollständigem Abhängigkeitsstapel: `qtype_stack`, `qbehaviour_adaptivemultipart`,
`qbehaviour_stackmathgame` und `filter_shortcodes` installiert.

### Weiterhin offen

`tests/behat/behat_local_stackmatheditor.php` liegt noch im Repository — die anderen vier
Dateien hast du bereits gelöscht, diese fehlt noch.

---

## Patch 08 — CI-Lauf 3: Moodle 5.1-Layout und die ersten echten Behat-Ergebnisse

Erstmals ist Behat wirklich gelaufen: **25 Szenarien, 14 grün, 11 rot.** Das ist die
Risikoposition, die ich seit Patch 02 mitschleppe — jetzt mit Befunden statt Vermutungen.

### PHPUnit: nur Moodle 5.2 rot

4.5 und 5.0 waren grün, 5.2 scheiterte an

```
Failed to run realpath('moodle/local/stackmathgame')
```

**Moodle 5.1 hat den Webroot nach `public/` verschoben.** Dort liegt das Plugin unter
`moodle/public/local/stackmathgame`. Mein hartkodierter Pfad aus Patch 07 war für 4.5 und 5.0
richtig und für 5.2 falsch.

`moodle-plugin-ci` kennt das Layout bereits (`Moodle::getPublicDirectory()` prüft auf
`public/version.php`) und exportiert den installierten Pfad als `PLUGIN_DIR`. Alle Prüfbefehle
nach dem Install nutzen jetzt `"$PLUGIN_DIR"` — versionsunabhängig und ohne Annahme über das
Layout. Die Lehre: wo das Werkzeug einen Pfad exportiert, ist Selberbauen immer falsch.

### Behat: vier Ursachen

**1. Das Frageverhalten ist nicht archetypal — der schwerste Befund.**

```
Coding error detected: The requested behaviour is not actually an archetypal one.
* line 187 of /question/engine/lib.php
```

`qbehaviour_stackmathgame_type` überschreibt `is_archetypal()` nicht, und die Basisklasse liefert
`false`. Das hat zwei Folgen, von denen keine auf die Ursache zeigt:

* `question_engine::get_archetypal_behaviours()` baut die Auswahlliste „Frageverhalten" im
  Quiz-Formular. Ein nicht-archetypales Verhalten fehlt dort — **Lehrende können STACK Math Game
  gar nicht auswählen.**
* Gelangt der Wert anders in `quiz.preferredbehaviour`, wirft
  `make_archetypal_behaviour()` beim Start eines Versuchs.

Der Fehler liegt in `moodle-qbehaviour_stackmathgame`, nicht hier. Patch beiliegend als
`docs/patches/qbehaviour_stackmathgame_archetypal.patch` (drei Zeilen). Lokal angewendet und
verifiziert: danach erscheint `stackmathgame` in `get_archetypal_behaviours()`.

Damit das nicht wieder erst mitten in einem Versuch auffällt, prüft `prerequisite_checker` es
jetzt und nennt im Panel die fehlende Methode beim Namen. Ein Unit-Test sichert es ab und
überspringt sich sauber, wenn das Verhaltens-Plugin fehlt.

**2. `Session->wait()` ohne JS-Treiber.** Der Schritt „I am on the Moodle homepage" rief
`wait()` auf, was nur der JS-Treiber implementiert. Zwei Platzhalter-Szenarien scheiterten
deshalb an einer Treiber-Beschwerde statt an ihrem Gegenstand. Ersetzt durch
`wait_for_pending_js()`, das den Treiber selbst prüft.

**3. `"2" "table_row"` war ein zu schwacher Locator.** Er trifft jede Zeile, deren Text eine „2"
enthält — und die Question-ID in der Zeilenunterschrift enthielt eine. Das Szenario bearbeitete
Slot 1 und prüfte dann Slot 2. Jetzt über den Fragetitel adressiert, der pro Zeile eindeutig ist.

**4. Vier Selects mit identischem Label „Target slot".** Für Behat mehrdeutig, für einen
Screenreader ebenso — und es verdeckte eine echte Lücke: ein Szenario konnte immer nur eines der
vier Ziele setzen, die übrigen Sprünge wurden ohne Ziel gespeichert. Das Label enthält jetzt den
Ausgang: „Target slot (After a correct answer)".

Außerdem meinen eigenen Testfehler korrigiert: das Unerreichbarkeits-Szenario leitete nur
`gradedright` und `default` um und ließ `complete` linear, sodass Slot 2 weiterhin erreichbar
war. Die Analyse hatte recht, das Szenario war falsch.

**Und drei Altlasten in `quiz_game_settings.feature`:** sie folgten einem Link „Game settings"
auf der Quiz-Bearbeitungsseite, den das Plugin dort nicht anlegt (die Einstellungen hängen in der
Modul-Navigationsleiste), und ein Szenario prüfte den **deutschen** Text „Spieleinstellungen" auf
einer englischen Testsite — das konnte nie zutreffen. Auf den Page-Resolver umgestellt; das
Dropdown-Szenario ist jetzt korrekt als `@javascript` markiert, weil der Eintrag per AMD injiziert
wird.

### Verifiziert

Alle neun Gates PASS. **147 Tests, 459 Assertions, 0 Fehler, 9 Skips** — gegen einen Baum mit
vollständigem Abhängigkeitsstapel und gepatchtem Verhaltens-Plugin.

Behat konnte ich hier weiterhin nicht ausführen (Selenium/Chrome fehlen). Die elf Korrekturen
sind aus den Logs und dem Quelltext abgeleitet, nicht durch einen grünen Lauf bestätigt.

---

## Patch 09 — Mein Fehler, dreimal in Folge, und die tatsächlich fehlende JS-Prüfung

### Alle sechs Fehlschläge dieses Laufs hatten dieselbe Ursache

```
In CodeParser.php line 37:
  Failed to find '<workspace>/version.php' file.
```

`"$PLUGIN_DIR"` war leer. **`moodle-plugin-ci` schreibt `PLUGIN_DIR` und `MOODLE_DIR` in seine
eigene `.env`-Datei neben der Binärdatei, nicht nach `$GITHUB_ENV`.** In einer `run:`-Zeile
expandiert die Variable deshalb zu nichts, und ein leeres Positionsargument löst auf das
Arbeitsverzeichnis auf.

`AbstractPluginCommand::configure()` macht das Argument genau dann optional, wenn `PLUGIN_DIR`
gesetzt ist, und nimmt es als Vorgabe. **Nach einem `install` ist der richtige Aufruf also gar
kein Argument** — und diese Vorgabe ist auch unter Moodle 5.1+ mit `public/`-Layout korrekt.

Damit war die ursprüngliche Template-Fassung die ganze Zeit richtig, und ich habe sie dreimal
hintereinander verschlimmbessert:

| Patch | Was ich tat | Warum es falsch war |
|---|---|---|
| 05 | `plugin` an *jeden* Befehl gehängt | Die echte Ursache war der abgebrochene Install (falscher Branch), nicht ein fehlendes Argument |
| 07 | auf `moodle/local/stackmathgame` festgenagelt | Bricht unter Moodle 5.1+, wo das Plugin in `public/local/` liegt |
| 08 | `"$PLUGIN_DIR"` eingesetzt | Die Variable existiert in der Shell nicht |

Dreimal dasselbe Muster: einen Pfad selbst konstruieren, wo das Werkzeug ihn längst kennt. Jetzt
steht in `ENTWICKLUNGSUMGEBUNG.md` ausdrücklich, wann das Argument gehört und wann nicht — und
dass man es, wenn man es in einem Shell-Schritt wirklich braucht, aus `ci/.env` zurückliest.

### Die JS-Prüfung der Subplugins fehlte tatsächlich

Ralf hat mehrfach danach gefragt, und der Einwand war berechtigt. Ich hatte es für erledigt
gehalten, weil ein Grunt-Schritt in der Pipeline steht. Er deckt die Mode-Subplugins aber nicht
ab.

**Nachgewiesen statt vermutet:** ein absichtlich fehlerhaftes `mode/rpg/amd/src/game.js`
eingebaut und den Eltern-Grunt laufen lassen — Ergebnis „Done.". Direkt auf dem Subplugin
gestartet, meldete derselbe Befehl acht ESLint-Warnungen, die die CI nie gesehen hatte.

Grunt bestimmt die Komponente aus dem Verzeichnis, in dem es läuft. Das steht seit Patch 01 in
der Doku und im makefile — nur in der CI stand es nicht. Behoben:

* Neuer Schritt in beiden Pipelines, der über `mode/*/` iteriert. Er löst den Plugin-Pfad aus
  `ci/.env` auf, weil `$PLUGIN_DIR` in der Shell eben nicht existiert.
* **Verifiziert, dass der Schritt anschlägt:** mit einem absichtlich fehlerhaften
  `mode/wisewizzard/amd/src/game.js` schlägt er fehl und nennt das Subplugin.
* Die acht bestehenden Warnungen in `mode/rpg` behoben (Ausrichtungs-Leerzeichen, kleingeschriebene
  Kommentaranfänge, ein leerer `catch`-Block ohne Begründung).
* Dabei fiel ein veraltetes `mode/rpg/amd/build/game.min.js.map` auf — genau der Fehler, den die
  Doku als teuersten in diesem Projekt beschreibt. Neu gebaut.

### Verifiziert

Alle neun Gates PASS, mit den **blanken** Aufrufen, die die CI jetzt verwendet. Zusätzlich alle
drei Mode-Subplugins einzeln: PASS. **147 Tests, 459 Assertions, 0 Fehler.** Jest 16 grün.

---

## Offene Risiken

* **Maxima in der CI.** Ohne funktionierende Maxima-Anbindung überspringen sich STACK-Testfälle
  und melden trotzdem „passed". Für Issue #5 ist das der teuerste Posten im Plan; bis dahin muss
  jede Skip-Meldung ehrlich protokolliert werden.
* **Sitzungen 001 und 002** fehlen im Repository (siehe Nebenbefund oben).
* **`qbehaviour_stackmathgame` muss gepatcht werden** (`docs/patches/`), sonst ist das Spiel
  grundsätzlich nicht startbar und in der Quiz-Auswahl nicht sichtbar.
* **Behat-Korrekturen sind nicht durch einen grünen Lauf bestätigt**, nur aus den Logs abgeleitet.
* **Vier veraltete Dateien** müssen im Repository von Hand gelöscht werden.
* **Schwellenwerte der Lasttests** (`p95 < 2s`, `http_req_failed < 1%`) sind ein Startwert, kein
  gemessenes Ziel. Sie müssen nach dem ersten echten Lauf auf repräsentativer Hardware
  nachgezogen werden.

---

## Reward-Logik

Diese Sitzung hat Submit-, Profil- oder Reward-Code bisher **nicht** verändert. Die
At-most-once-Garantie wurde daher nicht neu unter Parallelität getestet. Sobald Issue #5
angefasst wird, ist das hier ausdrücklich zu vermerken.
