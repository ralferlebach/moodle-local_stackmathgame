@local @local_stackmathgame
Feature: STACK Math Game settings in the quiz navigation
  As a teacher
  I want to reach the game settings from the quiz's own navigation
  So that I can set up the game without knowing a URL

  # Issue #13. The entry used to be missing from "More" on every quiz page: the navigation
  # callback looked for the activity in $PAGE->cm, which is not populated yet when the secondary
  # navigation is built, and returned before adding anything. These scenarios follow the click
  # path a teacher takes and never open the settings URL directly.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | teacher@example.com |
      | student1 | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name      | idnumber | preferredbehaviour |
      | quiz     | C1     | Test Quiz | quiz1    | deferredfeedback   |

  Scenario: A teacher reaches the game settings of an ordinary quiz through More
    # An ordinary quiz: game off, no STACK Math Game behaviour. The settings page is where the
    # teacher learns what is still missing, so the entry is there before any of it is set up.
    Given I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    When I navigate to "Game settings" in current page administration
    Then I should see "Prerequisites for running a game"
    And I should see "Game design"

  @javascript
  Scenario: The entry works in the More menu on a narrow screen
    Given I change window size to "mobile"
    And I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    When I navigate to "Game settings" in current page administration
    Then I should see "Prerequisites for running a game"

  Scenario: A student does not see the entry
    Given I am on the "Test Quiz" "mod_quiz > View" page logged in as "student1"
    Then I should not see "Game settings"
