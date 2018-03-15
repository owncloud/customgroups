<?php
/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
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

namespace OCA\CustomGroups;

use OCA\CustomGroups\Dav\Roles;
use OCA\CustomGroups\Service\Helper;
use OCP\IGroupManager;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Custom Groups Manager
 *
 * Keeps custom groups app in sync with core
 */
class CustomGroupsManager {

	/**
	 * Custom groups backend
	 *
	 * @var CustomGroupsBackend
	 */
	private $groupsBackend;

	/**
	 * Custom groups handler
	 *
	 * @var CustomGroupsDatabaseHandler
	 */
	private $groupsHandler;

	/**
	 * Group manager
	 *
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * Membership helper
	 *
	 * @var Helper
	 */
	private $helper;

	/**
	 * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
	*/
	private $dispatcher;

	/**
	 * Membership helper
	 *
	 * @param Helper $helper custom groups helper
	 * @param CustomGroupsDatabaseHandler $groupsHandler custom groups handler
	 * @param CustomGroupsBackend $groupsBackend custom groups backend
	 * @param IGroupManager $groupManager group manager
	 */
	public function __construct(
		Helper $helper,
		CustomGroupsDatabaseHandler $groupsHandler,
		CustomGroupsBackend $groupsBackend,
		IGroupManager $groupManager
	) {
		$this->helper = $helper;
		$this->groupsHandler = $groupsHandler;
		$this->groupsBackend = $groupsBackend;
		$this->groupManager = $groupManager;

		$this->dispatcher = \OC::$server->getEventDispatcher();
	}

	/**
	 * Creates a group node with the given collection uri and display name
	 *
	 * @param string $uri group collection uri
	 * @param string $displayName group display name
	 * @return boolean|int true or status code
	 */
	public function createGroup($uri, $displayName) {
		$groupId = $this->groupsHandler->createGroup($uri, $displayName);
		if (is_null($groupId)) {
			return false;
		}

		// Add this group and user in core so it is available to other apps to use
		//$gid = $this->groupsBackend->formatGroupId($uri);
		//$group = $this->groupManager->createGroupFromBackend($gid, $this->groupsBackend);
		$userId = $this->helper->getUserId();
		//$user = $this->helper->getUser($userId);
		//$group->addUser($user);

		// add current user as admin
		$result = $this->groupsHandler->addToGroup($userId, $groupId, CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$event = new GenericEvent(null, ['groupName' => $uri, 'user' => $this->helper->getUserId()]);
		$this->dispatcher->dispatch('\OCA\CustomGroups::addGroupAndUser', $event);

		return $result;
	}


	/**
	 * Updates a group node with new display name
	 *
	 * @param string $uri group collection uri
	 * @param string $displayName group display name
	 *
	 * @return boolean|int true or status code
	 */
	public function updateGroup($uri, $displayName) {
		$groupInfo = $this->groupsHandler->getGroupBy('uri', $uri);

		$event = new GenericEvent(null, ['oldGroupName' => $groupInfo['display_name'],
			'newGroupName' => $displayName]);
		$this->dispatcher->dispatch('\OCA\CustomGroups::updateGroupName', $event);

		$result = $this->groupsHandler->updateGroup(
			$groupInfo['group_id'],
			$groupInfo['uri'],
			$displayName
		);

		// Update this group and user in core so it is available to other apps to use
		//$gid = $this->groupsBackend->formatGroupId($uri);
		//$this->groupManager->createGroupFromBackend($gid, $this->groupsBackend);

		return $result;
	}

	/**
	 * Deletes a group node with the given collection uri
	 *
	 * @param string $uri group collection uri
	 *
	 * @return boolean|int true or status code
	 */
	public function deleteGroup($uri) {
		$groupInfo = $this->groupsHandler->getGroupBy('uri', $uri);

		// Remove this group and user in core so it is not available to other apps to use
		//$gid = $this->groupsBackend->formatGroupId($uri);
		//$group = $this->groupManager->get($gid);
		//$group->delete();

		$result = $this->groupsHandler->deleteGroup($groupInfo['group_id']);

		$event = new GenericEvent(null, ['groupName' => $groupInfo['display_name']]);
		$this->dispatcher->dispatch('\OCA\CustomGroups::deleteGroup', $event);

		return $result;
	}

	/**
	 * Adds a new member to group
	 *
	 * @param string $uri uri of the group
	 * @param string $userId user id to add
	 *
	 * @return boolean|int true or status code
	 */
	public function addUser($uri, $userId) {
		$groupInfo = $this->groupsHandler->getGroupBy('uri', $uri);
		$groupId = $groupInfo['group_id'];

		if (!$this->groupsHandler->addToGroup($userId, $groupId, CustomGroupsDatabaseHandler::ROLE_MEMBER)) {
			return false;
		}

		// Add this user in core so it is available to other apps
		//$gid = $this->groupsBackend->formatGroupId($uri);
		//$group = $this->groupManager->get($gid);
		//$user = $this->helper->getUser($userId);
		//$group->addUser($user);

		$this->helper->notifyUser($userId, $groupInfo);
		$event = new GenericEvent(null, ['groupName' => $groupInfo['display_name'], 'user' => $userId]);
		$this->dispatcher->dispatch('\OCA\CustomGroups::addUserToGroup', $event);

		return true;
	}

	/**
	 * Removed a member from group
	 *
	 * @param string $uri uri of the group
	 * @param string $userId user id to add
	 *
	 * @return boolean|int true or status code
	 */
	public function updateUser($uri, $userId, $rolePropValue) {
		$groupInfo = $this->groupsHandler->getGroupBy('uri', $uri);

		if (!$this->groupsHandler->setGroupMemberInfo($groupInfo['group_id'], $userId, $rolePropValue)) {
			return false;
		}

		// Notify directly after internal custom group change, core does not need to be
		// aware of this app logic (admin/not-admin of custom group)
		$this->helper->notifyUserRoleChange($userId, $groupInfo, $rolePropValue);

		if($rolePropValue === Roles::BACKEND_ROLE_MEMBER) {
			$roleName = "Member";
		} elseif ($rolePropValue === Roles::BACKEND_ROLE_ADMIN) {
			$roleName = "Group owner";
		}

		$event = new GenericEvent(null, ['user' => $userId, 'groupName' => $groupInfo['display_name'], 'roleNumber' => $rolePropValue, 'roleDisaplayName' => $roleName]);
		$this->dispatcher->dispatch('\OCA\CustomGroups::changeRoleInGroup', $event);
		return true;
	}

	/**
	 * Removed a member from group
	 *
	 * @param string $uri uri of the group
	 * @param string $userId user id to add
	 *
	 * @return boolean|int true or status code
	 */
	public function removeUser($uri, $userId) {
		$groupInfo = $this->groupsHandler->getGroupBy('uri', $uri);

		$currentUserId = $this->helper->getUserId();
		$groupId = $groupInfo['group_id'];

		if (!$this->groupsHandler->removeFromGroup($userId, $groupId)) {
			return false;
		}

		//$user = $this->helper->getUser($userId);
		//$gid = $this->groupsBackend->formatGroupId($uri);
		//$this->groupManager->get($gid)->removeUser($user);

		if ($currentUserId !== $userId) {
			$this->helper->notifyUserRemoved($userId, $groupInfo);
			$event = new GenericEvent(null, ['user_displayName' => $userId, 'group_displayName' => $groupInfo['display_name']]);
			$this->dispatcher->dispatch('\OCA\CustomGroups::removeUserFromGroup', $event);
		}

		//Send dispatcher event if the removal is self
		if ($currentUserId === $userId) {
			$event = new GenericEvent(null, ['userId' => $userId, 'groupName' => $groupInfo['display_name']]);
			$this->dispatcher->dispatch('\OCA\CustomGroups::leaveFromGroup', $event);
		}

		return true;
	}
}
