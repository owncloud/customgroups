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
namespace OCA\CustomGroups\Tests\unit\Dav;

use OCA\CustomGroups\Dav\GroupMembershipCollection;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IUser;
use Sabre\DAV\PropPatch;
use OCA\CustomGroups\Dav\MembershipNode;
use OCA\CustomGroups\Service\MembershipHelper;
use OCP\IGroupManager;
use OCA\CustomGroups\Search;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\IConfig;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class GroupMembershipCollectionTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class GroupMembershipCollectionTest extends \Test\TestCase {
	const CURRENT_USER = 'currentuser';
	const NODE_USER = 'nodeuser';

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var GroupMembershipCollection
	 */
	private $node;

	/**
	 * @var MembershipHelper
	 */
	private $helper;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	public function setUp() {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->handler->expects($this->never())->method('getGroup');
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);

		// currently logged in user
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::CURRENT_USER);
		$this->userSession->method('getUser')->willReturn($user);

		$nodeUser = $this->createMock(IUser::class);
		$nodeUser->method('getUID')->willReturn(self::NODE_USER);
		$this->userManager->method('get')->will(
			$this->returnValueMap([
				[self::NODE_USER, $nodeUser],
				[strtoupper(self::NODE_USER), $nodeUser],
			]));

		$this->helper = $this->getMockBuilder(MembershipHelper::class)
			->setMethods(['notifyUser'])
			->setConstructorArgs([
				$this->handler,
				$this->userSession,
				$this->userManager,
				$this->groupManager,
				$this->createMock(IManager::class),
				$this->createMock(IURLGenerator::class),
				$this->createMock(IConfig::class)
			])
			->getMock();

		$this->node = new GroupMembershipCollection(
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One', 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
			$this->handler,
			$this->helper
		);
	}

	/**
	 * Sets a user's member info, for testing
	 *
	 * @param array $memberInfo user member info
	 */
	private function setCurrentUserMemberInfo($memberInfo) {
		$this->handler->expects($this->any())
			->method('getGroupMemberInfo')
			->with(1, self::CURRENT_USER)
			->willReturn($memberInfo);
	}

	private function setCurrentUserSuperAdmin($isSuperAdmin) {
		$this->groupManager->expects($this->any())
			->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn($isSuperAdmin);
	}

	public function testBase() {
		$this->assertEquals('group1', $this->node->getName());
		$this->assertNull($this->node->getLastModified());
	}

	public function testDeleteAsAdmin() {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]);

		$this->handler->expects($this->at(1))
			->method('deleteGroup')
			->with(1);

		$called = array();
		\OC::$server->getEventDispatcher()->addListener('deleteGroup', function ($event) use (&$called) {
			$called[] = 'deleteGroup';
			array_push($called, $event);
		});

		$this->node->delete();

		$this->assertSame('deleteGroup', $called[0]);
		$this->assertTrue($called[1] instanceof GenericEvent);
		$this->assertArrayHasKey('groupName', $called[1]);
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testDeleteAsNonAdmin() {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]);

		$this->handler->expects($this->never())
			->method('deleteGroup');

		$this->node->delete();
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testDeleteAsNonMember() {
		$this->setCurrentUserMemberInfo(null);

		$this->handler->expects($this->never())
			->method('deleteGroup');

		$this->node->delete();
	}

	public function testGetProperties() {
		$props = $this->node->getProperties(null);
		$this->assertEquals('Group One', $props[GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
		$props = $this->node->getProperties([GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
		$this->assertEquals('Group One', $props[GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
	}

	public function adminSetFlagProvider() {
		return [
			// admin can change display name
			[false, true, 200, true],
			// non-admin cannot change anything
			[false, false, 403, false],
			// non-member cannot change anything
			[false, null, 403, false],
			// super-admin non-member can change anything
			[false, true, 200, true],
		];
	}

	/**
	 * @dataProvider adminSetFlagProvider
	 */
	public function testSetProperties($isSuperAdmin, $currentUserRole, $statusCode, $called) {
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		if ($currentUserRole !== null) {
			$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => $currentUserRole]);
		} else {
			$this->setCurrentUserMemberInfo(null);
		}

		if ($called) {
			$this->handler->expects($this->at(1))
				->method('updateGroup')
				->with(1, 'group1', 'Group Renamed')
				->willReturn(true);
		} else {
			$this->handler->expects($this->never())
				->method('updateGroup');
		}

		$propPatch = new PropPatch([GroupMembershipCollection::PROPERTY_DISPLAY_NAME => 'Group Renamed']);
		$this->node->propPatch($propPatch);

		$propPatch->commit();
		$this->assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		$this->assertEquals($statusCode, $result[GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
	}

	public function rolesProvider() {
		return [
			[false, ['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]],
			[false, ['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]],
			[true, null],
		];
	}

	public function adminProvider() {
		return [
			[false, ['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]],
			[true, null],
		];
	}

	/**
	 * @dataProvider adminProvider
	 */
	public function testAddMemberAsAdmin($isSuperAdmin, $currentMemberInfo) {
		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->handler->expects($this->once())
			->method('addToGroup')
			->with(self::NODE_USER, 1, false)
			->willReturn(true);

		$this->helper->expects($this->once())
			->method('notifyUser')
			->with(
				self::NODE_USER,
				['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One', 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]
			);

		$called = array();
		\OC::$server->getEventDispatcher()->addListener('addUserToGroup', function ($event) use (&$called) {
			$called[] = 'addUserToGroup';
			array_push($called, $event);
		});

		$this->node->createFile(self::NODE_USER);

		$this->assertSame('addUserToGroup', $called[0]);
		$this->assertTrue($called[1] instanceof GenericEvent);
		$this->assertArrayHasKey('groupName', $called[1]);
		$this->assertArrayHasKey('user',$called[1]);
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\PreconditionFailed
	 * @dataProvider adminProvider
	 */
	public function testAddMemberAsAdminFails($isSuperAdmin, $currentMemberInfo) {
		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->handler->expects($this->once())
			->method('addToGroup')
			->with(self::NODE_USER, 1, false)
			->willReturn(false);

		$this->node->createFile(self::NODE_USER);
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\PreconditionFailed
	 * @dataProvider adminProvider
	 */
	public function testAddNonExistingMemberAsAdmin($isSuperAdmin, $currentMemberInfo) {
		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile('userunexist');
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\PreconditionFailed
	 * @dataProvider adminProvider
	 */
	public function testAddNonExistingMemberMismatchCaseAsAdmin($isSuperAdmin, $currentMemberInfo) {
		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile('USER2');
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testAddMemberAsNonAdmin() {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile(self::NODE_USER);
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testAddMemberAsNonMember() {
		$this->setCurrentUserMemberInfo(null);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile(self::NODE_USER);
	}

	public function testIsMember() {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]);
		$this->handler->expects($this->any())
			->method('inGroup')
			->will($this->returnValueMap([
				[self::NODE_USER, 1, true],
				['user3', 1, false],
			]));

		$this->assertTrue($this->node->childExists(self::NODE_USER));
		$this->assertFalse($this->node->childExists('user3'));
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testIsMemberAsNonMember() {
		$this->setCurrentUserMemberInfo(null);

		$this->node->childExists(self::NODE_USER);
	}

	public function testIsMemberAsNonMemberButSuperAdmin() {
		$this->setCurrentUserSuperAdmin(true);
		$this->setCurrentUserMemberInfo(null);

		$this->handler->expects($this->any())
			->method('inGroup')
			->will($this->returnValueMap([
				[self::NODE_USER, 1, true],
				['user3', 1, false],
			]));

		$this->assertTrue($this->node->childExists(self::NODE_USER));
		$this->assertFalse($this->node->childExists('user3'));
	}

	/**
	 * @dataProvider rolesProvider
	 */
	public function testGetMember($isSuperAdmin, $currentMemberInfo) {
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$membershipsMap = [
			[1, self::NODE_USER, ['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]],
		];
		if (!is_null($currentMemberInfo)) {
			$membershipsMap[] = [1, self::CURRENT_USER, $currentMemberInfo];
		}

		$this->handler->expects($this->any())
			->method('getGroupMemberInfo')
			->will($this->returnValueMap($membershipsMap));

		$memberInfo = $this->node->getChild(self::NODE_USER);

		$this->assertInstanceOf(MembershipNode::class, $memberInfo);
		$this->assertEquals(self::NODE_USER, $memberInfo->getName());
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testGetMemberAsNonMember() {
		$this->setCurrentUserMemberInfo(null);

		$this->node->getChild(self::NODE_USER);
	}

	/**
	 * @dataProvider rolesProvider
	 */
	public function testGetMembers($isSuperAdmin, $currentMemberInfo) {
		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, null)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
				['group_id' => 1, 'user_id' => 'user3', 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER],
			]);

		$memberInfos = $this->node->getChildren();

		$this->assertCount(2, $memberInfos);
		$this->assertInstanceOf(MembershipNode::class, $memberInfos[0]);
		$this->assertEquals(self::NODE_USER, $memberInfos[0]->getName());
		$this->assertInstanceOf(MembershipNode::class, $memberInfos[1]);
		$this->assertEquals('user3', $memberInfos[1]->getName());
	}

	/**
	 * @dataProvider rolesProvider
	 */
	public function testSearchMembers($isSuperAdmin, $currentMemberInfo) {
		$search = new Search('us', 16, 256);

		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $search)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
				['group_id' => 1, 'user_id' => 'user3', 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER],
			]);

		$memberInfos = $this->node->search($search);

		$this->assertCount(2, $memberInfos);
		$this->assertInstanceOf(MembershipNode::class, $memberInfos[0]);
		$this->assertEquals(self::NODE_USER, $memberInfos[0]->getName());
		$this->assertInstanceOf(MembershipNode::class, $memberInfos[1]);
		$this->assertEquals('user3', $memberInfos[1]->getName());
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testGetMembersAsNonMember() {
		$this->setCurrentUserMemberInfo(null);

		$this->node->getChildren();
	}

	/**
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testSetName() {
		$this->node->setName('x');
	}

	/**
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testCreateDirectory() {
		$this->node->createDirectory('somedir');
	}
}
