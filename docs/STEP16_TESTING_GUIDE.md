# STACK Math Game – Schritt-für-Schritt-Testanleitung (Stand 2026032612)

Diese Anleitung dokumentiert den testbaren Stand der Codebasis für:

- `local_stackmathgame`
- `qbehaviour_stackmathgame`
- die drei Mode-Subplugins
  - `exitgames`
  - `wisewizzard`
  - `rpg`

Sie ist bewusst auf **Konsolen- und Browser-Tests** ausgelegt, damit der Arbeitsschritt reproduzierbar abgesichert werden kann.

## 1. Erwartete Dateistruktur

Im Moodle-Root müssen mindestens diese Pfade existieren:

```bash
ls -ld local/stackmathgame \
      local/stackmathgame/mode/exitgames \
      local/stackmathgame/mode/wisewizzard \
      local/stackmathgame/mode/rpg \
      question/behaviour/stackmathgame
```

Zusätzlich prüfen:

```bash
test -f local/stackmathgame/db/subplugins.php && echo OK: subplugins.php
test -f local/stackmathgame/db/subplugins.json && echo OK: subplugins.json
test -f local/stackmathgame/classes/plugininfo/stackmathgamemode.php && echo OK: plugininfo
test -f question/behaviour/stackmathgame/behaviour.php && echo OK: behaviour
```

## 2. Versionsstand prüfen

```bash
grep '\$plugin->version' local/stackmathgame/version.php
grep '\$plugin->version' question/behaviour/stackmathgame/version.php
```

Erwartet:

- beide Plugins auf `2026032612`

## 3. Subplugin-Registrierung prüfen

```bash
php -r 'require "local/stackmathgame/db/subplugins.php"; var_export($subplugins); echo PHP_EOL;'
cat local/stackmathgame/db/subplugins.json
```

Erwartet:

- in `db/subplugins.php`:
  - `stackmathgamemode => local/stackmathgame/mode`
- in `db/subplugins.json`:
  - `plugintypes.stackmathgamemode = local/stackmathgame/mode`
  - `subplugintypes.stackmathgamemode = mode`

## 4. Syntax-Smoketest

```bash
find local/stackmathgame -name '*.php' -print0 | xargs -0 -n1 php -l
find question/behaviour/stackmathgame -name '*.php' -print0 | xargs -0 -n1 php -l
```

Es darf **kein ParseError** auftreten.

## 5. Sprachstrings prüfen

### a) Syntax

```bash
php -l local/stackmathgame/lang/en/local_stackmathgame.php
php -l local/stackmathgame/lang/de/local_stackmathgame.php
```

### b) Kritische Schlüssel

```bash
grep -n "subplugintype_stackmathgamemode" local/stackmathgame/lang/en/local_stackmathgame.php
grep -n "subplugintype_stackmathgamemode_plural" local/stackmathgame/lang/en/local_stackmathgame.php
grep -n "submitanswerprocessed" local/stackmathgame/lang/en/local_stackmathgame.php
grep -n "submitanswerfallback" local/stackmathgame/lang/en/local_stackmathgame.php
```

## 6. Webservice-Registrierung prüfen

```bash
sed -n '1,220p' local/stackmathgame/db/services.php
```

Es müssen mindestens diese Funktionen vorhanden sein:

- `local_stackmathgame_get_quiz_config`
- `local_stackmathgame_get_profile_state`
- `local_stackmathgame_submit_answer`
- `local_stackmathgame_save_progress`
- `local_stackmathgame_get_narrative`
- `local_stackmathgame_get_question_fragment`
- `local_stackmathgame_prefetch_next_node`

## 7. Hook-/Capability-Schnitt prüfen

```bash
grep -R "viewstudio\|managethemes\|play\|selectdesign\|managelabels" -n local/stackmathgame/db/access.php local/stackmathgame/classes/hook/output_hooks.php
```

Ziel:

- `viewstudio` existiert in `db/access.php`
- Hook-Datei referenziert nur vorhandene Capabilities

## 8. Mode-Default-Design-Pakete prüfen

```bash
find local/stackmathgame/mode -path '*/packages/*/manifest.json' -print
find local/stackmathgame/mode -path '*/packages/*/preview/thumbnail.svg' -print
```

Erwartet:

- für `exitgames`, `wisewizzard`, `rpg` jeweils ein Paket mit `manifest.json`

Optionaler Detailcheck:

```bash
sed -n '1,120p' local/stackmathgame/mode/exitgames/packages/exitgames_default/manifest.json
sed -n '1,120p' local/stackmathgame/mode/wisewizzard/packages/wisewizzard_default/manifest.json
sed -n '1,120p' local/stackmathgame/mode/rpg/packages/rpg_default/manifest.json
```

## 9. Install-/Upgrade-Lauf vorbereiten

Vor dem Browser-Test:

```bash
php admin/cli/purge_caches.php
```

Danach Upgrade starten:

```bash
php admin/cli/upgrade.php --non-interactive
```

### Erfolgskriterium

- kein Fehler zu `stackmathgamemode`
- keine Meldung `Quelle fehlt` für `exitgames`, `wisewizzard`, `rpg`
- kein Hook-/Capability-Notice

## 10. DB-Struktur nach Installation prüfen

### Tabellenliste

```bash
php admin/cli/mysql_compressed_rows.php >/dev/null 2>&1 || true
php -r '
$cfg = include "config.php";
echo "Use SQL client for final table verification.".PHP_EOL;
'
```

Wenn MySQL/MariaDB genutzt wird:

```bash
mysql -u root -p -D moodle45_aliseadele -e "SHOW TABLES LIKE 'mdl_local_stackmathgame%';"
```

Erwartet u. a.:

- `mdl_local_stackmathgame_label`
- `mdl_local_stackmathgame_profile`
- `mdl_local_stackmathgame_quizcfg`
- `mdl_local_stackmathgame_design`
- `mdl_local_stackmathgame_eventlog`

## 11. Browser-Smoke-Test für Quizattempt

### Vorbedingungen

- ein Quiz mit STACK-Frage existiert
- Behaviour `stackmathgame` ist auswählbar/gesetzt
- `local_stackmathgame`-Konfiguration für das Quiz ist aktiv

### Browser-Test

1. Quizattempt öffnen
2. DevTools öffnen
3. Prüfen, dass oberhalb/nahe der Frage eine `smg-runtime-shell` erscheint
4. Im DOM prüfen:

```js
!!document.querySelector('.smg-runtime-shell')
```

5. Fragecontainer prüfen:

```js
const q = document.querySelector('.que');
q?.dataset.smgControlled
q?.dataset.smgBehaviour
q?.dataset.smgSlot
q?.dataset.smgState
```

Erwartet:

- `smgControlled = "1"`
- `smgBehaviour = "stackmathgame"`
- Slot und State vorhanden

## 12. Konsolen-Tests im Browser

### Runtime geladen?

```js
typeof require === 'function'
```

### Native Fallback-Controls vorhanden?

```js
!!document.querySelector('.smg-native-controls')
```

### Native Feedback-Box vorhanden oder leer?

```js
!!document.querySelector('.smg-native-feedback')
```

### Runtime-Shell vorhanden?

```js
!!document.querySelector('.smg-runtime-shell')
```

### Submit-Button vorhanden?

```js
[...document.querySelectorAll('button')].map(b => b.textContent.trim()).filter(Boolean)
```

## 13. AJAX-Test: Konfiguration laden

Im Browser-Console-Test mit Moodle-AMD/JS:

```js
require(['core/ajax'], function(Ajax) {
  const quizid = parseInt(new URLSearchParams(location.search).get('cmid') || '0', 10);
  console.log('Use actual quizid from page context if needed:', quizid);
});
```

Da `quizid` nicht immer direkt in der URL steht, ist der robustere Weg, erst im DOM zu schauen oder die Runtime ihre Werte laden zu lassen.

### Praktischer Netzwerktest

Im DevTools-Netzwerkfilter nach folgenden Methoden suchen:

- `local_stackmathgame_get_quiz_config`
- `local_stackmathgame_get_profile_state`
- `local_stackmathgame_get_narrative`
- `local_stackmathgame_prefetch_next_node`

Erwartet:

- HTTP 200
- JSON-Antworten ohne PHP-Notice/Warning

## 14. AJAX-Test: Submit-Pfad

1. Antwort in einer STACK-Frage eingeben
2. In der Runtime auf **Game check** klicken
3. Netzwerk prüfen:
   - `local_stackmathgame_submit_answer`
   - danach `local_stackmathgame_get_question_fragment`
   - oder Fallback-Reload der Attempt-Seite

### Browser-Konsole parallel

```js
console.log('Question state before:', document.querySelector('.que')?.dataset.smgState);
```

Nach dem Klick erneut:

```js
setTimeout(() => {
  console.log('Question state after:', document.querySelector('.que')?.dataset.smgState);
}, 1500);
```

## 15. Erwartete funktionale Ergebnisse

Nach **Game check** sollte mindestens erkennbar sein:

- kein Vollreload der Seite
- Runtime-Shell bleibt sichtbar
- mindestens eine der folgenden Änderungen tritt ein:
  - `state` ändert sich
  - Message in der Runtime-Shell ändert sich
  - Score/XP ändern sich
  - Fragebox wird ersetzt/refresht

## 16. DB-/Eventlog-Test nach Submit

Nach einem erfolgreichen Submit:

```bash
mysql -u root -p -D moodle45_aliseadele -e "SELECT id, userid, labelid, quizid, eventtype, source, timecreated FROM mdl_local_stackmathgame_eventlog ORDER BY id DESC LIMIT 10;"
```

Erwartet:

- Einträge mit `eventtype = answer_submitted`

Profilprüfung:

```bash
mysql -u root -p -D moodle45_aliseadele -e "SELECT id, userid, labelid, score, xp, levelno, lastquizid, lastaccess FROM mdl_local_stackmathgame_profile ORDER BY id DESC LIMIT 10;"
```

## 17. Was in diesem Stand noch bewusst unvollständig ist

Dieser Stand ist **funktional testbar**, aber noch nicht Endausbau.

Noch nicht vollständig fertig:

- kompletter serverseitiger Fragment-Refresh für alle Randbereiche
- vollständige Frage-/Seiten-Navigation ohne native Quizmechanik
- komplette Härtung aller STACK-Sonderfälle im pageless Submit
- produktionsreifes Reward-Balancing

## 18. Empfehlung für die Ergebnisabnahme

Der Arbeitsschritt gilt als erfolgreich abgesichert, wenn:

1. Installation/Upgrade ohne Subplugin-/Hook-/Capability-Fehler durchläuft
2. alle drei Mode-Subplugins erkannt werden
3. ein Quizattempt mit `stackmathgame` geladen werden kann
4. `Game check` einen serverseitigen Request auslöst
5. die Runtime-Shell ohne Vollreload aktualisiert wird
6. `mdl_local_stackmathgame_eventlog` und `mdl_local_stackmathgame_profile` Veränderungen zeigen



## Ergebnisgesicherte Erweiterungen in Schritt 16/17

- Runtime rendert jetzt Modus/Design-Kontext aus `runtimejson` serverseitig vorbereitet.
- Bundled Design-Pakete werden über lokale Asset-URLs aufgelöst.
- Profilzusammenfassung (`summaryjson`) enthält `solvedcount`, `partialcount`, `trackedslots`, `levelprogress`.
- Submit-Deltas werden nur noch bei Zustandsverbesserung einer Frage vergeben.
- `prefetch_next_node` bevorzugt noch nicht gelöste Slots.

### Zusätzlicher Konsolentest

```bash
php -r '
require "config.php";
require_once "$CFG->dirroot/local/stackmathgame/classes/game/theme_manager.php";
print_r(local_stackmathgame\game\theme_manager::get_theme_config(local_stackmathgame\game\theme_manager::ensure_default_design()));
'
```
