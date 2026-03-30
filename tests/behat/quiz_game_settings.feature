@local @local_stackmathgame @javascript
Feature: STACK Math Game quiz settings configuration
  As an editing teacher
  I want to configure game settings for my quiz
  So that students experience the game layer

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
      | activity | course | name      | idnumber |
      | quiz     | C1     | Test Quiz | quiz1    |
    And I log in as "teacher1"

  Scenario: Game settings page is accessible for editing teacher
    When I am on the "Test Quiz" "mod_quiz > Edit" page
    And I choose "Game settings" from the quiz tertiary nav
    Then I should see "Game settings"

  Scenario: Saving game settings redirects back to the settings page
    When I am on the "Test Quiz" "mod_quiz > Edit" page
    And I choose "Game settings" from the quiz tertiary nav
    And I press "Save changes"
    Then I should see "Changes saved"

  Scenario: Game settings entry appears in the quiz tertiary nav dropdown
    When I am on the "Test Quiz" "mod_quiz > Edit" page
    Then I should see "Game settings" in the quiz tertiary nav
