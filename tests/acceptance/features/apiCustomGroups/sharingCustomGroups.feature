@api
Feature: Sharing Custom Groups

  Background:
    Given using OCS API version "1"
    And using new dav path

  Scenario: Check that skeleton is properly set
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    Then user "user0" should see the following elements
      | /FOLDER/           |
      | /PARENT/           |
      | /PARENT/parent.txt |
      | /textfile0.txt     |
      | /textfile1.txt     |
      | /textfile2.txt     |
      | /textfile3.txt     |
      | /textfile4.txt     |
      | /welcome.txt       |

  Scenario: Creating a share with a custom group
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "member1" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "sharing-group"
    And user "user1" has made user "member1" a member of custom group "sharing-group"
    When user "user0" creates a share using the sharing API with settings
      | path      | welcome.txt               |
      | shareWith | customgroup_sharing-group |
      | shareType | group                     |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Creating a new share with user who already received a share through their custom group
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "sharing-group"
    And user "user0" has shared file "welcome.txt" with group "customgroup_sharing-group"
    When user "user0" creates a share using the sharing API with settings
      | path      | welcome.txt |
      | shareWith | user1       |
      | shareType | user        |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Keep custom group permissions in sync
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "group1"
    And user "user0" has shared file "textfile0.txt" with group "customgroup_group1"
    And user "user1" has moved file "/textfile0.txt" to "/FOLDER/textfile0.txt"
    When user "user0" updates the last share using the sharing API with
      | permissions | read |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the response when user "user0" gets the info of the last share should include
      | id                | A_NUMBER       |
      | item_type         | file           |
      | item_source       | A_NUMBER       |
      | share_type        | group          |
      | file_source       | A_NUMBER       |
      | file_target       | /textfile0.txt |
      | permissions       | read           |
      | stime             | A_NUMBER       |
      | storage           | A_NUMBER       |
      | mail_send         | 0              |
      | uid_owner         | user0          |
      | storage_id        | home::user0    |
      | file_parent       | A_NUMBER       |
      | displayname_owner | User Zero      |
      | mimetype          | text/plain     |

  Scenario: Sharee can see the custom group share
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "group1"
    And user "user0" has shared file "textfile0.txt" with group "customgroup_group1"
    When user "user1" sends HTTP method "GET" to OCS API endpoint "/apps/files_sharing/api/v1/shares?shared_with_me=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the last share_id should be included in the response

  Scenario: Share of folder and sub-folder to same user
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "group1"
    And user "user0" has shared file "/PARENT" with user "user1"
    When user "user0" shares file "/PARENT/CHILD" with group "customgroup_group1" using the sharing API
    Then user "user1" should see the following elements
      | /FOLDER/           |
      | /PARENT/           |
      | /CHILD/            |
      | /PARENT/parent.txt |
      | /CHILD/child.txt   |
    And the HTTP status code should be "200"

  Scenario: Share a file by multiple channels
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user2" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "group1"
    And user "user1" has made user "user2" a member of custom group "group1"
    And user "user0" has created folder "/common"
    And user "user0" has created folder "/common/sub"
    And user "user0" has shared folder "common" with group "customgroup_group1"
    And user "user1" has shared file "textfile0.txt" with user "user2"
    And user "user1" has moved file "/textfile0.txt" to "/common/textfile0.txt"
    And user "user1" has moved file "/common/textfile0.txt" to "/common/sub/textfile0.txt"
    When user "user2" downloads file "/textfile0.txt" with range "bytes=9-17" using the WebDAV API
    Then the downloaded content should be "test text"
    And user "user2" should see the following elements
      | /common/sub/textfile0.txt |

  Scenario: Delete all custom group shares
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "group1"
    And user "user0" has shared file "textfile0.txt" with group "customgroup_group1"
    And user "user1" has moved file "/textfile0.txt" to "/FOLDER/textfile0.txt"
    When user "user0" deletes the last share using the sharing API
    And user "user1" sends HTTP method "GET" to OCS API endpoint "/apps/files_sharing/api/v1/shares?shared_with_me=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the last share_id should not be included in the response

  Scenario: Keep user custom group shares
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user2" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "group1"
    And user "user1" has made user "user2" a member of custom group "group1"
    And user "user0" has created folder "/TMP"
    And user "user0" has shared folder "TMP" with group "customgroup_group1"
    And user "user1" has created folder "/myFOLDER"
    And user "user1" has moved file "/TMP" to "/myFOLDER/myTMP"
    When the administrator deletes user "user2" using the provisioning API
    Then user "user1" should see the following elements
      | /myFOLDER/myTMP/ |

  Scenario: Sharing again an own file while belonging to a custom group
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user0" has created a custom group called "sharing-group"
    And group "sharing-group" has been created
    And user "user0" has shared file "welcome.txt" with group "customgroup_sharing-group"
    And user "user0" deletes the last share using the sharing API
    When user "user0" creates a share using the sharing API with settings
      | path      | welcome.txt               |
      | shareWith | customgroup_sharing-group |
      | shareType | group                     |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Sharing subfolder when parent already shared
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user1" has created a custom group called "sharing-group"
    And user "user0" has created folder "/test"
    And user "user0" has created folder "/test/sub"
    And user "user0" has shared folder "/test" with group "customgroup_sharing-group"
    When user "user0" creates a share using the sharing API with settings
      | path      | /test/sub |
      | shareWith | user1     |
      | shareType | user      |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "user1" folder "/sub" should exist

  Scenario: Sharing subfolder when parent already shared with custom group of sharer
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user0" has created a custom group called "sharing-group"
    And user "user0" has created folder "/test"
    And user "user0" has created folder "/test/sub"
    And user "user0" has shared file "/test" with group "customgroup_sharing-group"
    When user "user0" creates a share using the sharing API with settings
      | path      | /test/sub |
      | shareWith | user1     |
      | shareType | user      |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "user1" folder "/sub" should exist

  Scenario: Unshare from self using custom groups
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user0" has created a custom group called "sharing-group"
    And user "user0" has made user "user1" a member of custom group "sharing-group"
    And user "user0" has shared file "/PARENT/parent.txt" with group "customgroup_sharing-group"
    And user "user0" has stored etag of element "/PARENT"
    And user "user1" has stored etag of element "/"
    When user "user1" deletes the last share using the sharing API
    Then the etag of element "/" of user "user1" should have changed
    And the etag of element "/PARENT" of user "user0" should not have changed

  Scenario: Increasing permissions is allowed for owner
    Given as user "admin"
    And user "user0" has been created with default attributes and skeleton files
    And user "user1" has been created with default attributes and skeleton files
    And user "user0" has created a custom group called "sharing-group"
    And user "user0" has made user "user1" a member of custom group "sharing-group"
    And user "user0" has shared folder "/FOLDER" with group "customgroup_sharing-group"
    When user "user0" updates the last share using the sharing API with
      | permissions | read |
    And user "user0" updates the last share using the sharing API with
      | permissions | all |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
