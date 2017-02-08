Feature: Custom Groups

Background:
		Given using api version "1"

Scenario: Create a user
		Given As an "admin"
		And user "brand-new-user" does not exist
		When sending "POST" to "/cloud/users" with
			| userid | brand-new-user |
			| password | 123456 |
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And user "brand-new-user" exists
