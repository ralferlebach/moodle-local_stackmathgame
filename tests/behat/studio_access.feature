@local @local_stackmathgame @javascript
Feature: STACK Math Game Design Studio access control
  As a Moodle administrator
  I want to access the Game Design Studio
  But as a teacher I should not see the Studio icon

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | teacher1 | Teacher   | One      | teacher@example.com|
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: Admin sees the Studio icon in the navbar
    Given I log in as "admin"
    Then ".smg-studio-nav-link" "css_element" should exist

  Scenario: Admin can access the Game Design Studio page
    Given I log in as "admin"
    When I navigate to "/local/stackmathgame/studio.php"
    Then I should see "Game Design Studio"

  Scenario: Teacher does not see the Studio icon
    Given I log in as "teacher1"
    Then ".smg-studio-nav-link" "css_element" should not exist
