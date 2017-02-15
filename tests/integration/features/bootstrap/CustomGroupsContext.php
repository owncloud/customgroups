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
		} catch (\GuzzleHttp\Exception\ServerException $e) {
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
		} catch (\GuzzleHttp\Exception\ServerException $e) {
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


