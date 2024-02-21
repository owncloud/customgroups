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
use OCA\CustomGroups\Service\GuestIntegrationHelper;
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
use OCA\CustomGroups\Dav\Roles;
use OCP\IGroup;

/**
 * Class GroupMembershipCollectionTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class GroupMembershipCollectionTest extends \Test\TestCase {
	public const CURRENT_USER = 'currentuser';
	public const NODE_USER = 'nodeuser';

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
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var IUser
	 */
	private $currentUser;

	/**
	 * @var IUser
	 */
	private $nodeUser;

	public function setUp(): void {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$userSession = $this->createMock(IUserSession::class);

		// currently logged in user
		$this->currentUser = $this->createMock(IUser::class);
		$this->currentUser->method('getUID')->willReturn(self::CURRENT_USER);
		$userSession->method('getUser')->willReturn($this->currentUser);

		$this->nodeUser = $this->createMock(IUser::class);
		$this->nodeUser->method('getUID')->willReturn(self::NODE_USER);
		$userManager->method('get')->willReturnMap(
			[
				[self::NODE_USER, false, $this->nodeUser],
				[\strtoupper(self::NODE_USER), false, $this->nodeUser],
				[self::CURRENT_USER, false, $this->currentUser],
			]
		);

		$this->config = $this->createMock(IConfig::class);
		$this->guestIntegrationHelper = $this->createMock(GuestIntegrationHelper::class);
		$this->helper = $this->getMockBuilder(MembershipHelper::class)
			->setMethods(['notifyUser', 'isGroupDisplayNameAvailable'])
			->setConstructorArgs([
				$this->handler,
				$userSession,
				$userManager,
				$this->groupManager,
				$this->createMock(IManager::class),
				$this->createMock(IURLGenerator::class),
				$this->config,
				$this->guestIntegrationHelper
			])
			->getMock();

		$this->node = new GroupMembershipCollection(
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One', 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
			$this->groupManager,
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
		$this->handler->expects($this->any())
			->method('getGroupMemberInfo')
			->with(1, self::CURRENT_USER)
			->willReturn($memberInfo);
	}

	private function setCurrentUserSuperAdmin($isSuperAdmin): void {
		$this->groupManager->expects($this->any())
			->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn($isSuperAdmin);
	}

	public function testBase(): void {
		self::assertEquals('group1', $this->node->getName());
		self::assertNull($this->node->getLastModified());
	}

	public function testDeleteAsAdmin(): void {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]);
		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn(true);

		$group = $this->createMock(IGroup::class);
		$group->expects($this->once())
			->method('delete');

		$this->groupManager->expects($this->once())
			->method('get')
			->with('customgroup_group1')
			->willReturn($group);

		$called = [];
		\OC::$server->getEventDispatcher()->addListener('\OCA\CustomGroups::deleteGroup', function ($event) use (&$called) {
			$called[] = '\OCA\CustomGroups::deleteGroup';
			\array_push($called, $event);
		});

		$this->node->delete();

		self::assertSame('\OCA\CustomGroups::deleteGroup', $called[0]);
		self::assertInstanceOf(GenericEvent::class, $called[1]);
		self::assertArrayHasKey('groupName', $called[1]);
		self::assertEquals('Group One', $called[1]->getArgument('groupName'));
		self::assertArrayHasKey('groupId', $called[1]);
		self::assertEquals(1, $called[1]->getArgument('groupId'));
	}

	/**
	 */
	public function testDeleteAsNonAdmin(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]);
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(true);
		$this->handler->method('getGroup')
			->willReturn(['display_name' => 'group1']);

		$this->handler->expects($this->never())
			->method('deleteGroup');

		$this->node->delete();
	}

	/**
	 */
	public function testDeleteAsNonMember(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(null);
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->handler->expects($this->never())
			->method('deleteGroup');

		$this->node->delete();
	}

	public function testGetProperties(): void {
		$props = $this->node->getProperties(null);
		self::assertEquals('Group One', $props[GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
		$props = $this->node->getProperties([GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
		self::assertEquals('Group One', $props[GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
	}

	public function adminSetFlagProvider(): array {
		return [
			// admin can change display name
			[false, Roles::BACKEND_ROLE_ADMIN, 200, true],
			// non-admin cannot change anything
			[false, Roles::BACKEND_ROLE_MEMBER, 403, false],
			// non-member cannot change anything
			[false, null, 403, false],
			// super-admin non-member can change anything
			[false, Roles::BACKEND_ROLE_ADMIN, 200, true],
		];
	}

	/**
	 * @dataProvider adminSetFlagProvider
	 */
	public function testSetProperties($isSuperAdmin, $currentUserRole, $statusCode, $called): void {
		$this->setCurrentUserSuperAdmin($isSuperAdmin);
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn($isSuperAdmin);

		if ($currentUserRole !== null) {
			$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => $currentUserRole]);
		} else {
			$this->setCurrentUserMemberInfo(null);
		}

		$this->helper
			->method('isGroupDisplayNameAvailable')
			->willReturn(true);

		if ($called) {
			$calledEvent = [];
			\OC::$server->getEventDispatcher()->addListener('\OCA\CustomGroups::updateGroupName', function ($event) use (&$calledEvent) {
				$calledEvent[] = '\OCA\CustomGroups::updateGroupName';
				\array_push($calledEvent, $event);
			});
			$this->handler->expects($this->once())
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
		self::assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		if (isset($calledEvent)) {
			self::assertSame('\OCA\CustomGroups::updateGroupName', $calledEvent[0]);
			self::assertInstanceOf(GenericEvent::class, $calledEvent[1]);
			self::assertArrayHasKey('oldGroupName', $calledEvent[1]);
			self::assertEquals('Group One', $calledEvent[1]->getArgument('oldGroupName'));
			self::assertArrayHasKey('newGroupName', $calledEvent[1]);
			self::assertEquals('Group Renamed', $calledEvent[1]->getArgument('newGroupName'));
			self::assertArrayHasKey('groupId', $calledEvent[1]);
			self::assertEquals(1, $calledEvent[1]->getArgument('groupId'));
		}

		self::assertEquals($statusCode, $result[GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
	}

	public function testSetDisplayNameNoDuplicates(): void {
		$this->setCurrentUserSuperAdmin(true);
		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn(true);

		$this->helper->expects($this->once())
			->method('isGroupDisplayNameAvailable')
			->willReturn(false);

		$this->handler->expects($this->never())
			->method('updateGroup');

		$propPatch = new PropPatch([GroupMembershipCollection::PROPERTY_DISPLAY_NAME => 'Group Renamed']);
		$this->node->propPatch($propPatch);

		$propPatch->commit();
		self::assertEmpty($propPatch->getRemainingMutations());
		$result = $propPatch->getResult();
		self::assertEquals(409, $result[GroupMembershipCollection::PROPERTY_DISPLAY_NAME]);
	}

	public function rolesProvider(): array {
		return [
			[false, ['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]],
			[false, ['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]],
			[true, null],
		];
	}

	public function adminProvider(): array {
		return [
			[false, ['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]],
			[true, null],
		];
	}

	/**
	 * @dataProvider adminProvider
	 */
	public function testAddMemberAsAdmin($isSuperAdmin, $currentMemberInfo): void {
		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn($isSuperAdmin);

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

		$called = [];
		\OC::$server->getEventDispatcher()->addListener('\OCA\CustomGroups::addUserToGroup', function ($event) use (&$called) {
			$called[] = '\OCA\CustomGroups::addUserToGroup';
			\array_push($called, $event);
		});

		$this->node->createFile(self::NODE_USER);

		self::assertSame('\OCA\CustomGroups::addUserToGroup', $called[0]);
		self::assertInstanceOf(GenericEvent::class, $called[1]);
		self::assertArrayHasKey('groupName', $called[1]);
		self::assertEquals('Group One', $called[1]->getArgument('groupName'));
		self::assertArrayHasKey('user', $called[1]);
		self::assertEquals('nodeuser', $called[1]->getArgument('user'));
		self::assertArrayHasKey('groupId', $called[1]);
		self::assertEquals(1, $called[1]->getArgument('groupId'));
	}

	/**
	 * @dataProvider adminProvider
	 */
	public function testAddMemberAsAdminFails($isSuperAdmin, $currentMemberInfo): void {
		$this->expectException(\Sabre\DAV\Exception\PreconditionFailed::class);

		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn($isSuperAdmin);

		$this->handler->expects($this->once())
			->method('addToGroup')
			->with(self::NODE_USER, 1, false)
			->willReturn(false);

		$this->node->createFile(self::NODE_USER);
	}

	/**
	 * @dataProvider adminProvider
	 */
	public function testAddNonExistingMemberAsAdmin($isSuperAdmin, $currentMemberInfo): void {
		$this->expectException(\Sabre\DAV\Exception\PreconditionFailed::class);

		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn($isSuperAdmin);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile('userunexist');
	}

	/**
	 * @dataProvider adminProvider
	 */
	public function testAddNonExistingMemberMismatchCaseAsAdmin($isSuperAdmin, $currentMemberInfo): void {
		$this->expectException(\Sabre\DAV\Exception\PreconditionFailed::class);

		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn($isSuperAdmin);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile('USER2');
	}

	/**
	 */
	public function testAddMemberAsNonAdmin(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]);

		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile(self::NODE_USER);
	}

	/**
	 */
	public function testAddMemberAsNonMember(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(null);

		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile(self::NODE_USER);
	}

	/**
	 */
	public function testAddMemberWithShareToMemberRestrictionAndNoCommonGroup(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]);
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->config->method('getAppValue')
			->with('core', 'shareapi_only_share_with_group_members', 'no')
			->willReturn('yes');

		$this->groupManager->method('getUserGroupIds')
			->willReturnMap([
				[$this->currentUser, null, ['group1', 'group2']],
				[$this->nodeUser, null, ['group3', 'group4']],
			]);

		$this->handler->expects($this->never())
			->method('addToGroup');

		$this->node->createFile(self::NODE_USER);
	}

	public function testAddMemberWithShareToMemberRestrictionAndCommonGroup(): void {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]);
		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn(true);

		$this->config->method('getAppValue')
			->with('core', 'shareapi_only_share_with_group_members', 'no')
			->willReturn('yes');

		$this->groupManager->method('getUserGroupIds')
			->willReturnMap([
				[$this->currentUser, null, ['group1', 'group2']],
				[$this->nodeUser, null, ['group1', 'group4']],
			]);

		$this->handler->expects($this->once())
			->method('addToGroup')
			->with(self::NODE_USER, 1, false)
			->willReturn(true);

		$this->node->createFile(self::NODE_USER);
	}

	public function testIsMember(): void {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]);
		$this->handler->expects($this->any())
			->method('inGroup')
			->willReturnMap([
				[self::NODE_USER, 1, true],
				['user3', 1, false],
			]);

		self::assertTrue($this->node->childExists(self::NODE_USER));
		self::assertFalse($this->node->childExists('user3'));
	}

	/**
	 */
	public function testIsMemberAsNonMember(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(null);
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->node->childExists(self::NODE_USER);
	}

	public function testIsMemberAsNonMemberButSuperAdmin(): void {
		$this->setCurrentUserSuperAdmin(true);
		$this->setCurrentUserMemberInfo(null);

		$this->handler->expects($this->any())
			->method('inGroup')
			->willReturnMap([
				[self::NODE_USER, 1, true],
				['user3', 1, false],
			]);

		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		self::assertTrue($this->node->childExists(self::NODE_USER));
		self::assertFalse($this->node->childExists('user3'));
	}

	/**
	 * @dataProvider rolesProvider
	 */
	public function testGetMember($isSuperAdmin, $currentMemberInfo): void {
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$membershipsMap = [
			[1, self::NODE_USER, ['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]],
		];
		if ($currentMemberInfo !== null) {
			$membershipsMap[] = [1, self::CURRENT_USER, $currentMemberInfo];
		}

		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn(true);

		$this->handler
			->method('getGroupMemberInfo')
			->willReturnMap($membershipsMap);

		$memberInfo = $this->node->getChild(self::NODE_USER);

		self::assertInstanceOf(MembershipNode::class, $memberInfo);
		self::assertEquals(self::NODE_USER, $memberInfo->getName());
	}

	/**
	 */
	public function testGetMemberAsNonMember(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(null);
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->node->getChild(self::NODE_USER);
	}

	/**
	 * @dataProvider rolesProvider
	 */
	public function testGetMembers($isSuperAdmin, $currentMemberInfo): void {
		$this->setCurrentUserMemberInfo($currentMemberInfo);
		$this->setCurrentUserSuperAdmin($isSuperAdmin);

		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn($isSuperAdmin);

		$this->handler
			->method('getGroupMembers')
			->with(1, null)
			->willReturn([
				['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
				['group_id' => 1, 'user_id' => 'user3', 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER],
			]);

		$memberInfos = $this->node->getChildren();

		self::assertCount(2, $memberInfos);
		self::assertInstanceOf(MembershipNode::class, $memberInfos[0]);
		self::assertEquals(self::NODE_USER, $memberInfos[0]->getName());
		self::assertInstanceOf(MembershipNode::class, $memberInfos[1]);
		self::assertEquals('user3', $memberInfos[1]->getName());
	}

	/**
	 * @dataProvider rolesProvider
	 */
	public function testSearchMembers($isSuperAdmin, $currentMemberInfo): void {
		$search = new Search('us', 16, 256);

		$this->config->method('getSystemValue')
			->willReturn(false);
		$this->groupManager->method('isAdmin')
			->willReturn($isSuperAdmin);

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

		self::assertCount(2, $memberInfos);
		self::assertInstanceOf(MembershipNode::class, $memberInfos[0]);
		self::assertEquals(self::NODE_USER, $memberInfos[0]->getName());
		self::assertInstanceOf(MembershipNode::class, $memberInfos[1]);
		self::assertEquals('user3', $memberInfos[1]->getName());
	}

	/**
	 */
	public function testGetMembersAsNonMember(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->setCurrentUserMemberInfo(null);
		$this->config->method('getSystemValue')
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->willReturn(false);

		$this->node->getChildren();
	}

	/**
	 */
	public function testSetName(): void {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->node->setName('x');
	}

	/**
	 */
	public function testCreateDirectory(): void {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->node->createDirectory('somedir');
	}

	public function providesUpdateDisplayNameValidateException(): array {
		return [
			[''],
			[null],
			['a'],
			[' a'],
			['á'],
			[' áé'],
			['12345678911234567892123456789312345678941234567895123456789612345']
		];
	}

	/**
	 * @dataProvider providesUpdateDisplayNameValidateException
	 * @param string $groupName
	 */
	public function testUpdateDisplayNameValidateException($groupName): void {
		$this->expectException(\OCA\CustomGroups\Exception\ValidationException::class);

		$this->node->updateDisplayName($groupName);
	}
}
