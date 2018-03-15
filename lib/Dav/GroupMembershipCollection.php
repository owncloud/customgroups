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

use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\CustomGroupsManager;
use Sabre\DAV\Exception\PreconditionFailed;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\Forbidden;
use OCA\CustomGroups\Search;
use OCA\CustomGroups\Service\Helper;

/**
 * Group memberships collection for a given group
 */
class GroupMembershipCollection implements \Sabre\DAV\ICollection, \Sabre\DAV\IProperties {
	const NS_OWNCLOUD = 'http://owncloud.org/ns';

	const PROPERTY_GROUP_ID = '{http://owncloud.org/ns}group-id';
	const PROPERTY_DISPLAY_NAME = '{http://owncloud.org/ns}display-name';
	const PROPERTY_ROLE = '{http://owncloud.org/ns}role';

	/**
	 * Custom groups handler
	 *
	 * @var CustomGroupsDatabaseHandler
	 */
	private $groupsHandler;

	/**
	 * Membership helper
	 *
	 * @var Helper
	 */
	private $helper;

	/**
	 * Custom groups manager
	 *
	 * @var CustomGroupsManager
	 */
	private $manager;

	/**
	 * Group information
	 *
	 * @var array
	 */
	private $groupInfo;

	/**
	 * Constructor
	 *
	 * @param array $groupInfo group info
	 * @param CustomGroupsManager $manager custom group manager
	 * @param CustomGroupsDatabaseHandler $groupsHandler custom groups handler
	 * @param Helper $helper membership helper
	 */
	public function __construct(
		array $groupInfo,
		CustomGroupsManager $manager,
		CustomGroupsDatabaseHandler $groupsHandler,
		Helper $helper
	) {
		$this->manager = $manager;
		$this->groupsHandler = $groupsHandler;
		$this->groupInfo = $groupInfo;
		$this->helper = $helper;
	}

	/**
	 * Deletes the group
	 *
	 * @throws Forbidden if no permisson to delete this group
	 */
	public function delete() {
		$groupId = $this->groupInfo['group_id'];
		if (!$this->helper->isUserAdmin($groupId)) {
			throw new Forbidden("No permission to delete group \"$groupId\"");
		}

		$this->manager->deleteGroup($this->groupInfo['uri']);
	}

	/**
	 * Returns the name of the node.
	 *
	 * @return string
	 */
	public function getName() {
		return (string)$this->groupInfo['uri'];
	}

	/**
	 * Not supported
	 *
	 * @param string $name the new name
	 * @throws MethodNotAllowed not supported
	 */
	public function setName($name) {
		throw new MethodNotAllowed();
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
	 * Updates properties on this node.
	 *
	 * @param PropPatch $propPatch PropPatch request
	 */
	public function propPatch(PropPatch $propPatch) {
		$propPatch->handle(self::PROPERTY_DISPLAY_NAME, [$this, 'updateDisplayName']);
	}

	/**
	 * Returns a list of properties for this node.
	 *
	 * @param array|null $properties requested properties or null for all
	 * @return array property values
	 */
	public function getProperties($properties) {
		$result = [];
		if ($properties === null || in_array(self::PROPERTY_DISPLAY_NAME, $properties)) {
			$result[self::PROPERTY_DISPLAY_NAME] = $this->groupInfo['display_name'];
		}
		if ($properties === null || in_array(self::PROPERTY_GROUP_ID, $properties)) {
			$result[self::PROPERTY_GROUP_ID] = $this->groupInfo['group_id'];
		}
		if ($properties === null || in_array(self::PROPERTY_ROLE, $properties)) {
			// role is only set if the group info was queried from a specific user
			if (isset($this->groupInfo['role'])) {
				$result[self::PROPERTY_ROLE] = Roles::backendToDav($this->groupInfo['role']);
			}
		}
		return $result;
	}

	/**
	 * Adds a new member to this group
	 *
	 * @param string $userId user id to add
	 * @param resource|string $data unused
	 * @throws Forbidden
	 * @throws PreconditionFailed
	 */
	public function createFile($userId, $data = null) {
		$groupId = $this->groupInfo['group_id'];
		if (!$this->helper->isUserAdmin($groupId)) {
			throw new Forbidden("No permission to add members to group \"$groupId\"");
		}

		// check if the user name actually exists
		$user = $this->helper->getUser($userId);
		// not existing user or mismatch user casing
		if (is_null($user) || $userId !== $user->getUID()) {
			throw new PreconditionFailed("The user \"$userId\" does not exist");
		}

		if (!$this->helper->canAddMember($userId)) {
			throw new Forbidden("Cannot add member \"$userId\" to group \"$groupId\"");
		}

		if (!$this->manager->addUser($this->groupInfo['uri'], $userId)) {
			throw new PreconditionFailed("The user \"$userId\" is already member of this group");
		}
	}

	/**
	 * Not supported
	 *
	 * @param string $name name
	 * @throws MethodNotAllowed not supported
	 */
	public function createDirectory($name) {
		throw new MethodNotAllowed('Cannot create collections');
	}

	/**
	 * Returns a membership node
	 *
	 * @param string $userId user id
	 * @return MembershipNode membership node
	 * @throws NotFound if the given user has no membership in this group
	 * @throws Forbidden if the current user has insufficient permissions
	 */
	public function getChild($userId) {
		$groupId = $this->groupInfo['group_id'];
		if (!$this->helper->isUserMember($groupId) && !$this->helper->isUserAdmin($groupId)) {
			throw new Forbidden("No permission to list members of group \"$groupId\"");
		}
		$memberInfo = $this->groupsHandler->getGroupMemberInfo($groupId, $userId);
		if (is_null($memberInfo)) {
			throw new NotFound(
				"User with id \"$userId\" is not member of group with uri \"$groupId\""
			);
		}
		return $this->createCustomGroupMemberNode($memberInfo);
	}

	/**
	 * Returns a list of all memberships
	 *
	 * @return MembershipNode[] list of memberships
	 * @throws Forbidden if the current user has insufficient permissions
	 */
	public function getChildren() {
		return $this->search();
	}

	public function search(Search $search = null) {
		$groupId = $this->groupInfo['group_id'];
		if (!$this->helper->isUserMember($groupId)
			&& !$this->helper->isUserAdmin($groupId)) {
			throw new Forbidden("No permission to list members of group \"$groupId\"");
		}
		$members = $this->groupsHandler->getGroupMembers($groupId, $search);
		return array_map(function ($memberInfo) {
			return $this->createCustomGroupMemberNode($memberInfo);
		}, $members);
	}

	/**
	 * Returns whether a user has a membership in this group.
	 *
	 * @param string $userId user id
	 * @return boolean true if the user has a membership, false otherwise
	 * @throws Forbidden if the current user has insufficient permissions
	 */
	public function childExists($userId) {
		$groupId = $this->groupInfo['group_id'];
		if (!$this->helper->isUserMember($groupId) && !$this->helper->isUserAdmin($groupId)) {
			throw new Forbidden("No permission to list members of group \"$groupId\"");
		}
		return $this->groupsHandler->inGroup($userId, $groupId);
	}

	/**
	 * Update the display name.
	 * Returns 403 status code if the current user has insufficient permissions.
	 *
	 * @param string $displayName display name to set
	 * @return boolean|int true or status code
	 */
	public function updateDisplayName($displayName) {
		if (!$this->helper->isUserAdmin($this->groupInfo['group_id'])) {
			return 403;
		}

		if ($this->groupInfo['display_name'] !== $displayName && !$this->helper->isGroupDisplayNameAvailable($displayName)) {
			return 409;
		}

		$result = $this->manager->updateGroup($this->groupInfo['uri'], $displayName);

		$this->groupInfo['display_name'] = $displayName;

		return $result;
	}

	/**
	 * Creates a membership node based on the given membership info.
	 *
	 * @param array $memberInfo membership info
	 * @return MembershipNode membership node
	 */
	private function createCustomGroupMemberNode(array $memberInfo) {
		return new MembershipNode(
			$memberInfo,
			$this->groupInfo,
			$this->manager,
			$this->groupsHandler,
			$this->helper
		);
	}
}
