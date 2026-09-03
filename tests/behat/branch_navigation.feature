@local @local_stackmathgame @javascript
Feature: Branch navigation works for every branching mode
  As a student playing a STACK Math Game quiz
  I want a way forward after every scene I finish
  So that a linear quiz is playable at all

  # The regression this guards: `linear` is the mode every auto-created slot receives, and it was
  # the one mode none of the three game modules handled. The normal case was the broken one.

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
      | activity | course | name      | idnumber | preferredbehaviour | questionsperpage |
      | quiz     | C1     | Game Quiz | quiz1    | stackmathgame      | 1                |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name        | template |
      | Test questions   | stack | Scene one   | test3    |
      | Test questions   | stack | Scene two   | test3    |
      | Test questions   | stack | Scene three | test3    |
    And quiz "Game Quiz" contains the following questions:
      | question    | page |
      | Scene one   | 1    |
      | Scene two   | 2    |
      | Scene three | 3    |
    And the STACK Math Game is enabled for quiz "Game Quiz"

  Scenario: The default linear branching offers a way to the next scene
    Given I am on the "Game Quiz" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    When I set the field with xpath "//input[contains(@id, '_ans1')]" to "x^3"
    And I press "Check"
    Then I should see "Next scene"

  Scenario: The last scene finishes the run rather than offering nothing
    Given slot 3 of quiz "Game Quiz" branches to "end" on "gradedright"
    And I am on the "Game Quiz" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    When I follow the game navigation to slot 3
    And I set the field with xpath "//input[contains(@id, '_ans1')]" to "x^3"
    And I press "Check"
    Then I should see "Finish the run"

  Scenario: An explicit slot jump is followed
    Given slot 1 of quiz "Game Quiz" branches to slot 3 on "gradedright"
    And I am on the "Game Quiz" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    When I set the field with xpath "//input[contains(@id, '_ans1')]" to "x^3"
    And I press "Check"
    And I press "Next scene"
    Then I should see "Scene three"

  Scenario: A wrong answer offers no way forward
    # The retry is the point of the scene. Offering a way on would let a player walk past every
    # question without solving any of them.
    Given I am on the "Game Quiz" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    When I set the field with xpath "//input[contains(@id, '_ans1')]" to "x^2"
    And I press "Check"
    Then I should not see "Next scene"
    And I should not see "Finish the run"
