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

// Kern-Plugin-Strings.
$string['pluginname']          = 'STACK-Mathe-Spiel';
$string['gamesettings']        = 'Spiel-Einstellungen';
$string['settingsheading']     = 'STACK-Mathe-Spiel';
$string['settingsdesc']        = 'Dieses Plugin blendet eine spielorientierte Oberfläche in STACK-Quizversuche ein.';
$string['studio_title']        = 'Game-Design-Studio';
$string['nextquestion']        = 'Nächste Frage';
$string['finishpractice']      = 'Übung beenden';
$string['checkanswerhidden']   = 'Der native Prüfen-Button ist ausgeblendet, weil dieses Quiz durch das Spielverhalten gesteuert wird.';
$string['gamestatusready']     = 'Spielebene initialisiert';

// Capability-Anzeige-Strings.
$string['configurequiz']        = 'STACK-Mathe-Spiel im Quiz konfigurieren';
$string['managethemes']         = 'Themes für STACK-Mathe-Spiel verwalten';
$string['play']                 = 'STACK-Mathe-Spiel-Aktivitäten spielen';
$string['pluginadministration'] = 'Administration STACK-Mathe-Spiel';
$string['settings']             = 'Einstellungen';

// Quiz-Einstellungsformular-Strings.
$string['enabled']              = 'Spielebene aktivieren';
$string['enabled_help']         = 'Wenn aktiviert, wird die STACK-Mathe-Spiel-Oberfläche für Studierende in Quizversuche eingeblendet.';
$string['teacherdisplayname']   = 'Anzeigename für Lehrende';
$string['teacherdisplayname_help'] = 'Interner Name, der Lehrenden bei der Verwaltung der Spieleinstellungen angezeigt wird. Nicht für Studierende sichtbar.';
$string['labelsettings']        = 'Label / Fortschrittsraum';
$string['label']                = 'Spiel-Label';
$string['label_help']           = 'Wählen Sie den Fortschrittsraum, zu dem dieses Quiz beiträgt.';
$string['newlabel']             = 'Oder neues Label anlegen';
$string['newlabel_help']        = 'Geben Sie einen Namen ein, um ein neues Label zu erstellen. Leer lassen, wenn Sie oben ein bestehendes Label ausgewählt haben.';
$string['newlabelplaceholder']  = 'z. B. Algebra Semester 1';
$string['labelselectionnotice'] = 'Wenn Sie einen neuen Label-Namen eingeben und ein bestehendes Label auswählen, hat das bestehende Label Vorrang.';
$string['designsettings']       = 'Spieldesign';
$string['design']               = 'Design';
$string['design_help']          = 'Wählen Sie das visuelle Design (Theme) für dieses Quiz.';
$string['nodesignsavailable']   = 'Keine aktiven Spieldesigns gefunden. Bitten Sie einen Game Designer, im Game-Design-Studio ein Design zu erstellen oder zu aktivieren.';
$string['err_designrequired']   = 'Bitte wählen Sie ein Spieldesign aus.';
$string['err_labelrequired']    = 'Bitte wählen Sie ein bestehendes Label aus oder geben Sie einen Namen für ein neues ein.';

// Subplugin-Typ-Strings.
$string['subplugintype_stackmathgamemode']        = 'Spielmodus';
$string['subplugintype_stackmathgamemode_plural'] = 'Spielmodi';

// Shortcode-Beschreibungen.
$string['shortcode_smgscore']       = 'Zeigt den aktuellen Punktestand für ein Spiellabel an.';
$string['shortcode_smgxp']          = 'Zeigt die aktuellen XP für ein Spiellabel an.';
$string['shortcode_smglevel']       = 'Zeigt das aktuelle Level für ein Spiellabel an.';
$string['shortcode_smgprogress']    = 'Zeigt die aktuelle Fortschrittsnutzlast für ein Spiellabel an.';
$string['shortcode_smgnarrative']   = 'Gibt narrativen Inhalt aus oder umschließt ihn.';
$string['shortcode_smgavatar']      = 'Zeigt die aktuelle Avatar-Nutzlast für ein Spiellabel an.';
$string['shortcode_smgleaderboard'] = 'Zeigt eine einfache Rangliste für ein Spiellabel an.';
$string['shortcode_smgscore_help']       = 'Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: field="score|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgxp_help']          = 'Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: field="xp|levelno|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smglevel_help']       = 'Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: field="levelno|levelprogress".';
$string['shortcode_smgprogress_help']    = 'Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: format="summary|json|raw" oder field="solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgnarrative_help']   = 'Zeigt narrativen Text aus dem aktiven Design. Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: scene="world_enter|victory|defeat|boss_intro|reward" und design="designslug".';
$string['shortcode_smgavatar_help']      = 'Zeigt die Avatar-Nutzlast für das aktuelle Label/Profil. Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: field="avatarkey".';
$string['shortcode_smgleaderboard_help'] = 'Zeigt eine Rangliste für ein Label. Außerhalb eines Quiz-Kontexts ist label="..." Pflicht. Optional: limit="10".';
$string['shortcodeslabelrequired']       = 'Außerhalb eines Quiz-Kontexts benötigen STACK-Math-Game-Shortcodes ein label-Argument.';

// External-API-Strings.
$string['submitansweraccepted']  = 'Antwortdaten wurden von der External-API-Schicht angenommen.';
$string['submitanswerprocessed'] = 'Antwort verarbeitet und Quizversuch aktualisiert.';
$string['submitanswerfallback']  = 'Spielverarbeitung ist in den passiven Modus zurückgefallen.';

// Laufzeit-UI-Labels.
$string['selectdesign']     = 'Spieldesign auswählen';
$string['managelabels']     = 'Spiel-Labels verwalten';
$string['viewstudio']       = 'Game-Design-Studio ansehen';
$string['managenarratives'] = 'Spielnarrative verwalten';
$string['manageassets']     = 'Spiel-Assets verwalten';
$string['managemechanics']  = 'Spielmechaniken verwalten';
$string['runtimemode']      = 'Modus';
$string['runtimetracked']   = 'Erfasst';
$string['runtimepartial']   = 'Teilweise';
$string['runtimesolved']    = 'Gelöst';

// AMD-Laufzeit-Strings.
$string['gamelayerloading'] = 'Spielebene wird geladen...';
$string['gameruntimeerror'] = 'Laufzeitfehler';
$string['gamecheckanswer']  = 'Spiel-Check';
$string['gameusenative']    = 'Native Steuerung verwenden';
$string['gameprofile']      = 'Profil';
$string['gamecurrentdesign'] = 'Design';
$string['gamenextnode']     = 'Nächster Knoten';

// Studio-Galerie-Strings.
$string['studio_tab_overview']   = 'Übersicht';
$string['studio_tab_edit']       = 'Design bearbeiten';
$string['studio_tab_import']     = 'Importieren';
$string['studio_intro']          = 'Das Game-Design-Studio ermöglicht die Verwaltung der visuellen Designs, Narrative, Assets und Spielmechaniken für STACK-Mathe-Spiel-Quizze.';
$string['studio_hint_themes']    = 'Verfügbare Designs verwalten und vorschauen.';
$string['studio_hint_assets']    = 'Asset-Pakete als ZIP-Datei importieren.';
$string['studio_hint_mechanics'] = 'Spielmechaniken je Modus konfigurieren.';
$string['studio_hint_roles']     = 'Lehrende wählen ein Design; Game Designer verwalten die Bibliothek.';
$string['studio_capsummary']     = 'Ihre Studio-Berechtigungen — Themes: {$a->managethemes}, Narrative: {$a->managenarratives}, Assets: {$a->manageassets}, Mechaniken: {$a->managemechanics}.';
$string['studio_nodesigns']      = 'Keine aktiven Designs gefunden. Erstellen Sie eines über „Design bearbeiten" oder importieren Sie ein Paket.';
$string['studio_nothumbnail']    = 'Kein Vorschaubild';
$string['studio_bundled']        = 'Bundled';
$string['studio_imported']       = 'Importiert';
$string['addnewdesign']          = 'Neues Design anlegen';

// Studio-Import-Strings.
$string['studio_importzip']    = 'Design-ZIP-Paket';
$string['studio_importformat'] = 'Laden Sie eine ZIP-Datei hoch, die eine manifest.json mit dem Feld modecomponent enthält.';
$string['importdesign']        = 'Design importieren';
$string['exportdesign']        = 'Design exportieren';
$string['errordesignnotfound'] = 'Das angeforderte Design konnte nicht gefunden werden.';

// Design-Bearbeitungsformular-Strings.
$string['designname']           = 'Designname';
$string['designslug']           = 'Slug (eindeutige Kennung)';
$string['designmode']           = 'Spielmodus';
$string['designthumbnail']      = 'Vorschaubild';
$string['designassetsmanifest'] = 'Asset-Manifest (JSON)';
$string['designnarrativejson']  = 'Narrativ (JSON)';
$string['designuijson']         = 'UI-Konfiguration (JSON)';
$string['designmechanicsjson']  = 'Mechanik-Konfiguration (JSON)';
$string['savedesign']           = 'Design speichern';
$string['err_invalidjson']      = 'Das Feld „{$a}" enthält kein gültiges JSON.';

// Fehler- und Navigations-Strings.
$string['quiznotfound'] = 'Das Quiz mit der ID {$a} wurde nicht gefunden oder seine Kursaktivität wurde gelöscht. Eine gespeicherte Spielkonfiguration für dieses Quiz wurde entfernt.';
$string['returnhome']   = 'Zur Startseite zurück';

// Ereignis-Strings.
$string['event_progress_updated']    = 'Spielfortschritt aktualisiert';
$string['event_question_solved']     = 'Spielfrage gelöst';
$string['event_stash_item_granted']  = 'Stash-Gegenstand gewährt';

// Datenschutz-Metadaten-Strings.
$string['privacy:metadata']                          = 'STACK-Mathe-Spiel speichert Spielprofil- und Ereignisprotokoll-Daten pro Nutzer.';
$string['privacy:metadata:profile']                  = 'Spielprofil-Datensätze (Punkte, XP, Fortschritt) pro Nutzer und Label.';
$string['privacy:metadata:profile:userid']           = 'Nutzer-ID';
$string['privacy:metadata:profile:labelid']          = 'Spiel-Label-ID';
$string['privacy:metadata:profile:score']            = 'Gesamtpunktestand';
$string['privacy:metadata:profile:xp']               = 'Erfahrungspunkte gesamt';
$string['privacy:metadata:profile:levelno']          = 'Aktuelles Level';
$string['privacy:metadata:profile:softcurrency']     = 'Spielinterne Softwährung';
$string['privacy:metadata:profile:hardcurrency']     = 'Spielinterne Hartwährung';
$string['privacy:metadata:profile:avatarconfigjson'] = 'Avatar-Konfiguration';
$string['privacy:metadata:profile:progressjson']     = 'Fragenfortschrittsdaten';
$string['privacy:metadata:profile:statsjson']        = 'Aggregierte Statistiken';
$string['privacy:metadata:profile:flagsjson']        = 'Feature-Flags';
$string['privacy:metadata:profile:lastquizid']       = 'Zuletzt besuchtes Quiz';
$string['privacy:metadata:profile:lastaccess']       = 'Letzter Zugriffszeitpunkt';
$string['privacy:metadata:profile:timecreated']      = 'Profil-Erstellungszeitpunkt';
$string['privacy:metadata:eventlog']                 = 'Ereignisprotokoll mit Spielaktionen pro Nutzer.';
$string['privacy:metadata:eventlog:userid']          = 'Nutzer-ID';
$string['privacy:metadata:eventlog:labelid']         = 'Spiel-Label-ID';
$string['privacy:metadata:eventlog:quizid']          = 'Quiz-ID';
$string['privacy:metadata:eventlog:questionid']      = 'Fragen-ID';
$string['privacy:metadata:eventlog:eventtype']       = 'Ereignistyp';
$string['privacy:metadata:eventlog:payloadjson']     = 'Ereignis-Nutzlastdaten';
$string['privacy:metadata:eventlog:timecreated']     = 'Ereigniszeitpunkt';
