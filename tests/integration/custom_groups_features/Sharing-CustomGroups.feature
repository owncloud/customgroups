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

Scenario: Keep custom group permissions in sync
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

Scenario: Sharee can see the custom group share
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user1" created a custom group called "group1"
    And file "textfile0.txt" of user "user0" is shared with group "customgroup_group1"
    And As an "user1"
    When sending "GET" to "/apps/files_sharing/api/v1/shares?shared_with_me=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And last share_id is included in the answer

Scenario: Share of folder and sub-folder to same user
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user1" created a custom group called "group1"
    And file "/PARENT" of user "user0" is shared with user "user1"
    When file "/PARENT/CHILD" of user "user0" is shared with group "customgroup_group1"
    Then user "user1" should see following elements
      | /FOLDER/ |
      | /PARENT/ |
      | /CHILD/ |
      | /PARENT/parent.txt |
      | /CHILD/child.txt |
    And the HTTP status code should be "200"

Scenario: Share a file by multiple channels
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And user "user1" created a custom group called "group1"
	And user "user1" made user "user2" member of custom group "group1"
    And user "user0" created a folder "/common"
    And user "user0" created a folder "/common/sub"
    And folder "common" of user "user0" is shared with group "customgroup_group1"
    And file "textfile0.txt" of user "user1" is shared with user "user2"
    And User "user1" moved file "/textfile0.txt" to "/common/textfile0.txt"
    And User "user1" moved file "/common/textfile0.txt" to "/common/sub/textfile0.txt"
    And As an "user2"
    When Downloading file "/textfile0.txt" with range "bytes=9-17"
    Then Downloaded content should be "test text"
    And user "user2" should see following elements
      | /common/sub/textfile0.txt |

Scenario: Delete all custom group shares
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user1" created a custom group called "group1"
    And file "textfile0.txt" of user "user0" is shared with group "customgroup_group1"
    And User "user1" moved file "/textfile0.txt" to "/FOLDER/textfile0.txt"
    And As an "user0"
    And Deleting last share
    And As an "user1"
    When sending "GET" to "/apps/files_sharing/api/v1/shares?shared_with_me=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And last share_id is not included in the answer

Scenario: Keep user custom group shares 
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And user "user1" created a custom group called "group1"
    And user "user1" made user "user2" member of custom group "group1"
    And user "user0" created a folder "/TMP"
    And folder "TMP" of user "user0" is shared with group "customgroup_group1"
    And user "user1" created a folder "/myFOLDER"
    And User "user1" moves file "/TMP" to "/myFOLDER/myTMP"
    And user "user2" does not exist
    And user "user1" should see following elements
      | /myFOLDER/myTMP/ |

Scenario: Sharing again an own file while belonging to a custom group
    Given As an "admin"
    And user "user0" exists
    And user "user0" created a custom group called "sharing-group"
    And group "sharing-group" exists
    And file "welcome.txt" of user "user0" is shared with group "customgroup_sharing-group"
    And Deleting last share
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | welcome.txt |
      | shareWith | customgroup_sharing-group |
      | shareType | 1 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

Scenario: Sharing subfolder when parent already shared
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user1" created a custom group called "sharing-group"
    And user "user0" created a folder "/test"
    And user "user0" created a folder "/test/sub"
    And folder "/test" of user "user0" is shared with group "customgroup_sharing-group"
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | /test/sub |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
	And as "user1" the folder "/sub" exists

Scenario: Sharing subfolder when parent already shared with custom group of sharer
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a custom group called "sharing-group"
    And user "user0" created a folder "/test"
    And user "user0" created a folder "/test/sub"
    And file "/test" of user "user0" is shared with group "customgroup_sharing-group"
    And As an "user0"
    When sending "POST" to "/apps/files_sharing/api/v1/shares" with
      | path | /test/sub |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
	And as "user1" the folder "/sub" exists

Scenario: Unshare from self using custom groups
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a custom group called "sharing-group"
    And user "user0" made user "user1" member of custom group "sharing-group"
    And file "/PARENT/parent.txt" of user "user0" is shared with group "customgroup_sharing-group"
    And user "user0" stores etag of element "/PARENT"
    And user "user1" stores etag of element "/"
    And As an "user1"
    When Deleting last share
    Then etag of element "/" of user "user1" has changed
    And etag of element "/PARENT" of user "user0" has not changed

Scenario: Increasing permissions is allowed for owner
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a custom group called "sharing-group"
    And user "user0" made user "user1" member of custom group "sharing-group"
    And As an "user0"
    And folder "/FOLDER" of user "user0" is shared with group "customgroup_sharing-group"
    And Updating last share with
      | permissions | 0 |
    When Updating last share with
      | permissions | 31 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
