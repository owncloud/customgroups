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
use OCA\CustomGroups\Service\GuestIntegrationHelper;
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
	public const USER = 'user1';

	/**
	 * @var UsersCollection
	 */
	private $collection;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	public function setUp(): void {
		parent::setUp();
		$handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$handler->expects($this->never())->method('getGroup');
		$userSession = $this->createMock(IUserSession::class);
		$userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::USER);
		$userSession->method('getUser')->willReturn($user);

		$config = $this->createMock(IConfig::class);
		$this->guestIntegrationHelper = $this->createMock(GuestIntegrationHelper::class);

		$helper = new MembershipHelper(
			$handler,
			$userSession,
			$userManager,
			$this->groupManager,
			$this->createMock(IManager::class),
			$this->createMock(IURLGenerator::class),
			$config,
			$this->guestIntegrationHelper
		);

		$this->collection = new UsersCollection(
			$this->createMock(IGroupManager::class),
			$handler,
			$helper,
			$config
		);
	}

	public function testBase(): void {
		$this->assertEquals('users', $this->collection->getName());
		$this->assertNull($this->collection->getLastModified());
	}

	/**
	 */
	public function testListUsers(): void {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->getChildren();
	}

	/**
	 */
	public function testCreateUser(): void {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->createDirectory('user1');
	}

	public function testGetCurrentUser(): void {
		$membershipCollection = $this->collection->getChild(self::USER);
		$this->assertInstanceOf(GroupsCollection::class, $membershipCollection);
		$this->assertEquals(self::USER, $membershipCollection->getName());
	}

	/**
	 */
	public function testGetAnotherUser(): void {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->collection->getChild('another');
	}

	public function testGetAnotherUserAsAdmin(): void {
		$this->groupManager->method('isAdmin')->with(self::USER)->willReturn(true);
		$membershipCollection = $this->collection->getChild('another');
		$this->assertInstanceOf(GroupsCollection::class, $membershipCollection);
		$this->assertEquals('another', $membershipCollection->getName());
	}

	public function testUserExistsCurrent(): void {
		$this->assertTrue($this->collection->childExists(self::USER));
	}

	public function testUserExistsAnother(): void {
		$this->assertFalse($this->collection->childExists('another'));
	}

	/**
	 */
	public function testSetName(): void {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->setName('x');
	}

	/**
	 */
	public function testDelete(): void {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->delete();
	}

	/**
	 */
	public function testCreateFile(): void {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->createFile('somefile.txt');
	}
}
