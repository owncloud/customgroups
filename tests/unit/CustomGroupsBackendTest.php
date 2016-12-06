<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH.
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

use OCP\IDBConnection;
use OCA\CustomGroups\CustomGroupsManager;
use OCA\CustomGroups\CustomGroupsBackend;
use OCP\GroupInterface;

/**
 * Class CustomGroupsBackendTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class CustomGroupsBackendTest extends \Test\TestCase {
	const GROUP_ID_PREFIX = CustomGroupsBackend::GROUP_ID_PREFIX;

	/**
	 * @var CustomGroupsManager
	 */
	private $manager;

	public function setUp() {
		$this->manager = $this->createMock(CustomGroupsManager::class);
		$this->backend = new CustomGroupsBackend($this->manager);
	}

	public function testImplementsAction() {
		$this->assertTrue($this->backend->implementsActions(GroupInterface::GROUP_DETAILS));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::CREATE_GROUP));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::DELETE_GROUP));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::ADD_TO_GROUP));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::REMOVE_FROM_GROUP));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::COUNT_USERS));
	}

	public function testInGroup() {
		$this->manager->expects($this->any())
			->method('inGroup')
			->will($this->returnValueMap([
				['user1', 1, true],
				['user2', 1, false],
			]));

		$this->assertTrue($this->backend->inGroup('user1', self::GROUP_ID_PREFIX . '1'));
		$this->assertFalse($this->backend->inGroup('user2', self::GROUP_ID_PREFIX . '1'));
		$this->assertFalse($this->backend->inGroup('user1', '1'));
	}

	public function testGetUserGroups() {
		$this->manager->expects($this->any())
			->method('getUserGroups')
			->will($this->returnValueMap([
				['user1', [1, 2]],
				['user2', [1, 3]],
			]));

		$this->assertEquals(
			[
				self::GROUP_ID_PREFIX . '1',
				self::GROUP_ID_PREFIX . '2',
			],
			$this->backend->getUserGroups('user1')
		);
		$this->assertEquals(
			[
				self::GROUP_ID_PREFIX . '1',
				self::GROUP_ID_PREFIX . '3',
			],
			$this->backend->getUserGroups('user2')
		);
	}

	public function testGetGroups() {
		$this->manager->expects($this->any())
			->method('searchGroups')
			->with('ser', 10, 5)
			->will($this->returnValue([
				['group_id' => 1],
				['group_id' => 2],
			]));

		$this->assertEquals(
			[
				self::GROUP_ID_PREFIX . '1',
				self::GROUP_ID_PREFIX . '2',
			],
			$this->backend->getGroups('ser', 10, 5)
		);
	}

	public function testGroupExists() {
		$this->manager->expects($this->any())
			->method('getGroup')
			->will($this->returnValueMap([
				[1, ['group_id' => 1, 'display_name' => 'Group One']],
				[2, null],
			]));

		$this->assertTrue($this->backend->groupExists(self::GROUP_ID_PREFIX . '1'));
		$this->assertFalse($this->backend->groupExists(self::GROUP_ID_PREFIX . '2'));
		$this->assertFalse($this->backend->groupExists(1));
	}

	public function testGetGroupDetails() {
		$this->manager->expects($this->any())
			->method('getGroup')
			->will($this->returnValueMap([
				[1, ['group_id' => 1, 'display_name' => 'Group One']],
				[2, null],
			]));

		$groupInfo = $this->backend->getGroupDetails(self::GROUP_ID_PREFIX . '1');
		$this->assertEquals(self::GROUP_ID_PREFIX . '1', $groupInfo['gid']);
		$this->assertEquals('Group One', $groupInfo['displayName']);

		$this->assertNull($this->backend->getGroupDetails(self::GROUP_ID_PREFIX . '2'));
		$this->assertNull($this->backend->getGroupDetails(1));
	}

	public function testUsersInGroup() {
		$this->manager->expects($this->never())->method('getGroup');
		$this->manager->expects($this->never())->method('getGroupMembers');
		$this->assertEquals([], $this->backend->usersInGroup(self::GROUP_ID_PREFIX . '1'));
	}

}
