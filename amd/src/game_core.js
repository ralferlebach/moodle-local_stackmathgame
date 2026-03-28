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
 * Shared game-core helpers for local_stackmathgame mode subplugins.
 *
 * Provides the defaultConfig() helper that all mode subplugins use when
 * a questionmap row has no configjson, and any future shared utilities.
 *
 * All three game modules (stackmathgamemode_exitgames/game,
 * stackmathgamemode_wisewizzard/game, stackmathgamemode_rpg/game) depend
 * on this module via require(['local_stackmathgame/game_core']).
 *
 * @module     local_stackmathgame/game_core
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    /**
     * Return a safe default slot config matching slot_config_schema::defaults().
     *
     * Used by mode subplugins when a questionmap row has no configjson.
     *
     * @returns {Object} Default config with linear branching and empty narrative.
     */
    function defaultConfig() {
        return {
            version: 1,
            enabled: true,
            scene: {type: 'challenge'},
            branching: {
                gradedright: {mode: 'linear'},
                gradedwrong: {mode: 'linear'},
                complete:    {mode: 'linear'},
                default:     {mode: 'linear'}
            },
            rewards: {
                score: 0,
                xp: 0,
                achievementkeys: [],
                badgeids: [],
                stash: []
            },
            narrative: {intro: '', success: '', fail: ''},
            display: {showxp: true, showinventory: false, showavatar: false}
        };
    }

    return {defaultConfig: defaultConfig};
});
