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
use Sabre\DAV\PropPatch;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\PreconditionFailed;
use OCA\DAV\Connector\Sabre\Principal;

/**
 * Membership collection for a specific user
 */
class UserMembershipCollection extends Principal implements \Sabre\DAV\ICollection {

	/**
	 * Custom groups handler
	 *
	 * @var CustomGroupsDatabaseHandler
	 */
	private $groupsHandler;

	/**
	 * User id
	 *
	 * @var string
	 */
	private $userId;

	/**
	 * Membership helper
	 *
	 * @var MembershipHelper
	 */
	private $helper;

	/**
	 * Constructor
	 *
	 * @param string $userId user id
	 * @param CustomGroupsDatabaseHandler $groupsHandler custom groups handler
	 * @param MembershipHelper $helper membership helper
	 */
	public function __construct(
		$userId,
		CustomGroupsDatabaseHandler $groupsHandler,
		MembershipHelper $helper
	) {
		$this->groupsHandler = $groupsHandler;
		$this->userId = $userId;
		$this->helper = $helper;
	}

	/**
	 * Not supported
	 *
	 * @throws MethodNotAllowed not supported
	 */
	public function delete() {
		throw new MethodNotAllowed('Not supported');
	}

	/**
	 * Returns the name of the node.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->userId;
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
	 * Not supported
	 *
	 * @throws MethodNotAllowed not supported
	 */
	public function createFile($name, $data = null) {
		throw new MethodNotAllowed('Not supported');
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
	 * Returns a group node
	 *
	 * @param string $groupUri group uri
	 * @return GroupCollection group node
	 * @throws NotFound if the user is not member of the given group
	 */
	public function getChild($groupUri) {
		$groupInfo = $this->groupsHandler->getGroupByUri($groupUri);
		if (!is_null($groupInfo)) {
			$memberInfo = $this->groupsHandler->getGroupMemberInfo($groupInfo['group_id'], $this->userId);
			if (!is_null($memberInfo)) {
				return $this->createGroupNode($memberInfo);
			}
		}

		throw new NotFound("Group with uri \"$groupUri\" not found.");
	}

	/**
	 * Returns a list of all memberships
	 *
	 * @return CustomGroupMemberNode[] list of memberships
	 * @throws Forbidden if the current user has insufficient permissions
	 */
	public function getChildren() {
		$memberInfos = $this->groupsHandler->getUserMemberships($this->userId);
		return array_map(function ($memberInfo) {
			return $this->createGroupNode($memberInfo);
		}, $memberInfos);
	}

	/**
	 * Returns whether a user has a membership in the given.
	 *
	 * @param string $groupUri group uri
	 * @return boolean true if the user has a membership, false otherwise
	 */
	public function childExists($groupUri) {
		try {
			$this->getChild($groupUri);
		} catch (NotFound $e) {
			return false;
		}
		return true;
	}

	/**
	 * Creates a membership node based on the given membership info.
	 *
	 * @param array $memberInfo membership info
	 * @return CustomGroupMemberNode membership node
	 */
	private function createGroupNode(array $memberInfo) {
		return new MembershipNode(
			$memberInfo,
			$memberInfo['uri'],
			$this->groupsHandler,
			$this->helper
		);
	}
}
