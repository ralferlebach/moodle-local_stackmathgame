@local @local_stackmathgame
Feature: Authoring the game flow of a quiz
  As an editing teacher
  I want to configure each question as a game scene
  So that a quiz becomes a playable story without touching the database

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
      | activity | course | name      | idnumber | preferredbehaviour | questionsperpage |
      | quiz     | C1     | Game Quiz | quiz1    | stackmathgame      | 1                |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name        | questiontext |
      | Test questions   | shortanswer | Scene one   | 1 + 1?       |
      | Test questions   | shortanswer | Scene two   | 2 + 2?       |
      | Test questions   | shortanswer | Scene three | 3 + 3?       |
    And quiz "Game Quiz" contains the following questions:
      | question    | page |
      | Scene one   | 1    |
      | Scene two   | 2    |
      | Scene three | 3    |
    And the STACK Math Game is enabled for quiz "Game Quiz"
    And I log in as "teacher1"

  Scenario: The flow lists every slot with its question
    When I am on the "Game Quiz" "local_stackmathgame > Game flow" page
    Then I should see "Game flow"
    And I should see "Scene one"
    And I should see "Scene two"
    And I should see "Scene three"
    # The question title and the configured scene must be visible together: neither alone tells
    # a teacher what the slot does.
    And I should see "Challenge"

  Scenario: A scene type, narrative and branch survive a round trip
    When I am on the "Game Quiz" "local_stackmathgame > Game flow" page
    And I click on "Edit" "link" in the "Scene one" "table_row"
    And I set the field "Scene type" to "Boss"
    And I set the field "Shown when the scene opens" to "The dragon stirs."
    And I set the field "After a correct answer" to "Jump to a slot"
    And I set the field "Target slot (After a correct answer)" to "3: Scene three"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "Jump to slot 3"
    When I click on "Edit" "link" in the "Scene one" "table_row"
    Then the field "Scene type" matches value "Boss"
    And the field "Shown when the scene opens" matches value "The dragon stirs."

  Scenario: A jump without a target cannot be saved
    When I am on the "Game Quiz" "local_stackmathgame > Game flow" page
    And I click on "Edit" "link" in the "Scene one" "table_row"
    And I set the field "After a correct answer" to "Jump to a slot"
    And I press "Save changes"
    Then I should see "Choose a target slot for the jump"

  Scenario: A slot no path reaches is reported
    # No single card is wrong here. Only the graph shows the problem, which is why the page
    # analyses it rather than leaving it to be discovered mid-attempt.
    When I am on the "Game Quiz" "local_stackmathgame > Game flow" page
    And I click on "Edit" "link" in the "Scene one" "table_row"
    And I set the field "After a correct answer" to "Jump to a slot"
    And I set the field "Target slot (After a correct answer)" to "3: Scene three"
    And I set the field "When the scene is complete" to "Jump to a slot"
    And I set the field "Target slot (When the scene is complete)" to "3: Scene three"
    And I set the field "Otherwise" to "Jump to a slot"
    And I set the field "Target slot (Otherwise)" to "3: Scene three"
    And I press "Save changes"
    Then I should see "No path reaches these slots"

  Scenario: Bulk apply changes only the field that was filled in
    When I am on the "Game Quiz" "local_stackmathgame > Game flow" page
    And I click on "Edit" "link" in the "Scene two" "table_row"
    And I set the field "Shown when the scene opens" to "Keep me."
    And I press "Save changes"
    And I set the field "smg-flow-slot-2" to "1"
    And I set the field "Scene type" to "Boss"
    And I press "Apply"
    Then I should see "Applied to 1 slot"
    When I click on "Edit" "link" in the "Scene two" "table_row"
    Then the field "Scene type" matches value "Boss"
    And the field "Shown when the scene opens" matches value "Keep me."

  Scenario: The flow is reachable from the game settings
    When I am on the "Game Quiz" "local_stackmathgame > Game settings" page
    And I follow "Game flow"
    Then I should see "Game flow"
