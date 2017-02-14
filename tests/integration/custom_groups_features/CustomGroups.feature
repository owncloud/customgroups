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


Scenario: Rename a custom group
		Given As an "admin"
		And user "user0" exists
		And user "user0" created a custom group called "group0"
		And custom group "group0" exists
		When user "user0" renamed custom group "group0" as "renamed-group0"
		Then the HTTP status code should be "201"
		And custom group "renamed-group0" exists
