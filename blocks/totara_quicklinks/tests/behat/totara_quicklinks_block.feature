@totara @block @block_quicklinks
Feature: Test Quick Links block

  Background:
    Given I am on a totara site
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | Frist    | teacher1@example.com |
      | learner1 | Learner   | First    | learner1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role            |
      | teacher1 | C1     | editingteacher  |
      | learner1 | C1     | student         |

  @javascript
  Scenario: Learner can add the Quick Links block to the Dashboard
    And I log in as "learner1"
    And I click on "Dashboard" in the totara menu
    And I press "Customise this page"
    And I add the "Quick Links" block
    And I configure the "Quick Links" block
    And I expand all fieldsets
    And I set the following fields to these values:
      | config_title | My Links |
    And I press "Save changes"
    Then I should see "My Links"
    And I should see "Home" in the "My Links" "block"
    And I should see "Reports" in the "My Links" "block"
    And I should see "Courses" in the "My Links" "block"

    # Check the learner can add new links.
    When I click on "Actions" "link" in the "My Links" "block"
    And I follow "Configure My Links block"
    And I click on "Manage links" "link"
    And I set the field "linktitle" to "Totara"
    And I set the field "linkurl" to "https://www.totaralms.com/"
    And I click on "Add link" "button"
    And I click on "Dashboard" in the totara menu
    Then I should see "My Links"
    And I should see "Totara" in the "My Links" "block"
    And I should see "Home" in the "My Links" "block"
    And I should see "Reports" in the "My Links" "block"
    And I should see "Courses" in the "My Links" "block"

    # Check the learner can remove links.
    When I click on "Actions" "link" in the "My Links" "block"
    And I follow "Configure My Links block"
    And I click on "Manage links" "link"
    And I click on "Delete" "link" in the "Home" "table_row"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    And I click on "Delete" "link" in the "Reports" "table_row"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    And I click on "Dashboard" in the totara menu
    Then I should see "My Links"
    And I should not see "Home" in the "My Links" "block"
    And I should not see "Reports" in the "My Links" "block"
    And I should see "Totara" in the "My Links" "block"
    And I should see "Courses" in the "My Links" "block"

  @javascript
  Scenario: Teacher can add the Quick Links block onto a course
    And I log in as "teacher1"
    And I follow "Course 1"
    And I click on "Turn editing on" "button"
    And I add the "Quick Links" block
    And I configure the "Quick Links" block
    And I expand all fieldsets
    And I set the following fields to these values:
      | config_title | Course Links |
    And I press "Save changes"
    Then I should see "Course Links"
    And I should see "Home" in the "Course Links" "block"
    And I should see "Reports" in the "Course Links" "block"
    And I should see "Courses" in the "Course Links" "block"

    And I configure the "Course Links" block
    And I click on "Manage links" "link"
    And I set the field "linktitle" to "Totara"
    And I set the field "linkurl" to "https://www.totaralms.com/"
    And I click on "Add link" "button"
    Then I click on "Find Learning" in the totara menu
    And I follow "Course 1"
    Then I should see "Course Links"
    And I should see "Totara" in the "Course Links" "block"
    And I log out

    # Check the learner can view the block.
    And I log in as "learner1"
    And I follow "Course 1"
    Then I should see "Course Links"
    And I should see "Home" in the "Course Links" "block"
    And I should see "Reports" in the "Course Links" "block"
    And I should see "Courses" in the "Course Links" "block"
    And I should see "Totara" in the "Course Links" "block"
    And I log out

    # Check the teacher can remove links.
    And I log in as "teacher1"
    And I follow "Course 1"
    And I click on "Turn editing on" "button"
    And I click on "Actions" "link" in the "Course Links" "block"
    And I follow "Configure Course Links block"
    And I click on "Manage links" "link"
    And I click on "Delete" "link" in the "Home" "table_row"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    And I click on "Delete" "link" in the "Reports" "table_row"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    Then I click on "Find Learning" in the totara menu
    And I follow "Course 1"
    Then I should see "Course Links"
    And I should not see "Home" in the "Course Links" "block"
    And I should not see "Reports" in the "Course Links" "block"
    And I should see "Totara" in the "Course Links" "block"
    And I should see "Courses" in the "Course Links" "block"
    And I log out

    # Check the learner can see the correct block content.
    And I log in as "learner1"
    And I follow "Course 1"
    Then I should see "Course Links"
    And I should not see "Home" in the "Course Links" "block"
    And I should not see "Reports" in the "Course Links" "block"
    And I should see "Courses" in the "Course Links" "block"
    And I should see "Totara" in the "Course Links" "block"
    And I log out