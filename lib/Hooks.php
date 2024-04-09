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
		$customGroupsDb = \OC::$server->query(CustomGroupsDatabaseHandler::class);
		foreach ($customGroupsDb->getUserMemberships($params['uid'], null) as $customgroup) {
			$customGroupsDb->removeFromGroup($params['uid'], $customgroup['group_id']);
		}
	}
}
