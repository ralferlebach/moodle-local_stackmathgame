@local @local_stackmathgame
Feature: STACK Math Game quiz navigation
  As a teacher
  I want to see the STACK Math Game settings option in the quiz navigation
  So that I can configure the game layer for my quiz

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
      | activity | course | name       | idnumber |
      | quiz     | C1     | Test Quiz  | quiz1    |
    And I log in as "teacher1"

  @javascript
  Scenario: Quiz settings page is accessible for teachers
    Given I am on the "Test Quiz" "mod_quiz > Edit" page
    When I navigate to "/local/stackmathgame/quiz_settings.php?cmid=[cmid]"
    Then I should see "Game settings"
