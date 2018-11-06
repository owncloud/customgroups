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

use Sabre\DAV\IExtendedCollection;
use Sabre\DAV\MkCol;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\Forbidden;

use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\Search;
use OCA\CustomGroups\Service\MembershipHelper;
use Symfony\Component\EventDispatcher\GenericEvent;
use Sabre\DAV\Exception\Conflict;
use OCP\IGroupManager;

/**
 * Collection of custom groups
 */
class GroupsCollection implements IExtendedCollection {

	/**
	 * Custom groups handler
	 *
	 * @var CustomGroupsDatabaseHandler
	 */
	private $groupsHandler;

	/**
	 * Group manager from core
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * Membership helper
	 *
	 * @var MembershipHelper
	 */
	private $helper;

	/**
	 * User id for which to use memberships or null for all groups
	 *
	 * @var string
	 */
	private $userId;

	/**
	 * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
	*/
	private $dispatcher;

	/**
	 * Constructor
	 *
	 * @param CustomGroupsDatabaseHandler $groupsHandler custom groups handler
	 * @param MembershipHelper $helper helper
	 */
	public function __construct(
		IGroupManager $groupManager,
		CustomGroupsDatabaseHandler $groupsHandler,
		MembershipHelper $helper,
		$userId = null
	) {
		$this->groupManager = $groupManager;
		$this->groupsHandler = $groupsHandler;
		$this->helper = $helper;
		$this->userId = $userId;

		$this->dispatcher = \OC::$server->getEventDispatcher();
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

	public function createDirectory($name) {
		if (!$this->helper->canCreateGroups()) {
			throw new Forbidden('No permission to create groups');
		}

		$this->createGroup($name, $name);
	}

	/**
	 * Creates a new custom group
	 *
	 * @param string $name group URI
	 * @throws MethodNotAllowed if the group already exists
	 */
	public function createExtendedCollection($name, Mkcol $mkCol) {
		if (!$this->helper->canCreateGroups()) {
			throw new Forbidden('No permission to create groups');
		}

		$displayName = $name;

		// can't use handle() here as it's called too late
		$mutations = $mkCol->getMutations();
		if (isset($mutations[GroupMembershipCollection::PROPERTY_DISPLAY_NAME])) {
			$displayName = $mutations[GroupMembershipCollection::PROPERTY_DISPLAY_NAME];
			$mkCol->setResultCode(GroupMembershipCollection::PROPERTY_DISPLAY_NAME, 202); // accepted
		}

		$this->createGroup($name, $displayName);
	}

	/**
	 * Creates a group node with the given name and display name
	 * 
	 * @param string $name group uri
	 * @param string $displayName group display name
	 */
	private function createGroup($name, $displayName) {
		if (!$this->helper->isGroupDisplayNameAvailable($displayName)) {
			throw new Conflict("Group with display name \"$displayName\" already exists");
		}

		$groupId = $this->groupsHandler->createGroup($name, $displayName);
		if (is_null($groupId)) {
			throw new MethodNotAllowed("Group with uri \"$name\" already exists");
		}

		// add current user as admin
		$this->groupsHandler->addToGroup($this->helper->getUserId(), $groupId, true);

		$event = new GenericEvent(null, [
			'groupName' => $name,
			'groupId' => $groupId,
			'user' => $this->helper->getUserId()]);
		$this->dispatcher->dispatch('\OCA\CustomGroups::addGroupAndUser', $event);
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
		return $this->search();
	}

	/**
	 * Search nodes
	 *
	 * @param Search $search search
	 */
	public function search(Search $search = null) {
		if ($this->userId !== null) {
			$allGroups = $this->groupsHandler->getUserMemberships($this->userId, $search);
		} else {
			$allGroups = $this->groupsHandler->getGroups($search);
		}
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
		if ($this->userId !== null) {
			return $this->userId;
		}
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
			$this->groupManager,
			$this->groupsHandler,
			$this->helper
		);
	}
}
