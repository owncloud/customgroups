Feature: Custom Groups

Background:
		Given using api version "1"
		And using new dav path

Scenario: Create a custom group
		Given as user "admin"
		And user "user0" has been created
		When user "user0" created a custom group called "group0"
		Then the HTTP status code should be "201"
		And custom group "group0" exists

Scenario: Create an already existing custom group
		Given as user "admin"
		And user "user0" has been created
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		When user "user0" created a custom group called "group0"
		Then the HTTP status code should be "405"

Scenario: Delete a custom group
		Given as user "admin"
		And user "user0" has been created
		And user "user0" created a custom group called "group0"
		When user "user0" deleted a custom group called "group0"
		Then the HTTP status code should be "204"
		And custom group "group0" doesn't exist

Scenario: Rename a custom group
		Given as user "admin"
		And user "user0" has been created
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		When user "user0" renamed custom group "group0" as "renamed-group0"
		Then custom group "group0" exists with display name "renamed-group0"

Scenario: A non-admin member cannot rename its custom group
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		And user "user0" made user "member1" member of custom group "group0"
		When user "member1" renamed custom group "group0" as "renamed-group0"
		Then custom group "group0" exists with display name "group0"

Scenario: Get members of a group
		Given as user "admin"
		And user "user0" has been created
		When user "user0" created a custom group called "group0"
		Then members of "group0" requested by user "user0" are
					| user0 |

Scenario: Creator of a custom group becames admin automatically
		Given as user "admin"
		And user "user0" has been created
		When user "user0" created a custom group called "group0"
		Then user "user0" is admin of custom group "group0"

Scenario: Creator of a custom group can add members
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "member2" has been created
		And user "user0" created a custom group called "group0"
		When user "user0" made user "member1" member of custom group "group0"
		And user "user0" made user "member2" member of custom group "group0"
		Then members of "group0" requested by user "user0" are
					| user0 |
					| member1 |
					| member2 |

Scenario: A non-admin member of a custom group cannot add members
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "member2" has been created
		And user "user0" created a custom group called "group0"
		And user "user0" made user "member1" member of custom group "group0"
		When user "member1" made user "member2" member of custom group "group0"
		Then the HTTP status code should be "403"
		And members of "group0" requested by user "user0" are
					| user0 |
					| member1 |

Scenario: A non-member of a custom group cannot list its members
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "not-member" has been created
		And user "user0" created a custom group called "group0"
		When user "user0" made user "member1" member of custom group "group0"
		Then user "not-member" is not able to get members of custom group "group0"

Scenario: A custom group member can list members
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "member2" has been created
		And user "user0" created a custom group called "group0"
		When user "user0" made user "member1" member of custom group "group0"
		When user "user0" made user "member2" member of custom group "group0"
		Then members of "group0" requested by user "member1" are
					| user0 |
					| member1 |
					| member2 |

Scenario: A non-admin member of a custom group cannot delete a custom group
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "user0" created a custom group called "group0"
		When user "user0" made user "member1" member of custom group "group0"
		When user "member1" deleted a custom group called "group0"
		Then the HTTP status code should be "403"
		And custom group "group0" exists

Scenario: Creator of a custom group can remove members
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "member2" has been created
		And user "user0" created a custom group called "group0"
		And user "user0" made user "member1" member of custom group "group0"
		And user "user0" made user "member2" member of custom group "group0"
		When user "user0" removed membership of user "member1" from custom group "group0"
		Then members of "group0" requested by user "user0" are
					| user0 |
					| member2 |

Scenario: A non-admin member of a custom group cannot remove members
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "member2" has been created
		And user "user0" created a custom group called "group0"
		And user "user0" made user "member1" member of custom group "group0"
		And user "user0" made user "member2" member of custom group "group0"
		When user "member2" removed membership of user "member1" from custom group "group0"
		Then the HTTP status code should be "403"
		And members of "group0" requested by user "user0" are
					| user0 |
					| member1 |
					| member2 |

Scenario: Group owner cannot remove self if no other admin exists in the group
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "user0" created a custom group called "group0"
		And user "user0" made user "member1" member of custom group "group0"
		When user "user0" removed membership of user "user0" from custom group "group0"
		Then the HTTP status code should be "403"
		And members of "group0" requested by user "user0" are
					| user0 |
					| member1 |

Scenario: A member of a custom group can leave the custom group himself
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "user0" created a custom group called "group0"
		And user "user0" made user "member1" member of custom group "group0"
		When user "member1" removed membership of user "member1" from custom group "group0"
		Then the HTTP status code should be "204"
		And members of "group0" requested by user "user0" are
					| user0 |

Scenario: A user can list his groups
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "user0" created a custom group called "group0"
		And user "user0" created a custom group called "group1"
		And user "user0" created a custom group called "group2"
		When user "user0" made user "member1" member of custom group "group0"
		And user "user0" made user "member1" member of custom group "group1"
		And user "user0" made user "member1" member of custom group "group2"
		Then custom groups of "member1" requested by user "member1" are
					| group0 |
					| group1 |
					| group2 |

Scenario: Change role of a member of a group
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		And user "user0" made user "member1" member of custom group "group0"
		When user "user0" changed role of "member1" to admin in custom group "group0"
		Then user "member1" is admin of custom group "group0"

Scenario: Create a custom group and let another user as admin
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		And user "user0" made user "member1" member of custom group "group0"
		And user "user0" changed role of "member1" to admin in custom group "group0"
		When user "user0" changed role of "user0" to member in custom group "group0"
		Then user "member1" is admin of custom group "group0"
		And user "user0" is member of custom group "group0"

Scenario: Superadmin can do everything
		Given as user "admin"
		And user "user0" has been created
		And user "user1" has been created
		And user "user2" has been created
		And user "admin" created a custom group called "group0"
		And user "admin" created a custom group called "group1"
		And user "admin" deleted a custom group called "group1"
		And user "admin" made user "user0" member of custom group "group0"
		And user "admin" made user "user1" member of custom group "group0"
		And user "admin" made user "user2" member of custom group "group0"
		And user "admin" renamed custom group "group0" as "renamed-group0"
		And custom group "group0" exists with display name "renamed-group0"
		And user "admin" changed role of "user0" to admin in custom group "group0"
		And user "user0" is admin of custom group "group0"
		When user "admin" removed membership of user "user1" from custom group "group0"
		Then members of "group0" requested by user "admin" are
					| user0 |
					| user2 |
					| admin |

Scenario: Superadmin can add a user to any custom group
		Given as user "admin"
		And user "user0" has been created
		And user "user1" has been created
		And user "user0" created a custom group called "group0"
		When user "admin" made user "user1" member of custom group "group0"
		Then the HTTP status code should be "201"
		And members of "group0" requested by user "admin" are
					| user0 |
					| user1 |

Scenario: Superadmin can rename any custom group
		Given as user "admin"
		And user "user0" has been created
		And user "user1" has been created
		And user "user0" created a custom group called "group0"
		When user "admin" renamed custom group "group0" as "renamed-group0"
		Then custom group "group0" exists with display name "renamed-group0"

Scenario: A member converted to group owner can do the same as group owner
		Given as user "admin"
		And user "user0" has been created
		And user "user1" has been created
		And user "user2" has been created
		And user "user0" created a custom group called "group0"
		And user "user0" created a custom group called "group1"
		And user "user0" made user "user1" member of custom group "group0"
		And user "user0" made user "user1" member of custom group "group1"
		And user "user0" changed role of "user1" to admin in custom group "group0"
		And user "user0" changed role of "user1" to admin in custom group "group1"
		And user "user1" deleted a custom group called "group1"
		And user "user1" made user "user2" member of custom group "group0"
		And user "user1" renamed custom group "group0" as "renamed-group0"
		And custom group "group0" exists with display name "renamed-group0"
		And user "user1" changed role of "user2" to admin in custom group "group0"
		And user "user2" is admin of custom group "group0"
		When user "user1" removed membership of user "user0" from custom group "group0"
		Then members of "group0" requested by user "user1" are
					| user1 |
					| user2 |

Scenario: A group owner cannot remove his own admin permissions if there is no other owner in the group
		Given as user "admin"
		And user "user0" has been created
		And user "member1" has been created
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		And user "user0" made user "member1" member of custom group "group0"
		When user "user0" changed role of "user1" to member in custom group "group0"
		Then user "user0" is admin of custom group "group0"

Scenario: A non-existing user cannot be added to a custom group
		Given as user "admin"
		And user "user0" has been created
		And user "user0" created a custom group called "group0"
		When user "user0" made user "non-existing-user" member of custom group "group0"
		Then the HTTP status code should be "412"
