@local @local_stackmathgame @javascript
Feature: Several questions on one quiz page are presented as the server resolves them
  As a student playing a quest or a page of alternatives
  I want to see the stage or the alternative I am on
  So that I cannot skip a stage, and a reload does not lose my place

  # re-audit N06: the runtime used to present the first question of the page whatever the server
  # had resolved, and every stage of a quest was writable.

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
    And the following "activities" exist:
      | activity | course | name       | idnumber | preferredbehaviour | questionsperpage | navmethod |
      | quiz     | C1     | Group Quiz | quiz1    | stackmathgame      | 0                | free      |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name        | template |
      | Test questions   | stack | Stage one   | test3    |
      | Test questions   | stack | Stage two   | test3    |
      | Test questions   | stack | Stage three | test3    |
    And quiz "Group Quiz" contains the following questions:
      | question    | page |
      | Stage one   | 1    |
      | Stage two   | 1    |
      | Stage three | 1    |
    And the STACK Math Game is enabled for quiz "Group Quiz"
    And the STACK CAS is ready

  Scenario: A three-stage quest presents its stages in order, also after a reload
    Given the questions on page 1 of quiz "Group Quiz" form a "quest"
    And I am on the "Group Quiz" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    Then the game presents slot 1
    When I set the field with xpath "//div[@data-smg-question-part]//input[contains(@id, '_ans1')]" to "x^3"
    And I wait until STACK has validated the answer
    And I press "Check answer"
    And I click on "Next stage" "link"
    Then the game presents slot 2
    When I reload the page
    Then the game presents slot 2
    When I set the field with xpath "//div[@data-smg-question-part]//input[contains(@id, '_ans1')]" to "x^3"
    And I wait until STACK has validated the answer
    And I press "Check answer"
    And I click on "Next stage" "link"
    Then the game presents slot 3

  Scenario: A future quest stage cannot be opened through the address
    Given the questions on page 1 of quiz "Group Quiz" form a "quest"
    And I am on the "Group Quiz" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    When I reopen the attempt page asking for slot 3
    Then the game presents slot 1

  Scenario: Only the offered alternative is presented
    Given the questions on page 1 of quiz "Group Quiz" form a "alternatives"
    And I am on the "Group Quiz" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    Then the game presents slot 1
    When I reopen the attempt page asking for slot 2
    Then the game presents slot 1
