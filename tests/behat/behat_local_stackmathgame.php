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
     * Navigate to the STACK Math Game quiz settings page for a quiz by name.
     *
     * @Given I navigate to the STACK Math Game settings for quiz :quizname
     * @param string $quizname
     */
    public function i_navigate_to_smg_settings_for_quiz(string $quizname): void {
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {quiz} q ON q.id = cm.instance
                 WHERE q.name = :name AND m.name = 'quiz'";
        $cmid = $this->get_session()->evaluateScript(
            "return (function(){ return document.querySelector('[name=cmid]') "
            . "? document.querySelector('[name=cmid]').value : null; })()"
        );
        if (!$cmid) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'Could not find cmid on current page',
                $this->getSession()
            );
        }
        $url = new moodle_url('/local/stackmathgame/quiz_settings.php', ['cmid' => $cmid]);
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
