@api
Feature: Sharing Custom Groups

  Background:
    Given using OCS API version "1"
    And using new dav path
    And these users have been created with default attributes and without skeleton files:
      | username |
      | Alice    |
      | Brian    |
    And user "Alice" has created a custom group called "sharing-group"
    And user "Alice" has made user "Brian" a member of custom group "sharing-group"
    And user "Alice" has created folder "/shared"


  Scenario: share file/folder with read permission in custom group
    Given user "Alice" has uploaded file with content "some data" to "/filetoshare.txt"
    When user "Alice" shares file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions "read" using the sharing API
    And user "Brian" gets the info of the last share using the sharing API
    Then the fields of the last response about user "Alice" sharing with user "Brian" should include
      | uid_owner   | %username%                |
      | share_with  | customgroup_sharing-group |
      | file_target | /filetoshare.txt          |
      | item_type   | file                      |
      | permissions | read                      |
    And as "Brian" file "/filetoshare.txt" should exist
    When user "Alice" shares folder "/shared" with group "customgroup_sharing-group" with permissions "read" using the sharing API
    And user "Brian" gets the info of the last share using the sharing API
    Then the fields of the last response about user "Alice" sharing with user "Brian" should include
      | uid_owner   | %username%                |
      | share_with  | customgroup_sharing-group |
      | file_target | /shared                   |
      | item_type   | folder                    |
      | permissions | read                      |
    And as "Brian" folder "/shared" should exist


  Scenario: Custom group member cannot upload a file with read-only permission
    Given user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "read"
    When user "Brian" uploads file "filesForUpload/textfile.txt" to "/shared/textfile.txt" using the WebDAV API
    Then the HTTP status code should be "403"
    And as "Brian" file "/shared/textFile.txt" should not exist


  Scenario:Custom group member can upload file to a shared folder with upload-only permission works
    Given user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "create"
    When user "Brian" uploads file "filesForUpload/textfile.txt" to "/shared/textfile.txt" using the WebDAV API
    Then the HTTP status code should be "201"
    And the content of file "/shared/textfile.txt" for user "Alice" should be:
    """
    This is a testfile.

    Cheers.
    """


  Scenario: Custom group member can upload file to a shared folder with read/write permission works
    Given user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "change"
    When user "Brian" uploads file "filesForUpload/textfile.txt" to "/shared/textfile.txt" using the WebDAV API
    Then the HTTP status code should be "201"
    And the content of file "/shared/textfile.txt" for user "Alice" should be:
    """
    This is a testfile.

    Cheers.
    """


  Scenario: Custom group member cannot delete a file inside a shared folder with read-only permission
    Given user "Alice" has uploaded file with content "some data" to "/shared/filetoshare.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "read"
    When user "Brian" deletes file "/shared/filetoshare.txt" using the WebDAV API
    Then the HTTP status code should be "403"
    And as "Brian" file "/shared/filetoshare.txt" should exist


  Scenario: Custom group member cannot delete a file inside a shared folder with upload-only permission
    Given user "Alice" has uploaded file with content "some data" to "/shared/filetoshare.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "create"
    When user "Brian" deletes file "/shared/filetoshare.txt" using the WebDAV API
    Then the HTTP status code should be "403"
    And as "Alice" file "/shared/filetoshare.txt" should exist
    When user "Brian" uploads file "filesForUpload/textfile.txt" to "/shared/textfile.txt" using the WebDAV API
    And user "Brian" deletes file "/shared/textfile.txt" using the WebDAV API
    Then the HTTP status code should be "403"
    And as "Alice" file "/shared/textfile.txt" should exist


  Scenario: Custom group member can delete a file inside a shared folder with delete permission
    Given user "Alice" has uploaded file with content "some data" to "/shared/filetoshare.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "delete"
    When user "Brian" deletes file "/shared/filetoshare.txt" using the WebDAV API
    Then the HTTP status code should be "204"
    And as "Brian" file "/shared/filetoshare.txt" should not exist
    And as "Alice" file "/shared/filetoshare.txt" should not exist
    When user "Brian" deletes folder "/shared" using the WebDAV API
    Then the HTTP status code should be "204"
    And as "Brian" folder "/shared" should not exist
    But as "Alice" folder "/shared" should exist


  Scenario: Custom group member renames a received share with share, read, change permissions
    Given user "Alice" has uploaded file with content "thisIsAFileInsideTheSharedFolder" to "/shared/filetoshare.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "share,read,change"
    When user "Brian" moves folder "/shared" to "myFolder" using the WebDAV API
    Then the HTTP status code should be "201"
    And as "Brian" folder "myFolder" should exist
    But as "Alice" folder "myFolder" should not exist
    When user "Brian" moves file "/myFolder/filetoshare.txt" to "/myFolder/renamedFile.txt" using the WebDAV API
    Then the HTTP status code should be "201"
    And as "Brian" file "/myFolder/renamedFile.txt" should exist
    And as "Alice" file "/shared/renamedFile.txt" should exist
    But as "Alice" file "/shared/filetoshare.txt" should not exist


  Scenario: Custom group member is not allowed to reshare file/folder when reshare permission is not given
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsAFileInsideTheSharedFolder" to "/filetoshare.txt"
    And user "Alice" has shared file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions "read,update"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "read,update"
    When user "Brian" shares file "/filetoshare.txt" with user "Carol" with permissions "read,update" using the sharing API
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And as "Carol" file "/filetoshare.txt" should not exist
    But as "Brian" file "/filetoshare.txt" should exist
    When user "Brian" shares folder "/shared" with user "Carol" with permissions "read,update" using the sharing API
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should not exist
    But as "Brian" folder "/shared" should exist


  Scenario: Custom group member is allowed to reshare file/folder with share,change permission
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsAFileInsideTheSharedFolder" to "/shared/filetoshare.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "share,change"
    When user "Brian" shares folder "/shared" with user "Carol" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should exist
    And as "Carol" file "/shared/filetoshare.txt" should exist
    And the content of file "/shared/filetoshare.txt" for user "Carol" should be "thisIsAFileInsideTheSharedFolder"


  Scenario: Custom group member is allowed to reshare file/folder with the same permissions
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Alice" has shared file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions "share,read"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "share,read"
    When user "Brian" shares file "/filetoshare.txt" with user "Carol" with permissions "share,read" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" file "/filetoshare.txt" should exist
    And the content of file "/filetoshare.txt" for user "Carol" should be "thisIsASharedFile"
    When user "Brian" shares folder "/shared" with user "Carol" with permissions "share,read" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should exist


  Scenario: Custom group member is allowed to reshare a sub-folder with the same permissions
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has created folder "/shared/SUB"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "share,read"
    When user "Brian" shares folder "/shared/SUB" with user "Carol" with permissions "share,read" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    When user "Carol" accepts share "/SUB" offered by user "Brian" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" folder "/SUB" should exist
    And as "Brian" folder "/shared/SUB" should exist


  Scenario: Custom group member is allowed to reshare folder/file with permissions less than shared to them
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Alice" has shared file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions "share,update,read"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "share,update,read"
    When user "Brian" shares folder "/shared" with user "Carol" with permissions "share,read" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should exist
    When user "Brian" shares file "/filetoshare.txt" with user "Carol" with permissions "share,read" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" file "/filetoshare.txt" should exist
    And the content of file "/filetoshare.txt" for user "Carol" should be "thisIsASharedFile"


  Scenario: Custom group member is not allowed to reshare folder/file with permissions other than shared to them
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Alice" has shared file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions "share,read"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "share,read"
    When user "Brian" shares folder "/shared" with user "Carol" with permissions "share,update,read" using the sharing API
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should not exist
    When user "Brian" shares file "/filetoshare.txt" with user "Carol" with permissions "share,update,read" using the sharing API
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And as "Carol" file "/filetoshare.txt" should not exist


  Scenario Outline: Custom group member is not allowed to reshare file and set permissions other than shared to them
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Alice" has shared file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions <received_permissions>
    When user "Brian" shares file "/textfile0.txt" with user "Carol" with permissions <reshare_permissions> using the sharing API
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And as "Carol" file "/filetoshare.txt" should not exist
    But as "Brian" file "/filetoshare.txt" should exist
    Examples:
      | received_permissions | reshare_permissions |
      # passing on more bits including reshare
      | 17                   | 19                  |
      | 17                   | 23                  |
      | 17                   | 31                  |
      # passing on more bits but not reshare
      | 17                   | 3                   |
      | 17                   | 7                   |
      | 17                   | 15                  |


  Scenario Outline: Custom group member is allowed to reshare file and set create (4) or delete (8) permissions bits, which get ignored
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Alice" has shared file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions <received_permissions>
    When user "Brian" shares file "/filetoshare.txt" with user "Carol" with permissions <reshare_permissions> using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the fields of the last response to user "Brian" sharing with user "Carol" should include
      | share_with  | %username%            |
      | file_target | /filetoshare.txt      |
      | path        | /filetoshare.txt      |
      | permissions | <granted_permissions> |
      | uid_owner   | %username%            |
    And as "Carol" file "/filetoshare.txt" should exist
    # The receiver of the reshare can always delete their received share, even though they do not have delete permission
    And user "Carol" should be able to delete file "/filetoshare.txt"
    # But the upstream sharers will still have the file
    But as "Brian" file "/filetoshare.txt" should exist
    And as "Alice" file "/filetoshare.txt" should exist
    Examples:
      | received_permissions | reshare_permissions | granted_permissions |
      | 19                   | 23                  | 19                  |
      | 19                   | 31                  | 19                  |
      | 19                   | 7                   | 3                   |
      | 19                   | 15                  | 3                   |
      | 17                   | 21                  | 17                  |
      | 17                   | 5                   | 1                   |
      | 17                   | 25                  | 17                  |
      | 17                   | 9                   | 1                   |


  Scenario Outline: custom group member is not allowed to reshare folder and set more permissions other than shared to them
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has shared folder "/shared" with user "Brian" with permissions <received_permissions>
    When user "Brian" shares folder "/shared" with user "Carol" with permissions <reshare_permissions> using the sharing API
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should not exist
    But as "Brian" folder "/shared" should exist
    Examples:
     | received_permissions | reshare_permissions |
      # try to pass on more bits including reshare
     | 17                   | 19                  |
     | 17                   | 21                  |
     | 17                   | 23                  |
     | 17                   | 31                  |
     | 19                   | 23                  |
     | 19                   | 31                  |
     # try to pass on more bits but not reshare
     | 17                   | 3                   |
     | 17                   | 5                   |
     | 17                   | 7                   |
     | 17                   | 15                  |
     | 19                   | 7                   |
     | 19                   | 15                  |


  Scenario Outline: custom group member is not allowed to reshare folder and add delete permission bit (8)
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has shared folder "/shared" with user "Brian" with permissions <received_permissions>
    When user "Brian" shares folder "/shared" with user "Carol" with permissions <reshare_permissions> using the sharing API
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should not exist
    But as "Brian" folder "/shared" should exist
    Examples:
      | received_permissions | reshare_permissions |
      # try to pass on extra delete (including reshare)
      | 17                   | 25                  |
      | 19                   | 27                  |
      | 23                   | 31                  |
      # try to pass on extra delete (but not reshare)
      | 17                   | 9                   |
      | 19                   | 11                  |
      | 23                   | 15                  |


  Scenario: reshare receiver can create folder/file inside the received folder with create permission
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group" with permissions "share,read,create"
    When user "Brian" shares folder "/shared" with user "Carol" with permissions "create,read" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should exist
    When user "Carol" uploads file with content "some data" to "/shared/filetoshare.txt" using the WebDAV API
    Then the HTTP status code should be "201"
    And as "Alice" file "/shared/filetoshare.txt" should exist
    And the content of file "/shared/filetoshare.txt" for user "Alice" should be "some data"
    And as "Brian" file "/shared/filetoshare.txt" should exist
    And the content of file "/shared/filetoshare.txt" for user "Brian" should be "some data"
    When user "Carol" creates folder "/shared/test" using the WebDAV API
    Then the HTTP status code should be "201"
    And as "Alice" folder "/shared/test" should exist
    And as "Brian" folder "/shared/test" should exist
