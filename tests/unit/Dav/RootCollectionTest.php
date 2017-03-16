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

use OCA\CustomGroups\Dav\RootCollection;
use OCA\CustomGroups\Dav\UsersCollection;
use OCA\CustomGroups\Dav\GroupsCollection;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\Service\MembershipHelper;

/**
 * Class RootCollectionTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class RootCollectionTest extends \Test\TestCase {

	public function setUp() {
		parent::setUp();
		$handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$helper = $this->createMock(MembershipHelper::class);

		$this->collection = new RootCollection(
			$handler,
			$helper
		);
	}

	public function testGetGroups() {
		$groups = $this->collection->getChild('groups');
		$this->assertInstanceOf(GroupsCollection::class, $groups);
	}

	public function testGetUsers() {
		$users = $this->collection->getChild('users');
		$this->assertInstanceOf(UsersCollection::class, $users);
	}

	/**
	 * @expectedException Sabre\DAV\Exception\NotFound
	 */
	public function testGetNonExisting() {
		$this->collection->getChild('somethingelse');
	}
}
