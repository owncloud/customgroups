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
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Sabre\HTTP\ClientException;
use Sabre\HTTP\ClientHttpException;
use Sabre\HTTP\ResponseInterface;
use TestHelpers\WebDavHelper;
use TestHelpers\SetupHelper;
use PHPUnit\Framework\Assert;

require_once 'bootstrap.php';

/**
 * Custom Groups context.
 */
class CustomGroupsContext implements Context {
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
	 * @throws Exception
	 */
	public function getCustomGroups($user) {
		$properties = ['oc:display-name'];
		$appPath = '/customgroups/groups/';
		$response = WebDavHelper::propfind(
			$this->featureContext->getBaseUrl(),
			$user,
			$this->featureContext->getPasswordForUser($user),
			$appPath,
			$properties,
			1,
			"customgroups"
		);
		$this->featureContext->setResponse($response);
		$customGroupsXml = $this->featureContext->getResponseXml($response)->xpath('//oc:display-name');
		Assert::assertArrayHasKey(
			0, $customGroupsXml, "cannot find 'oc:display-name' property"
		);
		$customGroups = [];
		foreach ($customGroupsXml as $group) {
			\array_push($customGroups, [(string) $group]);
		}
		return $customGroups;
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
				&& (\array_values($customGroupName)[0] == $customGroup)
			) {
				$exists = true;
			}
		}
		if (!$exists) {
			PHPUnit\Framework\Assert::fail(
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
	 * @throws Exception
	 */
	public function customGroupShouldNotExist($customGroup) {
		$customGroupsList = $this->getCustomGroups("admin");
		$exists = false;
		foreach ($customGroupsList as $customGroupPath => $customGroupName) {
			if ((!empty($customGroupName))
				&& (\array_values($customGroupName)[0] == $customGroup)
			) {
				$exists = true;
			}
		}
		if ($exists) {
			PHPUnit\Framework\Assert::fail(
				"$customGroup is in propfind answer"
			);
		}
	}

	/**
	 * Set the elements of a proppatch
	 *
	 * @param string $user
	 * @param string $customGroup
	 * @param string $propertyName
	 * @param string $propertyValue
	 *
	 * @return void
	 */
	public function sendProppatchToCustomGroup(
		$user, $customGroup, $propertyName, $propertyValue
	) {
		$appPath = '/customgroups/groups/' . $customGroup;

		$response = WebDavHelper::proppatch(
			$this->featureContext->getBaseUrl(),
			$user,
			$this->featureContext->getPasswordForUser($user),
			$appPath,
			$propertyName,
			$propertyValue,
			"oc='http://owncloud.org/ns'",
			$this->featureContext->getDavPathVersion(),
			"customgroups"
		);
		$this->featureContext->setResponse($response);
	}

	/**
	 * Set property of a group member
	 *
	 * @param string $userRequesting
	 * @param string $userRequested
	 * @param string $propertyName
	 * @param string $propertyValue
	 * @param string $customGroup
	 *
	 * @return void
	 */
	public function sendProppatchToCustomGroupMember(
		$userRequesting, $userRequested, $propertyName, $propertyValue, $customGroup
	) {
		$path = '/customgroups/groups/' . $customGroup . '/' . $userRequested;
		$response = WebDavHelper::proppatch(
			$this->featureContext->getBaseUrl(),
			$userRequesting,
			$this->featureContext->getPasswordForUser($userRequesting),
			$path,
			$propertyName,
			$propertyValue,
			"oc='http://owncloud.org/ns'",
			$this->featureContext->getDavPathVersion(),
			"customgroups"
		);
		$this->featureContext->setResponse($response);
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
	 */
	public function userChangedRoleOfMember(
		$userRequesting, $userRequested, $role, $customGroup
	) {
		$this->sendProppatchToCustomGroupMember(
			$userRequesting, $userRequested, 'role', $role, $customGroup
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
	 */
	public function userRenamedCustomGroupAs($user, $customGroup, $newName) {
		$this->sendProppatchToCustomGroup($user, $customGroup, 'display-name', $newName);
	}

	/**
	 * takes xpath inputs and returns an associative array from the response data
	 *
	 * @param array $responseData
	 * @param string $keyXpath
	 * @param string $valueXpath
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getResponseWithKeyValue($responseData, $keyXpath, $valueXpath) {
		$keys = [];
		$values = [];
		foreach ($responseData as $index => $value) {
			$path = $responseData[$index]->xpath($keyXpath);
			Assert::assertArrayHasKey(
				0, $path, "cannot find '$keyXpath' property"
			);
			$path_i = (string)$path[$index];
			$role = $value->xpath($valueXpath);
			Assert::assertArrayHasKey(
				0, $role, "cannot find '$valueXpath' property"
			);
			$role_i = (string)$role[$index];
			\array_push($keys, $path_i);
			\array_push($values, $role_i);
		}
		return \array_combine($keys, $values);
	}

	/**
	 * retrieve all members of a custom group
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getCustomGroupMembers($user, $group) {
		$properties = ['oc:role'];
		$appPath = '/customgroups/groups/' . $group;
		$response = WebDavHelper::propfind(
			$this->featureContext->getBaseUrl(),
			$user,
			$this->featureContext->getPasswordForUser($user),
			$appPath,
			$properties,
			1,
			"customgroups"
		);
		$this->featureContext->setResponse($response);
		$responseData = $this->featureContext->getResponseXml($response)->xpath('//d:response');

		return $this->getResponseWithKeyValue($responseData, '//d:href', '//oc:role');
	}

	/**
	 * get the user's role in a custom group
	 *
	 * @param string $userRequested
	 * @param string $group
	 *
	 * @return mixed
	 */
	public function getUserRoleInACustomGroup(
		$userRequested, $group
	) {
		$properties = ['oc:role'];
		$appPath = '/customgroups/groups/' . $group . '/' . $userRequested;
		$response = WebDavHelper::propfind(
			$this->featureContext->getBaseUrl(),
			$userRequested,
			$this->featureContext->getPasswordForUser($userRequested),
			$appPath,
			$properties,
			1,
			"customgroups"
		);
		$this->featureContext->setResponse($response);
		$rolesXml = $this->featureContext->getResponseXml($response)->xpath('//oc:role');
		Assert::assertArrayHasKey(
			0, $rolesXml, "cannot find 'oc:role' property"
		);
		return (string) $rolesXml[0];
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
			$user, $customGroup
		);
		PHPUnit\Framework\Assert::assertEquals($role, $currentRole);
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
				$basePath = $this->featureContext->getBasePath();
				if ($basePath !== '') {
					$basePath .= '/';
				}
				$memberPath
					= '/' . $basePath
					. $this->featureContext->getDavPath() . $appPath . $customGroup . '/' . $member;
				if (!\array_key_exists($memberPath, $respondedArray)) {
					PHPUnit\Framework\Assert::fail(
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
		PHPUnit\Framework\Assert::assertEmpty($respondedArray);
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
	 * @return array
	 * @throws Exception
	 */
	public function getCustomGroupsOfAUser($userRequesting, $userRequested) {
		$properties = ['oc:role'];
		$path = '/customgroups/users/' . $userRequested;
		$response = WebDavHelper::propfind(
			$this->featureContext->getBaseUrl(),
			$userRequesting,
			$this->featureContext->getPasswordForUser($userRequesting),
			$path,
			$properties,
			1,
			"customgroups"
		);
		$this->featureContext->setResponse($response);
		$responseData = $this->featureContext->getResponseXml($response)->xpath('//d:response');
		Assert::assertArrayHasKey(
			0, $responseData, "cannot find 'd:response' property"
		);
		return $this->getResponseWithKeyValue($responseData, '//d:href', '//oc:role');
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
				$basePath = $this->featureContext->getBasePath();
				if ($basePath !== '') {
					$basePath .= '/';
				}
				$groupPath
					= '/' . $basePath . $this->featureContext->getDavPath()
					. $appPath . $userRequested . '/' . $customGroup . '/';
				if (!\array_key_exists($groupPath, $respondedArray)) {
					PHPUnit\Framework\Assert::fail(
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
