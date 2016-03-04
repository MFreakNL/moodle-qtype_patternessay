@ou @ou_vle @qtype @qtype_pmatch
Feature: Test backup and restore of a pmatch question with responses and matches
  In order to manage pmatch questions
  As an admin
  I need to be able to backup and restore with all testquestion data.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype    | name         | template |
      | Test questions   | pmatch   | My first pattern match question | listen    |
    And the default question test responses exist for question "My first pattern match question"
    And I log in as "admin"

  @javascript @_switch_window
  Scenario: Test backup and restore with testquestion data.
    Given I am on the pattern match test responses page for question "My first pattern match question"
    # Check course C1 version of uploaded responses.
    Then I should see "Pattern-match question testing tool: Testing question: My first pattern match question"
    And I should see "Showing the responses for the selected question: My first pattern match question"
    And I should see "Pos=0/0 Neg=0/0 Unm=13 Acc=0%"
    And I should see "1" in the "#qtype-pmatch-testquestion_r0_c4" "css_element"
    And I should see "testing one two three four" in the "#qtype-pmatch-testquestion_r0_c5" "css_element"
    # Now mark one response only.
    When I set the field with xpath "//td[@id='qtype-pmatch-testquestion_r1_c0']/input" to "1"
    And I press "Test the question using these responses"
    And I press "Continue"
    # Make a backup and restore to new course.
    Given I am on homepage
    When I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course 2 |
    Then I should see "Course 2"
    # Check the new course's testquestion data.
    When I navigate to "Question bank" node in "Course administration"
    Then I should see "My first pattern match question"
    When I follow "Edit"
    Then I should see "Editing a Pattern match question"
    When I navigate to "Question bank" node in "Course administration"
    And I follow "Preview"
    And I switch to "questionpreview" window
    And I follow "Test this question"
    Then I should see "Pattern-match question testing tool: Testing question: My first pattern match question"
    # Now check the marked row - this will have moved to a different row during restore - so re-order.
    When I follow "Computed mark"
    And I should see "testing" in the "#qtype-pmatch-testquestion_r0_c5" "css_element"
    And I should see "0" in the "#qtype-pmatch-testquestion_r0_c4" "css_element"
    And I should see "0" in the "#qtype-pmatch-testquestion_r0_c3" "css_element"
    And I should see "2" in the "#qtype-pmatch-testquestion_r0_c2" "css_element"
    And I should see "testing one two three four" in the "#qtype-pmatch-testquestion_r1_c5" "css_element"