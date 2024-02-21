<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
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

use OCA\CustomGroups\Dav\Roles;

/**
 * Class RolesTest
 *
 * @package OCA\CustomGroups\Tests\unit\Dav
 */
class RolesTest extends \Test\TestCase {
	public function testBackendToDavMappings() {
		self::assertEquals('member', Roles::backendToDav(0));
		self::assertEquals('admin', Roles::backendToDav(1));
	}

	public function testDavToBackendMappings() {
		self::assertEquals(0, Roles::davToBackend('member'));
		self::assertEquals(1, Roles::davToBackend('admin'));
	}
}
