# Session 006 — CI (#18) und die Browser-Journeys für alle drei Spiele

**Status:** abgeschlossen (Ergebnisse unten; offene Punkte am Ende)
**Version:** 2026090252 (Release bleibt 0.9.0)
**Ausgangsstand:** `develop` bei `55958df` (= 2026090251)

---

## Auftrag

1. Codebase neu aus `develop` ziehen, CI reparieren (Folgeaudit vom 02.11.2026, Paket G01 / #18).
2. In der Playwright-Journey neben dem RPG auch einen **Exit-Game-** und einen **Mentor-Strang**.
3. In allen drei Abläufen jede Aktion **ausschließlich über die Oberfläche**, nie per URL oder
   ähnliche Abkürzung. **Anmelden, Umloggen und das Öffnen des Kurses** sind Systemfunktionen,
   nicht über die Oberfläche und nicht Teil des Tests.

## Die rote CI (G01 / #18)

| Befund | Ursache | Behebung |
|---|---|---|
| Code-Checker: 3 Fehler, 1 Warnung in `e2e/support/smg-e2e.php` | Pfadauflösung vor `config.php` (globaler Zustand), zwei mehrzeilige Kontrollstrukturen, eine zu lange Zeile | Pfad im `require`-Ausdruck selbst aufgelöst; Liste in eine Variable; Zeile umbrochen |
| Collaboration: RPG-Journey ohne Seed entdeckt | `playwright.config.js` sucht in `.`, also auch in `e2e/` | `testIgnore: ['e2e/**']` |
| Asset-Test erst im Retry grün | wartete auf sichtbares `.que`, das das RPG absichtlich versteckt | wartet auf die sichtbare Spieloberfläche des jeweiligen Modus |
| Settings-Test zeitweise nur im Retry grün | `loginAs()` wartete nicht auf das Login; der nächste Aufruf kam ohne Sitzung | wartet, bis die Anmeldung angekommen ist |
| Barrierefreiheit Settings zeitweise nur im Retry grün | axe prüfte, während Moodles Autocomplete noch initialisierte (leere Listbox) | prüft erst nach Abschluss von Moodles Skripten |

Die beiden letzten standen nicht im Audit; sie traten bei wiederholten Läufen auf. Nachweis: fünf
Läufe der Collaboration-Suite hintereinander, jeweils **6/6 im ersten Versuch**.

## Drei Spiele, nur über die Oberfläche

`e2e/rpg-full-ui.spec.js` ist ersetzt durch `e2e/game-full-ui.spec.js`: ein Test je Spiel (RPG,
Exit-Game, Digital Mentor), jeder mit eigenem Kurs. `SMG_GAMES` wählt die Spiele, Standard: alle drei.

**Systemfunktionen** (`e2e/support/system.js`, nicht unter Test):
* `signIn()` — Login per HTTP in den Cookie-Speicher des Browserkontexts; ersetzt auch das Umloggen.
* `openCourse()` — Kursseite über ihre Adresse.

**Oberflächenwege** (`e2e/support/ui.js` und die Autorenhelfer):

| Schritt | Weg |
|---|---|
| Fragen importieren | Kurs → Mehr → Question bank → Auswahlliste „Import“ (bestätigt mit Enter, wie per Tastatur) |
| Quiz anlegen | Bearbeiten-Schalter → „Add an activity or resource“ (Fokus + Enter) → Quiz → Formular |
| Fragen hinzufügen | Quiz → Reiter „Questions“ → Add → from question bank |
| Voraussetzungen, Aktivieren, Design | Quiz → Mehr → Game settings |
| Szenen konfigurieren | Game settings → „Game flow“ → je Zeile „Edit“; Rückweg über „Cancel“ |
| Designpaket (optional) | Studio-Symbol in der Kopfleiste → Reiter „Import“ |
| Spielen | Kurs → Quiz anklicken → „Attempt quiz“ → Spiel bis „Finish the run“ → Summary → Abgabe |

Neu: `games/exitgames.js` (Design „ExitGame Default“, Endszene `boss`, keine Zähler, Weiter-Knopf
`a.smg-eg-next`). Der Mentor nutzte das vorhandene `games/wisewizzard.js`.

`.github/e2e-seed.php` legt jetzt einen Kurs je Spiel an (sonst bietet die Fragenauswahl im zweiten
Spiel jede Frage doppelt an) und schaltet Moodles Einführungstouren ab (deren Overlay fing Klicks ab).

`rpg-verified-play.spec.js` folgt derselben Regel: Systemanmeldung, Quiz über den Kurs öffnen, und
der direkte Sprung zur Gleichungskette läuft über Moodles Quiz-Navigation statt über eine Adresse.

## Ein echter Produktfehler, gefunden durch die 390-px-Journey

Auf dem Handy lag das RPG-Panel (`z-index: 2`) über Boosts „Open block drawer“ (ebenfalls 2, im
DOM früher). Die Quiz-Navigation ließ sich nicht öffnen. Das Panel liegt jetzt auf 1, die Bühne
isoliert ihre Ebenen (`isolation: isolate`), damit ihre Figuren nicht nach außen durchschlagen.

## Moodle-Core-Meldungen

Die schon dokumentierte Core-Meldung `'')` des Fragenbank-Filters tritt jetzt, da die Journey
durch die Fragenbank navigiert, auch auf `question/edit.php` auf. Dort lädt das Plugin kein eigenes
Skript. Die Ausnahme im Fehlersammler gilt genau für diese Meldung auf diesen beiden Seiten.

## Nachweise (lokal, Moodle 4.5.15+ Build 20261030, PostgreSQL 16, Chromium, Maxima 5.46)

* E2E (`playwright.e2e.config.js`), Gesamtlauf am Endstand: **7/7 bestanden im ersten Versuch, 0 übersprungen**
  (drei Spiel-Journeys, vier verifizierte RPG-Fälle mit installiertem Editor 1.3), 29,9 min.
* Collaboration (`playwright.config.js`): fünf Läufe in Folge, je **6/6 im ersten Versuch**.
* Jest **55/55**. Code-Checker, PHPDoc, Validate, Savepoints, PHPCPD, PHPLint sauber; PHPMD nur die
  bekannten, nicht blockierenden Hinweise. AMD-Builds byte-identisch mit einem Neubau.
* PHPUnit lokal nicht erneut gelaufen: keine Änderung an PHP-Klassen dieser Sitzung.

## Offen

* Die CI-Läufe am abschließenden Commit (Dev-Gesamtlauf, Collaboration, E2E) stehen aus; lokal
  sind sie nicht mit einem CI-Lauf gleichzusetzen.
* Firefox, Moodle 5.x für die E2E-Journeys und die übrigen Audit-Pakete (G02–G10) sind nicht Teil
  dieser Sitzung.
* Behat lief lokal nicht (kein Selenium); unverändert seit dem grünen Dev-Lauf.
