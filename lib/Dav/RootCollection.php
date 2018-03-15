<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 * @author Piotr Mrowczynski <piotr@owncloud.com>
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

use OCA\CustomGroups\CustomGroupsBackend;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\CustomGroupsManager;
use Sabre\DAV\SimpleCollection;
use OCA\CustomGroups\Service\Helper;
use OCP\IGroupManager;

/**
 * Root collection for the custom groups and members
 */
class RootCollection extends SimpleCollection {
	/**
	 * Constructor
	 *
	 * @param IGroupManager $groupManager group manager
	 * @param CustomGroupsManager $customGroupsManager custom groups manager
	 * @param CustomGroupsBackend $groupsBackend
	 * @param CustomGroupsDatabaseHandler $groupsHandler groups database handler
	 * @param Helper $helper membership helper
	 * @throws \Sabre\DAV\Exception
	 */
	public function __construct(
		IGroupManager $groupManager,
		CustomGroupsManager $customGroupsManager,
		CustomGroupsBackend $groupsBackend,
		CustomGroupsDatabaseHandler $groupsHandler,
		Helper $helper
	) {
		$children = [
			new GroupsCollection(
				$customGroupsManager,
				$groupsHandler,
				$helper
			),
			new UsersCollection(
				$customGroupsManager,
				$groupsHandler,
				$helper
			),
		];
		parent::__construct('customgroups', $children);
	}
}
