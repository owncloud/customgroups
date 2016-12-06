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

use Sabre\DAV\ICollection;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\MethodNotAllowed;

/**
 * Collection of custom groups
 */
class GroupsCollection implements ICollection {

	/**
	 * Custom groups handler
	 *
	 * @var CustomGroupsDatabaseHandler
	 */
	private $groupsHandler;

	/**
	 * Membership helper
	 *
	 * @var MembershipHelper
	 */
	private $helper;

	/**
	 * Constructor
	 *
	 * @param CustomGroupsDatabaseHandler $groupsHandler custom groups handler
	 * @param MembershipHelper $helper helper
	 */
	public function __construct(
		CustomGroupsDatabaseHandler $groupsHandler,
		MembershipHelper $helper
	) {
		$this->groupsHandler = $groupsHandler;
		$this->helper = $helper;
	}

	/**
	 * Not supported
	 *
	 * @param string $name name
	 * @param resource|string $data unused
	 * @throws MethodNotAllowed not supported
	 */
	public function createFile($name, $data = null) {
		throw new MethodNotAllowed('Cannot create regular nodes');
	}

	/**
	 * Creates a new custom group
	 *
	 * @param string $name group URI
	 * @throws MethodNotAllowed if the group already exists
	 */
	public function createDirectory($name) {
		$groupId = $this->groupsHandler->createGroup($name, $name);
		if (is_null($groupId)) {
			throw new MethodNotAllowed("Group with uri \"$name\" already exists");
		}

		// add current user as admin
		$this->groupsHandler->addToGroup($this->helper->getUserId(), $groupId, true);
	}

	/**
	 * Returns the custom group node for the given URI.
	 *
	 * @param string $name group URI
	 * @return GroupMembershipCollection node
	 * @throws NotFound if the requested group does not exist
	 */
	public function getChild($name) {
		$group = $this->groupsHandler->getGroupByUri($name);
		if (is_null($group)) {
			throw new NotFound("Group with uri \"$name\" not found");
		}
		return $this->createMembershipsCollection($group);
	}

	/**
	 * Returns nodes for all existing custom groups.
	 *
	 * @return GroupMembershipCollection[] custom group nodes
	 */
	public function getChildren() {
		$allGroups = $this->groupsHandler->getGroups();
		return array_map(function ($groupInfo) {
			return $this->createMembershipsCollection($groupInfo);
		}, $allGroups);
	}

	/**
	 * Returns whether a custom group exists.
	 *
	 * @param string $name group URI
	 * @return boolean true if the group exists, false otherwise
	 */
	public function childExists($name) {
		return !is_null($this->groupsHandler->getGroupByUri($name));
	}

	/**
	 * Not supported
	 *
	 * @throws MethodNotAllowed not supported
	 */
	public function delete() {
		throw new MethodNotAllowed('Cannot delete this collection');
	}

	/**
	 * Returns the name of the node.
	 *
	 * This is used to generate the url.
	 *
	 * @return string node name
	 */
	public function getName() {
		return 'groups';
	}

	/**
	 * Not supported
	 *
	 * @param string $name name
	 * @throws MethodNotAllowed not supported
	 */
	public function setName($name) {
		throw new MethodNotAllowed('Cannot rename this collection');
	}

	/**
	 * Returns null
	 *
	 * @return int null
	 */
	public function getLastModified() {
		return null;
	}

	/**
	 * Creates a custom group node for the given group info.
	 *
	 * @param array $groupInfo group info
	 * @return GroupMembershipCollection node
	 */
	private function createMembershipsCollection(array $groupInfo) {
		return new GroupMembershipCollection(
			$groupInfo,
			$this->groupsHandler,
			$this->helper
		);
	}
}
