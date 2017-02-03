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
use OCA\CustomGroups\Dav\RootCollection;
use OCA\CustomGroups\Dav\MembershipNode;
use OCA\CustomGroups\Dav\GroupsCollection;
use Sabre\DAV\ServerPlugin;
use OCA\CustomGroups\Dav\CustomGroupMemberNode;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Xml\Element\Response;
use Sabre\DAV\Xml\Response\MultiStatus;
/**
 * Sabre plugin to handle custom groups
 */
class CustomGroupsPlugin extends ServerPlugin {
	const NS_OWNCLOUD = 'http://owncloud.org/ns';

	const REPORT_NAME = '{http://owncloud.org/ns}filter-members';

	/**
	 * Custom groups handler
	 *
	 * @var CustomGroupsDatabaseHandler
	 */
	protected $groupsHandler;

	/**
	 * Sabre server
	 *
	 * @var \Sabre\DAV\Server $server
	 */
	private $server;

	/**
	 * User session
	 *
	 * @var \OCP\IUserSession
	 */
	protected $userSession;

	/**
	 * Custom groups plugin
	 *
	 * @param CustomGroupsDatabaseHandler $groupsHandler custom groups handler
	 * @param IUserSession $userSession user session
	 */
	public function __construct(CustomGroupsDatabaseHandler $groupsHandler, IUserSession $userSession) {
		$this->groupsHandler = $groupsHandler;
		$this->userSession = $userSession;
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param \Sabre\DAV\Server $server Sabre server
	 */
	public function initialize(\Sabre\DAV\Server $server) {
		$this->server = $server;

		$uri = $this->server->getRequestUri();
		$uri = '/' . trim($uri) . '/';
		if (strpos($uri, '/customgroups/') === false) {
			return;
		}

		if ($this->userSession === null || $this->userSession->getUser() === null) {
			return;
		}

		$this->server->xml->namespaceMap[self::NS_OWNCLOUD] = 'oc';
		$ns = '{' . self::NS_OWNCLOUD . '}';
		$this->server->resourceTypeMapping[MembershipNode::class] = $ns . 'customgroups-membership';
		$this->server->resourceTypeMapping[GroupsCollection::class] = $ns . 'customgroups-group';
		$this->server->protectedProperties[] = $ns . 'user-id';
		$this->server->protectedProperties[] = $ns . 'group-uri';

		$this->server->on('report', [$this, 'onReport']);
	}

	/**
	 * Returns a list of reports this plugin supports.
	 *
	 * This will be used in the {DAV:}supported-report-set property.
	 *
	 * @param string $uri URI
	 * @return array
	 */
	public function getSupportedReportSet($uri) {
		$node = $this->server->tree->getNodeForPath($uri);
		if (!$node instanceof RootCollection) {
			return [self::REPORT_NAME];
		}
		return [];
	}

	/**
	 * REPORT operations to look for comments
	 *
	 * @param string $reportName report name
	 * @param array $report report data
	 * @param string $uri URI
	 * @return bool true if processed
	 * @throws BadRequest if missing properties
	 */
	public function onReport($reportName, $report, $uri) {
		$node = $this->server->tree->getNodeForPath($uri);
		if (!$node instanceof RootCollection || $reportName !== self::REPORT_NAME) {
			return;
		}

		$requestedProps = [];
		$filterRules = [];

		$ns = '{' . self::NS_OWNCLOUD . '}';
		foreach ($report as $reportProps) {
			$name = $reportProps['name'];
			if ($name === $ns . 'filter-rules') {
				$filterRules = $reportProps['value'];
			} else if ($name === '{DAV:}prop') {
				// propfind properties
				foreach ($reportProps['value'] as $propVal) {
					$requestedProps[] = $propVal['name'];
				}
			}
		}

		$filterUserId = null;
		$filterAdminFlag = null;
		foreach ($filterRules as $filterRule) {
			if ($filterRule['name'] === $ns . 'user-id') {
				$filterUserId = $filterRule['value'];
			} else if ($filterRule['name'] === $ns . 'role') {
				$filterAdminFlag = $filterRule['value'];
			}
		}

		if (is_null($filterUserId)) {
			// an empty filter would return all existing users which would be useless
			throw new BadRequest('Missing user-id property');
		}

		$memberInfos = $this->groupsHandler->getUserMemberships($filterUserId, $filterAdminFlag);

		$responses = [];
		foreach ($memberInfos as $memberInfo) {
			$node = new CustomGroupMemberNode($memberInfo, $this->groupsHandler, $this->userSession);
			$uri = $memberInfo['uri'];
			$nodePath = $this->server->getRequestUri() . '/' . $uri . '/' . $node->getName();
			$resultSet = $node->getProperties($requestedProps);
			$responses[] = new Response(
				$this->server->getBaseUri() . $nodePath,
				[200 => $resultSet],
				200
			);
		}

		$xml = $this->server->xml->write(
			'{DAV:}multistatus',
			new MultiStatus($responses)
		);

		$this->server->httpResponse->setStatus(207);
		$this->server->httpResponse->setHeader('Content-Type', 'application/xml; charset=utf-8');
		$this->server->httpResponse->setBody($xml);

		return false;
	}
}
