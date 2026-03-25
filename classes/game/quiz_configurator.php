<?php
// This file is part of Moodle - http://moodle.org/

namespace local_stackmathgame\game;

/**
 * Builds the game configuration from Moodle quiz structure + plugin overrides.
 *
 * This replaces the old approach of storing a JSON object in the last quiz question.
 * The quiz_sections become QuestionGroups; quiz_slots become Questions.
 *
 * @package    local_stackmathgame
 * @copyright  2025 Your Institution
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_configurator {

    /**
     * Load (or create) the plugin configuration record for a quiz.
     *
     * @param  int          $quizid
     * @return \stdClass|false
     */
    public static function get_plugin_config(int $quizid): \stdClass|false {
        global $DB;
        return $DB->get_record('local_stackmathgame_quiz', ['quizid' => $quizid]);
    }

    /**
     * Build the full game configuration JSON for the frontend.
     *
     * Combines:
     *  1. Moodle quiz sections (→ groups)
     *  2. Moodle quiz slots / questions (→ questions)
     *  3. Plugin DB config overrides (group names, enemy types, mechanics)
     *  4. Theme configuration
     *
     * Returns an array ready for json_encode / JS consumption.
     *
     * @param  int    $quizid
     * @param  int    $userid   Current user (needed to resolve attempt context)
     * @return array
     */
    public static function build_config(int $quizid, int $userid): array {
        global $DB;

        // 1. Plugin config record.
        $plugincfg  = self::get_plugin_config($quizid);
        $overrides  = $plugincfg ? json_decode($plugincfg->configjson ?? '{}', true) : [];
        $groupcfg   = $overrides['groups']    ?? [];
        $mechanicscfg = $overrides['mechanics'] ?? [];

        // 2. Moodle quiz sections → groups.
        $sections = $DB->get_records(
            'quiz_sections',
            ['quizid' => $quizid],
            'firstslot ASC'
        );

        // 3. Moodle quiz slots + question names.
        $slots = $DB->get_records_sql(
            "SELECT qs.id, qs.slot, qs.page, qs.requireprevious,
                    q.id AS questionid, q.name AS questionname, q.qtype,
                    qv.version AS qversion
               FROM {quiz_slots} qs
               JOIN {slot_tags} st ON st.slotid = qs.id  -- optional: for question tags
               JOIN {question_references} qr ON qr.itemid = qs.id
                    AND qr.component = 'mod_quiz'
                    AND qr.questionarea = 'slot'
               JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
               JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    AND qv.version = (
                        SELECT MAX(qv2.version)
                          FROM {question_versions} qv2
                         WHERE qv2.questionbankentryid = qbe.id
                    )
               JOIN {question} q ON q.id = qv.questionid
              WHERE qs.quizid = :quizid
              ORDER BY qs.slot ASC",
            ['quizid' => $quizid]
        );

        // 4. Build groups (sections).
        $groups     = [];
        $groupkeys  = [];
        $sectionarr = array_values($sections);
        $slotarr    = array_values($slots);
        $sectioncount = count($sectionarr);

        for ($i = 0; $i < $sectioncount; $i++) {
            $sec    = $sectionarr[$i];
            $secid  = 'section_' . $sec->id;
            $name   = !empty($groupcfg[$secid]['name'])
                      ? $groupcfg[$secid]['name']
                      : ($sec->heading ?: 'Group ' . ($i + 1));
            $enemy  = $groupcfg[$secid]['enemy'] ?? null;

            $groups[$secid] = [
                'id'          => $secid,
                'description' => $name,
                'enemy'       => $enemy, // null = theme default
                'firstslot'   => (int) $sec->firstslot,
            ];
            $groupkeys[] = $secid;
        }

        // 5. Build questions (slots), assigning to groups.
        $questions   = [];
        $pagecount   = 0;

        foreach ($slotarr as $slot) {
            // Determine which group this slot belongs to.
            $groupid = self::find_group_for_slot((int) $slot->slot, $sectionarr, $groupkeys);

            // Detect STACK questions with multiple inputs (variants handled by plugin config).
            $variants = (int) ($overrides['questions'][$slot->questionname]['variants'] ?? 1);
            $needs    = (int) ($overrides['questions'][$slot->questionname]['needs']    ?? 1);
            $color    = $overrides['questions'][$slot->questionname]['color']   ?? null;
            $filter   = $overrides['questions'][$slot->questionname]['filter']  ?? null;

            // Sanitise question ID: use slot's question name, stripped to safe chars.
            $qid = self::sanitise_question_id($slot->questionname, $slot->slot);

            $questions[$qid] = [
                'name'     => $slot->questionname,
                'group'    => $groupid,
                'page'     => $pagecount,
                'slot'     => (int) $slot->slot,
                'variants' => $variants,
                'needs'    => $needs,
                'color'    => $color,
                'filter'   => $filter,
                // onsuccess / onfailure: null = auto-chain (handled in JS)
                'onsuccess' => $overrides['questions'][$slot->questionname]['onsuccess'] ?? null,
                'onfailure' => $overrides['questions'][$slot->questionname]['onfailure'] ?? null,
                'askBeforeSkip' => (bool) ($overrides['questions'][$slot->questionname]['askBeforeSkip'] ?? false),
                'BubbleInfo' => $overrides['questions'][$slot->questionname]['BubbleInfo'] ?? null,
            ];

            $pagecount += $variants;
        }

        // 6. Mechanics config (scores, etc.).
        $mechanics = array_merge([
            'mana_start'       => 20,
            'mana_on_fail'     => -3,
            'fairies_on_win'   => 1,
        ], $mechanicscfg);

        return [
            'groups'    => $groups,
            'questions' => $questions,
            'mechanics' => $mechanics,
        ];
    }

    /**
     * Find the group ID for a given slot number based on section firstslot.
     */
    private static function find_group_for_slot(
        int $slotnum,
        array $sections,
        array $groupkeys
    ): string {
        $lastgroupidx = 0;
        foreach ($sections as $i => $sec) {
            if ((int) $sec->firstslot <= $slotnum) {
                $lastgroupidx = $i;
            }
        }
        return $groupkeys[$lastgroupidx] ?? ($groupkeys[0] ?? 'unsorted');
    }

    /**
     * Sanitise a question name into a safe JS/JSON key.
     * Falls back to 'q{slot}' if name is empty or contains only unsafe chars.
     */
    private static function sanitise_question_id(string $name, int $slot): string {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        $safe = trim($safe, '_');
        return $safe ?: 'q' . $slot;
    }

    /**
     * Save (create or update) the plugin configuration for a quiz.
     *
     * @param  int    $quizid
     * @param  int    $labelid
     * @param  int    $themeid
     * @param  array  $configarray  Raw config overrides array
     * @param  bool   $enabled
     */
    public static function save_plugin_config(
        int $quizid,
        int $labelid,
        int $themeid,
        array $configarray,
        bool $enabled
    ): void {
        global $DB;

        $now  = time();
        $existing = $DB->get_record('local_stackmathgame_quiz', ['quizid' => $quizid], 'id');

        $row = (object) [
            'quizid'       => $quizid,
            'labelid'      => $labelid > 0 ? $labelid : null,
            'themeid'      => $themeid > 0 ? $themeid  : null,
            'configjson'   => json_encode($configarray, JSON_UNESCAPED_UNICODE),
            'enabled'      => (int) $enabled,
            'timemodified' => $now,
        ];

        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_stackmathgame_quiz', $row);
        } else {
            $row->timecreated = $now;
            $DB->insert_record('local_stackmathgame_quiz', $row);
        }
    }
}
