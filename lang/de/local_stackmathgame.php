<?php
$string['pluginname'] = 'STACK-Mathe-Spiel';
$string['privacy:metadata'] = 'Das Plugin STACK-Mathe-Spiel speichert selbst keine personenbezogenen Daten.';
$string['gamesettings'] = 'Spiel-Einstellungen';
$string['settingsheading'] = 'STACK-Mathe-Spiel';
$string['settingsdesc'] = 'Dieses Plugin blendet eine spielorientierte Oberfläche in STACK-Quizversuche ein.';
$string['studio_title'] = 'Game-Design-Studio';
$string['nextquestion'] = 'Nächste Frage';
$string['finishpractice'] = 'Übung beenden';
$string['checkanswerhidden'] = 'Der native Prüfen-Button ist ausgeblendet, weil dieses Quiz durch das Spielverhalten gesteuert wird.';
$string['gamestatusready'] = 'Spielebene initialisiert';
$string['submitanswerplaceholder'] = 'Das Grundgerüst für die Antwortübermittlung ist installiert, aber das Schreiben in den Quizversuch ist noch nicht umgesetzt.';

$string['configurequiz'] = 'STACK-Mathe-Spiel im Quiz konfigurieren';
$string['managethemes'] = 'Themes für STACK-Mathe-Spiel verwalten';
$string['play'] = 'STACK-Mathe-Spiel-Aktivitäten spielen';
$string['pluginadministration'] = 'Administration STACK-Mathe-Spiel';
$string['pluginname_help'] = 'Stellt eine spielorientierte Oberfläche und Studio-Werkzeuge für STACK-Quizaktivitäten bereit.';
$string['settings'] = 'Einstellungen';

$string['shortcode_smgscore'] = 'Zeigt den aktuellen Punktestand für ein Spiellabel an.';
$string['shortcode_smgxp'] = 'Zeigt die aktuellen XP für ein Spiellabel an.';
$string['shortcode_smglevel'] = 'Zeigt das aktuelle Level für ein Spiellabel an.';
$string['shortcode_smgprogress'] = 'Zeigt die aktuelle Fortschrittsnutzlast für ein Spiellabel an.';
$string['shortcode_smgnarrative'] = 'Gibt narrativen Inhalt aus oder umschließt ihn.';
$string['shortcode_smgavatar'] = 'Zeigt die aktuelle Avatar-Nutzlast für ein Spiellabel an.';
$string['shortcode_smgleaderboard'] = 'Zeigt eine einfache Rangliste für ein Spiellabel an.';

$string['submitansweraccepted'] = 'Antwortdaten wurden von der External-API-Schicht angenommen.';
$string['subplugintype_stackmathgamemode'] = 'Spielmodus';
$string['subplugintype_stackmathgamemode_plural'] = 'Spielmodi';
$string['selectdesign'] = 'Spieldesign auswählen';
$string['managelabels'] = 'Spiel-Labels verwalten';
$string['viewstudio'] = 'Game-Design-Studio ansehen';
$string['managenarratives'] = 'Spielnarrative verwalten';
$string['manageassets'] = 'Spiel-Assets verwalten';
$string['managemechanics'] = 'Spielmechaniken verwalten';

$string['submitanswerprocessed'] = 'Answer processed and quiz attempt updated.';
$string['submitanswerfallback'] = 'Game processing fell back to passive mode.';

$string['runtimemode'] = 'Modus';
$string['runtimetracked'] = 'Erfasst';
$string['runtimepartial'] = 'Teilweise';
$string['runtimesolved'] = 'Gelöst';

$string['shortcode_smgscore_help'] = 'Outside a quiz context you must pass label="...". Optional: field="score|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgxp_help'] = 'Outside a quiz context you must pass label="...". Optional: field="xp|levelno|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smglevel_help'] = 'Outside a quiz context you must pass label="...". Optional: field="levelno|levelprogress".';
$string['shortcode_smgprogress_help'] = 'Outside a quiz context you must pass label="...". Optional: format="summary|json|raw" or field="solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgnarrative_help'] = 'Displays narrative text from the active design. Outside a quiz context you must pass label="...". Optional: scene="world_enter|victory|defeat|boss_intro|reward" and design="designslug".';
$string['shortcode_smgavatar_help'] = 'Displays the avatar payload for the current label/profile. Outside a quiz context you must pass label="...". Optional: field="avatarkey".';
$string['shortcode_smgleaderboard_help'] = 'Displays a leaderboard for a label. Outside a quiz context you must pass label="...". Optional: limit="10".';
$string['shortcodeslabelrequired'] = 'Außerhalb eines Quiz-Kontexts benötigen STACK-Math-Game-Shortcodes ein label-Argument.';
