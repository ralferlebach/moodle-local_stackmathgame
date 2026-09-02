<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addnewdesign'] = 'Neues Design anlegen';
$string['checkanswerhidden'] = 'Der native Prüfen-Button ist ausgeblendet, weil dieses Quiz durch das Spielverhalten gesteuert wird.';
$string['configurequiz'] = 'STACK-Mathe-Spiel im Quiz konfigurieren';
$string['design'] = 'Design';
$string['design_help'] = 'Wählen Sie das visuelle Design (Theme) für dieses Quiz.';
$string['designassetsmanifest'] = 'Asset-Manifest (JSON)';
$string['designmechanicsjson'] = 'Mechanik-Konfiguration (JSON)';
$string['designmode'] = 'Spielmodus';
$string['designname'] = 'Designname';
$string['designnarrativejson'] = 'Narrativ (JSON)';
$string['designsettings'] = 'Spieldesign';
$string['designslug'] = 'Slug (eindeutige Kennung)';
$string['designthumbnail'] = 'Vorschaubild';
$string['designuijson'] = 'UI-Konfiguration (JSON)';
$string['enabled'] = 'Spielebene aktivieren';
$string['enabled_help'] = 'Wenn aktiviert, wird die STACK-Mathe-Spiel-Oberfläche für Studierende in Quizversuche eingeblendet.';
$string['err_designrequired'] = 'Bitte wählen Sie ein Spieldesign aus.';
$string['err_invalidjson'] = 'Das Feld „{$a}" enthält kein gültiges JSON.';
$string['err_labelrequired'] = 'Bitte wählen Sie ein bestehendes Label aus oder geben Sie einen Namen für ein neues ein.';
$string['err_prerequisitesunmet'] = 'Das Spiel kann in diesem Test noch nicht starten und daher nicht aktiviert werden. {$a}';
$string['errordesignnotfound'] = 'Das angeforderte Design konnte nicht gefunden werden.';
$string['event_progress_updated'] = 'Spielfortschritt aktualisiert';
$string['event_question_solved'] = 'Spielfrage gelöst';
$string['event_stash_item_granted'] = 'Stash-Gegenstand gewährt';
$string['exportdesign'] = 'Design exportieren';
$string['finishpractice'] = 'Übung beenden';
$string['gamecheckanswer'] = 'Spiel-Check';
$string['gamecurrentdesign'] = 'Design';
$string['gamelayerloading'] = 'Spielebene wird geladen...';
$string['gamenextnode'] = 'Nächster Knoten';
$string['gameprofile'] = 'Profil';
$string['gameruntimeerror'] = 'Laufzeitfehler';
$string['gamesettings'] = 'Spiel-Einstellungen';
$string['gamestatusready'] = 'Spielebene initialisiert';
$string['gameusenative'] = 'Native Steuerung verwenden';
$string['importdesign'] = 'Design importieren';
$string['label'] = 'Spiel-Label';
$string['label_help'] = 'Wählen Sie den Fortschrittsraum, zu dem dieses Quiz beiträgt.';
$string['labelselectionnotice'] = 'Wenn Sie einen neuen Label-Namen eingeben und ein bestehendes Label auswählen, hat das bestehende Label Vorrang.';
$string['labelsettings'] = 'Label / Fortschrittsraum';
$string['manageassets'] = 'Spiel-Assets verwalten';
$string['managelabels'] = 'Spiel-Labels verwalten';
$string['managemechanics'] = 'Spielmechaniken verwalten';
$string['managenarratives'] = 'Spielnarrative verwalten';
$string['managethemes'] = 'Themes für STACK-Mathe-Spiel verwalten';
$string['newlabel'] = 'Oder neues Label anlegen';
$string['newlabel_help'] = 'Geben Sie einen Namen ein, um ein neues Label zu erstellen. Leer lassen, wenn Sie oben ein bestehendes Label ausgewählt haben.';
$string['newlabelplaceholder'] = 'z. B. Algebra Semester 1';
$string['nextquestion'] = 'Nächste Frage';
$string['nodesignsavailable'] = 'Keine aktiven Spieldesigns gefunden. Bitten Sie einen Game Designer, im Game-Design-Studio ein Design zu erstellen oder zu aktivieren.';
$string['play'] = 'STACK-Mathe-Spiel-Aktivitäten spielen';
$string['pluginadministration'] = 'Administration STACK-Mathe-Spiel';
$string['pluginname'] = 'STACK-Mathe-Spiel';
$string['prereq_behaviour'] = 'Frageverhalten';
$string['prereq_behaviour_notenforced'] = 'Die Durchsetzung ist für diese Aktivität abgeschaltet, das Frageverhalten wird deshalb nicht geprüft. Das Spiel wird sehr wahrscheinlich nicht funktionieren.';
$string['prereq_behaviour_ok'] = 'Der Test verwendet das Frageverhalten „STACK Math Game".';
$string['prereq_behaviour_unknownmodule'] = 'Diese Aktivität ist ein {$a} und kein Test. Die Verhaltensanforderung kann nicht geprüft werden.';
$string['prereq_behaviour_wrong'] = 'Der Test verwendet „{$a->actual}". Das Spiel benötigt „{$a->expected}" und startet nicht.';
$string['prereq_col_detail'] = 'Befund';
$string['prereq_col_requirement'] = 'Voraussetzung';
$string['prereq_col_status'] = 'Status';
$string['prereq_design'] = 'Design';
$string['prereq_design_missing'] = 'Es ist kein aktives Design zugewiesen. Bitte unten eines auswählen.';
$string['prereq_design_ok'] = 'Verwendet das Design „{$a}".';
$string['prereq_fix'] = 'Beheben';
$string['prereq_heading'] = 'Voraussetzungen für den Spielbetrieb';
$string['prereq_plugin_behaviour'] = 'Plugin für das Frageverhalten';
$string['prereq_plugin_missing'] = 'Das Plugin {$a} ist nicht installiert.';
$string['prereq_plugin_present'] = 'Installiert.';
$string['prereq_plugin_shortcodes'] = 'Shortcodes-Filter';
$string['prereq_plugin_stack'] = 'Fragetyp STACK';
$string['prereq_questionmap'] = 'Question-Map';
$string['prereq_questionmap_noslots'] = 'Der Test enthält noch keine Frageplätze.';
$string['prereq_questionmap_ok'] = 'Alle {$a} Frageplätze sind Szenen zugeordnet.';
$string['prereq_questionmap_stale'] = '{$a->mapped} von {$a->slots} Frageplätzen sind zugeordnet. Die Map wird automatisch neu aufgebaut, die Szenen sind aber noch nicht auf dem Stand des Tests.';
$string['prereq_questions'] = 'Fragen';
$string['prereq_questions_mixed'] = '{$a->stack} von {$a->total} Fragen sind STACK-Fragen. Die übrigen sind spielbar, liefern aber kein STACK-Feedback.';
$string['prereq_questions_none'] = 'Der Test enthält keine Fragen.';
$string['prereq_questions_nostack'] = 'Keine der {$a} Fragen ist eine STACK-Frage.';
$string['prereq_questions_ok'] = 'Alle {$a} Fragen sind STACK-Fragen.';
$string['prereq_status_error'] = 'Nicht erfüllt';
$string['prereq_status_ok'] = 'Erfüllt';
$string['prereq_status_warning'] = 'Zu prüfen';
$string['prereq_summary_blocked'] = '{$a} Voraussetzung(en) nicht erfüllt. Das Spiel startet in diesem Test nicht.';
$string['prereq_summary_ok'] = 'Alle Voraussetzungen sind erfüllt. Das Spiel kann in diesem Test starten.';
$string['prereq_summary_warnings'] = 'Das Spiel kann starten, {$a} Punkt(e) sollten aber geprüft werden.';
$string['privacy:metadata'] = 'STACK-Mathe-Spiel speichert Spielprofil- und Ereignisprotokoll-Daten pro Nutzer.';
$string['privacy:metadata:eventlog'] = 'Ereignisprotokoll mit Spielaktionen pro Nutzer.';
$string['privacy:metadata:eventlog:eventtype'] = 'Ereignistyp';
$string['privacy:metadata:eventlog:labelid'] = 'Spiel-Label-ID';
$string['privacy:metadata:eventlog:payloadjson'] = 'Ereignis-Nutzlastdaten';
$string['privacy:metadata:eventlog:questionid'] = 'Fragen-ID';
$string['privacy:metadata:eventlog:quizid'] = 'Quiz-ID';
$string['privacy:metadata:eventlog:timecreated'] = 'Ereigniszeitpunkt';
$string['privacy:metadata:eventlog:userid'] = 'Nutzer-ID';
$string['privacy:metadata:profile'] = 'Spielprofil-Datensätze (Punkte, XP, Fortschritt) pro Nutzer und Label.';
$string['privacy:metadata:profile:avatarconfigjson'] = 'Avatar-Konfiguration';
$string['privacy:metadata:profile:flagsjson'] = 'Feature-Flags';
$string['privacy:metadata:profile:hardcurrency'] = 'Spielinterne Hartwährung';
$string['privacy:metadata:profile:labelid'] = 'Spiel-Label-ID';
$string['privacy:metadata:profile:lastaccess'] = 'Letzter Zugriffszeitpunkt';
$string['privacy:metadata:profile:lastquizid'] = 'Zuletzt besuchtes Quiz';
$string['privacy:metadata:profile:levelno'] = 'Aktuelles Level';
$string['privacy:metadata:profile:progressjson'] = 'Fragenfortschrittsdaten';
$string['privacy:metadata:profile:score'] = 'Gesamtpunktestand';
$string['privacy:metadata:profile:softcurrency'] = 'Spielinterne Softwährung';
$string['privacy:metadata:profile:statsjson'] = 'Aggregierte Statistiken';
$string['privacy:metadata:profile:timecreated'] = 'Profil-Erstellungszeitpunkt';
$string['privacy:metadata:profile:userid'] = 'Nutzer-ID';
$string['privacy:metadata:profile:xp'] = 'Erfahrungspunkte gesamt';
$string['quiznotfound'] = 'Das Quiz mit der ID {$a} wurde nicht gefunden oder seine Kursaktivität wurde gelöscht. Eine gespeicherte Spielkonfiguration für dieses Quiz wurde entfernt.';
$string['returnhome'] = 'Zur Startseite zurück';
$string['runtimemode'] = 'Modus';
$string['runtimepartial'] = 'Teilweise';
$string['runtimesolved'] = 'Gelöst';
$string['runtimetracked'] = 'Erfasst';
$string['savedesign'] = 'Design speichern';
$string['selectdesign'] = 'Spieldesign auswählen';
$string['settings'] = 'Einstellungen';
$string['settingsdesc'] = 'Dieses Plugin blendet eine spielorientierte Oberfläche in STACK-Quizversuche ein.';
$string['settingsheading'] = 'STACK-Mathe-Spiel';
$string['shortcode_smgavatar'] = 'Zeigt die aktuelle Avatar-Nutzlast für ein Spiellabel an.';
$string['shortcode_smgavatar_help'] = 'Zeigt die Avatar-Nutzlast für das aktuelle Label/Profil. Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: field="avatarkey".';
$string['shortcode_smgleaderboard'] = 'Zeigt eine einfache Rangliste für ein Spiellabel an.';
$string['shortcode_smgleaderboard_help'] = 'Zeigt eine Rangliste für ein Label. Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: limit="10".';
$string['shortcode_smglevel'] = 'Zeigt das aktuelle Level für ein Spiellabel an.';
$string['shortcode_smglevel_help'] = 'Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: field="levelno|levelprogress".';
$string['shortcode_smgnarrative'] = 'Gibt narrativen Inhalt aus oder umschließt ihn.';
$string['shortcode_smgnarrative_help'] = 'Zeigt narrativen Text aus dem aktiven Design. Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: scene="world_enter|victory|defeat|boss_intro|reward" und design="designslug".';
$string['shortcode_smgprogress'] = 'Zeigt die aktuelle Fortschrittsnutzlast für ein Spiellabel an.';
$string['shortcode_smgprogress_help'] = 'Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: format="summary|json|raw" oder field="solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgscore'] = 'Zeigt den aktuellen Punktestand für ein Spiellabel an.';
$string['shortcode_smgscore_help'] = 'Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: field="score|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgxp'] = 'Zeigt die aktuellen XP für ein Spiellabel an.';
$string['shortcode_smgxp_help'] = 'Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: field="xp|levelno|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcodeslabelrequired'] = 'Außerhalb eines Quiz-Kontexts benötigen STACK-Math-Game-Shortcodes ein label-Argument.';
$string['stashmapping_desc'] = 'Weisen Sie jedem Frageplatz einen block_stash-Gegenstand zu. Löst eine Person diesen Platz zum ersten Mal, wird der Gegenstand ihrem Inventar hinzugefügt. Setzt voraus, dass block_stash installiert und im Kurs aktiviert ist.';
$string['stashmapping_enabled'] = 'Zuordnung aktiv';
$string['stashmapping_header'] = 'Stash-Belohnungen (Integration mit block_stash)';
$string['stashmapping_item'] = 'Stash-Gegenstand';
$string['stashmapping_noitem'] = '(kein Gegenstand – deaktiviert)';
$string['stashmapping_noslots'] = 'Für diesen Test wurden keine Frageplätze gefunden. Bitte zuerst Fragen hinzufügen.';
$string['stashmapping_qty'] = 'Vergebene Menge';
$string['stashmapping_slot'] = 'Frageplatz {$a}';
$string['studio_bundled'] = 'Bundled';
$string['studio_capsummary'] = 'Ihre Studio-Berechtigungen — Themes: {$a->managethemes}, Narrative: {$a->managenarratives}, Assets: {$a->manageassets}, Mechaniken: {$a->managemechanics}.';
$string['studio_hint_assets'] = 'Asset-Pakete als ZIP-Datei importieren.';
$string['studio_hint_mechanics'] = 'Spielmechaniken je Modus konfigurieren.';
$string['studio_hint_roles'] = 'Lehrende wählen ein Design; Game Designer verwalten die Bibliothek.';
$string['studio_hint_themes'] = 'Verfügbare Designs verwalten und vorschauen.';
$string['studio_imported'] = 'Importiert';
$string['studio_importformat'] = 'Laden Sie eine ZIP-Datei hoch, die eine manifest.json mit dem Feld modecomponent enthält.';
$string['studio_importzip'] = 'Design-ZIP-Paket';
$string['studio_intro'] = 'Das Game-Design-Studio ermöglicht die Verwaltung der visuellen Designs, Narrative, Assets und Spielmechaniken für STACK-Mathe-Spiel-Quizze.';
$string['studio_nodesigns'] = 'Keine aktiven Designs gefunden. Erstellen Sie eines über „Design bearbeiten" oder importieren Sie ein Paket.';
$string['studio_nothumbnail'] = 'Kein Vorschaubild';
$string['studio_tab_edit'] = 'Design bearbeiten';
$string['studio_tab_import'] = 'Importieren';
$string['studio_tab_overview'] = 'Übersicht';
$string['studio_title'] = 'Game-Design-Studio';
$string['submitansweraccepted'] = 'Antwortdaten wurden von der External-API-Schicht angenommen.';
$string['submitanswerfallback'] = 'Spielverarbeitung ist in den passiven Modus zurückgefallen.';
$string['submitanswerprocessed'] = 'Antwort verarbeitet und Quizversuch aktualisiert.';
$string['subplugintype_stackmathgamemode'] = 'Spielmodus';
$string['subplugintype_stackmathgamemode_plural'] = 'Spielmodi';
$string['teacherdisplayname'] = 'Anzeigename für Lehrende';
$string['teacherdisplayname_help'] = 'Interner Name, der Lehrenden bei der Verwaltung der Spieleinstellungen angezeigt wird. Nicht für Studierende sichtbar.';
$string['viewstudio'] = 'Game-Design-Studio ansehen';
