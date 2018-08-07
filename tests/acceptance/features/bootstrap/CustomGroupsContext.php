<?php
/**
 * @author Sergio Bertolin <sbertolin@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;

require_once 'bootstrap.php';

/**
 * Custom Groups context.
 */
class CustomGroupsContext implements Context, SnippetAcceptingContext {
	use BasicStructure;

	/** @var array */
	private $createdCustomGroups = [];

	/**
	 * @When user :user creates a custom group called :groupName using the API
	 * @Given user :user has created a custom group called :groupName
	 * @param string $user
	 * @param string $groupName
	 */
	public function userCreatesACustomGroup($user, $groupName){
		try {
			$appPath = '/customgroups/groups/';
			$this->response = $this->makeDavRequest($user, "MKCOL", $appPath . $groupName, null, null, "uploads");
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
			if ($this->response->getStatusCode() === 401 || $this->response->getStatusCode() >= 500) {
				throw $e;
			}
		}
		$this->createdCustomGroups[$groupName] = $groupName;
	}

	/**
	 * @When user :user deletes a custom group called :groupName using the API
	 * @Given user :user has deleted a custom group called :groupName
	 * @param string $user
	 * @param string $groupName
	 */
	public function userDeletesACustomGroup($user, $groupName){
		try {
			$appPath = '/customgroups/groups/';
			$this->response = $this->makeDavRequest($user, "DELETE", $appPath . $groupName, null, null, "uploads");
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
		unset($this->createdCustomGroups[$groupName]);
	}

	/*Function to retrieve all groups*/
	public function getCustomGroups($user){
		$client = $this->getSabreClient($user);
		$properties = [
						'{http://owncloud.org/ns}display-name'
					  ];
		$appPath = '/customgroups/groups/';
		$fullUrl = $this->getBaseUrl() . '/' . $this->davPath . $appPath;
		$response = $client->propfind($fullUrl, $properties, 1);
		return $response;
	}

	/**
	 * @Then custom group :customGroup should exist
	 * @param string $customGroup
	 */
	public function customGroupShouldExist($customGroup){
		$customGroupsList = $this->getCustomGroups("admin");
		$exists = false;
		foreach($customGroupsList as $customGroupPath => $customGroupName) {
			if ((!empty($customGroupName)) && (array_values($customGroupName)[0] == $customGroup)){
				$exists = true;
			}
		}
		if (!$exists){
			PHPUnit_Framework_Assert::fail("$customGroup" . " is not in propfind answer");
		}
	}

	/**
	 * @Then custom group :customGroup should exist with display name :displayName
	 * @param string $customGroup
	 * @param string $displayName
	 */
	public function customGroupExistsWithDisplayName($customGroup, $displayName){
		$customGroupsList = $this->getCustomGroups("admin");
		$exists = false;
		foreach($customGroupsList as $customGroupPath => $customGroupName) {
			if ((!empty($customGroupName)) && (array_values($customGroupName)[0] == $displayName)){
				$exists = true;
				break;
			}
		}
		if (!$exists){
			PHPUnit_Framework_Assert::fail("$customGroup" . " is not in propfind answer");
		}
	}

	/**
	 * @Then custom group :customGroup should not exist
	 * @param string $customGroup
	 */
	public function customGroupShouldNotExist($customGroup){
		$customGroupsList = $this->getCustomGroups("admin");
		$exists = false;
		foreach($customGroupsList as $customGroupPath => $customGroupName) {
			if ((!empty($customGroupName)) && (array_values($customGroupName)[0] == $customGroup)){
				$exists = true;
			}
		}
		if ($exists){
			PHPUnit_Framework_Assert::fail("$customGroup" . " is in propfind answer");
		}
	}

	/*Set the elements of a proppatch*/
	public function sendProppatchToCustomGroup($user, $customGroup, $properties = null){
		$client = $this->getSabreClient($user);
		$client->setThrowExceptions(true);
		$appPath = '/customgroups/groups/';
		$fullUrl = $this->getBaseUrl() . '/' . $this->davPath . $appPath . $customGroup;
		try {
			$response = $client->proppatch($fullUrl, $properties, 1);
			$this->response = $response;
			return $response;
		} catch (\Sabre\HTTP\ClientException $e) {
			$this->response = null;
			return null;
		}
	}

	/*Set property of a group member*/
	public function sendProppatchToCustomGroupMember($userRequesting, $customGroup, $userRequested, $properties = null){
		$client = $this->getSabreClient($userRequesting);
		$client->setThrowExceptions(true);
		$appPath = '/customgroups/groups/';
		$fullUrl = $this->getBaseUrl() . '/' . $this->davPath . $appPath . $customGroup . '/' . $userRequested;
		try {
			$response = $client->proppatch($fullUrl, $properties, 1);
			$this->response = $response;
			return $response;
		} catch (\Sabre\HTTP\ClientHttpException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When /^user "([^"]*)" changes role of "([^"]*)" to (admin|member) in custom group "([^"]*)" using the API$/
	 * @Given /^user "([^"]*)" has changed role of "([^"]*)" to (admin|member) in custom group "([^"]*)"$/
	 * @param string $userRequesting
	 * @param string $userRequested
	 * @param string $role
	 * @param string $customGroup
	 */
	public function userChangedRoleOfMember($userRequesting, $userRequested, $role, $customGroup) {
		$properties = [
						'{http://owncloud.org/ns}role' => $role
					  ];
		$this->response = $this->sendProppatchToCustomGroupMember($userRequesting, $customGroup, $userRequested, $properties);
	}

	/**
	 * @When user :user renames custom group :customGroup as :newName using the API
	 * @Given user :user has renamed custom group :customGroup as :newName
	 * @param string $user
	 * @param string $customGroup
	 * @param string $newName
	 */
	public function userRenamedCustomGroupAs($user, $customGroup, $newName) {
		$properties = [
						'{http://owncloud.org/ns}display-name' => $newName
					  ];
		$this->response = $this->sendProppatchToCustomGroup($user, $customGroup, $properties);
	}

	/*Function to retrieve all members of a group*/
	public function getCustomGroupMembers($user, $group){
		$client = $this->getSabreClient($user);
		$client->setThrowExceptions(true);
		$properties = [
						'{http://owncloud.org/ns}role'
					  ];
		$appPath = '/customgroups/groups/' . $group;
		$fullUrl = $this->getBaseUrl() . '/' . $this->davPath . $appPath;
		try {
			$response = $client->propfind($fullUrl, $properties, 1);
			$this->response = $response;
			return $response;
		} catch (\Sabre\HTTP\ClientHttpException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/*Function to retrieve all members of a group*/
	public function getUserRoleInACustomGroup($userRequesting, $userRequested, $group){
		$client = $this->getSabreClient($userRequesting);
		$properties = [
						'{http://owncloud.org/ns}role'
					  ];
		$userPath = $this->davPath .'/customgroups/groups/' . $group . '/' . $userRequested;
		$fullUrl = $this->getBaseUrl() . '/' . $userPath;
		$response = $client->propfind($fullUrl, $properties, 1);
		return $response['/' . $userPath]['{http://owncloud.org/ns}role'];
	}

	/**
	 * @Then /^user "([^"]*)" should be (?:an|a) (admin|member) of custom group "([^"]*)"$/
	 * @param string $user
	 * @param string $role
	 * @param string $customGroup
	 */
	public function checkIfUserIsAdminOfCustomGroup($user, $role, $customGroup){
		$currentRole = $this->getUserRoleInACustomGroup('admin', $user, $customGroup);
		PHPUnit_Framework_Assert::assertEquals($role, $currentRole);
	}

	/**
	 * @Then the members of :customGroup requested by user :user should be
	 * @param \Behat\Gherkin\Node\TableNode|null $memberList
	 * @param string $customGroup
	 * @param string $user
	 */
	public function usersAreMemberOfCustomGroup($memberList, $user, $customGroup){
		$appPath = '/customgroups/groups/';
		if ($memberList instanceof \Behat\Gherkin\Node\TableNode) {
			$members = $memberList->getRows();
			$membersSimplified = $this->simplifyArray($members);
			$respondedArray = $this->getCustomGroupMembers($user, $customGroup);
			foreach ($membersSimplified as $member) {
				$memberPath = '/' . $this->davPath . $appPath . $customGroup . '/' . $member;
				if (!array_key_exists($memberPath, $respondedArray)){
					PHPUnit_Framework_Assert::fail("$member path" . " is not in report answer");
				}
			}
		}
	}

	/**
	 * @Then user :user should not be able to get members of custom group :customGroup
	 * @param string $user
	 * @param string $customGroup
	 */
	public function tryingToGetMembersOfCustomGroup($customGroup, $user){
		$respondedArray = $this->getCustomGroupMembers($user, $customGroup);
		PHPUnit_Framework_Assert::assertEquals($this->response->getStatus(), 403);
		PHPUnit_Framework_Assert::assertEmpty($respondedArray);
	}

	/**
	 * @When user :userRequesting makes user :userRequested a member of custom group :customGroup using the API
	 * @Given user :userRequesting has made user :userRequested a member of custom group :customGroup
	 * @param string $userRequesting
	 * @param string $userRequested
	 * @param string $customGroup
	 */
	public function addMemberOfCustomGroup($userRequesting, $userRequested, $customGroup){
		try {
			$userPath = '/customgroups/groups/' . $customGroup . '/' . $userRequested;
			$this->response = $this->makeDavRequest($userRequesting, "PUT", $userPath, null, null, "uploads");
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When user :userRequesting removes membership of user :userRequested from custom group :customGroup using the API
	 * @Given user :userRequesting has removed membership of user :userRequested from custom group :customGroup
	 * @param string $userRequesting
	 * @param string $userRequested
	 * @param string $customGroup
	 */
	public function removeMemberOfCustomGroup($userRequesting, $userRequested, $customGroup){
		try {
			$userPath = '/customgroups/groups/' . $customGroup . '/' . $userRequested;
			$this->response = $this->makeDavRequest($userRequesting, "DELETE", $userPath, null, null, "uploads");
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/*Function to retrieve all custom groups which a user is member of*/
	public function getCustomGroupsOfAUser($userRequesting, $userRequested){
		$client = $this->getSabreClient($userRequesting);
		$client->setThrowExceptions(true);
		$properties = [
						'{http://owncloud.org/ns}role'
					  ];
		$userPath = $this->davPath .'/customgroups/users/' . $userRequested;
		$fullUrl = $this->getBaseUrl() . '/' . $userPath;
		try {
			$response = $client->propfind($fullUrl, $properties, 1);
			$this->response = $response;
			return $response;
		} catch (\Sabre\HTTP\ClientHttpException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @Then the custom groups of :userRequested requested by user :userRequesting should be
	 * @param \Behat\Gherkin\Node\TableNode|null $customGroupList
	 * @param string $userRequested
	 * @param string $userRequesting
	 */

	public function customGroupsWhichAUserIsMemberOfAre($customGroupList, $userRequested, $userRequesting){
		$appPath = '/customgroups/users/';
		if ($customGroupList instanceof \Behat\Gherkin\Node\TableNode) {
			$customGroups = $customGroupList->getRows();
			$customGroupsSimplified = $this->simplifyArray($customGroups);
			$respondedArray = $this->getCustomGroupsOfAUser($userRequesting, $userRequested);
			foreach ($customGroupsSimplified as $customGroup) {
				$groupPath = '/' . $this->davPath . $appPath . $userRequested . '/' . $customGroup . '/';
				if (!array_key_exists($groupPath, $respondedArray)){
					PHPUnit_Framework_Assert::fail("$customGroup path" . " is not in propfind answer");
				}
			}
		}
	}

	/**
	 * @Then /^the sabre HTTP status code answered should be "([^"]*)"$/
	 * @param int $statusCode
	 */
	public function theSabreHTTPStatusCodeAnsweredShouldBe($statusCode) {
		PHPUnit_Framework_Assert::assertEquals($statusCode, $this->response);
	}

	/**
	 * Abstract method implemented from Core's FeatureContext
	 */
	protected function resetAppConfigs() {
		// Remember the current capabilities
		$this->getCapabilitiesCheckResponse();
		$this->savedCapabilitiesXml[$this->getBaseUrl()] = $this->getCapabilitiesXml();
		// Set the required starting values for testing
		$this->setCapabilities($this->getCommonSharingConfigs());
	}

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 */
	public function cleanupCustomGroups()
	{
		foreach($this->createdCustomGroups as $customGroup) {
			$this->userDeletesACustomGroup($this->getAdminUsername(), $customGroup);
		}
	}

}


