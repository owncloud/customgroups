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
use OCA\CustomGroups\Dav\MembershipHelper;
use OCP\IGroupManager;
use OCA\CustomGroups\Dav\Roles;
use OCA\CustomGroups\Search;

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

	public function setUp() {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->handler->expects($this->never())->method('getGroup');
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		// currently logged in user
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::CURRENT_USER);
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);

		$this->helper = new MembershipHelper(
			$this->handler,
			$this->userSession,
			$this->userManager,
			$this->groupManager
		);
		$this->node = new MembershipNode(
			['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
			self::NODE_USER,
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
			$this->handler,
			$this->helper
		);
		$this->assertEquals('group1', $node->getName());
	}

	public function testDeleteAsAdmin() {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]);
		$this->handler->expects($this->once())
			->method('removeFromGroup')
			->with(self::NODE_USER, 1)
			->willReturn(true);

		$this->node->delete();
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\PreconditionFailed
	 */
	public function testDeleteAsAdminFailed() {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN]);
		$this->handler->expects($this->once())
			->method('removeFromGroup')
			->with(self::NODE_USER, 1)
			->willReturn(false);

		$this->node->delete();
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testDeleteAsNonAdmin() {
		$this->setCurrentUserMemberInfo(['group_id' => 1, 'user_id' => self::CURRENT_USER, 'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER]);
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
			$this->groupManager
		);

		$memberInfo = ['group_id' => 1, 'user_id' => self::NODE_USER, 'role' => $role];
		$node = new MembershipNode(
			$memberInfo,
			self::NODE_USER,
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

		$this->handler->expects($this->once())
			->method('removeFromGroup')
			->with(self::NODE_USER, 1)
			->willReturn(true);

		$node->delete();
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testDeleteAsNonMember() {
		$this->setCurrentUserMemberInfo(null);
		$this->handler->expects($this->never())
			->method('removeFromGroup');

		$this->node->delete();
	}

	/**
	 * Super admin can delete any member
	 */
	public function testDeleteAsSuperAdmin() {
		$this->setCurrentUserMemberInfo(null);
		$this->groupManager->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn(true);

		$this->handler->expects($this->once())
			->method('removeFromGroup')
			->with(self::NODE_USER, 1)
			->willReturn(true);

		$this->node->delete();
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testDeleteSelfAsLastAdmin() {
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
	 * @expectedException \Sabre\DAV\Exception\Forbidden
	 */
	public function testDeleteLastAdminAsSuperAdmin() {
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
		} else {
			$this->handler->expects($this->never())
				->method('setGroupMemberInfo');
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
		$this->groupManager->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn(true);

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
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testSetName() {
		$this->node->setName('x');
	}
}
