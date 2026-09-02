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
 * Prerequisite checks for running a game on a concrete activity.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\local\service;

use local_stackmathgame\game\quiz_configurator;
use local_stackmathgame\game\theme_manager;

/**
 * Decides whether a game may run on a given activity, and explains why not.
 *
 * A plugin dependency in version.php only guarantees that qbehaviour_stackmathgame is
 * installed somewhere on the site. It says nothing about the quiz in front of the teacher.
 * A quiz whose preferredbehaviour is something else will render the attempt page, load the
 * game engine and then behave like an ordinary quiz - which looks like a broken plugin rather
 * than a misconfigured activity. This service turns that into a stated, visible reason.
 *
 * Every check returns one of three severities:
 *   STATUS_OK       - satisfied.
 *   STATUS_WARNING  - the game runs, but not as intended.
 *   STATUS_ERROR    - blocking; the game must not start.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class prerequisite_checker {
    /** Check passed. */
    const STATUS_OK = 'ok';
    /** Check passed, but the result is not what the teacher probably intended. */
    const STATUS_WARNING = 'warning';
    /** Check failed in a way that must prevent the game from starting. */
    const STATUS_ERROR = 'error';

    /** The question behaviour this plugin's pageless answer flow depends on. */
    const REQUIRED_BEHAVIOUR = 'stackmathgame';

    /** The question type the game is built around. */
    const REQUIRED_QTYPE = 'stack';

    /**
     * Run every prerequisite check for an activity.
     *
     * @param int $cmid The course-module ID.
     * @return array[] List of checks, each with keys: key, status, label, message, fixurl.
     */
    public static function check(int $cmid): array {
        $cm = quiz_configurator::get_supported_cm($cmid);
        $config = quiz_configurator::get_plugin_config($cmid);

        $checks = [];
        $checks[] = self::check_plugin('qbehaviour_stackmathgame', 'prereq_plugin_behaviour');
        $checks[] = self::check_behaviour_archetypal();
        $checks[] = self::check_plugin('qtype_stack', 'prereq_plugin_stack');
        $checks[] = self::check_plugin('filter_shortcodes', 'prereq_plugin_shortcodes');
        $checks[] = self::check_behaviour($cm, $config);
        $checks[] = self::check_questions($cm);
        $checks[] = self::check_questionmap($cmid, $cm);
        $checks[] = self::check_design($config);

        return $checks;
    }

    /**
     * Return whether the game may start on this activity.
     *
     * @param int $cmid The course-module ID.
     * @return bool True when no check failed with STATUS_ERROR.
     */
    public static function is_playable(int $cmid): bool {
        foreach (self::check($cmid) as $check) {
            if ($check['status'] === self::STATUS_ERROR) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return the blocking checks only.
     *
     * @param int $cmid The course-module ID.
     * @return array[] The subset of checks with STATUS_ERROR.
     */
    public static function get_blockers(int $cmid): array {
        return array_values(array_filter(
            self::check($cmid),
            static fn(array $check): bool => $check['status'] === self::STATUS_ERROR
        ));
    }

    /**
     * Check whether a required plugin is installed.
     *
     * @param string $component The frankenstyle component name.
     * @param string $labelkey The language string key for the check label.
     * @return array The check result.
     */
    private static function check_plugin(string $component, string $labelkey): array {
        $installed = \core_component::get_component_directory($component) !== null;
        return self::result(
            $component,
            $installed ? self::STATUS_OK : self::STATUS_ERROR,
            $labelkey,
            $installed ? 'prereq_plugin_present' : 'prereq_plugin_missing',
            $component
        );
    }

    /**
     * Check that the question behaviour is usable as a quiz's preferred behaviour.
     *
     * A behaviour type that does not declare itself archetypal is invisible twice over: Moodle
     * leaves it out of the quiz "Question behaviour" menu, so a teacher cannot pick it at all,
     * and question_engine::make_archetypal_behaviour() throws a coding exception if the value
     * gets into the database some other way. The failure then appears when a student starts an
     * attempt, which is as far from the cause as it could land.
     *
     * The fix belongs in qbehaviour_stackmathgame, not here. This check exists so the problem is
     * stated on the settings page instead of being discovered mid-attempt.
     *
     * @return array The check result.
     */
    private static function check_behaviour_archetypal(): array {
        if (\core_component::get_component_directory('qbehaviour_stackmathgame') === null) {
            // Already reported by the plugin-presence check; saying it twice helps nobody.
            return self::result(
                'archetypal',
                self::STATUS_OK,
                'prereq_archetypal',
                'prereq_archetypal_skipped'
            );
        }

        $archetypes = \question_engine::get_archetypal_behaviours();
        if (array_key_exists(self::REQUIRED_BEHAVIOUR, $archetypes)) {
            return self::result('archetypal', self::STATUS_OK, 'prereq_archetypal', 'prereq_archetypal_ok');
        }

        return self::result(
            'archetypal',
            self::STATUS_ERROR,
            'prereq_archetypal',
            'prereq_archetypal_missing'
        );
    }

    /**
     * Check the activity's question behaviour against the one the runtime needs.
     *
     * The check is skipped when the stored configuration has requiresbehaviour = 0, which is
     * the only way an administrator can deliberately run the game on a quiz using a different
     * behaviour. It is not exposed in the teacher form: turning it off silently was what made
     * this bug invisible in the first place.
     *
     * @param \stdClass $cm The course-module record.
     * @param \stdClass|null $config The game configuration record.
     * @return array The check result.
     */
    private static function check_behaviour(\stdClass $cm, ?\stdClass $config): array {
        global $DB;

        if ($config && (int)$config->requiresbehaviour === 0) {
            return self::result(
                'behaviour',
                self::STATUS_WARNING,
                'prereq_behaviour',
                'prereq_behaviour_notenforced'
            );
        }

        if ($cm->modname !== 'quiz') {
            return self::result(
                'behaviour',
                self::STATUS_WARNING,
                'prereq_behaviour',
                'prereq_behaviour_unknownmodule',
                $cm->modname
            );
        }

        $behaviour = (string)$DB->get_field('quiz', 'preferredbehaviour', ['id' => $cm->instance]);
        if ($behaviour === self::REQUIRED_BEHAVIOUR) {
            return self::result('behaviour', self::STATUS_OK, 'prereq_behaviour', 'prereq_behaviour_ok');
        }

        return self::result(
            'behaviour',
            self::STATUS_ERROR,
            'prereq_behaviour',
            'prereq_behaviour_wrong',
            (object)['actual' => $behaviour, 'expected' => self::REQUIRED_BEHAVIOUR],
            new \moodle_url('/course/modedit.php', ['update' => $cm->id])
        );
    }

    /**
     * Check that the activity actually contains STACK questions.
     *
     * @param \stdClass $cm The course-module record.
     * @return array The check result.
     */
    private static function check_questions(\stdClass $cm): array {
        $counts = self::count_question_types((int)$cm->instance);
        $stack = $counts[self::REQUIRED_QTYPE] ?? 0;
        $total = array_sum($counts);

        if ($total === 0) {
            return self::result(
                'questions',
                self::STATUS_ERROR,
                'prereq_questions',
                'prereq_questions_none',
                null,
                new \moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id])
            );
        }

        if ($stack === 0) {
            return self::result(
                'questions',
                self::STATUS_ERROR,
                'prereq_questions',
                'prereq_questions_nostack',
                $total,
                new \moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id])
            );
        }

        if ($stack < $total) {
            // Not blocking. Other question types are answered through the same pageless submit
            // and simply do not produce STACK feedback, which is a limitation rather than a
            // fault - but it is one a teacher should be told about rather than discover.
            return self::result(
                'questions',
                self::STATUS_WARNING,
                'prereq_questions',
                'prereq_questions_mixed',
                (object)['stack' => $stack, 'total' => $total]
            );
        }

        return self::result('questions', self::STATUS_OK, 'prereq_questions', 'prereq_questions_ok', $stack);
    }

    /**
     * Check that the question map covers every slot of the activity.
     *
     * @param int $cmid The course-module ID.
     * @param \stdClass $cm The course-module record.
     * @return array The check result.
     */
    private static function check_questionmap(int $cmid, \stdClass $cm): array {
        global $DB;

        $slots = count(question_map_service::get_quiz_slot_records((int)$cm->instance));
        $mapped = $DB->count_records('local_stackmathgame_questionmap', ['cmid' => $cmid]);

        if ($slots === 0) {
            return self::result('questionmap', self::STATUS_WARNING, 'prereq_questionmap', 'prereq_questionmap_noslots');
        }
        if ($mapped < $slots) {
            // A warning rather than an error: the map is rebuilt on demand by the services that
            // need it, so a stale map self-heals. Reporting it still matters, because a teacher
            // who has just edited the quiz wants to know the scenes are not yet in step.
            return self::result(
                'questionmap',
                self::STATUS_WARNING,
                'prereq_questionmap',
                'prereq_questionmap_stale',
                (object)['mapped' => $mapped, 'slots' => $slots]
            );
        }

        return self::result(
            'questionmap',
            self::STATUS_OK,
            'prereq_questionmap',
            'prereq_questionmap_ok',
            $mapped
        );
    }

    /**
     * Check that an active design is assigned.
     *
     * @param \stdClass|null $config The game configuration record.
     * @return array The check result.
     */
    private static function check_design(?\stdClass $config): array {
        $designid = (int)($config->designid ?? 0);
        $design = $designid > 0 ? theme_manager::get_theme($designid) : null;
        if (!$design) {
            return self::result('design', self::STATUS_ERROR, 'prereq_design', 'prereq_design_missing');
        }
        return self::result('design', self::STATUS_OK, 'prereq_design', 'prereq_design_ok', $design->name);
    }

    /**
     * Count questions per question type in an activity.
     *
     * Uses question_map_service to resolve slots, so the Moodle 4.x question-reference schema
     * is handled in exactly one place rather than being reimplemented here.
     *
     * @param int $instanceid The quiz instance ID.
     * @return array<string, int> Map of qtype to count.
     */
    private static function count_question_types(int $instanceid): array {
        global $DB;

        $slots = question_map_service::get_quiz_slot_records($instanceid);
        $questionids = [];
        foreach ($slots as $slot) {
            if ((int)$slot->questionid > 0) {
                $questionids[] = (int)$slot->questionid;
            }
        }
        if (!$questionids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $qtypes = $DB->get_fieldset_select('question', 'qtype', 'id ' . $insql, $params);

        $counts = [];
        foreach ($qtypes as $qtype) {
            $counts[(string)$qtype] = ($counts[(string)$qtype] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * Build one check result.
     *
     * @param string $key Stable identifier for the check.
     * @param string $status One of the STATUS_* constants.
     * @param string $labelkey Language string key for the check name.
     * @param string $messagekey Language string key for the outcome.
     * @param mixed $a Optional parameter for the message string.
     * @param \moodle_url|null $fixurl Optional link to where the problem can be fixed.
     * @return array The check result.
     */
    private static function result(
        string $key,
        string $status,
        string $labelkey,
        string $messagekey,
        $a = null,
        ?\moodle_url $fixurl = null
    ): array {
        return [
            'key' => $key,
            'status' => $status,
            'label' => get_string($labelkey, 'local_stackmathgame'),
            'message' => get_string($messagekey, 'local_stackmathgame', $a),
            'fixurl' => $fixurl ? $fixurl->out(false) : null,
        ];
    }
}
