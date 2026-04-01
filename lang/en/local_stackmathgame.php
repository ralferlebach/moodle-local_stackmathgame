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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings for local_stackmathgame — PATCH ADDITION.
 *
 * This file adds strings for the per-slot Regiekarte (slot config) section
 * introduced in patch 2026032830. Merge with the existing lang file.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ── Per-slot Regiekarte section ───────────────────────────────────────────
$string['slotconfig_header'] = 'Slot configuration (Regiekarte)';
$string['slotconfig_desc'] = 'Configure scene type, branching, narrative text, and rewards per question slot. These settings drive the adaptive game mechanics.';
$string['slotconfig_slot'] = 'Slot {$a}';

// Scene types.
$string['slotconfig_scenetype'] = 'Scene type';
$string['slotconfig_scenetype_instruction'] = 'Instruction (no answer required)';
$string['slotconfig_scenetype_challenge'] = 'Challenge';
$string['slotconfig_scenetype_miniboss'] = 'Mini-boss';
$string['slotconfig_scenetype_boss'] = 'Boss';
$string['slotconfig_scenetype_reward'] = 'Reward scene';
$string['slotconfig_scenetype_transition'] = 'Transition';
$string['slotconfig_scenetype_outro'] = 'Outro';

// Branching.
$string['slotconfig_branch_gradedright'] = 'On correct answer';
$string['slotconfig_branch_gradedwrong'] = 'On incorrect answer';
$string['slotconfig_branch_default'] = 'Default / fallback';
$string['slotconfig_branch_linear'] = 'Continue in order';
$string['slotconfig_branch_slot'] = 'Jump to slot →';
$string['slotconfig_branch_end'] = 'End quiz';
$string['slotconfig_branch_target_placeholder'] = 'Slot#';

// Narrative.
$string['slotconfig_narrative_intro'] = 'Intro text (shown before answering)';
$string['slotconfig_narrative_success'] = 'Success text (correct answer)';
$string['slotconfig_narrative_fail'] = 'Fail text (incorrect answer)';

// Rewards.
$string['slotconfig_rewards'] = 'Rewards (Score / XP)';
$string['slotconfig_rewards_sep'] = 'Score · XP';
