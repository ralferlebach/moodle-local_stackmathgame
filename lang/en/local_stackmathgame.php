<?php
$string['pluginname'] = 'STACK Math Game';
$string['privacy:metadata'] = 'The STACK Math Game plugin does not store personal data itself.';
$string['gamesettings'] = 'Game settings';
$string['settingsheading'] = 'STACK Math Game';
$string['settingsdesc'] = 'This plugin injects a game-oriented interface layer into STACK quiz attempts.';
$string['studio_title'] = 'Game Design Studio';
$string['nextquestion'] = 'Next question';
$string['finishpractice'] = 'Finish practice';
$string['checkanswerhidden'] = 'The native Check button is hidden because this quiz is controlled by the game behaviour.';
$string['gamestatusready'] = 'Game layer initialised';
$string['submitanswerplaceholder'] = 'Answer submission endpoint scaffold is installed, but no quiz-attempt writeback is implemented yet.';

$string['configurequiz'] = 'Configure STACK Math Game in quiz';
$string['managethemes'] = 'Manage STACK Math Game themes';
$string['play'] = 'Play STACK Math Game activities';
$string['pluginadministration'] = 'STACK Math Game administration';
$string['pluginname_help'] = 'Provides a game-oriented interface layer and studio tooling for STACK quiz activities.';
$string['settings'] = 'Settings';

$string['shortcode_smgscore'] = 'Displays the current score for a game label.';
$string['shortcode_smgxp'] = 'Displays the current XP for a game label.';
$string['shortcode_smglevel'] = 'Displays the current level for a game label.';
$string['shortcode_smgprogress'] = 'Displays the current progress payload for a game label.';
$string['shortcode_smgnarrative'] = 'Displays or wraps narrative content.';
$string['shortcode_smgavatar'] = 'Displays the current avatar payload for a game label.';
$string['shortcode_smgleaderboard'] = 'Displays a simple leaderboard for a game label.';

$string['submitansweraccepted'] = 'Answer payload accepted by the external API layer.';
$string['subplugintype_stackmathgamemode'] = 'Game mode';
$string['subplugintype_stackmathgamemode_plural'] = 'Game modes';
$string['selectdesign'] = 'Select game design';
$string['managelabels'] = 'Manage game labels';
$string['viewstudio'] = 'View Game Design Studio';
$string['managenarratives'] = 'Manage game narratives';
$string['manageassets'] = 'Manage game assets';
$string['managemechanics'] = 'Manage game mechanics';

$string['submitanswerprocessed'] = 'Answer processed and quiz attempt updated.';
$string['submitanswerfallback'] = 'Game processing fell back to passive mode.';

$string['runtimemode'] = 'Mode';
$string['runtimetracked'] = 'Tracked';
$string['runtimepartial'] = 'Partial';
$string['runtimesolved'] = 'Solved';

$string['shortcode_smgscore_help'] = 'Outside a quiz context you must pass label="...". Optional: field="score|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgxp_help'] = 'Outside a quiz context you must pass label="...". Optional: field="xp|levelno|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smglevel_help'] = 'Outside a quiz context you must pass label="...". Optional: field="levelno|levelprogress".';
$string['shortcode_smgprogress_help'] = 'Outside a quiz context you must pass label="...". Optional: format="summary|json|raw" or field="solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgnarrative_help'] = 'Displays narrative text from the active design. Outside a quiz context you must pass label="...". Optional: scene="world_enter|victory|defeat|boss_intro|reward" and design="designslug".';
$string['shortcode_smgavatar_help'] = 'Displays the avatar payload for the current label/profile. Outside a quiz context you must pass label="...". Optional: field="avatarkey".';
$string['shortcode_smgleaderboard_help'] = 'Displays a leaderboard for a label. Outside a quiz context you must pass label="...". Optional: limit="10".';
$string['shortcodeslabelrequired'] = 'Outside a quiz context, STACK Math Game shortcodes require a label argument.';

$string['event_progress_updated'] = 'STACK Math Game progress updated';
$string['event_question_solved'] = 'STACK Math Game question solved';
$string['event_stash_item_granted'] = 'STACK Math Game stash item granted';
$string['integration_blockxp'] = 'Level Up! XP bridge';
$string['integration_blockstash'] = 'Stash bridge';
