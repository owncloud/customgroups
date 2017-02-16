<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;

require __DIR__ . '/../../vendor/autoload.php';


/**
 * Custom Groups context.
 */
class CustomGroupsContext implements Context, SnippetAcceptingContext {
	use Webdav;

	/** @var array */
	private $createdCustomGroups = [];

	/**
	 * @Given user :user created a custom group called :groupName
	 * @param string $user
	 * @param string $groupName
	 */
	public function userCreatedACustomGroup($user, $groupName){
		try {
			$appPath = '/customgroups/groups/';
			$this->response = $this->makeDavRequest($user, "MKCOL", $appPath . $groupName, null, null, "uploads");
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
		$this->createdCustomGroups[$groupName] = $groupName;
	}

	/**
	 * @Given user :user deleted a custom group called :groupName
	 * @param string $user
	 * @param string $groupName
	 */
	public function userDeletedACustomGroup($user, $groupName){
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
		$fullUrl = substr($this->baseUrl, 0, -4) . $this->davPath . $appPath;
		$response = $client->propfind($fullUrl, $properties, 1);
		return $response;
	}

	/**
	 * @Then custom group :customGroup exists
	 * @param string $customGroup
	 */
	public function customGroupExists($customGroup){
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
	 * @Then custom group :customGroup doesn't exist
	 * @param string $customGroup
	 */
	public function customGroupDoesntExists($customGroup){
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
		$appPath = '/customgroups/groups/';
		$fullUrl = substr($this->baseUrl, 0, -4) . $this->davPath . $appPath . $customGroup;
		$response = $client->proppatch($fullUrl, $properties, 1);
		return $response;
	}

	/**
	 * @When user :user renamed custom group :customGroup as :newName
	 * @param user $user
	 * @param string $customGroup
	 * @param string $newName
	 */
	public function userRenamedCustomGroupAs($user, $customGroup, $newName) {
		$properties = [
						'{http://owncloud.org/ns}display-name' => (string)$newName
					  ];
		$this->response = $this->sendProppatchToCustomGroup($user, $customGroup, $properties);
		$this->createdCustomGroups[$newName] = $this->createdCustomGroups[$customGroup];
		unset($this->createdCustomGroups[$customGroup]);
	}

	/*Function to retrieve all members of a group*/
	public function getCustomGroupMembers($user, $group){
		$client = $this->getSabreClient($user);
		$properties = [
						'{http://owncloud.org/ns}role'
					  ];
		$appPath = '/customgroups/groups/' . $group;
		$fullUrl = substr($this->baseUrl, 0, -4) . $this->davPath . $appPath;
		try {
			$response = $client->propfind($fullUrl, $properties, 1);
			$this->response = $response;
			return $response;
		} catch (\Sabre\DAV\Exception $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getHTTPCode();
		}
	}

	/*Function to retrieve all members of a group*/
	public function getUserRoleInACustomGroup($userRequesting, $userRequested, $group){
		$client = $this->getSabreClient($userRequesting);
		$properties = [
						'{http://owncloud.org/ns}role'
					  ];
		$userPath = $this->davPath .'/customgroups/groups/' . $group . '/' . $userRequested;
		$fullUrl = substr($this->baseUrl, 0, -4) . $userPath;
		$response = $client->propfind($fullUrl, $properties, 1);
		return $response['/' . $userPath]['{http://owncloud.org/ns}role'];
	}

	/**
	 * @Then user :user is admin of custom group :customGroup
	 * @param string $user
	 * @param string $customGroup
	 */
	public function checkIfUserIsAdminOfCustomGroup($user, $customGroup){
		$role = $this->getUserRoleInACustomGroup('admin', $user, $customGroup);
		PHPUnit_Framework_Assert::assertEquals($role, 1);
	}

	/**
	 * @Then members of :customGroup requested by user :user are
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
	 * @Then user :user is not able to get members of custom group :customGroup
	 * @param string $user
	 * @param string $customGroup
	 */
	public function tryingToGetMembersOfCustomGroup($customGroup, $user){
		$respondedArray = $this->getCustomGroupMembers($user, $customGroup);
		PHPUnit_Framework_Assert::assertEquals($this->response, 403);
		PHPUnit_Framework_Assert::assertEmpty($respondedArray);
	}

	/**
	 * @Given user :userRequesting maked user :userRequested member of custom group :customGroup
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
	 * @BeforeScenario
	 * @AfterScenario
	 */
	public function cleanupCustomGroups()
	{
		foreach($this->createdCustomGroups as $customGroup) {
			$this->userDeletedACustomGroup('admin', $customGroup);
		}
	}


}


