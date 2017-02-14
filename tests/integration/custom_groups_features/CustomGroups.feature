Feature: Custom Groups

Background:
		Given using api version "1"
		And using new dav path

Scenario: Create a group
		Given As an "admin"
		And user "user0" exists
		When user "user0" created a custom group called "group0"
		And the HTTP status code should be "201"
		And custom group "group0" exists
