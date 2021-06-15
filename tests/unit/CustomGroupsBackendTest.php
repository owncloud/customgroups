<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH
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
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\CustomGroupsBackend;
use OCP\GroupInterface;
use OCA\CustomGroups\Search;

/**
 * Class CustomGroupsBackendTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class CustomGroupsBackendTest extends \Test\TestCase {
	public const GROUP_ID_PREFIX = CustomGroupsBackend::GROUP_ID_PREFIX;

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	public function setUp(): void {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->backend = new CustomGroupsBackend($this->handler);
	}

	public function testImplementsAction() {
		$this->assertTrue($this->backend->implementsActions(GroupInterface::GROUP_DETAILS));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::CREATE_GROUP));
		$this->assertTrue($this->backend->implementsActions(GroupInterface::DELETE_GROUP));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::ADD_TO_GROUP));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::REMOVE_FROM_GROUP));
		$this->assertFalse($this->backend->implementsActions(GroupInterface::COUNT_USERS));
	}

	public function testInGroup() {
		$this->handler->expects($this->any())
			->method('inGroupByUri')
			->will($this->returnValueMap([
				['user1', 'one', true],
				['user2', 'one', false],
			]));

		$this->assertTrue($this->backend->inGroup('user1', self::GROUP_ID_PREFIX . 'one'));
		$this->assertFalse($this->backend->inGroup('user2', self::GROUP_ID_PREFIX . 'one'));
		$this->assertFalse($this->backend->inGroup('user1', 'one'));
	}

	public function testGetUserGroups() {
		$this->handler->expects($this->any())
			->method('getUserMemberships')
			->will($this->returnValueMap([
				['user1', null, [
					['group_id' => 1, 'uri' => 'one'],
					['group_id' => 2, 'uri' => 'two']]
				],
				['user2', null, [
					['group_id' => 1, 'uri' => 'one'],
					['group_id' => 3, 'uri' => 'three']]
				],
			]));

		$this->assertEquals(
			[
				self::GROUP_ID_PREFIX . 'one',
				self::GROUP_ID_PREFIX . 'two',
			],
			$this->backend->getUserGroups('user1')
		);
		$this->assertEquals(
			[
				self::GROUP_ID_PREFIX . 'one',
				self::GROUP_ID_PREFIX . 'three',
			],
			$this->backend->getUserGroups('user2')
		);
	}

	public function testGetGroups() {
		$this->handler->expects($this->any())
			->method('searchGroups')
			->with(new Search('ser', 5, 10))
			->will($this->returnValue([
				['group_id' => 1, 'uri' => 'one'],
				['group_id' => 2, 'uri' => 'two'],
			]));

		$this->assertEquals(
			[
				self::GROUP_ID_PREFIX . 'one',
				self::GROUP_ID_PREFIX . 'two',
			],
			$this->backend->getGroups('ser', 10, 5)
		);
	}

	public function testGroupExists() {
		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->will($this->returnValueMap([
				['one', ['group_id' => 1, 'display_name' => 'Group One', 'uri' => 'one']],
				['two', null],
			]));

		$this->assertTrue($this->backend->groupExists(self::GROUP_ID_PREFIX . 'one'));
		$this->assertFalse($this->backend->groupExists(self::GROUP_ID_PREFIX . 'two'));
		$this->assertFalse($this->backend->groupExists(1));
	}

	public function testGetGroupDetails() {
		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->will($this->returnValueMap([
				['one', ['group_id' => 1, 'display_name' => 'Group One', 'uri' => 'one']],
				['two', null],
			]));

		$groupInfo = $this->backend->getGroupDetails(self::GROUP_ID_PREFIX . 'one');
		$this->assertEquals(self::GROUP_ID_PREFIX . 'one', $groupInfo['gid']);
		$this->assertEquals('Group One', $groupInfo['displayName']);

		$this->assertNull($this->backend->getGroupDetails(self::GROUP_ID_PREFIX . 'two'));
		$this->assertNull($this->backend->getGroupDetails(1));
	}

	public function testUsersInGroup() {
		$this->handler->expects($this->once())
			->method('getGroupByUri')
			->with('one')
			->willReturn(['group_id' => 1, 'display_name' => 'Group One']);
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(1, new Search('ser', 5, 10))
			->willReturn([
				['user_id' => 'user1'],
				['user_id' => 'user2'],
			]);
		$this->assertEquals(
			['user1', 'user2'],
			$this->backend->usersInGroup(self::GROUP_ID_PREFIX . 'one', 'ser', 10, 5)
		);
	}
}
