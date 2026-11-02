# Session 005 — Re-Audit vom 02.11.2026 (N01, N03–N09) und die rote CI

**Status:** abgeschlossen (Ergebnisse unten; offene Punkte am Ende)
**Version:** 2026090251 (Release bleibt 0.9.0)
**Ausgangsstand:** `develop` bei `81cb62f` (= 2026090250)

---

## Ausgangslage

Re-Audit mit acht offenen P1-Befunden (N01, N03–N09) und einer roten CI: drei ESLint-Warnungen in
`game_engine.js` bei Warngrenze 0, dazu zwei fehlschlagende Behat-Szenarien in den
Spiel-Einstellungen. Gearbeitet wurde auf einer eigenen, isolierten Testsite (Moodle 4.5.15,
PostgreSQL, Präfixe `gm_`/`gmu_`/`gmb_`, Webserver 8020/8021, eigener Chromedriver 141 auf 4445)
mit qtype_stack 4.13.1, qbehaviour_stackmathgame (`main`), filter_shortcodes (`master`) und
local_stackmatheditor 1.4.0 als Kopie.

## N01 — Session-Keys im Event-Log

**Ursache.** `collectAnswers()` schickte `sesskey` als Antwortfeld mit, der Server ergänzte ihn
sonst selbst, und `api::log_event()` speicherte `array_values($flatpayload)` – den ganzen Request
ohne Feldnamen, mit Session-Key.

**Änderung.** Der Browser sendet keinen Session-Key mehr in den Antworten, der Server verwirft ein
mitgeschicktes `sesskey`-Feld und ergänzt keines. Geloggt wird eine Positivliste
(`submit_answer::diagnostic_answers()`): die Antwortfelder der Frage laut
`get_expected_data()`/Eingabenamen samt STACK-`_val`-Echo und die Sequenzprüfung, mit Namen. Der
CSRF-Schutz bleibt `require_sesskey()` auf dem Request.

**Altdaten.** Weil die Namen verloren sind, entfernt der Upgrade-Schritt 2026090251
(`local_stackmathgame_redact_eventlog_sesskeys()` in `db/upgradelib.php`) aus geloggten
Wertelisten jeden Wert in exakt der Sesskey-Form (`^[A-Za-z0-9]{10}$`, so erzeugt `sesskey()`);
benannte Formen verlieren ihren `sesskey`-Eintrag. Umgeschriebene Zeilen tragen
`sesskeyredacted: 1`. Bewusste Kosten: eine echte Antwort genau dieser Form wird mit entfernt – die
maßgebliche Kopie jeder Antwort ist der Fragenversuchsschritt; einen Credential zu behalten wäre
der schwerere Fehler. Idempotent.

## N03 — Check-Ablauf bei Fehlern

**Ursache.** Nach doppeltem Refresh-Fehler rief `followUp()` trotzdem `onAnswer()`, und das RPG
leerte sein Panel, obwohl die alte `.que` keine `.formulation` mehr hatte – Frage und letzte
Eingabe verschwanden. Ohne nativen Submit-Button folgte im Fallback ein Reload, der die Eingabe
verwarf. Ein Fehler in einem optionalen Bootstrap-Aufruf stoppte das ganze Spiel.

**Änderung.** Der Check ist eine flache Promise-Kette (`acceptSubmit`, `rejectSubmit`,
`refreshQuestion`, `followUp`, `dispatchAnswer`, `reportRecorded`); `mountQuestion()` im RPG lässt
das Panel unverändert, wenn nichts Neues zu montieren ist; Darstellungsfehler werden abgefangen und
nie erneut gesendet; ohne nativen Submitter kein Reload, sondern Status und erhaltene Eingabe;
Profil, Narrativ und Prefetch sind auch beim Start optional. Neu ist eine Statuszeile
(`.smg-runtime-status`, `aria-live`) mit sieben `status_*`-Strings (en/de).

## N04 — Ungespeichert-Zustand

**Ursache.** `markSaved()` setzte das ganze `#responseform` zurück, obwohl ein Submit einen Slot
speichert; der spätere Fragment-Tausch überschrieb neuere Eingaben; der verzögerte Reload für alte
Editoren prüfte die aktuellen Felder nicht.

**Änderung.** Moodles 4.5-`core_form/changechecker` kennt nur ein Formular-Flag; die Wahrheit pro
Feld führt die Engine (`state.baseline`, `unsavedFields()`), und `syncDirtyState()` setzt das Flag
danach (`resetFormDirtyState` nur, wenn kein Antwortfeld abweicht, sonst `markFormAsDirty`).
Während des Requests geänderte Werte werden vor dem Einhängen in die neue Frage übernommen
(`carryNewerEdits`) und bleiben ungespeichert. Der Re-Init-Reload wartet bis zu 3 s auf den
Editor, zählt erst nach Ende des Checks, unterbleibt beim Verlassen der Seite und nie über
ungespeicherten Werten (dann Status `status_editorlost`).

## N05 — Spiel-Einstellungen

**Ursache der zwei roten Szenarien.** Auf einer frischen Installation gab es kein Label: Das
Label `default` entstand nur nebenbei in `quiz_configurator::ensure_default()`. Seit Session 004
schreibt das Öffnen der Seite nichts mehr, und eine Lehrperson darf Labels wählen, aber nicht
anlegen – also blieb die Auswahl leer und jeder Save scheiterte mit „Please select an existing
label or enter a name for a new one.“ (geprüft: `gmb_`/`gmu_`-Installation mit altem Code: 0
Labels). Es fehlte eine autorisierte Initialisierung, nicht Testvorbereitung; Rechte wurden nicht
erweitert. `db/install.php` legt `default` jetzt an, der Upgrade-Schritt ergänzt es auf
bestehenden Sites.

**Weiter.** `quiz_settings_service`: `require_access()` (Kontext gehört zur cmid,
`configurequiz`) in `validate()`/`save()`; unverändertes Label wird erneut geprüft;
`design_intent()` unterscheidet keep/clear/invalid; `write_config()` schreibt genau die
validierten Werte (kein `ensure_default()` mehr beim ersten Save); Retry validiert erneut;
Label und Konfiguration in einer Transaktion (Test mit echtem Rollback über
`preventResetByRollback()`); Design-Picker begrenzt (`theme_manager::get_selectable()`, 50 +
aktuelles).

## N06 — Navigation, aktiver Slot, Quest-Stufen

`navigation_resolver::resolve()` führt bei falscher Antwort eine konfigurierte Regel aus
(`branch_resolver::configured_target()`: Slot-Sprung oder Ende), `linear` bleibt „stay“.
Quest: spielbar sind gelöste Stufen und die aktive – künftige nicht (Submit und Fragment
verweigern). Eine fertige Gruppe wird hinter ihrer Seite verlassen. Der Server bestimmt den
präsentierten Slot (`page_group_resolver::presented_slot()`, über
`output_hooks::presented_slot()` als `activeslot` an die Engine); Navigations-URLs tragen
`smgslot`, das nur gilt, wenn der Slot spielbar ist. Die Engine markiert die Frage, blendet die
übrigen der Seite aus und gibt die Marker beim Tausch weiter; der Seiten-Fallback sucht nach Slot.

Die drei `@smg_navtiming`-Szenarien drückten „Check answer“, bevor STACK die Eingabe validiert
hatte – das validierte dann nur. Neuer Schritt „I wait until STACK has validated the answer“, der
Pfeil wird als Link geklickt; der Tag und seine Ausnahme in beiden CI-Workflows sind entfernt.

## N07 — HUD-Cache

Schlüssel `smg_rpg_score:u<userid>:l<labelid>` (ohne Label `q<quizid>`), fremde Einträge werden
gelöscht, Serverzustand gewinnt immer; nach einer Antwort werden die Zähler aus dem zurückgegebenen
Profil neu berechnet.

## N08 — Fragment-Endpunkt

Entscheidung: Laufzeit-Endpunkt nur für laufende eigene Versuche. Beendete Versuche →
`attemptalreadyclosed` (Review über Moodles review.php mit dessen Review-Optionen); dazu
Spielbarkeitsprüfung wie beim Submit.

## N09 — Browser

Neu: `tests/playwright/e2e/rpg-verified-play.spec.js`, `support/play.js`, `support/smg-e2e.php`,
Fixture `tests/fixtures/e2e_verified_questions.xml`. `rpg-full-ui.spec.js` tippt sichtbar, prüft
einmal nach STACK-Konfiguration, beendet den Versuch echt und vergleicht mit der DB.

Die Journey fand zwei echte Fehler, beide behoben:
1. Nach dem Fragment-Tausch fehlte STACKs Sofortvalidierung (Skripte der Frage wurden nie
   ausgeführt) → jede weitere Antwort brauchte zwei Checks. Der Endpunkt liefert jetzt
   `javascript` (wie Moodles Fragment-Dienste), die Engine führt es aus, wartet auf
   `M.util.pending_js` und entfernt vorher die IDs der vom Modus verschobenen Altkopien, sonst
   band STACK an das alte Validierungsfeld.
2. Beim Seitenaufbau wartet die Engine auf die Skripte der Fragetypen, bevor der Modus umbaut.

Beobachtung: STACK validiert sofort nur Eingaben mit `mustverify`; für die anderen gibt es nichts
abzuwarten, ein Check bewertet. MathQuill schreibt `2x` ohne `*`; die Fixture fügt keine Sterne
ein, die Journey tippt daher `2*x` (Mal-Punkt).

## Testläufe (lokal, Moodle 4.5.15, PostgreSQL 16, PHP 8.3, Chromium 141)

* **Jest** (`tests/jest`): 3 Suites, 55/55 grün (vorher 28). Gegen den Stand `81cb62f` laufen die
  neuen Tests zu 21 von 22 rot (der eine grüne – „editor off: kein Reload“ – war schon vorher
  richtig).
* **PHPUnit** (ganze Suite `local_stackmathgame_testsuite`, Maxima aktiv): 309 Tests, 1331
  Assertions, 0 Fehler, 9 übersprungen (5× block_xp/block_stash nicht installiert, 4× bestehende
  Platzhalter-Tests). Alle 13 STACK-Tests liefen mit echtem CAS. Die neuen Tests gegen die alten
  `classes/` + `db/install.php`: 22 von 76 rot (16 Failures, 6 Errors).
* **Behat** (`--tags "@local_stackmathgame&&~@broken"`, keine weitere Ausnahme; `@broken` gibt es
  nicht): 34 Szenarien, 382 Schritte, alle grün. Vorher (alter Stand, gleiche Site): 30 Szenarien,
  davon 3 rot (die navtiming-Szenarien liefen ohne Ausschluss mit). Einzeln: branch_navigation
  5/5 (83 Schritte), page_groups 3/3 (54), quiz_game_settings 6/6 (55), quiz_prerequisites 4/4 (38);
  die zwei früher roten Szenarien je 1/1 (8 bzw. 9 Schritte).
* **Playwright**: `rpg-verified-play.spec.js` 4 Tests, zuletzt 2×4 und 3×4 Wiederholungen grün
  (insgesamt 1 Flake in 24 Läufen, siehe „Offen“). `rpg-full-ui.spec.js`: Aufbau, Spiel, echter
  Abschluss und DB-Vergleich bestanden; der letzte Schritt „Nothing broke quietly“ schlug fehl
  (Moodle-Core-Meldung „Invalid parameter value detected“ auf `mod/quiz/edit.php` und
  abgebrochene Requests beim Seitenwechsel) – nicht als grün gewertet.
* **ESLint** `--max-warnings 0` auf `amd/src` und `mode/*/amd/src`: 0 Befunde. `grunt amd` im
  Plugin und in den drei Modi erzeugt byte-identisch die eingecheckten `amd/build`-Dateien.
* **phpcs** (moodle): 0 Fehler/Warnungen in `amd classes db lang mode tests/unit tests/behat
  tests/fixtures *.php`. Unverändert offen: je 1 Fehler in vier `.github/*.php`-Skripten
  (MoodleInternalGlobalState, schon vor dieser Sitzung). `tests/playwright` ist laut `phpcs.xml`
  ausgenommen. **moodlecheck** auf allen geänderten PHP-Dateien: ohne Befund.

## Offen

* Firefox: nicht vorhanden, nicht getestet.
* Moodle 5.x / andere STACK-Versionen: nicht getestet.
* Ohne installiertes local_stackmatheditor: nur „Editor aus“ (Modus 0) getestet, nicht ein Baum
  ohne das Plugin.
* Seltener Flake (1 von 9 Läufen) in der Editor-an-Journey: nach dem Fragment-Tausch hängte sich
  der Editor 1.4.0 nicht neu an, und der Fallback-Reload blieb aus; Ursache nicht gefunden.
* Mariadb nicht lokal gelaufen (CI-Behat nutzt mariadb).
* `rpg-full-ui.spec.js` nicht vollständig grün (siehe oben); die Ursache der Core-Meldung auf
  der Quiz-Bearbeitungsseite wurde nicht untersucht.
* Höchstens-einmal-Belohnung unter echter Nebenläufigkeit nicht erneut gemessen.
