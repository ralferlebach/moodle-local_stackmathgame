@local @local_stackmathgame
Feature: STACK Math Game Design Studio
  As a Moodle administrator
  I want to access the Game Design Studio
  So that I can manage game designs for STACK Math Game quizzes

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | admin1   | Admin     | User     | admin@example.com  |
    And I log in as "admin"

  @javascript
  Scenario: Admin can access the Game Design Studio
    When I navigate to "/local/stackmathgame/studio.php"
    Then I should see "Game Design Studio"
    And I should see "Overview"

  @javascript
  Scenario: Studio shows no designs message when no designs exist
    When I navigate to "/local/stackmathgame/studio.php"
    Then I should see "Game Design Studio"
