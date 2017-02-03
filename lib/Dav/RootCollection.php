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

namespace OCA\CustomGroups\Dav;

use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\SimpleCollection;

/**
 * Root collection for the custom groups and members
 */
class RootCollection extends SimpleCollection {
	/**
	 * Constructor
	 *
	 * @param MembershipHelper $helper membership helper
	 */
	public function __construct(
		CustomGroupsDatabaseHandler $groupsHandler,
		MembershipHelper $helper
	) {
		$children = [
			new GroupsCollection(
				$groupsHandler,
				$helper
			),
			new UsersCollection(
				$groupsHandler,
				$helper
			),
		];
		parent::__construct('customgroups', $children);
	}
}
