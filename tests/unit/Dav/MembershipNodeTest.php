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
use OCA\CustomGroups\Service\GuestIntegrationHelper;
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
	public const CURRENT_USER = 'currentuser';
	public const NODE_USER = 'nodeuser';

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

	/** @var IConfig */
	private $config;

	public function setUp(): void {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = $this->createMock(IConfig::class);

		// currently logged in user
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::CURRENT_USER);
		$userSession
			->method('getUser')
			->willReturn($user);

		$this->guestIntegrationHelper = $this->createMock(GuestIntegrationHelper::class);
		$this->helper = $this->getMockBuilder(MembershipHelper::class)
			->setMethods(['notifyUserRoleChange', 'notifyUserRemoved', 'isTheOnlyAdmin'])
			->setConstructorArgs([
				$this->handler,
				$userSession,
				$this->userManager,
				$this->groupManager,
				$this->createMock(IManager::class),
				$this->createMock(IURLGenerator::class),
				$this->config,
				$this->guestIntegrationHelper
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
	private function setCurrentUserMemberInfo($memberInfo): void {
		$this->handler
			->method('getGroupMemberInfo')
			->with(1, self::CURRENT_USER)
			->willReturn($memberInfo);
	}

	public function testBase(): void {
		self::assertEquals(self::NODE_USER, $this->node->getName());
		self::assertNull($this->node->getLastModified());
	}

	public function testNodeName(): void {
		$node = new MembershipNode(
			['group_id' => 1, 'uri' => 'group1', 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
			'group1',
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
			$this->handler,
			$this->helper
		);
		self::assertEquals('group1', $node->getName());
	}

	public function testDeleteAsAdmin(): void {
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
				['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One']
			);

		$this->helper->expects($this->once())
			->method('isTheOnlyAdmin')
			->with(1, self::NODE_USER)
			->willReturn(false);

		$searchAdmins = new Search();
		$searchAdmins->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$this->handler
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

		self::assertSame('\OCA\CustomGroups::removeUserFromGroup', $called[0]);
		self::assertInstanceOf(GenericEvent::class, $called[1]);
		self::assertArrayHasKey('user_displayName', $called[1]);
		self::assertArrayHasKey('group_displayName', $called[1]);
		self::assertEquals('customGroups.removeUserFromGroup', $newCalled[0]);
		self::assertArrayHasKey('user', $newCalled[1]);
		self::assertEquals(self::NODE_USER, $newCalled[1]->getArgument('user'));
		self::assertArrayHasKey('groupName', $newCalled[1]);
		self::assertEquals('Group One', $newCalled[1]->getArgument('groupName'));
		self::assertArrayHasKey('groupId', $newCalled[1]);
		self::assertEquals(1, $newCalled[1]->getArgument('groupId'));
	}

	/**
	 */
	public function testDeleteAsAdminFailed(): void {
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
		$this->handler
			->method('getGroupMembers')
			->with(1, $searchAdmins)
			->willReturn([$memberInfo]);

		$this->node->delete();
	}

	/**
	 */
	public function testDeleteAsNonAdmin(): void {
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
	private function makeSelfNode($role): MembershipNode {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::NODE_USER);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$guestIntegrationHelper = $this->createMock(GuestIntegrationHelper::class);

		$helper = new MembershipHelper(
			$this->handler,
			$userSession,
			$this->userManager,
			$this->groupManager,
			$this->createMock(IManager::class),
			$this->createMock(IURLGenerator::class),
			$this->config,
			$guestIntegrationHelper
		);

		$memberInfo = ['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => $role];
		$node = new MembershipNode(
			$memberInfo,
			self::NODE_USER,
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
			$this->handler,
			$helper
		);
		$this->handler
			->method('getGroupMemberInfo')
			->with(1, self::NODE_USER)
			->willReturn($memberInfo);
		return $node;
	}

	public function testDeleteSelfAsNonAdmin(): void {
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
		self::assertEquals('\OCA\CustomGroups::leaveFromGroup', $deprecatedLeaveFromGroup[0]);
		self::assertInstanceOf(GenericEvent::class, $deprecatedLeaveFromGroup[1]);
		self::assertArrayHasKey('userId', $deprecatedLeaveFromGroup[1]);
		self::assertEquals('nodeuser', $deprecatedLeaveFromGroup[1]->getArgument('userId'));
		self::assertArrayHasKey('groupName', $deprecatedLeaveFromGroup[1]);
		self::assertEquals('Group One', $deprecatedLeaveFromGroup[1]->getArgument('groupName'));
		self::assertEquals('customGroups.leaveFromGroup', $newLeaveFromGroup[0]);
		self::assertInstanceOf(GenericEvent::class, $newLeaveFromGroup[1]);
		self::assertArrayHasKey('user', $newLeaveFromGroup[1]);
		self::assertEquals('nodeuser', $newLeaveFromGroup[1]->getArgument('user'));
		self::assertArrayHasKey('groupName', $newLeaveFromGroup[1]);
		self::assertEquals('Group One', $newLeaveFromGroup[1]->getArgument('groupName'));
		self::assertArrayHasKey('groupId', $newLeaveFromGroup[1]);
		self::assertEquals(1, $newLeaveFromGroup[1]->getArgument('groupId'));
	}

	/**
	 */
	public function testDeleteAsNonMember(): void {
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
	public function testDeleteAsSuperAdmin(): void {
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
		$this->handler
			->method('getGroupMembers')
			->with(1, $searchAdmins)
			->willReturn([['user_id' => 'adminuser']]);

		$this->node->delete();
	}

	/**
	 */
	public function testDeleteSelfAsLastAdmin(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$node = $this->makeSelfNode(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$searchAdmin = new Search();
		$searchAdmin->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler
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
	public function testDeleteLastAdminAsSuperAdmin(): void {
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

		$this->handler
			->method('getGroupMembers')
			->with(1, $searchAdmin)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]
			]);

		$this->handler->expects($this->never())
			->method('removeFromGroup');

		$node->delete();
	}

	public function propsProvider(): array {
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
	public function testGetProperties($propName, $propValue, $roleValue = 0): void {
		$node = new MembershipNode(
			['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => $roleValue, 'uri' => 'group1'],
			self::NODE_USER,
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
			$this->handler,
			$this->helper
		);

		$props = $node->getProperties(null);
		self::assertSame($propValue, $props[$propName]);
		$props = $node->getProperties([$propName]);
		self::assertSame($propValue, $props[$propName]);
	}

	public function adminSetFlagProvider(): array {
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
	public function testSetProperties($isSuperAdmin, $currentUserRole, $roleToSet, $statusCode, $called): void {
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

		$this->handler
			->method('getGroupMembers')
			->with(1, $searchAdmin)
			->willReturn([
				['group_id' => 1, 'user_id' => 'someotheradmin', 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
			]);

		$propPatch = new PropPatch([MembershipNode::PROPERTY_ROLE => $roleToSet]);
		$this->node->propPatch($propPatch);

		$propPatch->commit();
		self::assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		self::assertEquals($statusCode, $result[MembershipNode::PROPERTY_ROLE]);
	}

	/**
	 * Cannot remove admin perms from last admin
	 */
	public function testUnsetSelfAdminWhenLastAdmin(): void {
		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn(true);

		$searchAdmin = new Search();
		$searchAdmin->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler
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
		self::assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		self::assertEquals(403, $result[MembershipNode::PROPERTY_ROLE]);
	}

	/**
	 * Cannot remove admin perms from last admin
	 */
	public function testUnsetAdminWhenLastAdminAsSuperAdmin(): void {
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$node = $this->makeSelfNode(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->never())
			->method('setGroupMemberInfo');

		$searchAdmin = new Search();
		$searchAdmin->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler
			->method('getGroupMembers')
			->with(1, $searchAdmin)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]
			]);

		$propPatch = new PropPatch([MembershipNode::PROPERTY_ROLE => Roles::DAV_ROLE_MEMBER]);
		$node->propPatch($propPatch);

		$propPatch->commit();
		self::assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		self::assertEquals(403, $result[MembershipNode::PROPERTY_ROLE]);
	}

	/**
	 */
	public function testSetName(): void {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->node->setName('x');
	}
}
