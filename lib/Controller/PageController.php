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
use OCP\IUser;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCA\CustomGroups\Service\MembershipHelper;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\IRequest;

class PageController extends Controller {

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var MembershipHelper
	 */
	private $helper;

	public function __construct(
		$appName,
		IRequest $request,
		MembershipHelper $helper,
		CustomGroupsDatabaseHandler $handler
	) {
		parent::__construct($appName, $request);
		$this->helper = $helper;
		$this->handler = $handler;
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

		$results = $this->helper->searchForNewMembers($groupInfo['group_id'], $pattern, $limit);
		$results = array_map(function (IUser $entry) {
			return [
				'userId' => $entry->getUID(),
				'displayName' => $entry->getDisplayName()
			];
		}, $results);

		return new DataResponse(['results' => array_values($results)], Http::STATUS_OK);
	}

}
