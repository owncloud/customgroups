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
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Sabre\HTTP\ClientException;
use Sabre\HTTP\ClientHttpException;
use Sabre\HTTP\ResponseInterface;
use TestHelpers\SetupHelper;

require_once 'bootstrap.php';

/**
 * Custom Groups context.
 */
class CustomGroupsContext implements Context, SnippetAcceptingContext {
	/**
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 * @var ResponseInterface
	 */
	private $sabreResponse;

	/**
	 * @var array
	 */
	private $createdCustomGroups = [];

	/**
	 * @When user :user creates a custom group called :groupName using the API
	 * @Given user :user has created a custom group called :groupName
	 *
	 * @param string $user
	 * @param string $groupName
	 *
	 * @return void
	 */
	public function userCreatesACustomGroup($user, $groupName) {
		try {
			$appPath = '/customgroups/groups/';
			$response = $this->featureContext->makeDavRequest(
				$user, "MKCOL", $appPath . $groupName, null, null, "uploads"
			);
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$response = $e->getResponse();
			if ($response->getStatusCode() === 401
				|| $response->getStatusCode() >= 500
			) {
				throw $e;
			}
		}
		$this->featureContext->setResponse($response);
		$this->createdCustomGroups[$groupName] = $groupName;
	}

	/**
	 * @When user :user deletes a custom group called :groupName using the API
	 * @Given user :user has deleted a custom group called :groupName
	 *
	 * @param string $user
	 * @param string $groupName
	 *
	 * @return void
	 */
	public function userDeletesACustomGroup($user, $groupName) {
		try {
			$appPath = '/customgroups/groups/';
			$response = $this->featureContext->makeDavRequest(
				$user, "DELETE", $appPath . $groupName, null, null, "uploads"
			);
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$response = $e->getResponse();
		}
		$this->featureContext->setResponse($response);
		unset($this->createdCustomGroups[$groupName]);
	}

	/**
	 * Retrieve all custom groups
	 *
	 * @param string $user
	 *
	 * @return array
	 * @throws ClientHttpException
	 */
	public function getCustomGroups($user) {
		$client = $this->featureContext->getSabreClient($user);
		$properties = [
						'{http://owncloud.org/ns}display-name'
					  ];
		$appPath = '/customgroups/groups/';
		$fullUrl
			= $this->featureContext->getBaseUrl() . '/'
			. $this->featureContext->getDavPath() . $appPath;
		$sabreResponse = $client->propfind($fullUrl, $properties, 1);
		return $sabreResponse;
	}

	/**
	 * @Then custom group :customGroup should exist
	 *
	 * @param string $customGroup
	 *
	 * @return void
	 * @throws ClientHttpException
	 */
	public function customGroupShouldExist($customGroup) {
		$customGroupsList = $this->getCustomGroups("admin");
		$exists = false;
		foreach ($customGroupsList as $customGroupPath => $customGroupName) {
			if ((!empty($customGroupName))
				&& (array_values($customGroupName)[0] == $customGroup)
			) {
				$exists = true;
			}
		}
		if (!$exists) {
			PHPUnit_Framework_Assert::fail(
				"$customGroup is not in propfind answer"
			);
		}
	}

	/**
	 * @Then custom group :customGroup should exist with display name :displayName
	 *
	 * @param string $customGroup
	 * @param string $displayName
	 *
	 * @return void
	 * @throws ClientHttpException
	 */
	public function customGroupExistsWithDisplayName($customGroup, $displayName) {
		$customGroupsList = $this->getCustomGroups("admin");
		$exists = false;
		foreach ($customGroupsList as $customGroupPath => $customGroupName) {
			if ((!empty($customGroupName))
				&& (array_values($customGroupName)[0] == $displayName)
			) {
				$exists = true;
				break;
			}
		}
		if (!$exists) {
			PHPUnit_Framework_Assert::fail(
				"$customGroup is not in propfind answer"
			);
		}
	}

	/**
	 * @Then custom group :customGroup should not exist
	 *
	 * @param string $customGroup
	 *
	 * @return void
	 * @throws ClientHttpException
	 */
	public function customGroupShouldNotExist($customGroup) {
		$customGroupsList = $this->getCustomGroups("admin");
		$exists = false;
		foreach ($customGroupsList as $customGroupPath => $customGroupName) {
			if ((!empty($customGroupName))
				&& (array_values($customGroupName)[0] == $customGroup)
			) {
				$exists = true;
			}
		}
		if ($exists) {
			PHPUnit_Framework_Assert::fail(
				"$customGroup is in propfind answer"
			);
		}
	}

	/**
	 * Set the elements of a proppatch
	 *
	 * @param string $user
	 * @param string $customGroup
	 * @param array|null $properties
	 *
	 * @return bool
	 * @throws ClientException
	 */
	public function sendProppatchToCustomGroup(
		$user, $customGroup, $properties = null
	) {
		$client = $this->featureContext->getSabreClient($user);
		$client->setThrowExceptions(true);
		$appPath = '/customgroups/groups/';
		$fullUrl
			= $this->featureContext->getBaseUrl() . '/'
			. $this->featureContext->getDavPath() . $appPath . $customGroup;
		try {
			return $client->proppatch($fullUrl, $properties, 1);
		} catch (ClientHttpException $e) {
			$this->sabreResponse = $e->getResponse();
			return false;
		} catch (ClientException $e) {
			$this->sabreResponse = null;
			return false;
		}
	}

	/**
	 * Set property of a group member
	 *
	 * @param string $userRequesting
	 * @param string $customGroup
	 * @param string $userRequested
	 * @param array|null $properties
	 *
	 * @return bool
	 * @throws ClientException
	 */
	public function sendProppatchToCustomGroupMember(
		$userRequesting, $customGroup, $userRequested, $properties = null
	) {
		$client = $this->featureContext->getSabreClient($userRequesting);
		$client->setThrowExceptions(true);
		$appPath = '/customgroups/groups/';
		$fullUrl
			= $this->featureContext->getBaseUrl() . '/'
			. $this->featureContext->getDavPath()
			. $appPath . $customGroup . '/' . $userRequested;
		try {
			return $client->proppatch($fullUrl, $properties, 1);
		} catch (ClientHttpException $e) {
			// 4xx and 5xx responses cause an exception
			$this->sabreResponse = $e->getResponse();
			return false;
		}
	}

	/**
	 * @When /^user "([^"]*)" changes role of "([^"]*)" to (admin|member) in custom group "([^"]*)" using the API$/
	 * @Given /^user "([^"]*)" has changed role of "([^"]*)" to (admin|member) in custom group "([^"]*)"$/
	 *
	 * @param string $userRequesting
	 * @param string $userRequested
	 * @param string $role
	 * @param string $customGroup
	 *
	 * @return void
	 * @throws ClientException
	 */
	public function userChangedRoleOfMember(
		$userRequesting, $userRequested, $role, $customGroup
	) {
		$properties = [
						'{http://owncloud.org/ns}role' => $role
					  ];
		$this->sendProppatchToCustomGroupMember(
			$userRequesting, $customGroup, $userRequested, $properties
		);
	}

	/**
	 * @When user :user renames custom group :customGroup as :newName using the API
	 * @Given user :user has renamed custom group :customGroup as :newName
	 *
	 * @param string $user
	 * @param string $customGroup
	 * @param string $newName
	 *
	 * @return void
	 * @throws ClientException
	 */
	public function userRenamedCustomGroupAs($user, $customGroup, $newName) {
		$properties = [
						'{http://owncloud.org/ns}display-name' => $newName
					  ];
		$this->sendProppatchToCustomGroup($user, $customGroup, $properties);
	}

	/**
	 * retrieve all members of a custom group
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return array|null
	 */
	public function getCustomGroupMembers($user, $group) {
		$client = $this->featureContext->getSabreClient($user);
		$client->setThrowExceptions(true);
		$properties = [
						'{http://owncloud.org/ns}role'
					  ];
		$appPath = '/customgroups/groups/' . $group;
		$fullUrl
			= $this->featureContext->getBaseUrl() . '/'
			. $this->featureContext->getDavPath() . $appPath;
		try {
			$response = $client->propfind($fullUrl, $properties, 1);
			return $response;
		} catch (ClientHttpException $e) {
			// 4xx and 5xx responses cause an exception
			$this->sabreResponse = $e->getResponse();
			return null;
		}
	}

	/**
	 * get the user's role in a custom group
	 *
	 * @param string $userRequesting
	 * @param string $userRequested
	 * @param string $group
	 *
	 * @return mixed
	 * @throws ClientHttpException
	 */
	public function getUserRoleInACustomGroup(
		$userRequesting, $userRequested, $group
	) {
		$client = $this->featureContext->getSabreClient($userRequesting);
		$properties = [
						'{http://owncloud.org/ns}role'
					  ];
		$userPath
			= $this->featureContext->getDavPath()
			. '/customgroups/groups/' . $group . '/' . $userRequested;
		$fullUrl = $this->featureContext->getBaseUrl() . '/' . $userPath;
		$response = $client->propfind($fullUrl, $properties, 1);
		return $response['/' . $userPath]['{http://owncloud.org/ns}role'];
	}

	/**
	 * @Then /^user "([^"]*)" should be (?:an|a) (admin|member) of custom group "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $role
	 * @param string $customGroup
	 *
	 * @return void
	 * @throws ClientHttpException
	 */
	public function checkIfUserIsAdminOfCustomGroup($user, $role, $customGroup) {
		$currentRole = $this->getUserRoleInACustomGroup(
			'admin', $user, $customGroup
		);
		PHPUnit_Framework_Assert::assertEquals($role, $currentRole);
	}

	/**
	 * @Then the members of :customGroup requested by user :user should be
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $memberList
	 * @param string $user
	 * @param string $customGroup
	 *
	 * @return void
	 */
	public function usersAreMemberOfCustomGroup(
		$memberList, $user, $customGroup
	) {
		$appPath = '/customgroups/groups/';
		if ($memberList instanceof \Behat\Gherkin\Node\TableNode) {
			$members = $memberList->getRows();
			$membersSimplified = $this->featureContext->simplifyArray($members);
			$respondedArray = $this->getCustomGroupMembers($user, $customGroup);
			foreach ($membersSimplified as $member) {
				$memberPath
					= '/' . $this->featureContext->getDavPath()
					. $appPath . $customGroup . '/' . $member;
				if (!array_key_exists($memberPath, $respondedArray)) {
					PHPUnit_Framework_Assert::fail(
						"$member path is not in report answer"
					);
				}
			}
		}
	}

	/**
	 * @Then user :user should not be able to get members of custom group :customGroup
	 *
	 * @param string $customGroup
	 * @param string $user
	 *
	 * @return void
	 */
	public function tryingToGetMembersOfCustomGroup($customGroup, $user) {
		$respondedArray = $this->getCustomGroupMembers($user, $customGroup);
		PHPUnit_Framework_Assert::assertEquals(
			$this->sabreResponse->getStatus(), 403
		);
		PHPUnit_Framework_Assert::assertEmpty($respondedArray);
	}

	/**
	 * @When user :userRequesting makes user :userRequested a member of custom group :customGroup using the API
	 * @Given user :userRequesting has made user :userRequested a member of custom group :customGroup
	 *
	 * @param string $userRequesting
	 * @param string $userRequested
	 * @param string $customGroup
	 *
	 * @return void
	 */
	public function addMemberOfCustomGroup(
		$userRequesting, $userRequested, $customGroup
	) {
		try {
			$userPath
				= '/customgroups/groups/' . $customGroup . '/' . $userRequested;
			$response = $this->featureContext->makeDavRequest(
				$userRequesting, "PUT", $userPath, null, null, "uploads"
			);
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$response = $e->getResponse();
		}
		$this->featureContext->setResponse($response);
	}

	/**
	 * @When user :userRequesting removes membership of user :userRequested from custom group :customGroup using the API
	 * @Given user :userRequesting has removed membership of user :userRequested from custom group :customGroup
	 *
	 * @param string $userRequesting
	 * @param string $userRequested
	 * @param string $customGroup
	 *
	 * @return void
	 */
	public function removeMemberOfCustomGroup(
		$userRequesting, $userRequested, $customGroup
	) {
		try {
			$userPath
				= '/customgroups/groups/' . $customGroup . '/' . $userRequested;
			$response = $this->featureContext->makeDavRequest(
				$userRequesting, "DELETE", $userPath, null, null, "uploads"
			);
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$response = $e->getResponse();
		}
		$this->featureContext->setResponse($response);
	}

	/**
	 * retrieve all custom groups which the user is a member of
	 *
	 * @param string $userRequesting
	 * @param string $userRequested
	 *
	 * @return array|null
	 */
	public function getCustomGroupsOfAUser($userRequesting, $userRequested) {
		$client = $this->featureContext->getSabreClient($userRequesting);
		$client->setThrowExceptions(true);
		$properties = [
						'{http://owncloud.org/ns}role'
					  ];
		$userPath
			= $this->featureContext->getDavPath()
			. '/customgroups/users/' . $userRequested;
		$fullUrl = $this->featureContext->getBaseUrl() . '/' . $userPath;
		try {
			return $client->propfind($fullUrl, $properties, 1);
		} catch (ClientHttpException $e) {
			// 4xx and 5xx responses cause an exception
			$this->sabreResponse = $e->getResponse();
			return null;
		}
	}

	/**
	 * @Then the custom groups of :userRequested requested by user :userRequesting should be
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $customGroupList
	 * @param string $userRequested
	 * @param string $userRequesting
	 *
	 * @return void
	 */
	public function customGroupsWhichAUserIsMemberOfAre(
		$customGroupList, $userRequested, $userRequesting
	) {
		$appPath = '/customgroups/users/';
		if ($customGroupList instanceof \Behat\Gherkin\Node\TableNode) {
			$customGroups = $customGroupList->getRows();
			$customGroupsSimplified = $this->featureContext->simplifyArray(
				$customGroups
			);
			$respondedArray = $this->getCustomGroupsOfAUser(
				$userRequesting, $userRequested
			);
			foreach ($customGroupsSimplified as $customGroup) {
				$groupPath
					= '/' . $this->featureContext->getDavPath()
					. $appPath . $userRequested . '/' . $customGroup . '/';
				if (!array_key_exists($groupPath, $respondedArray)) {
					PHPUnit_Framework_Assert::fail(
						"$customGroup path" . " is not in propfind answer"
					);
				}
			}
		}
	}

	/**
	 * @BeforeScenario
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 */
	public function setUpScenario(BeforeScenarioScope $scope) {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->featureContext = $environment->getContext('FeatureContext');
		SetupHelper::init(
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
	}

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 *
	 * @return void
	 */
	public function cleanupCustomGroups() {
		foreach ($this->createdCustomGroups as $customGroup) {
			$this->userDeletesACustomGroup(
				$this->featureContext->getAdminUsername(), $customGroup
			);
		}
	}
}
