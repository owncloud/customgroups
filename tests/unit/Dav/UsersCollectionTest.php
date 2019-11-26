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

use OCA\CustomGroups\Dav\UsersCollection;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\IUser;
use OCA\CustomGroups\Service\MembershipHelper;
use OCP\IGroupManager;
use OCA\CustomGroups\Dav\GroupsCollection;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\IConfig;

/**
 * Class UsersCollectionTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class UsersCollectionTest extends \Test\TestCase {
	const USER = 'user1';

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var UsersCollection
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

	/** @var IConfig */
	private $config;

	public function setUp() {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->handler->expects($this->never())->method('getGroup');
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::USER);
		$this->userSession->method('getUser')->willReturn($user);

		$this->config = $this->createMock(IConfig::class);

		$this->helper = new MembershipHelper(
			$this->handler,
			$this->userSession,
			$this->userManager,
			$this->groupManager,
			$this->createMock(IManager::class),
			$this->createMock(IURLGenerator::class),
			$this->config
		);

		$this->collection = new UsersCollection(
			$this->createMock(IGroupManager::class),
			$this->handler,
			$this->helper,
			$this->config
		);
	}

	public function testBase() {
		$this->assertEquals('users', $this->collection->getName());
		$this->assertNull($this->collection->getLastModified());
	}

	/**
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testListUsers() {
		$this->collection->getChildren();
	}

	/**
	 * @expectedException Sabre\DAV\Exception\MethodNotAllowed
	 */
	public function testCreateUser() {
		$this->collection->createDirectory('user1');
	}

	public function testGetCurrentUser() {
		$membershipCollection = $this->collection->getChild(self::USER);
		$this->assertInstanceOf(GroupsCollection::class, $membershipCollection);
		$this->assertEquals(self::USER, $membershipCollection->getName());
	}

	/**
	 * @expectedException Sabre\DAV\Exception\Forbidden
	 */
	public function testGetAnotherUser() {
		$this->collection->getChild('another');
	}

	public function testGetAnotherUserAsAdmin() {
		$this->groupManager->method('isAdmin')->with(self::USER)->willReturn(true);
		$membershipCollection = $this->collection->getChild('another');
		$this->assertInstanceOf(GroupsCollection::class, $membershipCollection);
		$this->assertEquals('another', $membershipCollection->getName());
	}

	public function testUserExistsCurrent() {
		$this->assertTrue($this->collection->childExists(self::USER));
	}

	public function testUserExistsAnother() {
		$this->assertFalse($this->collection->childExists('another'));
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
