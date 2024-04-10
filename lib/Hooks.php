<?php
/**
 * @author Pasquale Tripodi <pasquale.tripodi@kiteworks.com>
 * @author Ilja Neumann <ilja.neumann@kiteworks.com>
 *
 * @copyright Copyright (c) 2024, ownCloud GmbH
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

class Hooks {
	public static function register(): void {
		\OCP\Util::connectHook(
			'OC_User',
			'post_deleteUser',
			self::class,
			'userDelete'
		);
	}

	public static function userDelete($params) {
		$customGroupsDbHandler = \OC::$server->query(CustomGroupsDatabaseHandler::class);
		foreach ($customGroupsDbHandler->getUserMemberships($params['uid'], null) as $customGroup) {
			$members = $customGroupsDbHandler->getGroupMembers($customgroup['group_id']);
			if (\count($members) === 1 && $members[0]['user_id'] === $params['uid']) {
				// removing custom group as deleted user is the only member/admin left
				$customGroupsDbHandler->deleteGroup($customgroup['group_id']);
			}
			$customGroupsDbHandler->removeFromGroup($params['uid'], $customGroup['group_id']);
		}
	}
}
