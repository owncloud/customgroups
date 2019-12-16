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

use OCA\CustomGroups\Dav\MembershipNode;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IUser;
use Sabre\DAV\PropPatch;
use OCA\CustomGroups\Service\MembershipHelper;
use OCP\IGroupManager;
use OCA\CustomGroups\Dav\Roles;
use OCA\CustomGroups\Search;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\IConfig;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class MembershipNodeTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class MembershipNodeTest extends \Test\TestCase {
	const CURRENT_USER = 'currentuser';
	const NODE_USER = 'nodeuser';

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var MembershipNode
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

	/** @var IConfig */
	private $config;

	public function setUp(): void {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = $this->createMock(IConfig::class);

		// currently logged in user
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::CURRENT_USER);
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);

		$this->helper = $this->getMockBuilder(MembershipHelper::class)
			->setMethods(['notifyUserRoleChange', 'notifyUserRemoved', 'isTheOnlyAdmin'])
			->setConstructorArgs([
				$this->handler,
				$this->userSession,
				$this->userManager,
				$this->groupManager,
				$this->createMock(IManager::class),
				$this->createMock(IURLGenerator::class),
				$this->config
			])
			->getMock();

		$this->node = new MembershipNode(
			['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
			self::NODE_USER,
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
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

	public function testBase() {
		$this->assertEquals(self::NODE_USER, $this->node->getName());
		$this->assertNull($this->node->getLastModified());
	}

	public function testNodeName() {
		$node = new MembershipNode(
			['group_id' => 1, 'uri' => 'group1', 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
			'group1',
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
			$this->handler,
			$this->helper
		);
		$this->assertEquals('group1', $node->getName());
	}

	public function testDeleteAsAdmin() {
		$memberInfo = ['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN];

		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->setCurrentUserMemberInfo($memberInfo);
		$this->handler->expects($this->once())
			->method('removeFromGroup')
			->with(self::NODE_USER, 1)
			->willReturn(true);

		$this->helper->expects($this->once())
			->method('notifyUserRemoved')
			->with(
				self::NODE_USER,
				['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]
			);

		$this->helper->expects($this->once())
			->method('isTheOnlyAdmin')
			->with(1, self::NODE_USER)
			->willReturn(false);

		$searchAdmins = new Search();
		$searchAdmins->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $searchAdmins)
			->willReturn([$memberInfo]);

		$called = [];
		\OC::$server->getEventDispatcher()->addListener('\OCA\CustomGroups::removeUserFromGroup', function ($event) use (&$called) {
			$called[] = '\OCA\CustomGroups::removeUserFromGroup';
			\array_push($called, $event);
		});
		$newCalled = [];
		\OC::$server->getEventDispatcher()->addListener('customGroups.removeUserFromGroup', function ($event) use (&$newCalled) {
			$newCalled[] = 'customGroups.removeUserFromGroup';
			$newCalled[] = $event;
		});

		$this->node->delete();

		$this->assertSame('\OCA\CustomGroups::removeUserFromGroup', $called[0]);
		$this->assertTrue($called[1] instanceof GenericEvent);
		$this->assertArrayHasKey('user_displayName', $called[1]);
		$this->assertArrayHasKey('group_displayName', $called[1]);
		$this->assertEquals('customGroups.removeUserFromGroup', $newCalled[0]);
		$this->assertArrayHasKey('user', $newCalled[1]);
		$this->assertEquals(self::NODE_USER, $newCalled[1]->getArgument('user'));
		$this->assertArrayHasKey('groupName', $newCalled[1]);
		$this->assertEquals('Group One', $newCalled[1]->getArgument('groupName'));
		$this->assertArrayHasKey('groupId', $newCalled[1]);
		$this->assertEquals(1, $newCalled[1]->getArgument('groupId'));
	}

	/**
	 */
	public function testDeleteAsAdminFailed() {
		$this->expectException(\Sabre\DAV\Exception\PreconditionFailed::class);

		$memberInfo = ['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN];
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->setCurrentUserMemberInfo($memberInfo);
		$this->handler->expects($this->once())
			->method('removeFromGroup')
			->with(self::NODE_USER, 1)
			->willReturn(false);

		$searchAdmins = new Search();
		$searchAdmins->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $searchAdmins)
			->willReturn([$memberInfo]);

		$this->node->delete();
	}

	/**
	 */
	public function testDeleteAsNonAdmin() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]);
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);
		$this->handler->expects($this->never())
			->method('removeFromGroup');

		$this->node->delete();
	}

	/**
	 * Creates a node for the NODE_USER user and give that
	 * user permissions if needed
	 *
	 * @param int $role admin perms for the NODE_USER
	 * @return MembershipNode new node
	 */
	private function makeSelfNode($role) {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::NODE_USER);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$helper = new MembershipHelper(
			$this->handler,
			$userSession,
			$this->userManager,
			$this->groupManager,
			$this->createMock(IManager::class),
			$this->createMock(IURLGenerator::class),
			$this->config
		);

		$memberInfo = ['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => $role];
		$node = new MembershipNode(
			$memberInfo,
			self::NODE_USER,
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
			$this->handler,
			$helper
		);
		$this->handler->expects($this->any())
			->method('getGroupMemberInfo')
			->with(1, self::NODE_USER)
			->willReturn($memberInfo);
		return $node;
	}

	public function testDeleteSelfAsNonAdmin() {
		$node = $this->makeSelfNode(CustomGroupsDatabaseHandler::ROLE_MEMBER);

		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(true);

		$this->handler->expects($this->once())
			->method('removeFromGroup')
			->with(self::NODE_USER, 1)
			->willReturn(true);

		$searchAdmins = new Search();
		$searchAdmins->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(1, $searchAdmins)
			->willReturn([['user_id' => 'adminuser']]);

		// no notification in this case
		$this->helper->expects($this->never())
			->method('notifyUserRemoved');

		$deprecatedLeaveFromGroup = [];
		\OC::$server->getEventDispatcher()->addListener('\OCA\CustomGroups::leaveFromGroup', function ($event) use (&$deprecatedLeaveFromGroup) {
			$deprecatedLeaveFromGroup[] = '\OCA\CustomGroups::leaveFromGroup';
			$deprecatedLeaveFromGroup[] = $event;
		});

		$newLeaveFromGroup = [];
		\OC::$server->getEventDispatcher()->addListener('customGroups.leaveFromGroup', function ($event) use (&$newLeaveFromGroup) {
			$newLeaveFromGroup[] = 'customGroups.leaveFromGroup';
			$newLeaveFromGroup[] = $event;
		});
		$node->delete();
		$this->assertEquals('\OCA\CustomGroups::leaveFromGroup', $deprecatedLeaveFromGroup[0]);
		$this->assertInstanceOf(GenericEvent::class, $deprecatedLeaveFromGroup[1]);
		$this->assertArrayHasKey('userId', $deprecatedLeaveFromGroup[1]);
		$this->assertEquals('nodeuser', $deprecatedLeaveFromGroup[1]->getArgument('userId'));
		$this->assertArrayHasKey('groupName', $deprecatedLeaveFromGroup[1]);
		$this->assertEquals('Group One', $deprecatedLeaveFromGroup[1]->getArgument('groupName'));
		$this->assertEquals('customGroups.leaveFromGroup', $newLeaveFromGroup[0]);
		$this->assertInstanceOf(GenericEvent::class, $newLeaveFromGroup[1]);
		$this->assertArrayHasKey('user', $newLeaveFromGroup[1]);
		$this->assertEquals('nodeuser', $newLeaveFromGroup[1]->getArgument('user'));
		$this->assertArrayHasKey('groupName', $newLeaveFromGroup[1]);
		$this->assertEquals('Group One', $newLeaveFromGroup[1]->getArgument('groupName'));
		$this->assertArrayHasKey('groupId', $newLeaveFromGroup[1]);
		$this->assertEquals(1, $newLeaveFromGroup[1]->getArgument('groupId'));
	}

	/**
	 */
	public function testDeleteAsNonMember() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);
		$this->setCurrentUserMemberInfo(null);
		$this->handler->expects($this->never())
			->method('removeFromGroup');

		$this->node->delete();
	}

	/**
	 * Super admin can delete any member
	 */
	public function testDeleteAsSuperAdmin() {
		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$this->setCurrentUserMemberInfo(null);
		$this->groupManager->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn(true);

		$this->handler->expects($this->once())
			->method('removeFromGroup')
			->with(self::NODE_USER, 1)
			->willReturn(true);

		$searchAdmins = new Search();
		$searchAdmins->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $searchAdmins)
			->willReturn([['user_id' => 'adminuser']]);

		$this->node->delete();
	}

	/**
	 */
	public function testDeleteSelfAsLastAdmin() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$node = $this->makeSelfNode(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$searchAdmin = new Search();
		$searchAdmin->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $searchAdmin)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]
			]);

		$this->handler->expects($this->never())
			->method('removeFromGroup');

		$node->delete();
	}

	/**
	 */
	public function testDeleteLastAdminAsSuperAdmin() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$node = $this->makeSelfNode(CustomGroupsDatabaseHandler::ROLE_MEMBER);

		$this->groupManager->method('isAdmin')
			->with(self::NODE_USER)
			->willReturn(true);

		$searchAdmin = new Search();
		$searchAdmin->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $searchAdmin)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]
			]);

		$this->handler->expects($this->never())
			->method('removeFromGroup');

		$node->delete();
	}

	public function propsProvider() {
		return [
			[
				MembershipNode::PROPERTY_ROLE,
				Roles::DAV_ROLE_ADMIN,
				CustomGroupsDatabaseHandler::ROLE_ADMIN,
			],
			[
				MembershipNode::PROPERTY_ROLE,
				Roles::DAV_ROLE_MEMBER,
				CustomGroupsDatabaseHandler::ROLE_MEMBER,
			],
			[
				MembershipNode::PROPERTY_USER_ID,
				self::NODE_USER,
			],
		];
	}

	/**
	 * @dataProvider propsProvider
	 */
	public function testGetProperties($propName, $propValue, $roleValue = 0) {
		$node = new MembershipNode(
			['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => $roleValue, 'uri' => 'group1'],
			self::NODE_USER,
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
			$this->handler,
			$this->helper
		);

		$props = $node->getProperties(null);
		$this->assertSame($propValue, $props[$propName]);
		$props = $node->getProperties([$propName]);
		$this->assertSame($propValue, $props[$propName]);
	}

	public function adminSetFlagProvider() {
		return [
			// admin can change flag for others
			[false, CustomGroupsDatabaseHandler::ROLE_ADMIN, Roles::DAV_ROLE_ADMIN, 200, true],
			[false, CustomGroupsDatabaseHandler::ROLE_ADMIN, Roles::DAV_ROLE_MEMBER, 200, true],
			// non-admin cannot change anything
			[false, CustomGroupsDatabaseHandler::ROLE_MEMBER, Roles::DAV_ROLE_ADMIN, 403, false],
			[false, CustomGroupsDatabaseHandler::ROLE_MEMBER, Roles::DAV_ROLE_MEMBER, 403, false],
			// non-member cannot change anything
			[false, null, Roles::DAV_ROLE_ADMIN, 403, false],
			[false, null, Roles::DAV_ROLE_MEMBER, 403, false],
			// super-admin can change even as non-member
			[true, null, Roles::DAV_ROLE_ADMIN, 200, true],
			[true, null, Roles::DAV_ROLE_MEMBER, 200, true],
			// set invalid property
			[true, null, 'invalid', 400, false],
		];
	}

	/**
	 * @dataProvider adminSetFlagProvider
	 */
	public function testSetProperties($isSuperAdmin, $currentUserRole, $roleToSet, $statusCode, $called) {
		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn($isSuperAdmin);
		if ($currentUserRole !== null) {
			$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => $currentUserRole]);
		} else {
			$this->setCurrentUserMemberInfo(null);
		}

		$this->groupManager->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn($isSuperAdmin);

		if ($called) {
			$this->handler->expects($this->once())
				->method('setGroupMemberInfo')
				->with(1, self::NODE_USER, Roles::davToBackend($roleToSet))
				->willReturn(true);
			$this->helper->expects($this->once())
				->method('notifyUserRoleChange')
				->with(
					self::NODE_USER,
					['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
					['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => Roles::davToBackend($roleToSet)]
				);
		} else {
			$this->handler->expects($this->never())
				->method('setGroupMemberInfo');
			$this->helper->expects($this->never())
				->method('notifyUserRoleChange');
		}

		$searchAdmin = new Search();
		$searchAdmin->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $searchAdmin)
			->willReturn([
				['group_id' => 1, 'user_id' => 'someotheradmin', 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
			]);

		$propPatch = new PropPatch([MembershipNode::PROPERTY_ROLE => $roleToSet]);
		$this->node->propPatch($propPatch);

		$propPatch->commit();
		$this->assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		$this->assertEquals($statusCode, $result[MembershipNode::PROPERTY_ROLE]);
	}

	/**
	 * Cannot remove admin perms from last admin
	 */
	public function testUnsetSelfAdminWhenLastAdmin() {
		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn(true);

		$searchAdmin = new Search();
		$searchAdmin->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $searchAdmin)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]
			]);
		$this->handler
			->method('setGroupMemberInfo')
			->willReturn(false);

		$propPatch = new PropPatch([MembershipNode::PROPERTY_ROLE => Roles::DAV_ROLE_MEMBER]);
		$this->node->propPatch($propPatch);

		$propPatch->commit();
		$this->assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		$this->assertEquals(403, $result[MembershipNode::PROPERTY_ROLE]);
	}

	/**
	 * Cannot remove admin perms from last admin
	 */
	public function testUnsetdminWhenLastAdminAsSuperAdmin() {
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$node = $this->makeSelfNode(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->never())
			->method('setGroupMemberInfo');

		$searchAdmin = new Search();
		$searchAdmin->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->any())
			->method('getGroupMembers')
			->with(1, $searchAdmin)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]
			]);

		$propPatch = new PropPatch([MembershipNode::PROPERTY_ROLE => Roles::DAV_ROLE_MEMBER]);
		$node->propPatch($propPatch);

		$propPatch->commit();
		$this->assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		$this->assertEquals(403, $result[MembershipNode::PROPERTY_ROLE]);
	}

	/**
	 */
	public function testSetName() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->node->setName('x');
	}
}
