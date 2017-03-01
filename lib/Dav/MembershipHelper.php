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
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\CustomGroups\Search;

/**
 * Membership helper
 *
 * Provides method related to the current user's membership and admin roles.
 */
class MembershipHelper {
	/**
	 * Custom groups handler
	 *
	 * @var CustomGroupsDatabaseHandler
	 */
	private $groupsHandler;

	/**
	 * User session
	 *
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * User manager
	 *
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * Group manager
	 *
	 * @var IGroupManager
	 */
	private $groupsManager;

	/**
	 * Membership info for the currently logged in user
	 *
	 * @var array
	 */
	private $userMemberInfo = [];

	/**
	 * Membership helper
	 *
	 * @param CustomGroupsDatabaseHandler $groupsHandler custom groups handler
	 * @param IUserSession $userSession user session
	 * @param IUserManager $userManager user manager
	 * @param IGroupManager $groupManager group manager
	 */
	public function __construct(
		CustomGroupsDatabaseHandler $groupsHandler,
		IUserSession $userSession,
		IUserManager $userManager,
		IGroupManager $groupManager
	) {
		$this->groupsHandler = $groupsHandler;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	/**
	 * Returns the currently logged in user id
	 *
	 * @return string user id
	 */
	public function getUserId() {
		return $this->userSession->getUser()->getUID();
	}

	/**
	 * Returns the user object for a given user id
	 *
	 * @param string $userId user id
	 * @return IUser|null user object or null if user does not exist
	 */
	public function getUser($userId) {
		return $this->userManager->get($userId);
	}

	/**
	 * Returns membership information for the current user
	 *
	 * @param int $groupId group id
	 * @return array membership information
	 */
	private function getUserMemberInfo($groupId) {
		if (!isset($this->userMemberInfo[$groupId])) {
			$userId = $this->getUserId();
			$this->userMemberInfo[$groupId] = $this->groupsHandler->getGroupMemberInfo($groupId, $userId);
		}
		return $this->userMemberInfo[$groupId];
	}

	/**
	 * Returns whether the current user can administrate this group
	 *
	 * @param int $groupId group id
	 * @return boolean true if the user can administrate, false otherwise
	 */
	public function isUserAdmin($groupId) {
		// ownCloud admin is always admin of any custom group
		if ($this->isUserSuperAdmin()) {
			return true;
		}
		$memberInfo = $this->getUserMemberInfo($groupId);
		return (!is_null($memberInfo) && $memberInfo['role']);
	}

	/**
	 * Returns whether the current user is an ownCloud admin
	 *
	 * @return boolean true if the user is an ownCloud admin, false otherwise
	 */
	public function isUserSuperAdmin() {
		return ($this->groupManager->isAdmin($this->getUserId()));
	}

	/**
	 * Returns whether the current user is member of this group
	 *
	 * @param int $groupId group id
	 * @return boolean true if the user is member, false otherwise
	 */
	public function isUserMember($groupId) {
		$memberInfo = $this->getUserMemberInfo($groupId);
		return (!is_null($memberInfo));
	}

	/**
	 * Returns whether the given group's member is the one and only group admin
	 *
	 * @param int $groupId group id
	 * @param string $userId user id of the admin to check
	 * @return bool true if it's the only admin, false otherwise
	 */
	public function isTheOnlyAdmin($groupId, $userId) {
		$searchAdmins = new Search();
		$searchAdmins->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);
		$groupAdmins = $this->groupsHandler->getGroupMembers($groupId, $searchAdmins);
		if (count($groupAdmins) > 1) {
			return false;
		}
		if ($groupAdmins[0]['user_id'] !== $userId) {
			return false;
		}

		return true;
	}

}
