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


  Scenario: creating a file/folder inside a received share as recipient
    Given user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    And user "Brian" has accepted share "/shared" offered by user "Alice"
    When user "Brian" creates folder "/shared/new-folder" using the WebDAV API
    And user "Brian" uploads file with content "some data" to "/shared/textfile.txt" using the WebDAV API
    Then as "Brian" folder "/shared/new-folder" should exist
    And as "Alice" folder "/shared/new-folder" should exist
    And as "Brian" file "/shared/textfile.txt" should exist
    And as "Alice" file "/shared/textfile.txt" should exist
    And the content of file "/shared/textfile.txt" for user "Alice" should be "some data"


  Scenario: moving a file/folder into a share as recipient
    Given user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    And user "Brian" has accepted share "/shared" offered by user "Alice"
    And user "Brian" has uploaded file with content "some data" to "/textfile0.txt"
    And user "Brian" has created folder "/folder"
    When user "Brian" moves file "textfile0.txt" to "/shared/textfile0.txt" using the WebDAV API
    And user "Brian" moves folder "/folder" to "/shared/folder" using the WebDAV API
    Then as "Brian" file "/shared/textfile0.txt" should exist
    And as "Alice" file "/shared/textfile0.txt" should exist
    And the content of file "/shared/textfile0.txt" for user "Alice" should be "some data"
    And as "Brian" folder "/shared/folder" should exist
    And as "Alice" folder "/shared/folder" should exist


  Scenario: delete a file/folder share as a recipient
    Given user "Alice" has uploaded file with content "some data" to "/filetoshare.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    And user "Alice" has shared folder "/filetoshare.txt" with group "customgroup_sharing-group"
    When user "Brian" deletes file "/filetoshare.txt" using the WebDAV API
    And user "Brian" deletes folder "/shared" using the WebDAV API
    Then as "Brian" file "/filetoshare.txt" should not exist
    And as "Brian" folder "/shared" should not exist
    But as "Alice" folder "/shared" should exist
    And as "Alice" file "/filetoshare.txt" should exist


  Scenario: delete a sub-file/sub-folder share as a recipient
    Given user "Alice" has created folder "/shared/folder"
    And user "Alice" has uploaded file with content "some data" to "/shared/filetoshare.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    When user "Brian" deletes file "/shared/filetoshare.txt" using the WebDAV API
    And user "Brian" deletes folder "/shared/folder" using the WebDAV API
    Then as "Brian" file "/shared/filetoshare.txt" should not exist
    And as "Alice" file "/shared/filetoshare.txt" should not exist
    And as "Brian" folder "/shared/folder" should not exist
    And as "Alice" folder "/shared/folder" should not exist
    But as "Brian" folder "/shared" should exist
    And as "Alice" folder "/shared" should exist


  Scenario: rename a shared file/folder as a recipient
    Given user "Alice" has uploaded file with content "some data" to "/textfile0.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    And user "Alice" has shared file "/textfile0.txt" with group "customgroup_sharing-group"
    When user "Brian" moves file "/textfile0.txt" to "/new_file.txt" using the WebDAV API
    And user "Brian" moves folder "/shared" to "/new_folder" using the WebDAV API
    Then as "Brian" file "/new_file.txt" should exist
    And as "Brian" folder "/new_folder" should exist
    And as "Alice" file "/textfile0.txt" should exist
    And as "Alice" folder "/shared" should exist
    But as "Alice" file "/new_file.txt" should not exist
    And as "Alice" folder "/new_folder" should not exist
    And as "Brian" file "/textfile0.txt" should not exist
    And as "Brian" folder "/shared" should not exist


  Scenario: rename a shared sub-file/sub-folder as a recipient
    Given user "Alice" has created folder "/shared/folder"
    And user "Alice" has uploaded file with content "some data" to "/shared/filetoshare.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    When user "Brian" moves file "/shared/filetoshare.txt" to "/shared/new_file.txt" using the WebDAV API
    And user "Brian" moves folder "/shared/folder" to "/shared/new_folder" using the WebDAV API
    Then as "Brian" file "/shared/new_file.txt" should exist
    And as "Alice" file "/shared/new_file.txt" should exist
    And as "Brian" folder "/shared/new_folder" should exist
    And as "Alice" folder "/shared/new_folder" should exist
    But as "Brian" file "/shared/filetoshare.txt" should not exist
    And as "Alice" file "/shared/filetoshare.txt" should not exist
    And as "Brian" folder "/shared/folder" should not exist
    And as "Alice" folder "/shared/folder" should not exist


  Scenario: getting shares received from custom groups
    Given user "Alice" has uploaded file with content "some data" to "/textfile0.txt"
    And user "Alice" has shared file "textfile0.txt" with group "customgroup_sharing-group"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    When user "Brian" gets the group shares shared with him using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And exactly 2 files or folders should be included in the response
    And folder "/shared" should be included in the response
    And file "/textfile0.txt" should be included in the response


  Scenario: Sharer can download file uploaded by sharee to a shared folder
    Given user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    When user "Brian" uploads file with content "some content" to "/shared/textFile.txt" using the WebDAV API
    And user "Alice" downloads file "/shared/textFile.txt" using the WebDAV API
    Then the HTTP status code should be "200"
    And the downloaded content should be "some content"


  Scenario: Sharee can download file uploaded by sharer to a shared folder
    Given user "Alice" has uploaded file with content "some content" to "/shared/textFile.txt"
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    When user "Brian" downloads file "/shared/textFile.txt" using the WebDAV API
    Then the HTTP status code should be "200"
    And the downloaded content should be "some content"

  Scenario: upload a file to a shared folder as a recipient
    Given user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    When user "Brian" uploads file "filesForUpload/textfile.txt" to "/shared/textfile.txt" using the WebDAV API
    Then the HTTP status code should be "201"
    And the content of file "/shared/textfile.txt" for user "Alice" should be:
    """
    This is a testfile.

    Cheers.
    """


  Scenario: Resharing a share received from custom group
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has shared folder "/shared" with group "customgroup_sharing-group"
    When user "Brian" shares folder "/shared" with user "Carol" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And as "Carol" folder "/shared" should exist
    When user "Carol" uploads file with content "some data" to "/shared/filetoshare.txt" using the WebDAV API
    Then the HTTP status code should be "201"
    And as "Alice" file "/shared/filetoshare.txt" should exist
    And the content of file "/shared/filetoshare.txt" for user "Alice" should be "some data"
    And as "Brian" file "/shared/filetoshare.txt" should exist
    And the content of file "/shared/filetoshare.txt" for user "Brian" should be "some data"

