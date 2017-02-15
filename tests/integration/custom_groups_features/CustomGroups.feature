Feature: Custom Groups

Background:
		Given using api version "1"
		And using new dav path

Scenario: Create a custom group
		Given As an "admin"
		And user "user0" exists
		When user "user0" created a custom group called "group0"
		Then the HTTP status code should be "201"
		And custom group "group0" exists

Scenario: Create an already existing custom group
		Given As an "admin"
		And user "user0" exists
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		When user "user0" created a custom group called "group0"
		Then the HTTP status code should be "405"

Scenario: Delete a custom group
		Given As an "admin"
		And user "user0" exists
		And user "user0" created a custom group called "group0"
		When user "user0" deleted a custom group called "group0"
		Then the HTTP status code should be "204"
		And custom group "group0" doesn't exist

Scenario: Rename a custom group
		Given As an "admin"
		And user "user0" exists
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		When user "user0" renamed custom group "group0" as "renamed-group0"
		Then the HTTP status code should be "204"
		And custom group "renamed-group0" exists

Scenario: Get members of a group
		Given As an "admin"
		And user "user0" exists
		When user "user0" created a custom group called "group0"
		Then members of "group0" requested by user "user0" are
					| user0 |
