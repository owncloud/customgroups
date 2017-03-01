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

Scenario: Creating a share with a custom group
	Given As an "admin"
	And user "user0" exists
	And user "user1" exists
	And user "member1" exists
	And user "user1" created a custom group called "sharing-group"
	And user "user1" made user "member1" member of custom group "sharing-group"
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | customgroup_sharing-group |
      | shareType | 1 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

Scenario: Creating a new share with user who already received a share through their custom group
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user1" created a custom group called "sharing-group"
    And file "welcome.txt" of user "user0" is shared with group "customgroup_sharing-group"
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

Scenario: keep custom group permissions in sync
    Given As an "admin"
    Given user "user0" exists
    And user "user1" exists
    And user "user1" created a custom group called "group1"
    And file "textfile0.txt" of user "user0" is shared with group "customgroup_group1"
    And User "user1" moved file "/textfile0.txt" to "/FOLDER/textfile0.txt"
    And As an "user0"
    When Updating last share with
      | permissions | 1 |
    And Getting info of last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Share fields of last share match with
      | id | A_NUMBER |
      | item_type | file |
      | item_source | A_NUMBER |
      | share_type | 1 |
      | file_source | A_NUMBER |
      | file_target | /textfile0.txt |
      | permissions | 1 |
      | stime | A_NUMBER |
      | storage | A_NUMBER |
      | mail_send | 0 |
      | uid_owner | user0 |
      | storage_id | home::user0 |
      | file_parent | A_NUMBER |
      | displayname_owner | user0 |
      | mimetype          | text/plain |


