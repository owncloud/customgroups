@api
Feature: Sharing Custom Groups

  Background:
    Given using OCS API version "1"
    And using new dav path


  Scenario: Check that skeleton is properly set
    Given user "Alice" has been created with default attributes and small skeleton files
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
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "sharing-group"
    And user "Brian" has made user "Carol" a member of custom group "sharing-group"
    When user "Alice" creates a share using the sharing API with settings
      | path      | filetoshare.txt           |
      | shareWith | customgroup_sharing-group |
      | shareType | group                     |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" file "filetoshare.txt" should exist
    And as "Carol" file "filetoshare.txt" should exist

  Scenario: Creating a new share with user who already received a share through their custom group
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Brian" has created a custom group called "sharing-group"
    And user "Alice" has shared file "filetoshare.txt" with group "customgroup_sharing-group"
    When user "Alice" creates a share using the sharing API with settings
      | path      | filetoshare.txt |
      | shareWith | Brian           |
      | shareType | user            |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Keep custom group permissions in sync
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/textfile0.txt"
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
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/textfile0.txt"
    And user "Brian" has created a custom group called "group1"
    And user "Alice" has shared file "textfile0.txt" with group "customgroup_group1"
    When user "Brian" sends HTTP method "GET" to OCS API endpoint "/apps/files_sharing/api/v1/shares?shared_with_me=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the last share_id should be included in the response

  Scenario: Share of folder and sub-folder to same user
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created folder "/PARENT"
    And user "Alice" has created folder "/PARENT/CHILD"
    And user "Alice" has uploaded file with content "parent" to "/PARENT/parent.txt"
    And user "Alice" has uploaded file with content "child" to "/PARENT/CHILD/child.txt"
    And user "Brian" has created folder "/FOLDER"
    And user "Brian" has created a custom group called "group1"
    And user "Alice" has shared folder "/PARENT" with user "Brian"
    When user "Alice" shares folder "/PARENT/CHILD" with group "customgroup_group1" using the sharing API
    Then user "Brian" should see the following elements
      | /FOLDER/           |
      | /PARENT/           |
      | /CHILD/            |
      | /PARENT/parent.txt |
      | /CHILD/child.txt   |
    And the HTTP status code should be "200"

  Scenario: Share a file by multiple channels
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has uploaded file with content "This is a test text" to "/textfile0.txt"
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "group1"
    And user "Brian" has made user "Carol" a member of custom group "group1"
    And user "Alice" has created folder "/common"
    And user "Alice" has created folder "/common/sub"
    And user "Alice" has shared folder "common" with group "customgroup_group1"
    And user "Brian" has shared file "textfile0.txt" with user "Carol"
    And user "Brian" has moved file "/textfile0.txt" to "/common/textfile0.txt"
    And user "Brian" has moved file "/common/textfile0.txt" to "/common/sub/textfile0.txt"
    When user "Carol" downloads file "/textfile0.txt" with range "bytes=10-18" using the WebDAV API
    Then the downloaded content should be "test text"
    And user "Carol" should see the following elements
      | /common/sub/textfile0.txt |

  Scenario: Delete all custom group shares
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Brian" has created a custom group called "group1"
    And user "Alice" has uploaded file with content "This is a test text" to "/textfile0.txt"
    And user "Alice" has shared file "textfile0.txt" with group "customgroup_group1"
    And user "Brian" has created folder "/FOLDER"
    And user "Brian" has moved file "/textfile0.txt" to "/FOLDER/textfile0.txt"
    When user "Alice" deletes the last share using the sharing API
    And user "Brian" sends HTTP method "GET" to OCS API endpoint "/apps/files_sharing/api/v1/shares?shared_with_me=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the last share id should not be included in the response

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
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "This is a test text" to "/textfile.txt"
    And user "Alice" has created a custom group called "sharing-group"
    And user "Alice" has shared file "textfile.txt" with group "customgroup_sharing-group"
    And user "Alice" deletes the last share using the sharing API
    When user "Alice" creates a share using the sharing API with settings
      | path      | textfile.txt              |
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
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created folder "/PARENT"
    And user "Alice" has uploaded file with content "This is a test text" to "/PARENT/parent.txt"
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
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Alice" has created folder "/FOLDER"
    And user "Alice" has created a custom group called "sharing-group"
    And user "Alice" has made user "Brian" a member of custom group "sharing-group"
    And user "Alice" has shared folder "/FOLDER" with group "customgroup_sharing-group"
    When user "Alice" updates the last share using the sharing API with
      | permissions | read |
    And user "Alice" updates the last share using the sharing API with
      | permissions | all |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: user shares a file to a custom group and normal group with same name
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "This is a test text" to "/textfile0.txt"
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "group1"
    And group "group1" has been created
    And user "Alice" has made user "Brian" a member of custom group "group1"
    And user "Carol" has been added to group "group1"
    When user "Alice" shares file "/textfile0.txt" with group "customgroup_group1" using the sharing API
    Then  the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" file "/textfile0.txt" should exist
    When user "Alice" shares file "/textfile0.txt" with group "group1" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" file "/textfile0.txt" should exist
    And custom group "group1" should exist
    And group "group1" should exist

  Scenario: sharing sub-folder to a custom group when the main folder is already shared with a user
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "group1"
    And user "Alice" has made user "Carol" a member of custom group "group1"
    And user "Alice" has created folder "/NEW-FOLDER"
    And user "Alice" has created folder "/NEW-FOLDER/sub-folder"
    And user "Alice" has shared folder "/NEW-FOLDER" with user "Brian"
    When user "Alice" shares folder "/NEW-FOLDER/sub-folder" with group "customgroup_group1" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" folder "/sub-folder" should exist

  Scenario: sharing sub-folder to a custom group when the main folder is already shared with a normal group
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "group1"
    And user "Alice" has made user "Brian" a member of custom group "group1"
    And group "grp1" has been created
    And user "Alice" has created folder "/NEW-FOLDER"
    And user "Alice" has created folder "/NEW-FOLDER/sub-folder"
    And user "Alice" has shared folder "/NEW-FOLDER" with group "grp1"
    When user "Alice" shares folder "/NEW-FOLDER/sub-folder" with group "customgroup_group1" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" folder "/sub-folder" should exist


  Scenario: sharing sub-folder to a user when the main folder is already shared with a custom group
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "group1"
    And user "Alice" has created folder "/NEW-FOLDER"
    And user "Alice" has created folder "/NEW-FOLDER/sub-folder"
    And user "Alice" has shared folder "/NEW-FOLDER" with group "customgroup_group1"
    When user "Alice" shares folder "/NEW-FOLDER/sub-folder" with user "Brian" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" folder "/sub-folder" should exist

  Scenario: sharing sub-folder to a normal group when the main folder is already shared with a custom group
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "group1"
    And group "group1" has been created
    And user "Brian" has been added to group "group1"
    And user "Alice" has created folder "/NEW-FOLDER"
    And user "Alice" has created folder "/NEW-FOLDER/sub-folder"
    And user "Alice" has shared folder "/NEW-FOLDER" with group "customgroup_group1"
    When user "Alice" shares folder "/NEW-FOLDER/sub-folder" with group "group1" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" folder "/sub-folder" should exist


  Scenario: share same folder to different user, normal group and custom group
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "sharing-group"
    And group "grp1" has been created
    And user "Brian" has been added to group "grp1"
    And user "Carol" has been created with default attributes and without skeleton files
    And user "David" has been created with default attributes and without skeleton files
    And user "Alice" has made user "Carol" a member of custom group "sharing-group"
    And user "Alice" has created folder "/foldertoshare"
    When user "Alice" shares folder "/foldertoshare" with group "grp1" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" folder "/foldertoshare" should exist
    When user "Alice" shares folder "/foldertoshare" with group "customgroup_sharing-group" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" folder "/foldertoshare" should exist
    When user "Alice" shares folder "/foldertoshare" with user "David" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "David" folder "/foldertoshare" should exist

  Scenario: share the same file to a different user, normal group and custom group
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "sharing-group"
    And user "Alice" has uploaded file with content "This is a test text" to "/filetoshare.txt"
    And group "grp1" has been created
    And user "Brian" has been added to group "grp1"
    And user "Carol" has been created with default attributes and without skeleton files
    And user "David" has been created with default attributes and without skeleton files
    And user "Alice" has made user "Carol" a member of custom group "sharing-group"
    When user "Alice" shares file "/filetoshare.txt" with group "grp1" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Brian" file "/filetoshare.txt" should exist
    When user "Alice" shares file "/filetoshare.txt" with group "customgroup_sharing-group" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" file "/filetoshare.txt" should exist
    When user "Alice" shares file "/filetoshare.txt" with user "David" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "David" file "/filetoshare.txt" should exist

  Scenario: share the same folder to the same user through different medium
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "sharing-group"
    And group "grp1" has been created
    And user "Brian" has been added to group "grp1"
    And user "Alice" has made user "Brian" a member of custom group "sharing-group"
    And user "Alice" has created folder "/foldertoshare"
    When user "Alice" shares folder "/foldertoshare" with group "grp1" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the response when user "Brian" gets the info of the last share should include
      | uid_owner   | Alice          |
      | share_with  | grp1           |
      | file_target | /foldertoshare |
      | item_type   | folder         |
    And as "Brian" folder "/foldertoshare" should exist
    When user "Alice" shares folder "/foldertoshare" with group "customgroup_sharing-group" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the response when user "Brian" gets the info of the last share should include
      | uid_owner   | Alice                     |
      | share_with  | customgroup_sharing-group |
      | file_target | /foldertoshare            |
      | item_type   | folder                    |
    And as "Brian" folder "/foldertoshare" should exist
    When user "Alice" shares folder "/foldertoshare" with user "Brian" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the response when user "Brian" gets the info of the last share should include
      | uid_owner   | Alice          |
      | share_with  | Brian          |
      | file_target | /foldertoshare |
      | item_type   | folder         |
    And as "Brian" folder "/foldertoshare" should exist

  Scenario: share the same file to the same user through different medium
    Given user "Alice" has been created with default attributes and without skeleton files
    And user "Brian" has been created with default attributes and without skeleton files
    And user "Alice" has created a custom group called "sharing-group"
    And user "Alice" has uploaded file with content "This is a test text" to "/filetoshare.txt"
    And group "grp1" has been created
    And user "Brian" has been added to group "grp1"
    And user "Alice" has made user "Brian" a member of custom group "sharing-group"
    When user "Alice" shares file "/filetoshare.txt" with group "grp1" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the response when user "Brian" gets the info of the last share should include
      | uid_owner   | Alice            |
      | share_with  | grp1             |
      | file_target | /filetoshare.txt |
      | item_type   | file             |
    And as "Brian" file "/filetoshare.txt" should exist
    When user "Alice" shares file "/filetoshare.txt" with group "customgroup_sharing-group" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the response when user "Brian" gets the info of the last share should include
      | uid_owner   | Alice                     |
      | share_with  | customgroup_sharing-group |
      | file_target | /filetoshare.txt          |
      | item_type   | file                      |
    And as "Brian" file "/filetoshare.txt" should exist
    When user "Alice" shares file "/filetoshare.txt" with user "Brian" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the response when user "Brian" gets the info of the last share should include
      | uid_owner   | Alice            |
      | share_with  | Brian            |
      | file_target | /filetoshare.txt |
      | item_type   | file             |
    And as "Brian" file "/filetoshare.txt" should exist
