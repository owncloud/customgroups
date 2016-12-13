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

use OCP\IDBConnection;
use OCP\ILogger;

/**
 * Database handler for custom groups
 */
class CustomGroupsDatabaseHandler {

	/**
	 * @var IDBConnection
	 */ 
	private $dbConn;

	/**
	 * @var ILogger
	 */ 
	private $logger;


	public function __construct(IDBConnection $dbConn, ILogger $logger) {
		$this->dbConn = $dbConn;
		$this->logger = $logger;
	}

	/**
	 * Checks whether the user is member of a group or not.
	 *
	 * @param string $uid uid of the user
	 * @param int $numericGroupId id of the group
	 * @return bool
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function inGroup($uid, $numericGroupId) {
		$qb = $this->dbConn->getQueryBuilder();

		$cursor = $qb->select('user_id')
			->from('custom_group_member')
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($numericGroupId)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->execute();

		$result = $cursor->fetch();
		$cursor->closeCursor();

		return $result ? true : false;
	}

	/**
	 * Get all groups a user belongs to
	 *
	 * @param string $uid Name of the user
	 * @return array an array of numeric group ids
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function getUserGroups($uid) {
		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select('group_id')
			->from('custom_group_member')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->orderBy('group_id', 'ASC')
			->execute();

		$groups = [];
		while ($row = $cursor->fetch()) {
			$groups[] = (int)$row['group_id'];
		}
		$cursor->closeCursor();

		return $groups;
	}

	/**
	 * Searches groups by display names where the given
	 * search string can appear anywhere within the display name
	 * field.
	 *
	 * @param string $search search string
	 * @param int $limit limit or -1 to disable
	 * @param int $offset offset
	 * @return array an array of group info
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function searchGroups($search = '', $limit = -1, $offset = 0) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select(['group_id', 'uri', 'display_name'])
			->from('custom_group')
			->orderBy('display_name', 'ASC');

		if ($search !== '') {
			$likeString = '%' . $this->dbConn->escapeLikeParameter(strtolower($search)) . '%';
			$qb->where($qb->expr()->like($qb->createFunction('LOWER(`display_name`)'), $qb->createNamedParameter($likeString)));
		}

		if ($limit > 0) {
			$qb->setMaxResults($limit);
		}

		if ($offset !== 0) {
			$qb->setFirstResult($offset);
		}

		$cursor = $qb->execute();
		$groups = $cursor->fetchAll();
		$cursor->closeCursor();
		return $groups;
	}

	/**
	 * Returns the info for a given group.
	 *
	 * @param string $numericGroupId group id
	 * @return array|null group info or null if not found
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function getGroup($numericGroupId) {
		return $this->getGroupBy('group_id', $numericGroupId);
	}

	/**
	 * Returns the info for a given group.
	 *
	 * @param string $uri group uri
	 * @return array|null group info or null if not found
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function getGroupByUri($uri) {
		return $this->getGroupBy('uri', $uri);
	}

	/**
	 * Returns the info for a given group.
	 *
	 * @param string $gid group id
	 * @return array|null group info or null if not found
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	private function getGroupBy($field, $numericGroupId) {
		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select(['group_id', 'uri', 'display_name'])
			->from('custom_group')
			->where($qb->expr()->eq($field, $qb->createNamedParameter($numericGroupId)))
			->execute();
		$result = $cursor->fetch();
		$cursor->closeCursor();

		if (!$result) {
			return null;
		}

		return $result;
	}

	/**
	 * Returns the info for all groups.
	 *
	 * @return array array of group info
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function getGroups() {
		return $this->searchGroups();
	}

	/**
	 * Creates a new group
	 *
	 * @param string $displayName display name
	 * @return int group id
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function createGroup($uri, $displayName = null) {
		try {
			$result = $this->dbConn->insertIfNotExist('*PREFIX*custom_group', [
				'uri' => $uri,
				'display_name' => $displayName,
			]);
		} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
			$this->logger->logException($e, ['app' => 'customgroups', 'message' => 'Cannot create a group that already exists']);
			return null;
		}

		if ($result === 1) {
			return $this->dbConn->lastInsertId('*PREFIX*custom_group');
		}

		return null;
	}

	/**
	 * Deletes the group with the given id
	 *
	 * @param int $gid numeric group id
	 * @return true if group was deleted, false otherwise
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function deleteGroup($gid) {
		$this->dbConn->beginTransaction();
		// Delete the group-user relation
		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('custom_group_member')
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($gid)))
			->execute();

		// Delete the group
		$qb = $this->dbConn->getQueryBuilder();
		$result = $qb->delete('custom_group')
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($gid)))
			->execute();
		$this->dbConn->commit();

		return ($result === 1);
	}

	/**
	 * Add a user to a group.
	 *
	 * @param string $uid user id
	 * @param int $gid numeric group id
	 * @return bool true if user was added, false otherwise
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function addToGroup($uid, $gid, $isAdmin = false) {
		$result = $this->dbConn->insertIfNotExist('*PREFIX*custom_group_member', [
			'user_id' => $uid,
			'group_id' => $gid,
			'is_admin' => $isAdmin ? 1 : 0
		]);

		return ($result === 1);
	}

	/**
	 * Remove a user from a group.
	 *
	 * @param string $uid user id
	 * @param int $gid numeric group id
	 * @return bool true if user was removed, false otherwise
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function removeFromGroup($uid, $gid) {
		$qb = $this->dbConn->getQueryBuilder();
		$result = $qb->delete('custom_group_member')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($gid)))
			->execute();

		return ($result === 1);
	}

	/**
	 * Returns the group members
	 *
	 * @param int $gid numeric group id
	 * @return array array of member info
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function getGroupMembers($gid) {
		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select(['user_id', 'group_id', 'is_admin'])
			->from('custom_group_member')
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($gid)))
			->orderBy('user_id', 'ASC')
			->execute();

		$results = [];
		while ($row = $cursor->fetch()) {
			$results[] = $this->formatMemberInfo($row);
		}
		$cursor->closeCursor();

		return $results;
	}

	/**
	 * Returns a specific group member info
	 *
	 * @param int $gid numeric group id
	 * @param int $uid user id
	 * @return array member info
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function getGroupMemberInfo($gid, $uid) {
		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select(['user_id', 'group_id', 'is_admin'])
			->from('custom_group_member')
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($gid)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->orderBy('user_id', 'ASC')
			->execute();

		$result = $cursor->fetchAll();
		$cursor->closeCursor();

		if (empty($result)) {
			return null;
		}

		return $this->formatMemberInfo($result[0]);
	}

	/**
	 * Update group member info
	 *
	 * @param int $gid numeric group id
	 * @param int $uid user id
	 * @param bool $isAdmin whether the member is a group admin
	 * @return bool true if the info got updated, false otherwise
	 * @throws \Doctrine\DBAL\Exception\DriverException in case of database exception
	 */
	public function setGroupMemberInfo($gid, $uid, $isAdmin) {
		$qb = $this->dbConn->getQueryBuilder();
		$result = $qb->update('custom_group_member')
			->set('is_admin', $qb->createNamedParameter($isAdmin ? 1 : 0))
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($gid)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($uid)))
			->execute();

		return $result === 1;
	}

	/**
	 * Formats the group member info from the database
	 *
	 * @param array $row database row
	 * @return array formatted array
	 */
	private function formatMemberInfo($row) {
		return [
			'user_id' => $row['user_id'],
			'group_id' => $row['group_id'],
			'is_admin' => (int)$row['is_admin'] !== 0,
		];
	}
}
