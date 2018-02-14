Feature: Custom Groups

  Background:
    Given using api version "1"
    And using new dav path

  Scenario: Create a custom group
    Given as user "admin"
    And user "user0" has been created
    When user "user0" creates a custom group called "group0" using the API
    Then the HTTP status code should be "201"
    And custom group "group0" should exist

  Scenario: Create an already existing custom group
    Given as user "admin"
    And user "user0" has been created
    And user "user0" has created a custom group called "group0"
    And custom group "group0" should exist
    When user "user0" creates a custom group called "group0" using the API
    Then the HTTP status code should be "405"

  Scenario: Delete a custom group
    Given as user "admin"
    And user "user0" has been created
    And user "user0" has created a custom group called "group0"
    When user "user0" deletes a custom group called "group0" using the API
    Then the HTTP status code should be "204"
    And custom group "group0" should not exist

  Scenario: Rename a custom group
    Given as user "admin"
    And user "user0" has been created
    And user "user0" has created a custom group called "group0"
    And custom group "group0" should exist
    When user "user0" renames custom group "group0" as "renamed-group0" using the API
    Then custom group "group0" should exist with display name "renamed-group0"

  Scenario: A non-admin member cannot rename its custom group
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "user0" has created a custom group called "group0"
    And custom group "group0" should exist
    And user "user0" has made user "member1" a member of custom group "group0"
    When user "member1" renames custom group "group0" as "renamed-group0" using the API
    Then custom group "group0" should exist with display name "group0"

  Scenario: Get members of a group
    Given as user "admin"
    And user "user0" has been created
    When user "user0" creates a custom group called "group0" using the API
    Then the members of "group0" requested by user "user0" should be
      | user0 |

  Scenario: Creator of a custom group becames admin automatically
    Given as user "admin"
    And user "user0" has been created
    When user "user0" creates a custom group called "group0" using the API
    Then user "user0" should be an admin of custom group "group0"

  Scenario: Creator of a custom group can add members
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "member2" has been created
    And user "user0" has created a custom group called "group0"
    When user "user0" makes user "member1" a member of custom group "group0" using the API
    And user "user0" makes user "member2" a member of custom group "group0" using the API
    Then the members of "group0" requested by user "user0" should be
      | user0   |
      | member1 |
      | member2 |

  Scenario: A non-admin member of a custom group cannot add members
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "member2" has been created
    And user "user0" has created a custom group called "group0"
    And user "user0" has made user "member1" a member of custom group "group0"
    When user "member1" makes user "member2" a member of custom group "group0" using the API
    Then the HTTP status code should be "403"
    And the members of "group0" requested by user "user0" should be
      | user0   |
      | member1 |

  Scenario: A non-member of a custom group cannot list its members
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "not-member" has been created
    And user "user0" has created a custom group called "group0"
    When user "user0" makes user "member1" a member of custom group "group0" using the API
    Then user "not-member" should not be able to get members of custom group "group0"

  Scenario: A custom group member can list members
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "member2" has been created
    And user "user0" has created a custom group called "group0"
    When user "user0" makes user "member1" a member of custom group "group0" using the API
    And user "user0" makes user "member2" a member of custom group "group0" using the API
    Then the members of "group0" requested by user "member1" should be
      | user0   |
      | member1 |
      | member2 |

  Scenario: A non-admin member of a custom group cannot delete a custom group
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "user0" has created a custom group called "group0"
    When user "user0" makes user "member1" a member of custom group "group0" using the API
    And user "member1" deletes a custom group called "group0" using the API
    Then the HTTP status code should be "403"
    And custom group "group0" should exist

  Scenario: Creator of a custom group can remove members
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "member2" has been created
    And user "user0" has created a custom group called "group0"
    And user "user0" has made user "member1" a member of custom group "group0"
    And user "user0" has made user "member2" a member of custom group "group0"
    When user "user0" removes membership of user "member1" from custom group "group0" using the API
    Then the members of "group0" requested by user "user0" should be
      | user0   |
      | member2 |

  Scenario: A non-admin member of a custom group cannot remove members
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "member2" has been created
    And user "user0" has created a custom group called "group0"
    And user "user0" has made user "member1" a member of custom group "group0"
    And user "user0" has made user "member2" a member of custom group "group0"
    When user "member2" removes membership of user "member1" from custom group "group0" using the API
    Then the HTTP status code should be "403"
    And the members of "group0" requested by user "user0" should be
      | user0   |
      | member1 |
      | member2 |

  Scenario: Group owner cannot remove self if no other admin exists in the group
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "user0" has created a custom group called "group0"
    And user "user0" has made user "member1" a member of custom group "group0"
    When user "user0" removes membership of user "user0" from custom group "group0" using the API
    Then the HTTP status code should be "403"
    And the members of "group0" requested by user "user0" should be
      | user0   |
      | member1 |

  Scenario: A member of a custom group can leave the custom group himself
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "user0" has created a custom group called "group0"
    And user "user0" has made user "member1" a member of custom group "group0"
    When user "member1" removes membership of user "member1" from custom group "group0" using the API
    Then the HTTP status code should be "204"
    And the members of "group0" requested by user "user0" should be
      | user0 |

  Scenario: A user can list his groups
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "user0" has created a custom group called "group0"
    And user "user0" has created a custom group called "group1"
    And user "user0" has created a custom group called "group2"
    When user "user0" makes user "member1" a member of custom group "group0" using the API
    And user "user0" makes user "member1" a member of custom group "group1" using the API
    And user "user0" makes user "member1" a member of custom group "group2" using the API
    Then the custom groups of "member1" requested by user "member1" should be
      | group0 |
      | group1 |
      | group2 |

  Scenario: Change role of a member of a group
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "user0" has created a custom group called "group0"
    And custom group "group0" should exist
    And user "user0" has made user "member1" a member of custom group "group0"
    When user "user0" changes role of "member1" to admin in custom group "group0" using the API
    Then user "member1" should be an admin of custom group "group0"

  Scenario: Create a custom group and let another user as admin
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "user0" has created a custom group called "group0"
    And custom group "group0" should exist
    And user "user0" has made user "member1" a member of custom group "group0"
    And user "user0" has changed role of "member1" to admin in custom group "group0"
    When user "user0" changes role of "user0" to member in custom group "group0" using the API
    Then user "member1" should be an admin of custom group "group0"
    And user "user0" should be a member of custom group "group0"

  Scenario: Superadmin can do everything
    Given as user "admin"
    And user "user0" has been created
    And user "user1" has been created
    And user "user2" has been created
    And user "admin" has created a custom group called "group0"
    And user "admin" has created a custom group called "group1"
    And user "admin" has deleted a custom group called "group1"
    And user "admin" has made user "user0" a member of custom group "group0"
    And user "admin" has made user "user1" a member of custom group "group0"
    And user "admin" has made user "user2" a member of custom group "group0"
    And user "admin" has renamed custom group "group0" as "renamed-group0"
    And custom group "group0" should exist with display name "renamed-group0"
    And user "admin" has changed role of "user0" to admin in custom group "group0"
    And user "user0" should be an admin of custom group "group0"
    When user "admin" removes membership of user "user1" from custom group "group0" using the API
    Then the members of "group0" requested by user "admin" should be
      | user0 |
      | user2 |
      | admin |

  Scenario: Superadmin can add a user to any custom group
    Given as user "admin"
    And user "user0" has been created
    And user "user1" has been created
    And user "user0" has created a custom group called "group0"
    When user "admin" makes user "user1" a member of custom group "group0" using the API
    Then the HTTP status code should be "201"
    And the members of "group0" requested by user "admin" should be
      | user0 |
      | user1 |

  Scenario: Superadmin can rename any custom group
    Given as user "admin"
    And user "user0" has been created
    And user "user1" has been created
    And user "user0" has created a custom group called "group0"
    When user "admin" renames custom group "group0" as "renamed-group0" using the API
    Then custom group "group0" should exist with display name "renamed-group0"

  Scenario: A member converted to group owner can do the same as group owner
    Given as user "admin"
    And user "user0" has been created
    And user "user1" has been created
    And user "user2" has been created
    And user "user0" has created a custom group called "group0"
    And user "user0" has created a custom group called "group1"
    And user "user0" has made user "user1" a member of custom group "group0"
    And user "user0" has made user "user1" a member of custom group "group1"
    And user "user0" has changed role of "user1" to admin in custom group "group0"
    And user "user0" has changed role of "user1" to admin in custom group "group1"
    And user "user1" has deleted a custom group called "group1"
    And user "user1" has made user "user2" a member of custom group "group0"
    And user "user1" has renamed custom group "group0" as "renamed-group0"
    And custom group "group0" should exist with display name "renamed-group0"
    And user "user1" has changed role of "user2" to admin in custom group "group0"
    And user "user2" should be an admin of custom group "group0"
    When user "user1" removes membership of user "user0" from custom group "group0" using the API
    Then the members of "group0" requested by user "user1" should be
      | user1 |
      | user2 |

  Scenario: A group owner cannot remove his own admin permissions if there is no other owner in the group
    Given as user "admin"
    And user "user0" has been created
    And user "member1" has been created
    And user "user0" has created a custom group called "group0"
    And custom group "group0" should exist
    And user "user0" has made user "member1" a member of custom group "group0"
    When user "user0" changes role of "user1" to member in custom group "group0" using the API
    Then user "user0" should be an admin of custom group "group0"

  Scenario: A non-existing user cannot be added to a custom group
    Given as user "admin"
    And user "user0" has been created
    And user "user0" has created a custom group called "group0"
    When user "user0" makes user "non-existing-user" a member of custom group "group0" using the API
    Then the HTTP status code should be "412"
