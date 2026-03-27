@local @local_stackmatheditor
Feature: MathQuill editor renders in quiz attempts
  As a student
  I want to see the MathQuill editor in STACK question input fields
  So that I can enter mathematical expressions visually

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student1 | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And a STACK quiz "Math Quiz" with algebraic input exists in "C1"

  @javascript
  Scenario: MathQuill editor appears on quiz attempt page
    Given I log in as "student1"
    When I attempt the quiz "Math Quiz"
    Then I should see the class "sme-mq-container"
    And I should see the class "sme-toolbar"
    And I should not see the original STACK input field

  @javascript
  Scenario: Typing in MathQuill field populates the hidden input
    Given I log in as "student1"
    And I am attempting the quiz "Math Quiz"
    When I type "x^2" into the MathQuill field for "ans1"
    Then the hidden input "ans1" should contain a non-empty Maxima value

  @javascript
  Scenario: Pre-fill restores previous answer on page reload
    Given I log in as "student1"
    And I have previously answered "sin(x)" in the quiz "Math Quiz"
    When I return to the quiz attempt page
    Then the MathQuill field for "ans1" should not be empty
    And the hidden input "ans1" should contain "sin(x)"

  @javascript
  Scenario: Pre-fill restores previous answer after navigating away and back
    Given I log in as "student1"
    And I have previously answered "x^2+1" in the quiz "Math Quiz"
    When I navigate to the next question and back
    Then the MathQuill field for "ans1" should not be empty

  @javascript
  Scenario: Toolbar buttons insert correct LaTeX
    Given I log in as "student1"
    And I am attempting the quiz "Math Quiz"
    And the MathQuill editor is visible for "ans1"
    When I click the toolbar button with title "Quadratwurzel (√)"
    Then the MathQuill field for "ans1" should contain LaTeX containing "sqrt"

  @javascript
  Scenario: Editor is absent when plugin is globally disabled
    Given the plugin enabled mode is set to "0"
    And I log in as "student1"
    When I attempt the quiz "Math Quiz"
    Then I should not see the class "sme-mq-container"
    And I should not see the class "sme-toolbar"

  @javascript
  Scenario: Editor is absent when disabled at question level in mode 3
    Given the plugin enabled mode is set to "3"
    And the STACK question "ans1" in "Math Quiz" has editor disabled
    And I log in as "student1"
    When I attempt the quiz "Math Quiz"
    Then I should not see the class "sme-mq-container"
