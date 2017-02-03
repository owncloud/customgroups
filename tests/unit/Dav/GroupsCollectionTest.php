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

use OCA\CustomGroups\Dav\GroupsCollection;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IUser;
use OCA\CustomGroups\Dav\GroupMembershipCollection;
use OCA\CustomGroups\Dav\MembershipHelper;
use OCP\IGroupManager;

/**
 * Class GroupsCollectionTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class GroupsCollectionTest extends \Test\TestCase {
	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var GroupsCollection
	 */
	private $collection;

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

		$this->helper = new MembershipHelper(
			$this->handler,
			$this->userSession,
			$this->userManager,
			$this->groupManager
		);
		$this->collection = new GroupsCollection($this->handler, $this->helper);
	}

	public function testBase() {
		$this->assertEquals('groups', $this->collection->getName());
		$this->assertNull($this->collection->getLastModified());
	}

	public function testListGroups() {
		$this->handler->expects($this->at(0))
			->method('getGroups')
			->will($this->returnValue([
				['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
				['group_id' => 2, 'uri' => 'group2', 'display_name' => 'Group Two'],
			]));

		$nodes = $this->collection->getChildren();
		$this->assertCount(2, $nodes);

		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[0]);
		$this->assertEquals('group1', $nodes[0]->getName());
		$this->assertInstanceOf(GroupMembershipCollection::class, $nodes[1]);
		$this->assertEquals('group2', $nodes[1]->getName());
	}

	public function testCreateGroup() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		$this->handler->expects($this->at(0))
			->method('createGroup')
			->with('group1', 'group1')
			->will($this->returnValue(1));
		$this->handler->expects($this->at(1))
			->method('addToGroup')
			->with('user1', 1, true);

		$this->collection->createDirectory('group1');
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testCreateGroupAlreadyExists() {
		$this->handler->expects($this->once())
			->method('createGroup')
			->with('group1', 'group1')
			->will($this->returnValue(null));
		$this->handler->expects($this->never())
			->method('addToGroup')
			->with('user1', 1, true);

		$this->collection->createDirectory('group1');
	}

	public function testGetGroup() {
		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->with('group1')
			->will($this->returnValue(['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One']));

		$groupNode = $this->collection->getChild('group1');
		$this->assertInstanceOf(GroupMembershipCollection::class, $groupNode);
		$this->assertEquals('group1', $groupNode->getName());
	}

	/**
	 * @expectedException \Sabre\DAV\Exception\NotFound
	 */
	public function testGetGroupNonExisting() {
		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->with('groupx')
			->will($this->returnValue(null));

		$this->collection->getChild('groupx');
	}

	public function testGroupExists() {
		$this->handler->expects($this->any())
			->method('getGroupByUri')
			->will($this->returnValueMap([
				['group1', ['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One']],
				['group2', null],
			]));

		$this->assertTrue($this->collection->childExists('group1'));
		$this->assertFalse($this->collection->childExists('group2'));
	}

	/**
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testSetName() {
		$this->collection->setName('x');
	}

	/**
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testDelete() {
		$this->collection->delete();
	}

	/**
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testCreateFile() {
		$this->collection->createFile('somefile.txt');
	}
}
