<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH.
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

/**
 * Group backend for custom groups for integration with core
 */
class CustomGroupsBackend implements \OCP\GroupInterface {

	const GROUP_ID_PREFIX = 'customgroup_';

	/** @var CustomGroupsManager */
	private $manager;

	public function __construct(
		CustomGroupsManager $manager
	) {
		$this->manager = $manager;
	}

	/**
	 * Checks if backend implements actions.
	 *
	 * @param int $actions bitwise-or'ed actions
	 * @return boolean
	 */
	public function implementsActions($actions) {
		return ($actions & self::GROUP_DETAILS) !== 0;
	}

	/**
	 * Checks whether the user is member of a group or not.
	 *
	 * @param string $uid uid of the user
	 * @param string $gid gid of the group
	 * @return bool
	 */
	public function inGroup($uid, $gid) {
		$numericGroupId = $this->extractNumericGroupId($gid);
		if (is_null($numericGroupId)) {
			return false;
		}

		return $this->manager->inGroup($uid, $numericGroupId);
	}

	/**
	 * Get all groups a user belongs to
	 *
	 * @param string $uid Name of the user
	 * @return array an array of group names
	 */
	public function getUserGroups($uid) {
		$groups = $this->manager->getUserGroups($uid);
		return array_map(function($numericGroupId) {
			return $this->formatGroupId($numericGroupId);
		}, $groups);
	}

	/**
	 * Returns a list with all groups
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of group names
	 *
	 */
	public function getGroups($search = '', $limit = -1, $offset = 0) {
		$groups = $this->manager->searchGroups($search, $limit, $offset);
		return array_map(function($groupInfo) {
			return $this->formatGroupId($groupInfo['group_id']);
		}, $groups);
	}

	/**
	 * Checks if a group exists
	 *
	 * @param string $gid group id
	 * @return bool true if the group exists, false otherwise
	 */
	public function groupExists($gid) {
		return !is_null($this->getGroupDetails($gid));
	}

	/**
	 * Returns the info for a given group.
	 *
	 * @param string $gid group id
	 * @return array|null group info or null if not found
	 */
	public function getGroupDetails($gid) {
		$numericGroupId = $this->extractNumericGroupId($gid);
		if (is_null($numericGroupId)) {
			return null;
		}

		$group = $this->manager->getGroup($numericGroupId);
		if (is_null($group)) {
			return null;
		}
		return [
			'gid' => $this->formatGroupId($group['group_id']),
			'displayName' => $group['display_name'],
		];
	}

	/**
	 * Returns a list of all users in a group
	 *
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of user ids
	 */
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		// not exposed to regular user management
		return [];
	}

	/**
	 * Extracts the numeric id from the group id string with
	 * the format "customgroup_$id"
	 *
	 * @param string $gid group id in format "customgroup_$id"
	 * @return extracted numeric id or null if the format did not match
	 */
	private function extractNumericGroupId($gid) {
		$len = strlen(self::GROUP_ID_PREFIX);
		$prefixPart = substr($gid, 0, $len);
		if ($prefixPart !== self::GROUP_ID_PREFIX) {
			return null;
		}

		$numericPart = substr($gid, $len);

		if (!is_numeric($numericPart)) {
			return null;
		}
		return (int)$numericPart;
	}

	/**
	 * Formats the given numeric group id to a string
	 *
	 * @param int $numericId numeric group id
	 * @return formatted id in format "customgroup_$id"
	 */
	private function formatGroupId($numericId) {
		return self::GROUP_ID_PREFIX . $numericId;
	}
}
