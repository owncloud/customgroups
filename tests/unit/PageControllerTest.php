<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
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
namespace OCA\CustomGroups\Tests\unit;

use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\Service\MembershipHelper;
use OCA\CustomGroups\Controller\PageController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Class PageControllerTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class PageControllerTest extends \Test\TestCase {
	const CURRENT_USER = 'currentuser';

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var PageController
	 */
	private $pageController;

	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	public function setUp(): void {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->pageController = new PageController(
			'customgroups',
			$this->createMock(IRequest::class),
			$this->config,
			$this->userSession,
			$this->userManager,
			$this->groupManager,
			$this->handler
		);
	}

	/**
	 * Make a test user
	 *
	 * @param string $uid user id
	 * @param string $displayName display name
	 * @param string $email email address
	 * @param string[] $searchTerms search terms
	 */
	private function makeUser($uid, $displayName, $email = '', $searchTerms = null) {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);
		$user->method('getEMailAddress')->willReturn($email);
		if ($searchTerms !== null) {
			$user->method('getSearchTerms')->willReturn($searchTerms);
		} else {
			$user->method('getSearchTerms')->willReturn([]);
		}
		return $user;
	}

	public function testSearchAllUsers() {
		$user1 = $this->makeUser('user1', 'User One');
		$user2 = $this->makeUser('user2', 'User Two');
		$user3 = $this->makeUser('user3', 'User Three');

		$this->config->method('getAppValue')
			->will($this->returnValueMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'no'],
				['core', 'shareapi_allow_share_dialog_user_enumeration', 'yes', 'yes'],
				['core', 'shareapi_share_dialog_user_enumeration_group_members', 'no', 'no'],
			]));

		$this->handler->expects($this->once())
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(128)
			->willReturn([
				['user_id' => 'user3'],
			]);
		$this->userManager->expects($this->once())
			->method('find')
			->with('us', 150, 0)
			->willReturn([$user1, $user2, $user3]);

		$response = $this->pageController->searchUsers('group1', 'us', 150);
		$data = $response->getData();

		$this->assertTrue(isset($data['results']));
		$this->assertEquals([
			// user3 excluded because already a member
			['userId' => 'user1', 'displayName' => 'User One'],
			['userId' => 'user2', 'displayName' => 'User Two'],
		], $data['results']);
	}

	public function testSearchAllUsersLimit() {
		$allUsers = [];

		for ($i = 1; $i < 25; $i++) {
			$allUsers[] = $this->makeUser('user' . $i, 'User ' . $i);
		}

		$this->config->method('getAppValue')
			->will($this->returnValueMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'no'],
				['core', 'shareapi_allow_share_dialog_user_enumeration', 'yes', 'yes'],
				['core', 'shareapi_share_dialog_user_enumeration_group_members', 'no', 'no'],
			]));

		$this->handler->expects($this->at(0))
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);
		$this->handler->expects($this->at(1))
			->method('getGroupMembers')
			->with(128)
			->willReturn([
				['user_id' => 'user3'],
			]);

		$allUsersChunks = \array_chunk($allUsers, 20);
		// first page
		$this->userManager->expects($this->at(0))
			->method('find')
			->with('us', 20, 0)
			->willReturn($allUsersChunks[0]);
		// second page
		$this->userManager->expects($this->at(1))
			->method('find')
			->with('us', 20, 20)
			->willReturn($allUsersChunks[1]);

		$response = $this->pageController->searchUsers('group1', 'us', 20);
		$data = $response->getData();

		$this->assertTrue(isset($data['results']));
		$this->assertCount(20, $data['results']);

		$expectedResults = [];
		for ($i = 1; $i <= 21; $i++) {
			if ($i === 3) {
				// user3 was already member and is excluded
				continue;
			}
			$expectedResults[] = ['userId' => 'user' . $i, 'displayName' => 'User ' . $i];
		}

		$this->assertEquals(
			$expectedResults,
			$data['results']
		);
	}

	public function exactSearchDataProvider() {
		$singleResult = [
			['userId' => 'user1', 'displayName' => 'User One'],
		];
		$doubleResult = [
			['userId' => 'user1', 'displayName' => 'User One'],
			['userId' => 'userone', 'displayName' => 'User One'],
		];
		return [
			// partial fails
			['us', []],
			['test', []],
			// exact match of any field returns result
			['user1', $singleResult],
			['User One', $doubleResult],
			['test@example.com', $singleResult],
			['meh', $singleResult],
			['moo', $singleResult],
		];
	}

	/**
	 * @dataProvider exactSearchDataProvider
	 */
	public function testSearchAllUsersExact($searchPattern, $expectedResults) {
		$user1 = $this->makeUser('user1', 'User One', 'test@example.com', ['meh', 'moo']);
		$user2 = $this->makeUser('user2', 'User Two');
		$userone = $this->makeUser('userone', 'User One'); // sometimes people have same display names
		$memberone = $this->makeUser('memberone', 'User One'); // same name but already member
		$membertwo = $this->makeUser('membertwo', 'Member Two'); // same name but already member

		$this->config->method('getAppValue')
			->will($this->returnValueMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'no'],
				['core', 'shareapi_allow_share_dialog_user_enumeration', 'yes', 'no'],
				['core', 'shareapi_share_dialog_user_enumeration_group_members', 'no', 'no'],
			]));

		$this->handler->expects($this->at(0))
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);
		$this->handler->expects($this->at(1))
			->method('getGroupMembers')
			->with(128)
			->willReturn([
				['user_id' => 'memberone'],
				['user_id' => 'membertwo'],
			]);
		$this->userManager->expects($this->once())
			->method('find')
			->with(\strtolower($searchPattern), 150, 0)
			->willReturn([$user1, $user2, $userone, $memberone, $membertwo]);

		$response = $this->pageController->searchUsers('group1', $searchPattern, 150);
		$data = $response->getData();
		$this->assertTrue(isset($data['results']));
		$this->assertEquals($expectedResults, $data['results']);
	}

	public function testSearchAllUsersExactLimit() {
		$allUsers = [];
		// imagine a world where lots of people have the same names
		for ($i = 1; $i < 25; $i++) {
			$allUsers[] = $this->makeUser('user' . $i, 'User One');
		}

		$this->config->method('getAppValue')
			->will($this->returnValueMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'no'],
				['core', 'shareapi_allow_share_dialog_user_enumeration', 'yes', 'no'],
				['core', 'shareapi_share_dialog_user_enumeration_group_members', 'no', 'no'],
			]));

		$this->handler->expects($this->at(0))
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);
		$this->handler->expects($this->at(1))
			->method('getGroupMembers')
			->with(128)
			->willReturn([
				['user_id' => 'user3'],
			]);

		$allUsersChunks = \array_chunk($allUsers, 20);
		$this->userManager->expects($this->at(0))
			->method('find')
			->with('user one', 20, 0)
			->willReturn($allUsersChunks[0]);
		$this->userManager->expects($this->at(1))
			->method('find')
			->with('user one', 20, 20)
			->willReturn($allUsersChunks[1]);

		$response = $this->pageController->searchUsers('group1', 'User One', 20);
		$data = $response->getData();
		$this->assertTrue(isset($data['results']));
		$this->assertCount(20, $data['results']);

		$expectedResults = [];
		for ($i = 1; $i <= 21; $i++) {
			if ($i === 3) {
				// user3 was already member and is excluded
				continue;
			}
			$expectedResults[] = ['userId' => 'user' . $i, 'displayName' => 'User One'];
		}

		$this->assertEquals(
			$expectedResults,
			$data['results']
		);
	}

	public function testSearchUsersInGroup() {
		$user1 = $this->makeUser('user1', 'User One');
		$user2 = $this->makeUser('user2', 'User Two');
		$user3 = $this->makeUser('user3', 'User Three');

		$this->config->method('getAppValue')
			->will($this->returnValueMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
				['core', 'shareapi_allow_share_dialog_user_enumeration', 'yes', 'yes'],
				['core', 'shareapi_share_dialog_user_enumeration_group_members', 'no', 'no'],
			]));

		$currentUser = $this->makeUser('currentuser', 'Current User');
		$this->userSession->method('getUser')->willReturn($currentUser);

		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($currentUser)
			->willReturn(['group1', 'group2']);

		$this->groupManager->expects($this->any())
			->method('findUsersInGroup')
			->will($this->returnValueMap([
				['group1', 'us', 150, 0, ['user1' => $user1, 'user2' => $user2]],
				['group2', 'us', 150, 0, ['user2' => $user2, 'user3' => $user3]],
			]));

		$this->handler->expects($this->once())
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);

		// user3 is already member so it will be filtered out of the result
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(128)
			->willReturn([['user_id' => 'user3']]);

		$response = $this->pageController->searchUsers('group1', 'us', 150);
		$data = $response->getData();

		$this->assertTrue(isset($data['results']));
		$this->assertEquals([
			['userId' => 'user1', 'displayName' => 'User One'],
			['userId' => 'user2', 'displayName' => 'User Two'],
		], $data['results']);
	}

	public function groupDataProvider() {
		$allUsers = [];

		for ($i = 1; $i < 100; $i++) {
			$allUsers['user' . $i] = $this->makeUser('user' . $i, 'User ' . $i);
		}

		$group1Users = \array_chunk($allUsers, 52)[0];
		$group1UsersChunks = \array_chunk($group1Users, 20);

		$group2Users = \array_chunk($allUsers, 50)[1];
		$group2UsersChunks = \array_chunk($group2Users, 20);

		return [
			[
				$allUsers, [
					// first group: 52 entries, user1 to user52
					['group1', 'us', 20, 0, $group1UsersChunks[0]],
					['group1', 'us', 20, 20, $group1UsersChunks[1]],
					['group1', 'us', 20, 40, $group1UsersChunks[2]],
					// second group: 50 entries, user50 to user100 (two users overlap)
					['group2', 'us', 20, 0, $group2UsersChunks[0]],
					['group2', 'us', 20, 20, $group2UsersChunks[1]],
					['group2', 'us', 20, 40, $group2UsersChunks[2]],
					// third group, single user overlapping
					['group3', 'us', 20, 0, ['user4' => $allUsers['user4']]],
				],
			],
			[
				$allUsers, [
					// first group: user1 to user10
					['group1', 'us', 20, 0, \array_chunk($allUsers, 10)[0]],
					// second group: user11 to user20
					['group2', 'us', 20, 0, \array_chunk($allUsers, 10)[1]],
					// third group empty
					['group3', 'us', 20, 0, ['user21' => $allUsers['user21']]],
				],
			],
		];
	}

	/**
	 * @dataProvider groupDataProvider
	 */
	public function testSearchUsersInGroupLimit($allUsers, $groupData) {
		$this->config->method('getAppValue')
			->will($this->returnValueMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
				['core', 'shareapi_allow_share_dialog_user_enumeration', 'yes', 'yes'],
				['core', 'shareapi_share_dialog_user_enumeration_group_members', 'no', 'no'],
			]));

		$currentUser = $this->makeUser('currentuser', 'Current User');
		$this->userSession->method('getUser')->willReturn($currentUser);

		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($currentUser)
			->willReturn(['group1', 'group2', 'group3']);

		$this->groupManager->expects($this->any())
			->method('findUsersInGroup')
			->will($this->returnValueMap($groupData));

		$this->handler->expects($this->once())
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);

		// user3 is already member so it will be filtered out of the result
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(128)
			->willReturn([['user_id' => 'user3']]);

		$response = $this->pageController->searchUsers('group1', 'us', 20);
		$data = $response->getData();

		$this->assertTrue(isset($data['results']));
		$this->assertCount(20, $data['results']);

		$expectedResults = [];
		for ($i = 1; $i <= 21; $i++) {
			if ($i === 3) {
				// user3 was already member and is excluded
				continue;
			}
			$expectedResults[] = ['userId' => 'user' . $i, 'displayName' => 'User ' . $i];
		}

		$this->assertEquals(
			$expectedResults,
			$data['results']
		);
	}

	/**
	 * @dataProvider exactSearchDataProvider
	 */
	public function testSearchUsersInGroupExact($searchPattern, $expectedResults) {
		$user1 = $this->makeUser('user1', 'User One', 'test@example.com', ['meh', 'moo']);
		$user2 = $this->makeUser('user2', 'User Two');
		$user3 = $this->makeUser('user3', 'User Three');
		$userone = $this->makeUser('userone', 'User One'); // sometimes people have same display names

		$this->config->method('getAppValue')
			->will($this->returnValueMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
				['core', 'shareapi_allow_share_dialog_user_enumeration', 'yes', 'no'],
				['core', 'shareapi_share_dialog_user_enumeration_group_members', 'no', 'no'],
			]));

		$currentUser = $this->makeUser('currentuser', 'Current User');
		$this->userSession->method('getUser')->willReturn($currentUser);

		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($currentUser)
			->willReturn(['group1', 'group2']);

		$this->groupManager->expects($this->any())
			->method('findUsersInGroup')
			->will($this->returnValueMap([
				['group1', \strtolower($searchPattern), 150, 0, ['user1' => $user1, 'user2' => $user2]],
				['group2', \strtolower($searchPattern), 150, 0, ['user2' => $user2, 'user3' => $user3, 'userone' => $userone]],
			]));

		$this->handler->expects($this->once())
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);

		// user3 is already member so it will be filtered out of the result
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(128)
			->willReturn([['user_id' => 'user3']]);

		$response = $this->pageController->searchUsers('group1', $searchPattern, 150);
		$data = $response->getData();
		$this->assertTrue(isset($data['results']));
		$this->assertEquals($expectedResults, $data['results']);
	}

	public function testSearchUsersInGroupExactLimit() {
		$allUsers = [];

		for ($i = 1; $i < 25; $i++) {
			$allUsers[] = $this->makeUser('user-out-' . $i, 'User ' . $i);
		}

		for ($i = 1; $i < 25; $i++) {
			$allUsers[] = $this->makeUser('user' . $i, 'User One');
		}

		$this->config->method('getAppValue')
			->will($this->returnValueMap([
				['core', 'shareapi_only_share_with_group_members', 'no', 'yes'],
				['core', 'shareapi_allow_share_dialog_user_enumeration', 'yes', 'no'],
				['core', 'shareapi_share_dialog_user_enumeration_group_members', 'no', 'no'],
			]));

		$currentUser = $this->makeUser('currentuser', 'Current User');
		$this->userSession->method('getUser')->willReturn($currentUser);

		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($currentUser)
			->willReturn(['group1', 'group2']);

		$allUsersChunk = \array_chunk($allUsers, 20);

		$this->groupManager->expects($this->any())
			->method('findUsersInGroup')
			->will($this->returnValueMap([
				['group1', 'user one', 20, 0, $allUsersChunk[0]],
				['group1', 'user one', 20, 20, $allUsersChunk[1]],
				['group1', 'user one', 20, 40, $allUsersChunk[2]],
				['group2', 'user one', 20, 0, ['user' => $allUsers[10]]],
			]));

		$this->handler->expects($this->once())
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);

		// user3 is already member so it will be filtered out of the result
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(128)
			->willReturn([['user_id' => 'user3']]);

		$response = $this->pageController->searchUsers('group1', 'User One', 20);
		$data = $response->getData();

		$this->assertTrue(isset($data['results']));
		$this->assertCount(20, $data['results']);

		$expectedResults = [];
		for ($i = 1; $i <= 21; $i++) {
			if ($i === 3) {
				// user3 was already member and is excluded
				continue;
			}
			$expectedResults[] = ['userId' => 'user' . $i, 'displayName' => 'User One'];
		}

		$this->assertEquals(
			$expectedResults,
			$data['results']
		);
	}
}
