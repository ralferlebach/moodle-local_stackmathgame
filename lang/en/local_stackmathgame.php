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

$string['addnewdesign'] = 'Add new design';
$string['checkanswerhidden'] = 'The native Check button is hidden because this quiz is controlled by the game behaviour.';
$string['configurequiz'] = 'Configure STACK Math Game in quiz';
$string['design'] = 'Design';
$string['design_help'] = 'Choose the visual design (theme) for this quiz.';
$string['designassetsmanifest'] = 'Asset manifest (JSON)';
$string['designmechanicsjson'] = 'Mechanics config (JSON)';
$string['designmode'] = 'Game mode';
$string['designname'] = 'Design name';
$string['designnarrativejson'] = 'Narrative (JSON)';
$string['designsettings'] = 'Game design';
$string['designslug'] = 'Slug (unique identifier)';
$string['designthumbnail'] = 'Thumbnail image';
$string['designuijson'] = 'UI config (JSON)';
$string['enabled'] = 'Enable game layer';
$string['enabled_help'] = 'When enabled, the STACK Math Game interface is injected into quiz attempts for students.';
$string['err_designrequired'] = 'Please select a game design.';
$string['err_invalidjson'] = 'The field "{$a}" does not contain valid JSON.';
$string['err_labelrequired'] = 'Please select an existing label or enter a name for a new one.';
$string['err_prerequisitesunmet'] = 'The game cannot start on this quiz yet, so it cannot be enabled. {$a}';
$string['errordesignnotfound'] = 'The requested design could not be found.';
$string['event_progress_updated'] = 'Game progress updated';
$string['event_question_solved'] = 'Game question solved';
$string['event_stash_item_granted'] = 'Stash item granted';
$string['exportdesign'] = 'Export design';
$string['finishpractice'] = 'Finish practice';
$string['gamecheckanswer'] = 'Game check';
$string['gamecurrentdesign'] = 'Design';
$string['gamelayerloading'] = 'Loading game layer...';
$string['gamenextnode'] = 'Next node';
$string['gameprofile'] = 'Profile';
$string['gameruntimeerror'] = 'Runtime error';
$string['gamesettings'] = 'Game settings';
$string['gamestatusready'] = 'Game layer initialised';
$string['gameusenative'] = 'Use native controls';
$string['importdesign'] = 'Import design';
$string['label'] = 'Game label';
$string['label_help'] = 'Choose the progress space this quiz contributes to.';
$string['labelselectionnotice'] = 'If you type a new label name and select an existing label, the existing label takes precedence.';
$string['labelsettings'] = 'Label / progress space';
$string['manageassets'] = 'Manage game assets';
$string['managelabels'] = 'Manage game labels';
$string['managemechanics'] = 'Manage game mechanics';
$string['managenarratives'] = 'Manage game narratives';
$string['managethemes'] = 'Manage STACK Math Game themes';
$string['newlabel'] = 'Or create a new label';
$string['newlabel_help'] = 'Type a name to create a brand-new label. Leave blank if you selected an existing label above.';
$string['newlabelplaceholder'] = 'e.g. Algebra Term 1';
$string['nextquestion'] = 'Next question';
$string['nodesignsavailable'] = 'No active game designs found. Please ask a game designer to create or activate a design in the Game Design Studio.';
$string['play'] = 'Play STACK Math Game activities';
$string['pluginadministration'] = 'STACK Math Game administration';
$string['pluginname'] = 'STACK Math Game';
$string['prereq_behaviour'] = 'Question behaviour';
$string['prereq_behaviour_notenforced'] = 'Enforcement is switched off for this activity, so the question behaviour is not checked. The game will very likely not work.';
$string['prereq_behaviour_ok'] = 'The quiz uses the STACK Math Game behaviour.';
$string['prereq_behaviour_unknownmodule'] = 'This activity is a {$a}, not a quiz. The behaviour requirement cannot be checked.';
$string['prereq_behaviour_wrong'] = 'The quiz uses "{$a->actual}". The game needs "{$a->expected}" and will not start.';
$string['prereq_col_detail'] = 'Detail';
$string['prereq_col_requirement'] = 'Requirement';
$string['prereq_col_status'] = 'Status';
$string['prereq_design'] = 'Design';
$string['prereq_design_missing'] = 'No active design is assigned. Pick one below.';
$string['prereq_design_ok'] = 'Using the design "{$a}".';
$string['prereq_fix'] = 'Fix this';
$string['prereq_heading'] = 'Prerequisites for running a game';
$string['prereq_plugin_behaviour'] = 'Question behaviour plugin';
$string['prereq_plugin_missing'] = 'The plugin {$a} is not installed.';
$string['prereq_plugin_present'] = 'Installed.';
$string['prereq_plugin_shortcodes'] = 'Shortcodes filter';
$string['prereq_plugin_stack'] = 'STACK question type';
$string['prereq_questionmap'] = 'Question map';
$string['prereq_questionmap_noslots'] = 'The quiz has no question slots yet.';
$string['prereq_questionmap_ok'] = 'All {$a} slots are mapped to scenes.';
$string['prereq_questionmap_stale'] = '{$a->mapped} of {$a->slots} slots are mapped. The map is rebuilt automatically, but the scenes are not in step with the quiz yet.';
$string['prereq_questions'] = 'Questions';
$string['prereq_questions_mixed'] = '{$a->stack} of {$a->total} questions are STACK questions. The others are playable but produce no STACK feedback.';
$string['prereq_questions_none'] = 'The quiz contains no questions.';
$string['prereq_questions_nostack'] = 'None of the {$a} questions is a STACK question.';
$string['prereq_questions_ok'] = 'All {$a} questions are STACK questions.';
$string['prereq_status_error'] = 'Not met';
$string['prereq_status_ok'] = 'Met';
$string['prereq_status_warning'] = 'Check this';
$string['prereq_summary_blocked'] = '{$a} requirement(s) not met. The game will not start on this quiz.';
$string['prereq_summary_ok'] = 'All requirements are met. The game can start on this quiz.';
$string['prereq_summary_warnings'] = 'The game can start, but {$a} point(s) are worth checking.';
$string['privacy:metadata'] = 'STACK Math Game stores game profile and event log data per user.';
$string['privacy:metadata:eventlog'] = 'Event log recording game actions per user.';
$string['privacy:metadata:eventlog:eventtype'] = 'Event type';
$string['privacy:metadata:eventlog:labelid'] = 'Game label ID';
$string['privacy:metadata:eventlog:payloadjson'] = 'Event payload data';
$string['privacy:metadata:eventlog:questionid'] = 'Question ID';
$string['privacy:metadata:eventlog:quizid'] = 'Quiz ID';
$string['privacy:metadata:eventlog:timecreated'] = 'Event time';
$string['privacy:metadata:eventlog:userid'] = 'User ID';
$string['privacy:metadata:profile'] = 'Game profile records (score, XP, progress) stored per user per label.';
$string['privacy:metadata:profile:avatarconfigjson'] = 'Avatar configuration';
$string['privacy:metadata:profile:flagsjson'] = 'Feature flags';
$string['privacy:metadata:profile:hardcurrency'] = 'In-game hard currency';
$string['privacy:metadata:profile:labelid'] = 'Game label ID';
$string['privacy:metadata:profile:lastaccess'] = 'Last access time';
$string['privacy:metadata:profile:lastquizid'] = 'Last accessed quiz';
$string['privacy:metadata:profile:levelno'] = 'Current level';
$string['privacy:metadata:profile:progressjson'] = 'Question progress data';
$string['privacy:metadata:profile:score'] = 'Total score';
$string['privacy:metadata:profile:softcurrency'] = 'In-game soft currency';
$string['privacy:metadata:profile:statsjson'] = 'Aggregate statistics';
$string['privacy:metadata:profile:timecreated'] = 'Profile creation time';
$string['privacy:metadata:profile:userid'] = 'User ID';
$string['privacy:metadata:profile:xp'] = 'Total experience points';
$string['quiznotfound'] = 'The quiz with ID {$a} could not be found or its course activity has been deleted. Any saved game configuration for this quiz has been removed.';
$string['returnhome'] = 'Return to home';
$string['runtimemode'] = 'Mode';
$string['runtimepartial'] = 'Partial';
$string['runtimesolved'] = 'Solved';
$string['runtimetracked'] = 'Tracked';
$string['savedesign'] = 'Save design';
$string['selectdesign'] = 'Select game design';
$string['settings'] = 'Settings';
$string['settingsdesc'] = 'This plugin injects a game-oriented interface layer into STACK quiz attempts.';
$string['settingsheading'] = 'STACK Math Game';
$string['shortcode_smgavatar'] = 'Displays the current avatar payload for a game label.';
$string['shortcode_smgavatar_help'] = 'Displays the avatar payload for the current label/profile. Outside a quiz context you must pass label="...". Optional: field="avatarkey".';
$string['shortcode_smgleaderboard'] = 'Displays a simple leaderboard for a game label.';
$string['shortcode_smgleaderboard_help'] = 'Displays a leaderboard for a label. Outside a quiz context you must pass label="...". Optional: limit="10".';
$string['shortcode_smglevel'] = 'Displays the current level for a game label.';
$string['shortcode_smglevel_help'] = 'Outside a quiz context you must pass label="...". Optional: field="levelno|levelprogress".';
$string['shortcode_smgnarrative'] = 'Displays or wraps narrative content.';
$string['shortcode_smgnarrative_help'] = 'Displays narrative text from the active design. Outside a quiz context you must pass label="...". Optional: scene="world_enter|victory|defeat|boss_intro|reward" and design="designslug".';
$string['shortcode_smgprogress'] = 'Displays the current progress payload for a game label.';
$string['shortcode_smgprogress_help'] = 'Outside a quiz context you must pass label="...". Optional: format="summary|json|raw" or field="solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgscore'] = 'Displays the current score for a game label.';
$string['shortcode_smgscore_help'] = 'Outside a quiz context you must pass label="...". Optional: field="score|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcode_smgxp'] = 'Displays the current XP for a game label.';
$string['shortcode_smgxp_help'] = 'Outside a quiz context you must pass label="...". Optional: field="xp|levelno|solvedcount|partialcount|trackedslots|levelprogress".';
$string['shortcodeslabelrequired'] = 'Outside a quiz context, STACK Math Game shortcodes require a label argument.';
$string['stashmapping_desc'] = 'Assign a block_stash item to each question slot. When a student solves that slot for the first time, the item is added to their stash inventory. Requires block_stash to be installed and enabled in this course.';
$string['stashmapping_enabled'] = 'Mapping active';
$string['stashmapping_header'] = 'Stash item rewards (block_stash integration)';
$string['stashmapping_item'] = 'Stash item';
$string['stashmapping_noitem'] = '(no item – disabled)';
$string['stashmapping_noslots'] = 'No question slots found for this quiz. Add questions first.';
$string['stashmapping_qty'] = 'Quantity granted';
$string['stashmapping_slot'] = 'Slot {$a}';
$string['studio_bundled'] = 'Bundled';
$string['studio_capsummary'] = 'Your studio permissions — themes: {$a->managethemes}, narratives: {$a->managenarratives}, assets: {$a->manageassets}, mechanics: {$a->managemechanics}.';
$string['studio_hint_assets'] = 'Import asset packages as ZIP files.';
$string['studio_hint_mechanics'] = 'Configure mechanics per mode.';
$string['studio_hint_roles'] = 'Teachers select a design; Game Designers manage the library.';
$string['studio_hint_themes'] = 'Manage and preview available designs.';
$string['studio_imported'] = 'Imported';
$string['studio_importformat'] = 'Upload a ZIP file containing a manifest.json that declares the modecomponent field.';
$string['studio_importzip'] = 'Design ZIP package';
$string['studio_intro'] = 'The Game Design Studio lets you manage the visual designs, narratives, assets and mechanics used by STACK Math Game quizzes.';
$string['studio_nodesigns'] = 'No active designs found. Create one via "Edit design" or import a package.';
$string['studio_nothumbnail'] = 'No thumbnail';
$string['studio_tab_edit'] = 'Edit design';
$string['studio_tab_import'] = 'Import';
$string['studio_tab_overview'] = 'Overview';
$string['studio_title'] = 'Game Design Studio';
$string['submitansweraccepted'] = 'Answer payload accepted by the external API layer.';
$string['submitanswerfallback'] = 'Game processing fell back to passive mode.';
$string['submitanswerprocessed'] = 'Answer processed and quiz attempt updated.';
$string['subplugintype_stackmathgamemode'] = 'Game mode';
$string['subplugintype_stackmathgamemode_plural'] = 'Game modes';
$string['teacherdisplayname'] = 'Teacher-facing display name';
$string['teacherdisplayname_help'] = 'Internal label shown to teachers when managing this quiz game settings. Not shown to students.';
$string['viewstudio'] = 'View Game Design Studio';
