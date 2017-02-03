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

use OCA\CustomGroups\Dav\UserMembershipCollection;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IUser;
use Sabre\DAV\PropPatch;
use OCA\CustomGroups\Dav\MembershipNode;
use OCA\CustomGroups\Dav\MembershipHelper;
use OCP\IGroupManager;

/**
 * Class UserMembershipCollectionTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class UserMembershipCollectionTest extends \Test\TestCase {
	const CURRENT_USER = 'currentuser';

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var UserMembershipCollection
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

		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->will($this->returnValueMap([
				['group1', ['group_id' => 1]],
				['group2', ['group_id' => 2]],
				['group3', null],
			]));

		$this->helper = new MembershipHelper(
			$this->handler,
			$this->userSession,
			$this->userManager,
			$this->groupManager
		);
		$this->node = new UserMembershipCollection(
			self::CURRENT_USER,
			$this->handler,
			$this->helper
		);
	}

	public function testBase() {
		$this->assertEquals(self::CURRENT_USER, $this->node->getName());
		$this->assertNull($this->node->getLastModified());
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testDelete() {
		$this->node->delete();
	}

	public function testIsMember() {
		$this->handler->expects($this->any())
			->method('getGroupMemberInfo')
			->will($this->returnValueMap([
				[1, self::CURRENT_USER, [
					'group_id' => 1,
					'uri' => 'group1',
					'user_id' => self::CURRENT_USER,
					'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER
				]],
			]));

		$this->assertTrue($this->node->childExists('group1'));
		$this->assertFalse($this->node->childExists('group2'));
		$this->assertFalse($this->node->childExists('group3'));
	}

	public function testIsMemberAsNonMember() {
		$this->assertFalse($this->node->childExists('group1'));
	}

	public function testGetMember() {
		$this->handler->expects($this->any())
			->method('getGroupMemberInfo')
			->will($this->returnValueMap([
				[1, self::CURRENT_USER, [
					'group_id' => 1,
					'uri' => 'group1',
					'user_id' => self::CURRENT_USER,
					'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER
				]],
			]));

		$memberInfo = $this->node->getChild('group1');

		$this->assertInstanceOf(MembershipNode::class, $memberInfo);
		$this->assertEquals('group1', $memberInfo->getName());
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\NotFound
	 */
	public function testGetMemberAsNonMember() {
		$this->handler->expects($this->any())
			->method('getGroupMemberInfo')
			->will($this->returnValue([]));

		$this->node->getChild(self::CURRENT_USER);
	}

	public function testGetMembers() {
		$this->handler->expects($this->any())
			->method('getUserMemberships')
			->with(self::CURRENT_USER)
			->willReturn([[
				'group_id' => 1,
				'uri' => 'group1',
			   	'user_id' => self::CURRENT_USER,
				'role' => CustomGroupsDatabaseHandler::ROLE_ADMIN
			], [
				'group_id' => 2,
				'uri' => 'group2',
			   	'user_id' => self::CURRENT_USER,
				'role' => CustomGroupsDatabaseHandler::ROLE_MEMBER
			],
			]);

		$memberInfos = $this->node->getChildren();

		$this->assertCount(2, $memberInfos);
		$this->assertInstanceOf(MembershipNode::class, $memberInfos[0]);
		$this->assertEquals('group1', $memberInfos[0]->getName());
		$this->assertInstanceOf(MembershipNode::class, $memberInfos[1]);
		$this->assertEquals('group2', $memberInfos[1]->getName());
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
	public function testCreateFile() {
		$this->node->createFile('somedir');
	}

	/**
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testCreateDirectory() {
		$this->node->createDirectory('somedir');
	}
}
