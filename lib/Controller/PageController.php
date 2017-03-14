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

namespace OCA\CustomGroups\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\Search;
use OCP\IUserManager;
use OCP\IUser;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

class PageController extends Controller {

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	public function __construct($appName, CustomGroupsDatabaseHandler $handler, IUserManager $userManager) {
		parent::__construct($appName);
		$this->handler = $handler;
		$this->userManager = $userManager;
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function index() {
		// TODO: cache or add to info.xml ?
		$modules = json_decode(file_get_contents(__DIR__ . '/../../js/modules.json'));
		return new TemplateResponse($this->appName, 'index', [
			'modules' => $modules
		]);
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function searchUsers($group, $pattern, $limit = 200) {
		$groupInfo = $this->handler->getGroupByUri($group);
		if (is_null($groupInfo)) {
			return new DataResponse(
				[
					'message' => (string)$this->l10n->t('Group with uri "%s" not found', [$group])
				],
				Http::STATUS_NOT_FOUND
			);
		}

		$existingMembers = $this->getGroupMemberUserIds($groupInfo['group_id'], $pattern);

		$results = $this->getEnoughMemberResults($pattern, $limit, $existingMembers);
		$existingMembers = null;

		$results = array_map(function (IUser $entry) {
			return [
				'userId' => $entry->getUID(),
				'displayName' => $entry->getDisplayName()
			];
		}, $results);

		return new DataResponse(['results' => array_values($results)], Http::STATUS_OK);
	}

	private function getGroupMemberUserIds($groupId, $pattern) {
		$search = new Search($pattern);

		$foundMembers = $this->handler->getGroupMembers($groupId, $search);
		$existingMembers = [];
		foreach ($foundMembers as $foundMember) {
			$existingMembers[$foundMember['user_id']] = true;
		}
		return $existingMembers;
	}

	private function getEnoughMemberResults($pattern, $limit, $existingMembers) {
		$totalResults = [];
		$totalResultCount = 0;

		$internalLimit = $limit;
		$internalOffset = 0;
		// loop until the $totalResults reaches $limit size or no more results exist
		do {
			$results = $this->userManager->searchDisplayName($pattern, $internalLimit, $internalOffset);
			foreach ($results as $result) {
				if (!isset($existingMembers[$result->getUID()])) {
					$totalResults[] = $result;
					$totalResultCount++;
				}
			}
			$resultsCount = count($results);
			$internalOffset += $resultsCount;
		} while ($totalResultCount < $limit && $resultsCount > 0);

		return $totalResults;
	}
}
