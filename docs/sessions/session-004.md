# Session 004 — Issues #12 bis #16 und der rote Behat-Lauf

**Status:** abgeschlossen
**Version:** 2026090250
**Ausgangsstand:** `develop` bei `f6ab4c6` (= Auslieferung 2026090248)

---

## Ausgangslage

Der CI-Lauf zu 2026090248 war bis auf ein Behat-Szenario grün. Dazu fünf neue Issues vom
31.10.2026 (#12–#16). Die Testumgebung musste neu aufgebaut werden; der Container der vorigen
Sitzung war verworfen.

Für dieses Repository fordert die Sitzung keine Schreibrechte an. Issues werden über die
öffentliche Seite gelesen; ausgeliefert wird als ZIP.

## Behat: „Check“ nicht klickbar

`branch_navigation.feature` drückte `"Check"`. Gefunden wurde STACKs eigener Prüfen-Knopf, den
das Spiel ausblendet, nicht der Knopf des Spiels („Check answer“). Die Szenarien drücken jetzt
diesen.

## #12 — Release-Gates

`maturity-gate` und `ecosystem-lockstep` sind aus `moodle-plugin-ci-main.yml` entfernt.
`ci-complete` fasst nur noch `test`, `release-artefact`, `coverage` und `stale-files` zusammen;
„skipped“ zählt nicht als grün. Release-Archiv und Coverage bleiben, weil sie echten Code prüfen.

## #13 — „Spiel-Einstellungen“ unter Test → Mehr

Ursache: `local_stackmathgame_extend_settings_navigation()` las die Aktivität aus `$PAGE->cm`.
Während Moodle die Sekundärnavigation baut, ist das noch leer; die Funktion kehrte auf jeder
Quizseite vorzeitig zurück. Jetzt wird die Aktivität aus dem übergebenen Kontext bestimmt.
Lokal verifiziert für Admin und Lehrperson auf einem normalen Quiz ohne Spielverhalten.
Behat (`quiz_nav.feature`) folgt jetzt dem Klickpfad über „Mehr“, auch mobil, und prüft, dass
Studierende den Eintrag nicht sehen.

## #14 — Einstellungen zuverlässig speichern

Neu: `game\quiz_settings_service` mit `current_values()`, `validate()` und `save()`; das Formular
und die Seite rufen dieselben Regeln auf.

* Ein eingegebener Label-Name hat Vorrang vor der Auswahl (das war der gemeldete Fehler).
* Gleicher Name wie ein aktives Label (ohne Groß/Klein) → Beitritt; wie ein archiviertes →
  Ablehnung.
* Längen (100/255), reine Leerzeichen, ungültige oder archivierte Referenzen → Feldfehler.
* `idnumber` auch für nicht-lateinische Namen gültig (Hash-Fallback), immer ≤ 100 Zeichen.
* Label und Konfiguration in einer Transaktion; bei Unique-Konflikt einmal wiederholt.
* Aktivierung wird gegen das **eingereichte** Design geprüft; Entwurf ohne Design speicherbar.
* Rechte serverseitig: Label anlegen nur mit `managelabels`, Design ändern nur mit `selectdesign`.
* Öffnen und Abbrechen schreiben nichts; widersprüchliche `cmid`/`quizid` werden abgelehnt; der
  Löschzweig ohne Rechteprüfung ist entfernt.
* Label-Auswahl über Server-Suche (`local_stackmathgame_search_labels`, 50 je Abfrage).
* Design-Vorschau löst wie die Laufzeit auf (`theme_manager::preview_url()`).
* Eindeutiger Index auf `local_stackmathgame.cmid` auch für frische Installationen, Upgrade mit
  Bereinigung von Duplikaten.

Nebenwirkung, im E2E aufgefallen: Ohne gespeicherte Konfiguration meldete das
Voraussetzungs-Panel „kein Design“. Das Panel bewertet jetzt das vorausgewählte Design.

## #15 — RPG verdeckt Feedback und beschneidet Eingaben

Die Felder wanderten bisher in eine 27 % breite Spalte der Szene mit `overflow:hidden`; Toolbar
und Gleichungsketten wurden abgeschnitten, `.outcome` blieb mit `.que` versteckt.

Jetzt stehen Frage, Eingaben (mit oder ohne Editor), Validierung, „Check answer“ und Moodles
Outcome zusammen im Pergament-Panel, das Outcome in einer `aria-live`-Region. Die Szene ist reine
Bühne. Pfeile tragen sichtbare Beschriftungen und nennen den Sperrgrund; gesperrte Pfeile bleiben
per Tastatur erreichbar (`aria-disabled`). Fehlende Grafiken werden in der Konsole mit Schlüssel
und einmal sichtbar gemeldet, damit Platzhalter-Design und kaputtes Paket unterscheidbar sind.

**Abweichung vom Original:** Die Eingabe schwebt nicht mehr in der Szene. Das ist die Bedingung
dafür, dass nichts abgeschnitten wird.

## #16 — Verlassen-Dialog beim Szenenwechsel

Ursache: `mod/quiz/module.js` lässt `core_form/changechecker` `#responseform` überwachen. Das Spiel
speichert per Webservice, das Formular blieb „geändert“, und der Pfeil zur nächsten Szene löste
`beforeunload` aus.

Die Engine setzt den Zustand mit `resetFormDirtyState()` zurück, sobald der Server die Antwort
gespeichert hat **und** die Frage den gespeicherten Stand zeigt. Danach geänderte Eingaben gelten
wieder als ungespeichert. Dazu: kein zweiter Submit während eines laufenden; gescheiterte
Folgeaufrufe blockieren den Weg nicht mehr; der native Fallback sendet das Formular ab, statt
neu zu laden.

Der E2E-Lauf zählt jetzt jeden `beforeunload`-Dialog: keiner nach gespeicherter Antwort, genau
einer beim Verlassen mit ungeprüfter Änderung.

## Was die E2E-Läufe zusätzlich fanden

* **HTTP 500 beim ersten Öffnen der Settings eines neuen Quiz** — von mir eingeführt: Die
  Design-Übersteuerung im Prüfer baute ohne gespeicherte Konfiguration ein Objekt ohne
  `requiresbehaviour`. Behoben, Regressionstest ergänzt.
* **Die Voraussetzungsprüfung schrieb.** „Eine Frage pro Seite“ las die Quizstruktur über
  `flow_service::get_slots()`, das vor dem Lesen die Fragenkarte synchronisiert und dabei eine
  Konfiguration anlegt. `get_structure()` liest die Slotnummern jetzt direkt aus dem Quiz; damit
  schreibt das Öffnen der Settings wirklich nichts mehr.
* **Testhelfer mit Timing-Fehlern:** „Expand all“ wurde geklickt, bevor Moodles Formularskript den
  Knopf verdrahtet hatte; die Dateiauswahl lud hoch, bevor die Datei im Feld lag. Beide Helfer
  prüfen jetzt das Ergebnis und wiederholen.
* **Moodle-Core-Meldungen, nicht dieses Plugin** (aktueller 4.5-Build 20261030): ein HTTP 400 auf
  die noch nie gesetzte Fragenbank-Präferenz `qbank_managecategories_includesubcategories_filter_default`
  und ein ungefangener Fehler mit der Meldung `'')` beim Hinzufügen von Fragen auf
  `mod/quiz/edit.php`. Gegenprobe: mit abgeschaltetem Plugin-Skript auf dieser Seite bleibt der
  Fehler. Genau diese zwei Meldungen sind im Fehler-Sammler ausgenommen, jeweils nur auf ihrer
  Seite bzw. URL.

## Offen

* #14 AK17/AK18 (Tastatur, schmale Layouts der Design-Kacheln) nur durch Moodles Standardformular
  abgedeckt, nicht eigens getestet.
* #16 verlangt Firefox-Abdeckung; lokal lief nur Chromium.
* Behat lief lokal nicht (kein Selenium); die CI ist hier maßgeblich.
