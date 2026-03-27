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

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat step definitions for local_stackmatheditor.
 *
 * @package    local_stackmatheditor
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_stackmatheditor extends behat_base {
    /**
     * Set the plugin enabled mode in Moodle config.
     *
     * @Given the plugin enabled mode is set to :mode
     * @param string $mode One of "0", "1", "2", "3".
     */
    public function the_plugin_enabled_mode_is_set_to(string $mode): void {
        set_config('enabled', $mode, 'local_stackmatheditor');
        purge_all_caches();
    }

    /**
     * Navigate to the STACK MathQuill quiz-level configuration page via the nav selector.
     *
     * @When I navigate to the STACK MathQuill quiz configuration
     */
    public function i_navigate_to_quiz_configuration(): void {
        $select = $this->find('css', 'form[action*="jumpto.php"] select[name="jump"]');
        $select->selectOption('STACK MathQuill-Editor einrichten');
        $this->getSession()->wait(3000, "document.readyState === 'complete'");
    }

    /**
     * Open the question-level configure page via the icon link next to a question.
     *
     * @When I click the MathQuill configure icon next to :questionname
     * @param string $questionname Question name.
     */
    public function i_click_configure_icon_next_to(string $questionname): void {
        $link = $this->find(
            'xpath',
            '//a[contains(@class,"sme-configure-edit-link")]'
                . '[ancestor::*[contains(.,"' . $questionname . '")]]'
        );
        $link->click();
        $this->getSession()->wait(2000, "document.readyState === 'complete'");
    }

    /**
     * Open the STACK MathQuill quiz configuration page directly by quiz name.
     *
     * @Given I am on the STACK MathQuill quiz configuration page for :quizname
     * @param string $quizname Quiz name.
     */
    public function i_am_on_quiz_config_page(string $quizname): void {
        global $DB;
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $url = new \moodle_url(
            '/local/stackmatheditor/configure.php',
            ['cmid' => $cm->id]
        );
        $this->getSession()->visit($url->out(false));
        $this->getSession()->wait(2000, "document.readyState === 'complete'");
    }

    /**
     * Assert that an element with the given CSS class exists on the page.
     *
     * @Then I should see the class :cssclass
     * @param string $cssclass CSS class name without the leading dot.
     */
    public function i_should_see_the_class(string $cssclass): void {
        $el = $this->find('css', '.' . $cssclass);
        if (!$el) {
            throw new ExpectationException(
                "Expected element with class '$cssclass' not found.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that no element with the given CSS class exists on the page.
     *
     * @Then I should not see the class :cssclass
     * @param string $cssclass CSS class name without the leading dot.
     */
    public function i_should_not_see_the_class(string $cssclass): void {
        $elements = $this->getSession()->getPage()->findAll('css', '.' . $cssclass);
        if (!empty($elements)) {
            throw new ExpectationException(
                "Element with class '$cssclass' should not be present on the page.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a specific option text exists in the quiz navigation select element.
     *
     * @Then I should see :text in the quiz navigation select
     * @param string $text Option label to look for.
     */
    public function i_should_see_option_in_nav_select(string $text): void {
        $select = $this->find('css', 'form[action*="jumpto.php"] select[name="jump"]');
        if (!$select) {
            throw new ExpectationException(
                'Quiz navigation select not found.',
                $this->getSession()
            );
        }
        $option = $select->find('xpath', './/option[contains(text(),"' . $text . '")]');
        if (!$option) {
            throw new ExpectationException(
                "Option '$text' not found in quiz navigation select.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a checkbox with the given id exists on the page.
     *
     * @Then I should see a checkbox with id :id
     * @param string $id Element id attribute value.
     */
    public function i_should_see_checkbox_with_id(string $id): void {
        $el = $this->find('css', '#' . $id . '[type="checkbox"]');
        if (!$el) {
            throw new ExpectationException(
                "Checkbox '#$id' not found.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that no checkbox with the given id exists on the page.
     *
     * @Then I should not see a checkbox with id :id
     * @param string $id Element id attribute value.
     */
    public function i_should_not_see_checkbox_with_id(string $id): void {
        $elements = $this->getSession()->getPage()->findAll(
            'css',
            '#' . $id . '[type="checkbox"]'
        );
        if (!empty($elements)) {
            throw new ExpectationException(
                "Checkbox '#$id' should not be present.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a checkbox is in the checked state.
     *
     * @Then the checkbox :id should be checked
     * @param string $id Element id attribute value.
     */
    public function the_checkbox_should_be_checked(string $id): void {
        $el = $this->find('css', '#' . $id);
        if (!$el || !$el->isChecked()) {
            throw new ExpectationException(
                "Checkbox '#$id' should be checked.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a checkbox is in the unchecked state.
     *
     * @Then the checkbox :id should be unchecked
     * @param string $id Element id attribute value.
     */
    public function the_checkbox_should_be_unchecked(string $id): void {
        $el = $this->find('css', '#' . $id);
        if (!$el || $el->isChecked()) {
            throw new ExpectationException(
                "Checkbox '#$id' should be unchecked.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that the MathQuill field associated with a named input is not empty.
     *
     * The MathQuill editor inserts an sme-input-wrap div immediately before
     * the hidden input in the DOM. An empty MathQuill field has the mq-empty
     * CSS class on its .mq-root-block span.
     *
     * @Then the MathQuill field for :inputname should not be empty
     * @param string $inputname Name attribute of the hidden input element.
     */
    public function the_mathquill_field_should_not_be_empty(string $inputname): void {
        $js = <<<JS
            (function() {
                var input = document.querySelector('input[name="{$inputname}"]');
                if (!input) { return false; }
                var wrap = input.previousElementSibling;
                if (!wrap) { return false; }
                var mqroot = wrap.querySelector('.mq-root-block');
                if (!mqroot) { return false; }
                return !mqroot.classList.contains('mq-empty');
            })()
JS;
        $result = $this->getSession()->evaluateScript($js);
        if (!$result) {
            throw new ExpectationException(
                "MathQuill field for input '$inputname' is empty or not found.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a hidden input element contains a specific Maxima value.
     *
     * @Then the hidden input :inputname should contain :value
     * @param string $inputname Name attribute.
     * @param string $value     Expected value.
     */
    public function the_hidden_input_should_contain(
        string $inputname,
        string $value
    ): void {
        $js = "return document.querySelector('input[name=\"{$inputname}\"]').value;";
        $actual = $this->getSession()->evaluateScript($js);
        if ($actual !== $value) {
            throw new ExpectationException(
                "Input '$inputname' contains '$actual', expected '$value'.",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a hidden input element contains any non-empty value.
     *
     * @Then the hidden input :inputname should contain a non-empty Maxima value
     * @param string $inputname Name attribute.
     */
    public function the_hidden_input_should_be_nonempty(string $inputname): void {
        $js = "return document.querySelector('input[name=\"{$inputname}\"]').value;";
        $actual = $this->getSession()->evaluateScript($js);
        if (empty($actual)) {
            throw new ExpectationException(
                "Input '$inputname' is empty; expected a Maxima expression.",
                $this->getSession()
            );
        }
    }

    /**
     * Simulate keyboard input into a MathQuill field by focusing its internal textarea.
     *
     * @When I type :text into the MathQuill field for :inputname
     * @param string $text      Text to type (LaTeX-style shorthand, e.g. "x^2").
     * @param string $inputname Name attribute of the corresponding hidden input.
     */
    public function i_type_into_mathquill_field(
        string $text,
        string $inputname
    ): void {
        $js = <<<JS
            (function() {
                var input = document.querySelector('input[name="{$inputname}"]');
                if (!input) { return false; }
                var container = input.previousElementSibling;
                if (!container) { return false; }
                var ta = container.querySelector('.mq-textarea textarea');
                if (!ta) { return false; }
                ta.focus();
                return true;
            })()
JS;
        $this->getSession()->evaluateScript($js);
        $this->getSession()->getDriver()->keyDown('//body', $text);
    }
}
