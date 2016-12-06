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
use OCP\ILogger;

/**
 * Class CustomGroupsDatabaseHandlerTest
 *
 * @group DB
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class CustomGroupsDatabaseHandlerTest extends \Test\TestCase {

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var ILogger
	 */
	private $logger;

	public function setUp() {
		parent::setUp();
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->logger = $this->createMock(ILogger::class);
		$this->handler = new CustomGroupsDatabaseHandler($this->connection, $this->logger);
	}

	public function tearDown() {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('custom_group_member')->execute();
		$qb->delete('custom_group')->execute();
	}

	public function testCreateGroup() {
		$this->assertNotNull($this->handler->createGroup('my_group', 'My Group'));
		// recreating returns null
		$this->assertNull($this->handler->createGroup('my_group', 'My Group'));
	}

	public function testCreateGroupDuplicateUri() {
		$this->assertNotNull($this->handler->createGroup('my_group', 'My Group'));
		$this->logger->expects($this->once())->method('logException');
		$this->assertNull($this->handler->createGroup('my_group', 'Different display name'));
	}

	public function testDeleteGroup() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');
		$this->assertTrue($this->handler->deleteGroup($groupId));

		$this->assertNull($this->handler->getGroup($groupId));

		$this->assertFalse($this->handler->deleteGroup($groupId));
	}

	public function testUpdateGroup() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');
		$this->assertTrue($this->handler->updateGroup($groupId, 'meine_gruppe', 'Meine Gruppe'));

		$groupInfo = $this->handler->getGroup($groupId);

		$this->assertEquals('meine_gruppe', $groupInfo['uri']);
		$this->assertEquals('Meine Gruppe', $groupInfo['display_name']);
	}

	public function testSearchGroups() {
		$group1Id = $this->handler->createGroup('my_group_1', 'My One Group');
		$group2Id = $this->handler->createGroup('my_group_2', 'My Group Two');
		$group3Id = $this->handler->createGroup('my_group_3', 'AA One');

		$results = $this->handler->searchGroups('one');
		$this->assertCount(2, $results);

		// results sorted by display_name
		$this->assertEquals('my_group_3', $results[0]['uri']);
		$this->assertEquals('AA One', $results[0]['display_name']);
		$this->assertEquals($group3Id, $results[0]['group_id']);

		$this->assertEquals('my_group_1', $results[1]['uri']);
		$this->assertEquals('My One Group', $results[1]['display_name']);
		$this->assertEquals($group1Id, $results[1]['group_id']);
	}

	public function testSearchGroupsPagination() {
		$count = 30;
		for ($i = 0; $i < $count; $i++) {
			$num = (string)$i;
			if (strlen($num) === 1) {
				// doing this for a correct display_name sort because
				// usually 1 comes before 10 in DB sorts...
				$num = '0' . $num;
			}
			$groupIds[$i] = $this->handler->createGroup('my_group_' . $num, 'My Group ' . $num);
		}

		$results = $this->handler->searchGroups('Group', 3, 5);
		$this->assertCount(3, $results);

		$this->assertEquals($groupIds[5], $results[0]['group_id']);
		$this->assertEquals($groupIds[6], $results[1]['group_id']);
		$this->assertEquals($groupIds[7], $results[2]['group_id']);

		// search beyond last page
		$results = $this->handler->searchGroups('Group', 100, 5);
		$this->assertCount(25, $results);
	}

	public function testGetGroup() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');
		$this->assertNotNull($groupId);

		$groupInfo = $this->handler->getGroup($groupId);
		$this->assertEquals($groupId, $groupInfo['group_id']);
		$this->assertEquals('My Group', $groupInfo['display_name']);
		$this->assertEquals('my_group', $groupInfo['uri']);

		$this->assertNull($this->handler->getGroup(-100));
	}

	public function testGetGroupByUri() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');
		$this->assertNotNull($groupId);

		$groupInfo = $this->handler->getGroupByUri('my_group');
		$this->assertEquals($groupId, $groupInfo['group_id']);
		$this->assertEquals('My Group', $groupInfo['display_name']);
		$this->assertEquals('my_group', $groupInfo['uri']);

		$this->assertNull($this->handler->getGroupByUri('unexist'));
	}

	public function testGetGroups() {
		$group1Id = $this->handler->createGroup('my_group_1', 'My One Group');
		$group2Id = $this->handler->createGroup('my_group_2', 'My Group Two');
		$group3Id = $this->handler->createGroup('my_group_3', 'AA One');

		$results = $this->handler->getGroups();

		$this->assertCount(3, $results);

		// results sorted by display_name
		$this->assertEquals('my_group_3', $results[0]['uri']);
		$this->assertEquals('AA One', $results[0]['display_name']);
		$this->assertEquals($group3Id, $results[0]['group_id']);

		$this->assertEquals('my_group_2', $results[1]['uri']);
		$this->assertEquals('My Group Two', $results[1]['display_name']);
		$this->assertEquals($group2Id, $results[1]['group_id']);

		$this->assertEquals('my_group_1', $results[2]['uri']);
		$this->assertEquals('My One Group', $results[2]['display_name']);
		$this->assertEquals($group1Id, $results[2]['group_id']);
	}

	public function testAddToGroup() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');

		$this->assertTrue($this->handler->addToGroup('user2', $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER));
		$this->assertTrue($this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN));

		$members = $this->handler->getGroupMembers($groupId);

		$this->assertCount(2, $members);

		$this->assertEquals('user1', $members[0]['user_id']);
		$this->assertEquals($groupId, $members[0]['group_id']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_ADMIN, $members[0]['role']);

		$this->assertEquals('user2', $members[1]['user_id']);
		$this->assertEquals($groupId, $members[1]['group_id']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_MEMBER, $members[1]['role']);

		// add again returns false
		$this->assertFalse($this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN));
	}

	public function testRemoveFromGroup() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');
		$groupId2 = $this->handler->createGroup('my_group2', 'My Group Two');

		$this->handler->addToGroup('user2', $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER);
		$this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$this->handler->addToGroup('user2', $groupId2, CustomGroupsDatabaseHandler::ROLE_MEMBER);

		$this->assertTrue($this->handler->removeFromGroup('user2', $groupId));

		$members = $this->handler->getGroupMembers($groupId);
		$this->assertCount(1, $members);

		$this->assertEquals('user1', $members[0]['user_id']);
		$this->assertEquals($groupId, $members[0]['group_id']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_ADMIN, $members[0]['role']);

		// member still exists in the other group
		$members2 = $this->handler->getGroupMembers($groupId2);

		$this->assertCount(1, $members2);
		$this->assertEquals('user2', $members2[0]['user_id']);
		$this->assertEquals($groupId2, $members2[0]['group_id']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_MEMBER, $members2[0]['role']);

		// remove again returns false
		$this->assertFalse($this->handler->removeFromGroup('user2', $groupId));
	}

	public function testGetGroupMembersFilter() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');

		$this->assertTrue($this->handler->addToGroup('user2', $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER));
		$this->assertTrue($this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN));

		$adminMembers = $this->handler->getGroupMembers($groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$nonAdminMembers = $this->handler->getGroupMembers($groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER);

		$this->assertCount(1, $adminMembers);
		$this->assertCount(1, $nonAdminMembers);

		$this->assertEquals('user1', $adminMembers[0]['user_id']);
		$this->assertEquals($groupId, $adminMembers[0]['group_id']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_ADMIN, $adminMembers[0]['role']);

		$this->assertEquals('user2', $nonAdminMembers[0]['user_id']);
		$this->assertEquals($groupId, $nonAdminMembers[0]['group_id']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_MEMBER, $nonAdminMembers[0]['role']);
	}

	public function testDeleteRemovesMembers() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');

		$this->handler->addToGroup('user2', $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER);
		$this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->assertTrue($this->handler->deleteGroup($groupId));

		$this->assertFalse($this->handler->inGroup('user1', $groupId));
		$this->assertFalse($this->handler->inGroup('user2', $groupId));
	}

	public function testGetGroupMemberInfo() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');

		$this->handler->addToGroup('user2', $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER);
		$this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$member = $this->handler->getGroupMemberInfo($groupId, 'user1');

		$this->assertEquals('user1', $member['user_id']);
		$this->assertEquals($groupId, $member['group_id']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_ADMIN, $member['role']);
	}

	public function testSetGroupMemberInfo() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');

		$this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->assertTrue($this->handler->setGroupMemberInfo($groupId, 'user1', CustomGroupsDatabaseHandler::ROLE_MEMBER));
		$member = $this->handler->getGroupMemberInfo($groupId, 'user1');
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_MEMBER, $member['role']);

		$this->assertTrue($this->handler->setGroupMemberInfo($groupId, 'user1', CustomGroupsDatabaseHandler::ROLE_ADMIN));
		$member = $this->handler->getGroupMemberInfo($groupId, 'user1');
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_ADMIN, $member['role']);

		// setting to same value also returns true
		$this->assertTrue($this->handler->setGroupMemberInfo($groupId, 'user1', CustomGroupsDatabaseHandler::ROLE_ADMIN));
	}

	public function testInGroup() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');

		$this->handler->addToGroup('user2', $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER);

		$this->assertTrue($this->handler->inGroup('user2', $groupId));
		$this->assertFalse($this->handler->inGroup('user3', $groupId));
	}

	public function testGetUserMemberships() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');
		$groupId2 = $this->handler->createGroup('my_group2', 'My Group Two');

		$this->handler->addToGroup('user2', $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER);
		$this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$this->handler->addToGroup('user2', $groupId2, CustomGroupsDatabaseHandler::ROLE_MEMBER);

		$groups = $this->handler->getUserMemberships('user2');
		$this->assertCount(2, $groups);
		$this->assertEquals($groupId, $groups[0]['group_id']);
		$this->assertEquals('user2', $groups[0]['user_id']);
		$this->assertEquals('my_group', $groups[0]['uri']);
		$this->assertEquals('My Group', $groups[0]['display_name']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_MEMBER, $groups[0]['role']);
		$this->assertEquals($groupId2, $groups[1]['group_id']);
		$this->assertEquals('user2', $groups[1]['user_id']);
		$this->assertEquals('my_group2', $groups[1]['uri']);
		$this->assertEquals('My Group Two', $groups[1]['display_name']);
		$this->assertEquals(CustomGroupsDatabaseHandler::ROLE_MEMBER, $groups[1]['role']);
	}

	public function testGetUserMembershipsFiltered() {
		$groupId = $this->handler->createGroup('my_group', 'My Group');
		$groupId2 = $this->handler->createGroup('my_group2', 'My Group Two');

		$this->handler->addToGroup('user1', $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER);
		$this->handler->addToGroup('user1', $groupId2, CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$adminGroups = $this->handler->getUserMemberships('user1', CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$this->assertCount(1, $adminGroups);
		$nonAdminGroups = $this->handler->getUserMemberships('user1', CustomGroupsDatabaseHandler::ROLE_MEMBER);
		$this->assertCount(1, $nonAdminGroups);

		$this->assertEquals($groupId, $nonAdminGroups[0]['group_id']);
		$this->assertEquals($groupId2, $adminGroups[0]['group_id']);
	}
}
