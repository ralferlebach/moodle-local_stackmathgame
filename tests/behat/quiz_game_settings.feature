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
    When I am on the "Test Quiz" "local_stackmathgame > Game settings" page
    Then I should see "Game settings"
    And I should see "Prerequisites for running a game"

  Scenario: Saving game settings redirects back to the settings page
    When I am on the "Test Quiz" "local_stackmathgame > Game settings" page
    And I press "Save changes"
    Then I should see "Changes saved"

  @javascript
  Scenario: Game settings entry appears in the quiz tertiary nav dropdown
    # The dropdown entry is injected by AMD, so this one needs a real browser. The label comes
    # from the "gamesettings" string, which is English on an English test site.
    When I am on the "Test Quiz" "mod_quiz > Edit" page
    Then I should see "Game settings" in the quiz tertiary nav
