Feature: provisioning
	Background:
		Given using api version "1"

	Scenario: Getting an not existing user
		Given As an "admin"
		When sending "GET" to "/cloud/users/test"
		Then the OCS status code should be "998"
		And the HTTP status code should be "200"

	Scenario: Listing all users
		Given As an "admin"
		When sending "GET" to "/cloud/users"
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"

	Scenario: Create a user
		Given As an "admin"
		And user "brand-new-user" does not exist
		When sending "POST" to "/cloud/users" with
			| userid | brand-new-user |
			| password | 123456 |
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And user "brand-new-user" exists
