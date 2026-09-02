@local @local_stackmathgame
Feature: STACK Math Game prerequisites are validated per quiz
  As an editing teacher
  I want to be told why a game cannot run on my quiz
  So that I do not mistake a misconfigured activity for a broken plugin

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | teacher@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | name       | idnumber | preferredbehaviour |
      | quiz     | C1     | Plain Quiz | quiz1    | deferredfeedback   |
    And I log in as "teacher1"

  Scenario: The panel names the wrong question behaviour as the blocker
    When I am on the "Plain Quiz" "local_stackmathgame > Game settings" page
    Then I should see "Prerequisites for running a game"
    And I should see "Question behaviour"
    # The message must name the behaviour actually in use. "Something is wrong" would send the
    # teacher looking through the game settings, which are not the problem.
    And I should see "deferredfeedback"
    And I should see "The game will not start on this quiz"

  Scenario: The game cannot be enabled while a prerequisite is unmet
    When I am on the "Plain Quiz" "local_stackmathgame > Game settings" page
    And I set the field "Enable game layer" to "1"
    And I press "Save changes"
    Then I should see "The game cannot start on this quiz yet"
    # Still on the form rather than redirected: the teacher has to see the reason.
    And I should see "Prerequisites for running a game"

  Scenario: Other settings remain saveable while a prerequisite is unmet
    # Refusing the whole form would stop a teacher preparing the configuration before an
    # administrator changes the quiz behaviour. Only the switch itself is refused.
    When I am on the "Plain Quiz" "local_stackmathgame > Game settings" page
    And I set the field "Enable game layer" to "0"
    And I press "Save changes"
    Then I should see "Changes saved"

  Scenario: The panel reports success once the behaviour is correct
    Given the following "activities" exist:
      | activity | course | name      | idnumber | preferredbehaviour |
      | quiz     | C1     | Game Quiz | quiz2    | stackmathgame      |
    When I am on the "Game Quiz" "local_stackmathgame > Game settings" page
    Then I should see "The quiz uses the STACK Math Game behaviour"
    # Questions are still missing, so the overall verdict stays blocked - the behaviour check
    # passing on its own must not be reported as "ready".
    And I should see "The game will not start on this quiz"
