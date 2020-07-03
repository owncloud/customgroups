@api
Feature: Sharing Custom Groups

  Background:
    Given using OCS API version "1"
    And using new dav path

  Scenario: Check that skeleton is properly set
    Given user "Alice" has been created with default attributes and skeleton files
    Then user "Alice" should see the following elements
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
    Given user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "sharing-group"
    And user "Brian" has made user "Carol" a member of custom group "sharing-group"
    When user "Alice" creates a share using the sharing API with settings
      | path      | welcome.txt               |
      | shareWith | customgroup_sharing-group |
      | shareType | group                     |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Creating a new share with user who already received a share through their custom group
    Given user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "sharing-group"
    And user "Alice" has shared file "welcome.txt" with group "customgroup_sharing-group"
    When user "Alice" creates a share using the sharing API with settings
      | path      | welcome.txt |
      | shareWith | Brian       |
      | shareType | user        |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Keep custom group permissions in sync
    Given user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has created folder "/FOLDER"
    And user "Brian" has created a custom group called "group1"
    And user "Alice" has shared file "textfile0.txt" with group "customgroup_group1"
    And user "Brian" has moved file "/textfile0.txt" to "/FOLDER/textfile0.txt"
    When user "Alice" updates the last share using the sharing API with
      | permissions | read |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the response when user "Alice" gets the info of the last share should include
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
      | uid_owner         | Alice          |
      | storage_id        | home::Alice    |
      | displayname_owner | Alice Hansen   |
      | mimetype          | text/plain     |

  Scenario: Sharee can see the custom group share
    Given user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "group1"
    And user "Alice" has shared file "textfile0.txt" with group "customgroup_group1"
    When user "Brian" sends HTTP method "GET" to OCS API endpoint "/apps/files_sharing/api/v1/shares?shared_with_me=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the last share_id should be included in the response

  Scenario: Share of folder and sub-folder to same user
    Given user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has created folder "/FOLDER"
    And user "Brian" has created a custom group called "group1"
    And user "Alice" has shared file "/PARENT" with user "Brian"
    When user "Alice" shares file "/PARENT/CHILD" with group "customgroup_group1" using the sharing API
    Then user "Brian" should see the following elements
      | /FOLDER/           |
      | /PARENT/           |
      | /CHILD/            |
      | /PARENT/parent.txt |
      | /CHILD/child.txt   |
    And the HTTP status code should be "200"

  Scenario: Share a file by multiple channels
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and skeleton files
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "group1"
    And user "Brian" has made user "Carol" a member of custom group "group1"
    And user "Alice" has created folder "/common"
    And user "Alice" has created folder "/common/sub"
    And user "Alice" has shared folder "common" with group "customgroup_group1"
    And user "Brian" has shared file "textfile0.txt" with user "Carol"
    And user "Brian" has moved file "/textfile0.txt" to "/common/textfile0.txt"
    And user "Brian" has moved file "/common/textfile0.txt" to "/common/sub/textfile0.txt"
    When user "Carol" downloads file "/textfile0.txt" with range "bytes=9-17" using the WebDAV API
    Then the downloaded content should be "test text"
    And user "Carol" should see the following elements
      | /common/sub/textfile0.txt |

  Scenario: Delete all custom group shares
    Given user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "group1"
    And user "Alice" has shared file "textfile0.txt" with group "customgroup_group1"
    And user "Brian" has created folder "/FOLDER"
    And user "Brian" has moved file "/textfile0.txt" to "/FOLDER/textfile0.txt"
    When user "Alice" deletes the last share using the sharing API
    And user "Brian" sends HTTP method "GET" to OCS API endpoint "/apps/files_sharing/api/v1/shares?shared_with_me=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the last share_id should not be included in the response

  Scenario: Keep user custom group shares
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "group1"
    And user "Brian" has made user "Carol" a member of custom group "group1"
    And user "Alice" has created folder "/TMP"
    And user "Alice" has shared folder "TMP" with group "customgroup_group1"
    And user "Brian" has created folder "/myFOLDER"
    And user "Brian" has moved file "/TMP" to "/myFOLDER/myTMP"
    When the administrator deletes user "Carol" using the provisioning API
    Then user "Brian" should see the following elements
      | /myFOLDER/myTMP/ |

  Scenario: Sharing again an own file while belonging to a custom group
    Given as user "admin"
    And user "Alice" has been created with default attributes and skeleton files
    And user "Alice" has created a custom group called "sharing-group"
    And group "sharing-group" has been created
    And user "Alice" has shared file "welcome.txt" with group "customgroup_sharing-group"
    And user "Alice" deletes the last share using the sharing API
    When user "Alice" creates a share using the sharing API with settings
      | path      | welcome.txt               |
      | shareWith | customgroup_sharing-group |
      | shareType | group                     |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Sharing subfolder when parent already shared
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "sharing-group"
    And user "Alice" has created folder "/test"
    And user "Alice" has created folder "/test/sub"
    And user "Alice" has shared folder "/test" with group "customgroup_sharing-group"
    When user "Alice" creates a share using the sharing API with settings
      | path      | /test/sub |
      | shareWith | Brian     |
      | shareType | user      |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" folder "/sub" should exist

  Scenario: Sharing subfolder when parent already shared with custom group of sharer
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "sharing-group"
    And user "Alice" has created folder "/test"
    And user "Alice" has created folder "/test/sub"
    And user "Alice" has shared file "/test" with group "customgroup_sharing-group"
    When user "Alice" creates a share using the sharing API with settings
      | path      | /test/sub |
      | shareWith | Brian     |
      | shareType | user      |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" folder "/sub" should exist

  Scenario: Unshare from self using custom groups
    Given user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "sharing-group"
    And user "Alice" has made user "Brian" a member of custom group "sharing-group"
    And user "Alice" has shared file "/PARENT/parent.txt" with group "customgroup_sharing-group"
    And user "Alice" has stored etag of element "/PARENT"
    And user "Brian" has stored etag of element "/"
    When user "Brian" unshares file "parent.txt" using the WebDAV API
    Then the HTTP status code should be "204"
    And the etag of element "/" of user "Brian" should have changed
    And the etag of element "/PARENT" of user "Alice" should not have changed

  Scenario: Increasing permissions is allowed for owner
    Given user "Alice" has been created with default attributes and skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "sharing-group"
    And user "Alice" has made user "Brian" a member of custom group "sharing-group"
    And user "Alice" has shared folder "/FOLDER" with group "customgroup_sharing-group"
    When user "Alice" updates the last share using the sharing API with
      | permissions | read |
    And user "Alice" updates the last share using the sharing API with
      | permissions | all |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
