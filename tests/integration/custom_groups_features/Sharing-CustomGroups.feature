Feature: Custom Groups

Background:
	Given using api version "1"
	And using new dav path

Scenario: Check that skeleton is properly set
	Given As an "admin"
	And user "user0" exists
	Then user "user0" should see following elements
		| /FOLDER/ |
		| /PARENT/ |
		| /PARENT/parent.txt |
		| /textfile0.txt |
		| /textfile1.txt |
		| /textfile2.txt |
		| /textfile3.txt |
		| /textfile4.txt |
		| /welcome.txt |

Scenario: Creating a share with a group
	Given As an "admin"
	And user "user0" exists
	And user "user1" exists
	And user "member1" exists
	And user "user1" created a custom group called "sharing-group"
	And user "user1" made user "member1" member of custom group "sharing-group"
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | sharing-group |
      | shareType | 1 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"