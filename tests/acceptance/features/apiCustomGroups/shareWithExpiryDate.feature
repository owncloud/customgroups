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

  Scenario: sharing with default expiration date enabled but not enforced for groups, user shares without specifying expireDate
    Given parameter "shareapi_default_expire_date_group_share" of app "core" has been set to "yes"
    When user "Alice" shares folder "/shared" with group "customgroup_sharing-group" using the sharing API
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the fields of the last response to user "Alice" sharing with group "customgroup_sharing-group" should include
      | share_type  | group                     |
      | file_target | /shared                   |
      | uid_owner   | %username%                |
      | expiration  |                           |
      | share_with  | customgroup_sharing-group |
    And the response when user "Brian" gets the info of the last share should include
      | expiration |  |


  Scenario: sharing with default expiration date enabled but not enforced for groups, user shares with expiration date
    Given parameter "shareapi_default_expire_date_group_share" of app "core" has been set to "yes"
    When user "Alice" creates a share using the sharing API with settings
      | path        | /shared                   |
      | shareType   | group                     |
      | shareWith   | customgroup_sharing-group |
      | permissions | read,share                |
      | expireDate  | +15 days                  |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the fields of the last response to user "Alice" sharing with group "customgroup_sharing-group" should include
      | share_type  | group                     |
      | file_target | /shared                   |
      | uid_owner   | %username%                |
      | expiration  | +15 days                  |
      | share_with  | customgroup_sharing-group |
    And the response when user "Brian" gets the info of the last share should include
      | expiration | +15 days |


  Scenario: sharing with default expiration date not enabled for groups, user shares with expiration date set
    When user "Alice" creates a share using the sharing API with settings
      | path        | /shared                   |
      | shareType   | group                     |
      | shareWith   | customgroup_sharing-group |
      | permissions | read,share                |
      | expireDate  | +15 days                  |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And the fields of the last response to user "Alice" sharing with group "customgroup_sharing-group" should include
      | share_type  | group                     |
      | file_target | /shared                   |
      | uid_owner   | %username%                |
      | expiration  | +15 days                  |
      | share_with  | customgroup_sharing-group |
    And the response when user "Brian" gets the info of the last share should include
      | expiration | +15 days |


  Scenario: sharing with default expiration date enabled and enforced for groups, user shares with expiration date and then disables
    Given parameter "shareapi_default_expire_date_group_share" of app "core" has been set to "yes"
    And parameter "shareapi_enforce_expire_date_group_share" of app "core" has been set to "yes"
    When user "Alice" creates a share using the sharing API with settings
      | path        | /shared                   |
      | shareType   | group                     |
      | shareWith   | customgroup_sharing-group |
      | permissions | read,share                |
      | expireDate  | +3 days                   |
    And the administrator sets parameter "shareapi_default_expire_date_group_share" of app "core" to "no"
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with group "customgroup_sharing-group" should include
      | share_type  | group                     |
      | file_target | /shared                  |
      | uid_owner   | %username%                |
      | share_with  | customgroup_sharing-group |
      | expiration  | +3 days                   |
    And the response when user "Brian" gets the info of the last share should include
      | expiration | +3 days |


  Scenario: sharing with default expiration date enabled but not enforced for groups, user shares with expiration date and then disables
    Given parameter "shareapi_default_expire_date_group_share" of app "core" has been set to "yes"
    When user "Alice" creates a share using the sharing API with settings
      | path        | /shared                   |
      | shareType   | group                     |
      | shareWith   | customgroup_sharing-group |
      | permissions | read,share                |
      | expireDate  | +15 days                  |
    And the administrator sets parameter "shareapi_default_expire_date_group_share" of app "core" to "no"
    And user "Alice" gets the info of the last share using the sharing API
    Then the fields of the last response to user "Alice" sharing with group "customgroup_sharing-group" should include
      | share_type  | group                     |
      | file_target | /shared                   |
      | uid_owner   | %username%                |
      | share_with  | customgroup_sharing-group |
      | expiration  | +15 days                  |
    And the response when user "Brian" gets the info of the last share should include
      | expiration | +15 days |


  Scenario: sharing with default expiration date enabled and enforced for groups, user shares without setting expiration date
    Given parameter "shareapi_default_expire_date_group_share" of app "core" has been set to "yes"
    And parameter "shareapi_enforce_expire_date_group_share" of app "core" has been set to "yes"
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    When user "Alice" shares file "/filetoshare.txt" with group "customgroup_sharing-group" using the sharing API
    Then the fields of the last response to user "Alice" sharing with group "customgroup_sharing-group" should include
      | share_type  | group            |
      | file_target | /filetoshare.txt |
      | uid_owner   | %username%       |
      | share_with  | %username%       |
      | expiration  | +7 days          |
    And the response when user "Brian" gets the info of the last share should include
      | expiration | +7 days |


  Scenario: sharing with default expiration date enabled and enforced for groups, user shares with expiration date more than the default
    Given parameter "shareapi_default_expire_date_group_share" of app "core" has been set to "yes"
    And parameter "shareapi_enforce_expire_date_group_share" of app "core" has been set to "yes"
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    When user "Alice" creates a share using the sharing API with settings
      | path        | filetoshare.txt           |
      | shareType   | group                     |
      | shareWith   | customgroup_sharing-group |
      | permissions | read,share                |
      | expireDate  | +10 days                  |
    Then the HTTP status code should be "200"
    And the OCS status code should be "404"
    And the OCS status message should be "Cannot set expiration date more than 7 days in the future"
    And user "Brian" should not have any received shares


  Scenario: sharing with default expiration date enabled and enforced for groups/max expire date is set, user shares without setting expiration date
    Given parameter "shareapi_default_expire_date_group_share" of app "core" has been set to "yes"
    And parameter "shareapi_enforce_expire_date_group_share" of app "core" has been set to "yes"
    And parameter "shareapi_expire_after_n_days_group_share" of app "core" has been set to "30"
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    When user "Alice" shares file "filetoshare.txt" with group "customgroup_sharing-group" using the sharing API
    Then the fields of the last response to user "Alice" sharing with group "customgroup_sharing-group" should include
      | share_type  | group                     |
      | file_target | /filetoshare.txt          |
      | uid_owner   | %username%                |
      | share_with  | customgroup_sharing-group |
      | expiration  | +30 days                  |
    And the response when user "Brian" gets the info of the last share should include
      | expiration | +30 days |


  Scenario: User should be able to set expiration while resharing a file with user
    Given user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Alice" has shared file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions "read,update,share"
    When user "Brian" creates a share using the sharing API with settings
      | path        | filetoshare.txt |
      | shareType   | user            |
      | permissions | change          |
      | shareWith   | Carol           |
      | expireDate  | +3 days         |
    Then the HTTP status code should be "200"
    And the OCS status code should be "100"
    And the information of the last share of user "Brian" should include
      | expiration | +3 days |
    And the response when user "Carol" gets the info of the last share should include
      | expiration | +3 days |


  Scenario Outline: resharing with user using the sharing API with expire days set and combinations of default/enforce expire date enabled
    Given parameter "shareapi_default_expire_date_user_share" of app "core" has been set to "<default-expire-date>"
    And parameter "shareapi_enforce_expire_date_user_share" of app "core" has been set to "<enforce-expire-date>"
    And parameter "shareapi_expire_after_n_days_user_share" of app "core" has been set to "30"
    And user "Carol" has been created with default attributes and without skeleton files
    And user "Alice" has uploaded file with content "thisIsASharedFile" to "/filetoshare.txt"
    And user "Alice" has shared file "/filetoshare.txt" with group "customgroup_sharing-group" with permissions "read,update,share"
    When user "Brian" creates a share using the sharing API with settings
      | path        | filetoshare.txt |
      | shareType   | user            |
      | permissions | change          |
      | shareWith   | Carol           |
    Then the HTTP status code should be "200"
    And the OCS status code should be "100"
    And the information of the last share of user "Brian" should include
      | expiration | <expected-expire-date> |
    And the response when user "Carol" gets the info of the last share should include
      | expiration | <expected-expire-date> |
    Examples:
      | default-expire-date | enforce-expire-date | expected-expire-date |
      | yes                 | yes                 | +30 days             |
      | no                  | yes                 |                      |

