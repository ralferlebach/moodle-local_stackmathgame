# Entwicklungsumgebung — local_stackmathgame

Einrichtung der lokalen Entwicklungsumgebung, die Qualitätsgates und die Session-Prompts.

Zielumgebung: Moodle 4.5 unter WSL/Ubuntu, erreichbar als
`http://localhost/moodle45_aliseadele`.

---

## 1. Warum diese Einrichtung aufwendiger ist als bei anderen Plugins

Drei Dinge unterscheiden dieses Plugin von einem gewöhnlichen `local_`-Plugin, und alle drei
schlagen auf die Umgebung durch:

**STACK braucht Maxima.** `qtype_stack` ist eine harte Abhängigkeit, und STACK ohne funktionierende
Maxima-Verbindung installiert zwar, bewertet aber nichts. Ein Teil der Testfälle — genau die
interessanten — ist damit nicht lauffähig, bis Maxima steht.

**Drei gebündelte Subplugins.** Unter `mode/` liegen drei eigenständige Moodle-Komponenten mit
eigener `version.php` und eigenem `amd/src/`. Grunt und ESLint bestimmen die Komponente aus dem
Verzeichnis, in dem sie laufen; ein Aufruf im Plugin-Root baut `amd/build/` und lässt
`mode/*/amd/build/` veraltet zurück. Das Ergebnis ist tückisch, weil `amd/src` dann korrekt
aussieht, während ausgeliefert ein altes Spielmodul wird. Das makefile iteriert deshalb explizit
über die Subplugins.

**Die Laufzeit ist eine Kette.** Hook → AMD-Bootstrap → vier Webservices → Mode-Subplugin →
Submit → Fragment-Refresh. Jedes Glied kann still versagen. Deshalb gibt es neben PHPUnit und
Behat auch Playwright: Ob ein Asset-Pfad stimmt, sieht man nicht im DOM, sondern nur im
Netzwerk-Log.

---

## 2. Voraussetzungen

| Werkzeug | Version | Wofür |
|---|---|---|
| PHP | 8.2–8.4 | 8.2 ist das CI-Minimum; 8.4 nur ab Moodle 5.0 |
| PostgreSQL oder MariaDB | 16 / 10.11 | Entwicklungs- und PHPUnit-Datenbank |
| Node.js | 20+ | Grunt, ESLint, Playwright |
| Composer | 2.x | moodle-plugin-ci |
| Maxima + gnuplot | nach STACK-Doku | STACK-Auswertung |
| Java (JRE 8+) | — | nur für JMeter |
| k6 | aktuell | nur für die Lasttests |

`moodle-plugin-ci` in **Version 4** — eine ältere lokale `moodle-cs` akzeptiert, was die CI
ablehnt, und produziert dann ein „lokal grün", das nichts bedeutet.

---

## 3. Moodle-Baum und Plugins

```bash
cd /var/www/html
git clone --branch MOODLE_405_STABLE --depth 1 https://github.com/moodle/moodle.git moodle45_aliseadele
cd moodle45_aliseadele
```

Abhängigkeiten in der Reihenfolge, in der Moodle sie braucht:

```bash
git clone --branch master https://github.com/maths/moodle-qbehaviour_adaptivemultipart.git \
    question/behaviour/adaptivemultipart
git clone --branch master https://github.com/maths/moodle-qtype_stack.git \
    question/type/stack
git clone --branch main   https://github.com/ralferlebach/moodle-qbehaviour_stackmathgame.git \
    question/behaviour/stackmathgame
git clone --branch master https://github.com/branchup/moodle-filter_shortcodes.git \
    filter/shortcodes

# Optional, aber empfohlen: sonst laufen die Bridges nie durch einen Testfall.
git clone --branch master https://github.com/FMCorz/moodle-block_xp.git   blocks/xp
git clone --branch master https://github.com/FMCorz/moodle-block_stash.git blocks/stash

git clone https://github.com/ralferlebach/moodle-local_stackmathgame.git local/stackmathgame
```

`qbehaviour_adaptivemultipart` ist keine Abhängigkeit dieses Plugins, sondern von `qtype_stack`.
Fehlt es, bricht die Moodle-Installation ab, bevor `local_stackmathgame` überhaupt erreicht wird
— ein Fehlerbild, das leicht dem falschen Plugin zugeschrieben wird.

Die drei Mode-Subplugins liegen im Repository unter `mode/` und werden **nicht** separat
geklont.

Anschließend Maxima nach STACK-Anleitung einrichten und unter
*Website-Administration → Plugins → Fragetypen → STACK → Gesundheitscheck* verifizieren. Ohne
grünen Healthcheck ist jeder STACK-Testfall wertlos.

---

## 4. `config.php` für die Entwicklung

```php
$CFG->wwwroot   = 'http://localhost/moodle45_aliseadele';
$CFG->dataroot  = '/var/moodledata/moodle45_aliseadele';

// PHPUnit — eigene Datenbank und eigenes Dataroot, sonst löscht die Initialisierung
// die Entwicklungsdaten.
$CFG->phpunit_prefix   = 'phpu_';
$CFG->phpunit_dataroot = '/var/moodledata/phpu_stackmathgame';

// Behat.
$CFG->behat_prefix     = 'bht_';
$CFG->behat_dataroot   = '/var/moodledata/bht_stackmathgame';
$CFG->behat_wwwroot    = 'http://127.0.0.1:8000';

// Entwicklung.
$CFG->debug        = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
$CFG->cachejs      = false;
```

`behat_wwwroot` bewusst auf `127.0.0.1`: `localhost` kann auf `::1` auflösen, wo PHPs
eingebauter Server nicht lauscht — der Client meldet dann HTTP 0, was wie ein Timeout aussieht.

```bash
php admin/cli/install_database.php --agree-license --adminpass='...' --adminemail='...'
php admin/tool/phpunit/cli/init.php
php admin/tool/behat/cli/init.php
```

---

## 5. Werkzeuge installieren

```bash
composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci ~/ci ^4
export PATH="$HOME/ci/bin:$HOME/ci/vendor/bin:$PATH"

cd /var/www/html/moodle45_aliseadele && npm install     # Grunt + ESLint aus dem Moodle-Baum

git clone --depth 1 https://github.com/moodlehq/moodle-local_moodlecheck.git local/moodlecheck
```

`local_moodlecheck` wird nur für `make lint-phpdoc` gebraucht und darf auf einem produktiven
System nicht liegen bleiben.

---

## 6. Qualitätsgates

Alles aus `local/stackmathgame/`:

| Befehl | Was passiert |
|---|---|
| `make check` | PHPCS, PHPDoc, Mustache, PHPCPD, ESLint, AMD-Build, PHPUnit |
| `make fix` | PHPDoc- und Style-Autofix, danach AMD-Rebuild |
| `make lint-php` | nur PHPCS, Moodle-Standard |
| `make lint-js` | ESLint über `amd/src/` **und** alle `mode/*/amd/src/` |
| `make amd` | AMD-Build für Parent und alle Subplugins |
| `make phpunit` | PHPUnit-Testsuite dieses Plugins |
| `make playwright` | Browser-Journeys gegen eine laufende Instanz |
| `make load-seed` | Kurs + Quiz + Token für die Lasttests anlegen |
| `make jmeter` | JMeter-Lastlauf über die Read-Endpunkte |

**Immer im Moodle-Baum prüfen.** `moodle.Files.LangFilesOrdering` und
`moodle.PHPUnit.TestCaseCovers` schweigen, wenn ein alleinstehendes Verzeichnis geprüft wird.

**Nach jeder JS-Änderung `make amd`, und das Ergebnis committen.** Ein veraltetes
`mode/rpg/amd/build/game.min.js` liefert im Browser ein altes Spielmodul aus, während der
Quelltext daneben aktuell aussieht — der teuerste Fehlersuchweg in diesem Projekt.

### Testarten und was sie jeweils können

| Art | Ort | Deckt ab | Deckt bewusst nicht ab |
|---|---|---|---|
| PHPUnit | `tests/unit/` | Services, Schema-Validierung, Branch-Auflösung, Bridges | Alles, was einen Browser braucht |
| Behat | `tests/behat/` | Serverseitige Formulare, Navigation, Capabilities | Das Spiel selbst — es entsteht erst nach dem AMD-Laden |
| Playwright | `tests/playwright/` | AMD-Bootstrap, Asset-Laden ohne 404, Mode-Rendering, Accessibility | Fachliche Bewertung |
| k6 / JMeter | `tests/load/` | Latenz der Read-Endpunkte, Reward-Exklusivität unter echter Parallelität | Funktionale Korrektheit |

Die Aufteilung ist keine Geschmacksfrage. PHPUnit kann die Reward-Exklusivität prinzipiell nicht
beweisen: ein einzelner PHP-Prozess liest und schreibt das Profil in derselben
Datenbank-Session, sieht also stets seinen eigenen vorherigen Schreibvorgang. Nur echt parallele
Requests können sich zwischen die Prüfung „schon gelöst?" und die XP-Erhöhung schieben. Dafür
existiert `stackmathgame-capacity-race.js`.

### CI

| Workflow | Auslöser | Zweck |
|---|---|---|
| `moodle-plugin-ci-dev.yml` | Push auf `development` | Schnelles paralleles Feedback |
| `moodle-plugin-ci-main.yml` | Push/PR auf `main` | Volle Matrix + Release-Gates |
| `playwright.yml` | manuell | Browser-Journeys und axe |
| `load-k6.yml`, `load-jmeter.yml` | manuell | Lasttests |

`moodle-plugin-ci-main.yml` ist der maßgebliche Release-Gate: Matrix, Versions-Gleichstand,
Release-Artefakt, Coverage-Untergrenze (50 %) und die Prüfung auf veraltete Dateien. Der Job
`ci-complete` ist der Status-Check für den Branch-Schutz.

---

## 7. Ein spielbares Quiz von Hand anlegen

1. Kurs anlegen, Quiz anlegen.
2. Quiz-Einstellungen → *Frageverhalten* → **STACK Math Game**. Ohne das startet kein Spiel,
   egal wie vollständig die Spieleinstellungen aussehen.
3. *Fragen pro Seite* auf **1**. Der Branch-Resolver navigiert zwischen Seiten.
4. Zwei bis vier geprüfte STACK-Fragen hinzufügen.
5. Spieleinstellungen öffnen, Spiel aktivieren, Label und Design wählen.
6. Question-Map erzeugen:
   `php local/stackmathgame/cli/rebuild_questionmap.php --cmid=<CMID>`
7. Als Teilnehmer:in einen Versuch starten.

Schneller geht es mit `php local/stackmathgame/tests/playwright/seed.php` — der legt dasselbe
automatisch an und gibt die Zugangsdaten aus.

---

## 8. Session-Prompt: Start

```text
# Session start — local_stackmathgame

You are continuing development of local_stackmathgame, a Moodle plugin that turns a quiz built
from STACK questions into a playable game.

Before changing code:

1. Read docs/ENTWICKLUNGSUMGEBUNG.md and the latest docs/sessions/session-*.md.
   One conversation is one session: open docs/sessions/session-NNN.md for this one at the
   start and append to it as you go, rather than writing it up at the end.
2. Read the open issues you are working on in full. Their acceptance criteria are the
   specification; do not work from a summary of them.
3. Inspect the actual public APIs before using them. In particular slot_config_schema,
   branch_resolver, question_map_service, package_registry and theme_manager already exist and
   are more complete than they look — several open bugs are consumers ignoring them, not gaps
   in them.
4. Never introduce a parallel data model. Per-slot game configuration lives in
   local_stackmathgame_questionmap.configjson and nowhere else.
5. cmid is the source of truth, not quizid. New code takes a cmid.
6. Client and server must not hold two different interpretations of the branching schema. The
   server resolver is canonical; the client consumes it.
7. A mode subplugin renders. It does not call web services and does not resolve branches. The
   contract is init(gameState) → {onAnswer(response, store)}.
8. Mode subplugins address assets by key, from the design's resolved runtime asset map. Never
   construct an asset path in JavaScript.
9. Use POST + sesskey + a context-appropriate capability for every mutation. Ask capabilities in
   the context the action belongs to, not in the system context.
10. make check is a fast local pre-check; GitHub CI is the authoritative release gate. Run the
    checks inside a Moodle tree — several sniffs stay silent on a standalone directory, so
    "green locally" outside a tree means nothing.

Current session objective:
> [One concrete slice. Name the issue number.]

Reward logic is security-relevant: a reward must be granted at most once per solved scene, and
that must hold for genuinely parallel requests, not merely for sequential ones.
```

---

## 9. Session-Prompt: Ende

```text
# Session end — local_stackmathgame

Before finishing:

1. One conversation is one session. docs/sessions/session-NNN.md is written *during* the
   session, not composed at the end. Never overwrite an earlier session's file.
2. Make sure it records changed files, capabilities and entry points touched, API contracts
   used, decisions taken and their reasons, tests written or run, and unresolved risks.
3. Verify no parallel per-slot data model was introduced and that configjson is still written
   through slot_config_schema.
4. Verify no new code keys off quizid where a cmid is available.
5. Verify the client did not gain its own branching interpretation, and that no mode subplugin
   gained a web-service call or a hand-built asset path.
6. Verify every mutation is POST + sesskey + a capability checked in the right context.
7. Run make check plus the relevant PHPUnit and Behat tests. Record skips honestly — a skipped
   STACK test is not a passing STACK test.
8. Rebuild AMD for the parent plugin and for every mode/* subplugin, and commit the build
   artefacts. A stale mode/*/amd/build/game.min.js ships an old game module while amd/src looks
   perfectly current.
9. Update docs/CHANGELOG.md. Add anything deleted to db/removed_files.txt.
10. Build and inspect the work ZIP.

Reward logic is security-relevant: if this session touched submit, profile or reward code, say
explicitly in the session record whether the at-most-once guarantee was re-tested under
concurrency.
```

Beide Prompts liegen auch unter `docs/prompt-templates/` als Textdateien.

---

## 10. Häufige Stolpersteine

| Symptom | Ursache |
|---|---|
| Spiel startet nicht, Einstellungen sehen vollständig aus | Quiz-`preferredbehaviour` ist nicht `stackmathgame` |
| JS-Änderung wirkt nicht | `mode/*/amd/build/` nicht neu gebaut, oder `$CFG->cachejs` nicht auf `false` |
| Assets bleiben leer, keine Fehlermeldung | Design-Assetmap wird nicht ausgewertet — siehe Issue #4 |
| Weiter-Button erscheint nie | Branch-Modus `linear`, den die Mode-Module nicht behandeln — Issue #2 |
| PHPCS lokal grün, CI rot | Außerhalb des Moodle-Baums geprüft, oder ältere `moodle-cs` |
| Behat meldet HTTP 0 | `behat_wwwroot` auf `localhost` statt `127.0.0.1` |
| STACK-Test „passed", ohne etwas zu prüfen | Maxima fehlt, der Testfall hat sich übersprungen |
| Nach Speichern der Spieleinstellungen ist etwas aus | `requiresbehaviour` fällt auf `0` — Issue #3 |
