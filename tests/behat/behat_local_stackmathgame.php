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
