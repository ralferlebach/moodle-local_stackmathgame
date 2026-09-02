<?php
// This file is part of Moodle - https://moodle.org/
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
 * Behat step definitions for local_stackmathgame.
 *
 * @package    local_stackmathgame
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_stackmathgame extends behat_base {
    /**
     * Visit the Moodle site homepage.
     *
     * @Given I am on the Moodle homepage
     */
    public function i_am_on_the_moodle_homepage(): void {
        $this->getSession()->visit($this->locate_path('/'));
        $this->getSession()->wait(2000, "document.readyState === 'complete'");
    }

    /**
     * Navigate to a relative Moodle path.
     *
     * Supports simple placeholder replacement for [cmid] using the current page
     * URL or a hidden input named cmid when present.
     *
     * @When I navigate to :path
     * @param string $path Relative path starting with '/'.
     */
    public function i_navigate_to(string $path): void {
        $resolvedpath = $path;
        if (strpos($resolvedpath, '[cmid]') !== false) {
            $resolvedpath = str_replace('[cmid]', (string)$this->resolve_current_cmid(), $resolvedpath);
        }

        $this->getSession()->visit($this->locate_path($resolvedpath));
        $this->getSession()->wait(2000, "document.readyState === 'complete'");
    }

    /**
     * Resolve the current page course-module id.
     *
     * @return int The resolved course-module id.
     */
    protected function resolve_current_cmid(): int {
        $currenturl = $this->getSession()->getCurrentUrl();
        $parsed = parse_url($currenturl);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $params);
            if (!empty($params['cmid'])) {
                return (int)$params['cmid'];
            }
        }

        $cmid = $this->get_session()->evaluateScript(
            "return (function(){ var input = document.querySelector('[name=cmid]'); return input ? input.value : null; })()"
        );
        if (!empty($cmid)) {
            return (int)$cmid;
        }

        throw new \Behat\Mink\Exception\ExpectationException(
            'Could not resolve cmid from the current page',
            $this->getSession()
        );
    }

    /**
     * Enable the game on a quiz, with a default design and a built question map.
     *
     * @Given the STACK Math Game is enabled for quiz :quizname
     * @param string $quizname The quiz name.
     */
    public function the_game_is_enabled_for_quiz(string $quizname): void {
        global $DB;

        $cmid = self::cmid_for_quiz_name($quizname);
        \local_stackmathgame\game\theme_manager::seed_default_theme();
        $config = \local_stackmathgame\game\quiz_configurator::ensure_default($cmid);
        $config->enabled = 1;
        $DB->update_record('local_stackmathgame', $config);
        \local_stackmathgame\local\service\question_map_service::ensure_for_cmid($cmid);
    }

    /**
     * Set a branching rule on one slot.
     *
     * Writes through slot_config_schema rather than hand-building JSON, so a feature file cannot
     * quietly introduce a config shape the resolver does not accept.
     *
     * @Given slot :slot of quiz :quizname branches to slot :target on :outcome
     * @param int $slot The slot number.
     * @param string $quizname The quiz name.
     * @param int $target The target slot number.
     * @param string $outcome The outcome key.
     */
    public function slot_branches_to_slot(int $slot, string $quizname, int $target, string $outcome): void {
        self::write_branch_rule($quizname, $slot, $outcome, ['mode' => 'slot', 'target' => $target]);
    }

    /**
     * Set a non-slot branching rule on one slot.
     *
     * @Given slot :slot of quiz :quizname branches to :mode on :outcome
     * @param int $slot The slot number.
     * @param string $quizname The quiz name.
     * @param string $mode The branch mode, "linear" or "end".
     * @param string $outcome The outcome key.
     */
    public function slot_branches_to_mode(int $slot, string $quizname, string $mode, string $outcome): void {
        self::write_branch_rule($quizname, $slot, $outcome, ['mode' => $mode]);
    }

    /**
     * Persist one branching rule into a slot's configjson.
     *
     * @param string $quizname The quiz name.
     * @param int $slot The slot number.
     * @param string $outcome The outcome key.
     * @param array $rule The rule.
     */
    protected static function write_branch_rule(string $quizname, int $slot, string $outcome, array $rule): void {
        global $DB;

        $cmid = self::cmid_for_quiz_name($quizname);
        \local_stackmathgame\local\service\question_map_service::ensure_for_cmid($cmid);
        $row = $DB->get_record(
            'local_stackmathgame_questionmap',
            ['cmid' => $cmid, 'slotnumber' => $slot],
            '*',
            MUST_EXIST
        );
        $config = \local_stackmathgame\local\service\slot_config_schema::parse((string)$row->configjson)
            ?? \local_stackmathgame\local\service\slot_config_schema::defaults();
        $config['branching'][$outcome] = $rule;
        $DB->set_field(
            'local_stackmathgame_questionmap',
            'configjson',
            json_encode($config, JSON_UNESCAPED_UNICODE),
            ['id' => $row->id]
        );
    }

    /**
     * Navigate the attempt to the page holding a given slot.
     *
     * @When I follow the game navigation to slot :slot
     * @param int $slot The slot number.
     */
    public function i_follow_the_game_navigation_to_slot(int $slot): void {
        $node = $this->find('css', '#quiznavbutton' . $slot);
        $node->click();
        $this->wait_for_pending_js();
    }

    /**
     * Resolve a page instance URL for the "I am on the ... page" step.
     *
     * @param string $type The page type, e.g. "Game settings".
     * @param string $identifier The activity name.
     * @return moodle_url The resolved URL.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'game settings':
                return new moodle_url('/local/stackmathgame/quiz_settings.php', [
                    'cmid' => self::cmid_for_quiz_name($identifier),
                ]);
            default:
                throw new Exception('Unrecognised local_stackmathgame page type "' . $type . '".');
        }
    }

    /**
     * Resolve a page URL for the "I am on the ... page" step.
     *
     * @param string $page The page name.
     * @return moodle_url The resolved URL.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'game design studio':
                return new moodle_url('/local/stackmathgame/studio.php');
            default:
                throw new Exception('Unrecognised local_stackmathgame page "' . $page . '".');
        }
    }

    /**
     * Look up the course-module ID of a quiz by its name.
     *
     * @param string $quizname The quiz name.
     * @return int The cmid.
     */
    protected static function cmid_for_quiz_name(string $quizname): int {
        global $DB;

        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {quiz} q ON q.id = cm.instance
                 WHERE q.name = :name AND m.name = 'quiz'";
        $cmid = $DB->get_field_sql($sql, ['name' => $quizname]);
        if (!$cmid) {
            throw new Exception('There is no quiz named "' . $quizname . '".');
        }
        return (int)$cmid;
    }

    /**
     * Navigate to the STACK Math Game quiz settings page for a quiz by name.
     *
     * Resolves the quiz by name against the database. The previous implementation read the cmid
     * out of the current page's DOM and ignored its own argument, so it silently opened the
     * settings of whatever quiz happened to be on screen - or failed on any page without a cmid
     * field, which included the page the step was usually called from.
     *
     * @Given I navigate to the STACK Math Game settings for quiz :quizname
     * @param string $quizname The quiz name.
     */
    public function i_navigate_to_smg_settings_for_quiz(string $quizname): void {
        $url = new moodle_url('/local/stackmathgame/quiz_settings.php', [
            'cmid' => self::cmid_for_quiz_name($quizname),
        ]);
        $this->getSession()->visit($this->locate_path($url->out(false)));
    }

    /**
     * Check that the STACK Math Game option is visible in the quiz tertiary nav select.
     *
     * @Then the quiz navigation select should contain :label
     * @param string $label
     */
    public function the_quiz_navigation_select_should_contain(string $label): void {
        $page = $this->getSession()->getPage();
        $select = $page->find('css', '.tertiary-navigation .urlselect select');
        if (!$select) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Tertiary navigation select not found',
                $this->getSession()
            );
        }
        $options = $select->findAll('css', 'option');
        foreach ($options as $option) {
            if (trim($option->getText()) === $label) {
                return;
            }
        }
        throw new \Behat\Mink\Exception\ExpectationException(
            "Option '$label' not found in quiz navigation select. "
            . "Found: " . implode(', ', array_map(fn($o) => trim($o->getText()), $options)),
            $this->getSession()
        );
    }
    /**
     * Verify the game settings option exists in the quiz tertiary nav select.
     *
     * @Then I should see :label in the quiz tertiary nav
     * @param string $label
     */
    public function i_should_see_in_the_quiz_tertiary_nav(string $label): void {
        $page = $this->getSession()->getPage();
        $select = $page->find('css', '.tertiary-navigation .urlselect select');
        if (!$select) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Tertiary navigation select not found',
                $this->getSession()
            );
        }
        $found = false;
        foreach ($select->findAll('css', 'option') as $option) {
            if (strpos(trim($option->getText()), $label) !== false) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Option containing '$label' not found in quiz tertiary nav",
                $this->getSession()
            );
        }
    }
}
