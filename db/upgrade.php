<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_stackmathgame.
 *
 * This upgrade path migrates the early scaffold schema
 *   - local_stackmathgame_cfg
 *   - local_stackmathgame_theme
 * to the host-oriented schema based on labels, designs and quiz configuration.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_stackmathgame_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026032501) {
        $now = time();

        // 1) Create new host tables.
        $labeltable = new xmldb_table('local_stackmathgame_label');
        if (!$dbman->table_exists($labeltable)) {
            $labeltable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $labeltable->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $labeltable->add_field('idnumber', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $labeltable->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $labeltable->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $labeltable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $labeltable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $labeltable->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $labeltable->add_field('timedeleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $labeltable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $labeltable->add_key('createdby_fk', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
            $labeltable->add_index('name_uix', XMLDB_INDEX_UNIQUE, ['name']);
            $labeltable->add_index('idnumber_uix', XMLDB_INDEX_UNIQUE, ['idnumber']);
            $labeltable->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $labeltable->add_index('timedeleted_ix', XMLDB_INDEX_NOTUNIQUE, ['timedeleted']);
            $dbman->create_table($labeltable);
        }

        $designtable = new xmldb_table('local_stackmathgame_design');
        if (!$dbman->table_exists($designtable)) {
            $designtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $designtable->add_field('name', XMLDB_TYPE_CHAR, '150', null, XMLDB_NOTNULL, null, null);
            $designtable->add_field('slug', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $designtable->add_field('modecomponent', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $designtable->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $designtable->add_field('thumbnailfilename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $designtable->add_field('thumbnailfileitemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $designtable->add_field('isbundled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
            $designtable->add_field('isactive', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $designtable->add_field('versioncode', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $designtable->add_field('narrativejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $designtable->add_field('uijson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $designtable->add_field('mechanicsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $designtable->add_field('assetmanifestjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $designtable->add_field('importmetajson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $designtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $designtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $designtable->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $designtable->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $designtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $designtable->add_key('createdby_fk', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
            $designtable->add_key('modifiedby_fk', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);
            $designtable->add_index('slug_uix', XMLDB_INDEX_UNIQUE, ['slug']);
            $designtable->add_index('modename_uix', XMLDB_INDEX_UNIQUE, ['modecomponent', 'name']);
            $designtable->add_index('modeactive_ix', XMLDB_INDEX_NOTUNIQUE, ['modecomponent', 'isactive']);
            $dbman->create_table($designtable);
        }

        $profiletable = new xmldb_table('local_stackmathgame_profile');
        if (!$dbman->table_exists($profiletable)) {
            $profiletable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $profiletable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $profiletable->add_field('labelid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $profiletable->add_field('score', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $profiletable->add_field('xp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $profiletable->add_field('levelno', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $profiletable->add_field('softcurrency', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $profiletable->add_field('hardcurrency', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $profiletable->add_field('avatarconfigjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $profiletable->add_field('progressjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $profiletable->add_field('statsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $profiletable->add_field('flagsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $profiletable->add_field('lastquizid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $profiletable->add_field('lastdesignid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $profiletable->add_field('lastaccess', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $profiletable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $profiletable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $profiletable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $profiletable->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $profiletable->add_key('labelid_fk', XMLDB_KEY_FOREIGN, ['labelid'], 'local_stackmathgame_label', ['id']);
            $profiletable->add_key('lastquizid_fk', XMLDB_KEY_FOREIGN, ['lastquizid'], 'quiz', ['id']);
            $profiletable->add_key('lastdesignid_fk', XMLDB_KEY_FOREIGN, ['lastdesignid'], 'local_stackmathgame_design', ['id']);
            $profiletable->add_index('userlabel_uix', XMLDB_INDEX_UNIQUE, ['userid', 'labelid']);
            $profiletable->add_index('label_score_ix', XMLDB_INDEX_NOTUNIQUE, ['labelid', 'score']);
            $profiletable->add_index('label_xp_ix', XMLDB_INDEX_NOTUNIQUE, ['labelid', 'xp']);
            $profiletable->add_index('label_level_ix', XMLDB_INDEX_NOTUNIQUE, ['labelid', 'levelno']);
            $dbman->create_table($profiletable);
        }

        $quizcfgtable = new xmldb_table('local_stackmathgame_quizcfg');
        if (!$dbman->table_exists($quizcfgtable)) {
            $quizcfgtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $quizcfgtable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $quizcfgtable->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $quizcfgtable->add_field('labelid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $quizcfgtable->add_field('designid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $quizcfgtable->add_field('enabled', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
            $quizcfgtable->add_field('requiresbehaviour', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $quizcfgtable->add_field('configjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $quizcfgtable->add_field('teacherdisplayname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $quizcfgtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $quizcfgtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $quizcfgtable->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $quizcfgtable->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $quizcfgtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $quizcfgtable->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $quizcfgtable->add_key('quizid_fk', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);
            $quizcfgtable->add_key('labelid_fk', XMLDB_KEY_FOREIGN, ['labelid'], 'local_stackmathgame_label', ['id']);
            $quizcfgtable->add_key('designid_fk', XMLDB_KEY_FOREIGN, ['designid'], 'local_stackmathgame_design', ['id']);
            $quizcfgtable->add_key('createdby_fk', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
            $quizcfgtable->add_key('modifiedby_fk', XMLDB_KEY_FOREIGN, ['modifiedby'], 'user', ['id']);
            $quizcfgtable->add_index('quizid_uix', XMLDB_INDEX_UNIQUE, ['quizid']);
            $quizcfgtable->add_index('labelid_ix', XMLDB_INDEX_NOTUNIQUE, ['labelid']);
            $quizcfgtable->add_index('designid_ix', XMLDB_INDEX_NOTUNIQUE, ['designid']);
            $quizcfgtable->add_index('enabled_ix', XMLDB_INDEX_NOTUNIQUE, ['enabled']);
            $dbman->create_table($quizcfgtable);
        }

        $designassettable = new xmldb_table('local_stackmathgame_design_asset');
        if (!$dbman->table_exists($designassettable)) {
            $designassettable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $designassettable->add_field('designid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $designassettable->add_field('assetkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $designassettable->add_field('assettype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $designassettable->add_field('storagekind', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $designassettable->add_field('filepath', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $designassettable->add_field('filename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $designassettable->add_field('filearea', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $designassettable->add_field('mimetype', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $designassettable->add_field('metadatajson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $designassettable->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $designassettable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $designassettable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $designassettable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $designassettable->add_key('designid_fk', XMLDB_KEY_FOREIGN, ['designid'], 'local_stackmathgame_design', ['id']);
            $designassettable->add_index('designasset_uix', XMLDB_INDEX_UNIQUE, ['designid', 'assetkey']);
            $designassettable->add_index('designasset_type_ix', XMLDB_INDEX_NOTUNIQUE, ['designid', 'assettype']);
            $dbman->create_table($designassettable);
        }

        $questionmaptable = new xmldb_table('local_stackmathgame_questionmap');
        if (!$dbman->table_exists($questionmaptable)) {
            $questionmaptable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $questionmaptable->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $questionmaptable->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $questionmaptable->add_field('slotnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $questionmaptable->add_field('designid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $questionmaptable->add_field('nodekey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $questionmaptable->add_field('nodetype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $questionmaptable->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $questionmaptable->add_field('configjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $questionmaptable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $questionmaptable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $questionmaptable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $questionmaptable->add_key('quizid_fk', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);
            $questionmaptable->add_key('questionid_fk', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
            $questionmaptable->add_key('designid_fk', XMLDB_KEY_FOREIGN, ['designid'], 'local_stackmathgame_design', ['id']);
            $questionmaptable->add_index('quizslot_uix', XMLDB_INDEX_UNIQUE, ['quizid', 'slotnumber']);
            $questionmaptable->add_index('quiznodekey_uix', XMLDB_INDEX_UNIQUE, ['quizid', 'nodekey']);
            $questionmaptable->add_index('quiznodetype_ix', XMLDB_INDEX_NOTUNIQUE, ['quizid', 'nodetype']);
            $dbman->create_table($questionmaptable);
        }

        $eventlogtable = new xmldb_table('local_stackmathgame_eventlog');
        if (!$dbman->table_exists($eventlogtable)) {
            $eventlogtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $eventlogtable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $eventlogtable->add_field('labelid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $eventlogtable->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $eventlogtable->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $eventlogtable->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $eventlogtable->add_field('designid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $eventlogtable->add_field('eventtype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $eventlogtable->add_field('source', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $eventlogtable->add_field('valueint', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $eventlogtable->add_field('valuechar', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $eventlogtable->add_field('payloadjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $eventlogtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $eventlogtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $eventlogtable->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $eventlogtable->add_key('labelid_fk', XMLDB_KEY_FOREIGN, ['labelid'], 'local_stackmathgame_label', ['id']);
            $eventlogtable->add_key('quizid_fk', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);
            $eventlogtable->add_key('questionid_fk', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
            $eventlogtable->add_key('profileid_fk', XMLDB_KEY_FOREIGN, ['profileid'], 'local_stackmathgame_profile', ['id']);
            $eventlogtable->add_key('designid_fk', XMLDB_KEY_FOREIGN, ['designid'], 'local_stackmathgame_design', ['id']);
            $eventlogtable->add_index('label_event_ix', XMLDB_INDEX_NOTUNIQUE, ['labelid', 'eventtype']);
            $eventlogtable->add_index('user_label_time_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'labelid', 'timecreated']);
            $dbman->create_table($eventlogtable);
        }

        $inventorytable = new xmldb_table('local_stackmathgame_inventory');
        if (!$dbman->table_exists($inventorytable)) {
            $inventorytable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $inventorytable->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $inventorytable->add_field('itemkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $inventorytable->add_field('quantity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $inventorytable->add_field('statejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $inventorytable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $inventorytable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $inventorytable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $inventorytable->add_key('profileid_fk', XMLDB_KEY_FOREIGN, ['profileid'], 'local_stackmathgame_profile', ['id']);
            $inventorytable->add_index('profileitem_uix', XMLDB_INDEX_UNIQUE, ['profileid', 'itemkey']);
            $dbman->create_table($inventorytable);
        }

        $boostertable = new xmldb_table('local_stackmathgame_booster');
        if (!$dbman->table_exists($boostertable)) {
            $boostertable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $boostertable->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $boostertable->add_field('boosterkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $boostertable->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
            $boostertable->add_field('charges', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boostertable->add_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $boostertable->add_field('configjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $boostertable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boostertable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $boostertable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $boostertable->add_key('profileid_fk', XMLDB_KEY_FOREIGN, ['profileid'], 'local_stackmathgame_profile', ['id']);
            $boostertable->add_index('profilestatus_ix', XMLDB_INDEX_NOTUNIQUE, ['profileid', 'status']);
            $boostertable->add_index('expiresat_ix', XMLDB_INDEX_NOTUNIQUE, ['expiresat']);
            $dbman->create_table($boostertable);
        }

        $avatartable = new xmldb_table('local_stackmathgame_avatar_upgrade');
        if (!$dbman->table_exists($avatartable)) {
            $avatartable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $avatartable->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $avatartable->add_field('upgradekey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $avatartable->add_field('tierno', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $avatartable->add_field('statejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $avatartable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $avatartable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $avatartable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $avatartable->add_key('profileid_fk', XMLDB_KEY_FOREIGN, ['profileid'], 'local_stackmathgame_profile', ['id']);
            $avatartable->add_index('profileupgrade_uix', XMLDB_INDEX_UNIQUE, ['profileid', 'upgradekey']);
            $dbman->create_table($avatartable);
        }

        $achievementtable = new xmldb_table('local_stackmathgame_achievement');
        if (!$dbman->table_exists($achievementtable)) {
            $achievementtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $achievementtable->add_field('achievementkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $achievementtable->add_field('name', XMLDB_TYPE_CHAR, '150', null, XMLDB_NOTNULL, null, null);
            $achievementtable->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $achievementtable->add_field('labelscope', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $achievementtable->add_field('modecomponent', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $achievementtable->add_field('configjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $achievementtable->add_field('isactive', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $achievementtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $achievementtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $achievementtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $achievementtable->add_index('achievementkey_uix', XMLDB_INDEX_UNIQUE, ['achievementkey']);
            $achievementtable->add_index('modeactive_ix', XMLDB_INDEX_NOTUNIQUE, ['modecomponent', 'isactive']);
            $dbman->create_table($achievementtable);
        }

        $profileachievementtable = new xmldb_table('local_stackmathgame_profile_achievement');
        if (!$dbman->table_exists($profileachievementtable)) {
            $profileachievementtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $profileachievementtable->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $profileachievementtable->add_field('achievementid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $profileachievementtable->add_field('earnedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $profileachievementtable->add_field('progressvalue', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $profileachievementtable->add_field('statejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $profileachievementtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $profileachievementtable->add_key('profileid_fk', XMLDB_KEY_FOREIGN, ['profileid'], 'local_stackmathgame_profile', ['id']);
            $profileachievementtable->add_key('achievementid_fk', XMLDB_KEY_FOREIGN, ['achievementid'], 'local_stackmathgame_achievement', ['id']);
            $profileachievementtable->add_index('profileachievement_uix', XMLDB_INDEX_UNIQUE, ['profileid', 'achievementid']);
            $dbman->create_table($profileachievementtable);
        }

        $shoptable = new xmldb_table('local_stackmathgame_shop_transaction');
        if (!$dbman->table_exists($shoptable)) {
            $shoptable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $shoptable->add_field('profileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $shoptable->add_field('transactiontype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $shoptable->add_field('itemkey', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $shoptable->add_field('currencytype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $shoptable->add_field('amount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $shoptable->add_field('payloadjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $shoptable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $shoptable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $shoptable->add_key('profileid_fk', XMLDB_KEY_FOREIGN, ['profileid'], 'local_stackmathgame_profile', ['id']);
            $shoptable->add_index('profiletype_ix', XMLDB_INDEX_NOTUNIQUE, ['profileid', 'transactiontype']);
            $shoptable->add_index('timecreated_ix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($shoptable);
        }

        $packagetable = new xmldb_table('local_stackmathgame_design_package');
        if (!$dbman->table_exists($packagetable)) {
            $packagetable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $packagetable->add_field('designid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $packagetable->add_field('packageidentifier', XMLDB_TYPE_CHAR, '150', null, XMLDB_NOTNULL, null, null);
            $packagetable->add_field('packageversion', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $packagetable->add_field('manifestjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $packagetable->add_field('checksum', XMLDB_TYPE_CHAR, '128', null, null, null, null);
            $packagetable->add_field('origin', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $packagetable->add_field('importedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $packagetable->add_field('exportedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $packagetable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $packagetable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $packagetable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $packagetable->add_key('designid_fk', XMLDB_KEY_FOREIGN, ['designid'], 'local_stackmathgame_design', ['id']);
            $packagetable->add_key('importedby_fk', XMLDB_KEY_FOREIGN, ['importedby'], 'user', ['id']);
            $packagetable->add_key('exportedby_fk', XMLDB_KEY_FOREIGN, ['exportedby'], 'user', ['id']);
            $packagetable->add_index('designversion_uix', XMLDB_INDEX_UNIQUE, ['designid', 'packageversion']);
            $packagetable->add_index('packageidentifier_ix', XMLDB_INDEX_NOTUNIQUE, ['packageidentifier']);
            $dbman->create_table($packagetable);
        }

        // 2) Migrate legacy themes to designs if present.
        $legacythemetable = new xmldb_table('local_stackmathgame_theme');
        $designmap = [];
        if ($dbman->table_exists($legacythemetable)) {
            $legacythemes = $DB->get_records('local_stackmathgame_theme', null, 'id ASC');
            foreach ($legacythemes as $legacytheme) {
                $slug = clean_param($legacytheme->shortname ?: ('legacy-theme-' . $legacytheme->id), PARAM_ALPHANUMEXT);
                if ($slug === '') {
                    $slug = 'legacy-theme-' . $legacytheme->id;
                }
                $base = $slug;
                $counter = 1;
                while ($DB->record_exists('local_stackmathgame_design', ['slug' => $slug])) {
                    $counter++;
                    $slug = $base . '-' . $counter;
                }
                $record = (object)[
                    'name' => $legacytheme->name,
                    'slug' => $slug,
                    'modecomponent' => 'stackmathgamemode_rpg',
                    'description' => null,
                    'thumbnailfilename' => null,
                    'thumbnailfileitemid' => null,
                    'isbundled' => !empty($legacytheme->isbuiltin) ? 1 : 0,
                    'isactive' => !empty($legacytheme->enabled) ? 1 : 0,
                    'versioncode' => 1,
                    'narrativejson' => null,
                    'uijson' => $legacytheme->configjson,
                    'mechanicsjson' => null,
                    'assetmanifestjson' => null,
                    'importmetajson' => json_encode(['legacythemeid' => $legacytheme->id]),
                    'timecreated' => $legacytheme->timecreated ?? $now,
                    'timemodified' => $legacytheme->timemodified ?? $now,
                    'createdby' => null,
                    'modifiedby' => null,
                ];
                $newdesignid = $DB->insert_record('local_stackmathgame_design', $record);
                $designmap[(int)$legacytheme->id] = (int)$newdesignid;
            }
        }

        // 3) Migrate legacy cfg rows to labels + quizcfg.
        $legacycfgtable = new xmldb_table('local_stackmathgame_cfg');
        $labelmap = [];
        if ($dbman->table_exists($legacycfgtable)) {
            $legacycfgs = $DB->get_records('local_stackmathgame_cfg', null, 'id ASC');
            foreach ($legacycfgs as $legacycfg) {
                $legacylabel = (int)($legacycfg->labelid ?? 0);
                if (!isset($labelmap[$legacylabel])) {
                    $labelname = $legacylabel > 0 ? 'legacy-label-' . $legacylabel : 'default';
                    $existing = $DB->get_record('local_stackmathgame_label', ['name' => $labelname]);
                    if ($existing) {
                        $labelmap[$legacylabel] = (int)$existing->id;
                    } else {
                        $labelmap[$legacylabel] = (int)$DB->insert_record('local_stackmathgame_label', (object)[
                            'name' => $labelname,
                            'idnumber' => $legacylabel > 0 ? 'legacy-' . $legacylabel : 'legacy-default',
                            'description' => 'Auto-migrated from legacy local_stackmathgame_cfg.labelid',
                            'status' => 1,
                            'timecreated' => $now,
                            'timemodified' => $now,
                            'createdby' => null,
                            'timedeleted' => null,
                        ]);
                    }
                }

                $quiz = $DB->get_record('quiz', ['id' => $legacycfg->quizid], 'id,course', IGNORE_MISSING);
                $courseid = $quiz ? (int)$quiz->course : 0;
                $legacythemeid = (int)($legacycfg->themeid ?? 0);
                $designid = $designmap[$legacythemeid] ?? null;
                if (!$designid) {
                    // Ensure there is at least one generic design to anchor migrated configs.
                    $fallback = $DB->get_record('local_stackmathgame_design', ['slug' => 'legacy-default-design']);
                    if (!$fallback) {
                        $fallbackid = $DB->insert_record('local_stackmathgame_design', (object)[
                            'name' => 'Legacy Default Design',
                            'slug' => 'legacy-default-design',
                            'modecomponent' => 'stackmathgamemode_exitgames',
                            'description' => 'Fallback design created during legacy migration.',
                            'thumbnailfilename' => null,
                            'thumbnailfileitemid' => null,
                            'isbundled' => 0,
                            'isactive' => 1,
                            'versioncode' => 1,
                            'narrativejson' => null,
                            'uijson' => null,
                            'mechanicsjson' => null,
                            'assetmanifestjson' => null,
                            'importmetajson' => json_encode(['source' => 'upgrade-fallback']),
                            'timecreated' => $now,
                            'timemodified' => $now,
                            'createdby' => null,
                            'modifiedby' => null,
                        ]);
                        $fallback = (object)['id' => $fallbackid];
                    }
                    $designid = (int)$fallback->id;
                }

                if (!$DB->record_exists('local_stackmathgame_quizcfg', ['quizid' => $legacycfg->quizid])) {
                    $DB->insert_record('local_stackmathgame_quizcfg', (object)[
                        'courseid' => $courseid,
                        'quizid' => $legacycfg->quizid,
                        'labelid' => $labelmap[$legacylabel],
                        'designid' => $designid,
                        'enabled' => !empty($legacycfg->enabled) ? 1 : 0,
                        'requiresbehaviour' => 1,
                        'configjson' => $legacycfg->configjson,
                        'teacherdisplayname' => null,
                        'timecreated' => $legacycfg->timecreated ?? $now,
                        'timemodified' => $legacycfg->timemodified ?? $now,
                        'createdby' => null,
                        'modifiedby' => null,
                    ]);
                }
            }
        }

        // 4) Drop legacy scaffold tables after successful migration.
        if ($dbman->table_exists($legacycfgtable)) {
            $dbman->drop_table($legacycfgtable);
        }
        if ($dbman->table_exists($legacythemetable)) {
            $dbman->drop_table($legacythemetable);
        }

        upgrade_plugin_savepoint(true, 2026032501, 'local', 'stackmathgame');
    }

    return true;
}
