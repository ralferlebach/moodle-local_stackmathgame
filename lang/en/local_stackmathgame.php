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
 * English language strings for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Core plugin strings.
$string['pluginname']          = 'STACK Math Game';
$string['gamesettings']        = 'Game settings';
$string['settingsheading']     = 'STACK Math Game';
$string['settingsdesc']        = 'This plugin injects a game-oriented interface layer into STACK quiz attempts.';
$string['studio_title']        = 'Game Design Studio';
$string['nextquestion']        = 'Next question';
$string['finishpractice']      = 'Finish practice';
$string['checkanswerhidden']   = 'The native Check button is hidden because this quiz is controlled by the game behaviour.';
$string['gamestatusready']     = 'Game layer initialised';

// Capability display strings.
$string['configurequiz']        = 'Configure STACK Math Game in quiz';
$string['managethemes']         = 'Manage STACK Math Game themes';
$string['play']                 = 'Play STACK Math Game activities';
$string['pluginadministration'] = 'STACK Math Game administration';
$string['settings']             = 'Settings';

// Quiz settings form strings.
$string['enabled']              = 'Enable game layer';
$string['enabled_help']         = 'When enabled, the STACK Math Game interface is injected into quiz attempts for students.';
$string['teacherdisplayname']   = 'Teacher-facing display name';
$string['teacherdisplayname_help'] = 'Internal label shown to teachers when managing this quiz game settings. Not shown to students.';
$string['labelsettings']        = 'Label / progress space';
$string['label']                = 'Game label';
$string['label_help']           = 'Choose the progress space this quiz contributes to.';
$string['newlabel']             = 'Or create a new label';
$string['newlabel_help']        = 'Type a name to create a brand-new label. Leave blank if you selected an existing label above.';
$string['newlabelplaceholder']  = 'e.g. Algebra Term 1';
$string['labelselectionnotice'] = 'If you type a new label name and select an existing label, the existing label takes precedence.';
$string['designsettings']       = 'Game design';
$string['design']               = 'Design';
$string['design_help']          = 'Choose the visual design (theme) for this quiz.';
$string['nodesignsavailable']   = 'No active game designs found. Please ask a game designer to create or activate a design in the Game Design Studio.';
$string['err_designrequired']   = 'Please select a game design.';
$string['err_labelrequired']    = 'Please select an existing label or enter a name for a new one.';

// Subplugin type strings.
$string['subplugintype_stackmathgamemode']        = 'Game mode';
$string['subplugintype_stackmathgamemode_plural'] = 'Game modes';

// Shortcode descriptions.
$string['shortcode_smgscore']       = 'Displays the current score for a game label.';
$string['shortcode_smgxp']          = 'Displays the current XP for a game label.';
$string['shortcode_smglevel']       = 'Displays the current level for a game label.';
$string['shortcode_smgprogress']    = 'Displays the current progress payload for a game label.';
$string['shortcode_smgnarrative']   = 'Displays or wraps narrative content.';
$string['shortcode_smgavatar']      = 'Displays the current avatar payload for a game label.';
$string['shortcode_smgleaderboard'] = 'Displays a simple leaderboard for a game label.';
$string['shortcode_smgscore_help']       = 'Outside a quiz context you must pass label="...". Optional: field="score|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgxp_help']          = 'Outside a quiz context you must pass label="...". Optional: field="xp|levelno|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smglevel_help']       = 'Outside a quiz context you must pass label="...". Optional: field="levelno|levelprogress".';
$string['shortcode_smgprogress_help']    = 'Outside a quiz context you must pass label="...". Optional: format="summary|json|raw" or field="solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgnarrative_help']   = 'Displays narrative text from the active design. Outside a quiz context you must pass label="...". Optional: scene="world_enter|victory|defeat|boss_intro|reward" and design="designslug".';
$string['shortcode_smgavatar_help']      = 'Displays the avatar payload for the current label/profile. Outside a quiz context you must pass label="...". Optional: field="avatarkey".';
$string['shortcode_smgleaderboard_help'] = 'Displays a leaderboard for a label. Outside a quiz context you must pass label="...". Optional: limit="10".';
$string['shortcodeslabelrequired']       = 'Outside a quiz context, STACK Math Game shortcodes require a label argument.';

// External API submit strings.
$string['submitansweraccepted']  = 'Answer payload accepted by the external API layer.';
$string['submitanswerprocessed'] = 'Answer processed and quiz attempt updated.';
$string['submitanswerfallback']  = 'Game processing fell back to passive mode.';

// Runtime UI labels.
$string['selectdesign']     = 'Select game design';
$string['managelabels']     = 'Manage game labels';
$string['viewstudio']       = 'View Game Design Studio';
$string['managenarratives'] = 'Manage game narratives';
$string['manageassets']     = 'Manage game assets';
$string['managemechanics']  = 'Manage game mechanics';
$string['runtimemode']      = 'Mode';
$string['runtimetracked']   = 'Tracked';
$string['runtimepartial']   = 'Partial';
$string['runtimesolved']    = 'Solved';

// AMD runtime strings.
$string['gamelayerloading'] = 'Loading game layer...';
$string['gameruntimeerror'] = 'Runtime error';
$string['gamecheckanswer']  = 'Game check';
$string['gameusenative']    = 'Use native controls';
$string['gameprofile']      = 'Profile';
$string['gamecurrentdesign'] = 'Design';
$string['gamenextnode']     = 'Next node';

// Studio gallery strings.
$string['studio_tab_overview']   = 'Overview';
$string['studio_tab_edit']       = 'Edit design';
$string['studio_tab_import']     = 'Import';
$string['studio_intro']          = 'The Game Design Studio lets you manage the visual designs, narratives, assets and mechanics used by STACK Math Game quizzes.';
$string['studio_hint_themes']    = 'Manage and preview available designs.';
$string['studio_hint_assets']    = 'Import asset packages as ZIP files.';
$string['studio_hint_mechanics'] = 'Configure mechanics per mode.';
$string['studio_hint_roles']     = 'Teachers select a design; Game Designers manage the library.';
$string['studio_capsummary']     = 'Your studio permissions — themes: {$a->managethemes}, narratives: {$a->managenarratives}, assets: {$a->manageassets}, mechanics: {$a->managemechanics}.';
$string['studio_nodesigns']      = 'No active designs found. Create one via "Edit design" or import a package.';
$string['studio_nothumbnail']    = 'No thumbnail';
$string['studio_bundled']        = 'Bundled';
$string['studio_imported']       = 'Imported';
$string['addnewdesign']          = 'Add new design';

// Studio import form strings.
$string['studio_importzip']    = 'Design ZIP package';
$string['studio_importformat'] = 'Upload a ZIP file containing a manifest.json that declares the modecomponent field.';
$string['importdesign']        = 'Import design';
$string['exportdesign']        = 'Export design';
$string['errordesignnotfound'] = 'The requested design could not be found.';

// Design edit form strings.
$string['designname']           = 'Design name';
$string['designslug']           = 'Slug (unique identifier)';
$string['designmode']           = 'Game mode';
$string['designthumbnail']      = 'Thumbnail image';
$string['designassetsmanifest'] = 'Asset manifest (JSON)';
$string['designnarrativejson']  = 'Narrative (JSON)';
$string['designuijson']         = 'UI config (JSON)';
$string['designmechanicsjson']  = 'Mechanics config (JSON)';
$string['savedesign']           = 'Save design';
$string['err_invalidjson']      = 'The field "{$a}" does not contain valid JSON.';

// Error / navigation strings.
$string['quiznotfound'] = 'The quiz with ID {$a} could not be found or its course activity has been deleted. Any saved game configuration for this quiz has been removed.';
$string['returnhome']   = 'Return to home';

// Event strings.
$string['event_progress_updated']    = 'Game progress updated';
$string['event_question_solved']     = 'Game question solved';
$string['event_stash_item_granted']  = 'Stash item granted';

// Privacy metadata strings.
$string['privacy:metadata']                          = 'STACK Math Game stores game profile and event log data per user.';
$string['privacy:metadata:profile']                  = 'Game profile records (score, XP, progress) stored per user per label.';
$string['privacy:metadata:profile:userid']           = 'User ID';
$string['privacy:metadata:profile:labelid']          = 'Game label ID';
$string['privacy:metadata:profile:score']            = 'Total score';
$string['privacy:metadata:profile:xp']               = 'Total experience points';
$string['privacy:metadata:profile:levelno']          = 'Current level';
$string['privacy:metadata:profile:softcurrency']     = 'In-game soft currency';
$string['privacy:metadata:profile:hardcurrency']     = 'In-game hard currency';
$string['privacy:metadata:profile:avatarconfigjson'] = 'Avatar configuration';
$string['privacy:metadata:profile:progressjson']     = 'Question progress data';
$string['privacy:metadata:profile:statsjson']        = 'Aggregate statistics';
$string['privacy:metadata:profile:flagsjson']        = 'Feature flags';
$string['privacy:metadata:profile:lastquizid']       = 'Last accessed quiz';
$string['privacy:metadata:profile:lastaccess']       = 'Last access time';
$string['privacy:metadata:profile:timecreated']      = 'Profile creation time';
$string['privacy:metadata:eventlog']                 = 'Event log recording game actions per user.';
$string['privacy:metadata:eventlog:userid']          = 'User ID';
$string['privacy:metadata:eventlog:labelid']         = 'Game label ID';
$string['privacy:metadata:eventlog:quizid']          = 'Quiz ID';
$string['privacy:metadata:eventlog:questionid']      = 'Question ID';
$string['privacy:metadata:eventlog:eventtype']       = 'Event type';
$string['privacy:metadata:eventlog:payloadjson']     = 'Event payload data';
$string['privacy:metadata:eventlog:timecreated']     = 'Event time';
