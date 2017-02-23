<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
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

namespace OCA\CustomGroups\Tests\unit;

use OCA\CustomGroups\Dav\CustomGroupsPlugin;
use OCA\CustomGroups\Dav\GroupsCollection;
use OCA\CustomGroups\Dav\ReportRequest;
use OCA\CustomGroups\Search;
use OCA\CustomGroups\Dav\GroupMembershipCollection;
use OCA\CustomGroups\Dav\MembershipNode;

class CustomGroupsPluginTest extends \Test\TestCase {
	/** @var \Sabre\DAV\Server|\PHPUnit_Framework_MockObject_MockObject */
	private $server;

	/** @var \Sabre\DAV\Tree|\PHPUnit_Framework_MockObject_MockObject */
	private $tree;

	/** @var  \OCP\IUserSession */
	private $userSession;

	/** @var CustomGroupsPlugin */
	private $plugin;

	public function setUp() {
		parent::setUp();
		$this->tree = $this->getMockBuilder('\Sabre\DAV\Tree')
			->disableOriginalConstructor()
			->getMock();

		$this->server = $this->getMockBuilder('\Sabre\DAV\Server')
			->setConstructorArgs([$this->tree])
			->setMethods(['getRequestUri', 'getBaseUri', 'generateMultiStatus'])
			->getMock();

		$this->server->expects($this->any())
			->method('getBaseUri')
			->will($this->returnValue('http://example.com/owncloud/remote.php/dav'));

		$this->userSession = $this->createMock('\OCP\IUserSession');

		$user = $this->createMock('\OCP\IUser');
		$user->expects($this->any())
			->method('getUID')
			->will($this->returnValue('testuser'));
		$this->userSession->expects($this->any())
			->method('getUser')
			->will($this->returnValue($user));

		$this->plugin = new CustomGroupsPlugin(
			$this->userSession
		);
	}

	public function testOnReportInvalidNode() {
		$path = 'totally/unrelated/13';

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/' . $path)
			->will($this->returnValue($this->createMock('\Sabre\DAV\INode')));

		$this->server->expects($this->any())
			->method('getRequestUri')
			->will($this->returnValue($path));
		$this->plugin->initialize($this->server);

		$this->assertNull($this->plugin->onReport(CustomGroupsPlugin::REPORT_NAME, [], '/' . $path));
	}

	public function testOnReportInvalidReportName() {
		$path = 'test';

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/' . $path)
			->will($this->returnValue($this->createMock('\Sabre\DAV\INode')));

		$this->server->expects($this->any())
			->method('getRequestUri')
			->will($this->returnValue($path));
		$this->plugin->initialize($this->server);

		$this->assertNull($this->plugin->onReport('{whoever}whatever', [], '/' . $path));
	}

	public function reportNodesDataProvider() {
		$cases = [];

		$props = [
			'{http://owncloud.org/ns}display-name',
			'{http://owncloud.org/ns}role',
		];

		$groupNode1 = $this->createMock(GroupMembershipCollection::class);
		$groupNode1->method('getName')->willReturn('group1');
		$groupNode1->expects($this->once())
			->method('getProperties')
			->with($props)
			->willReturn([
				'{http://owncloud.org/ns}display-name' => 'Group One',
				'{http://owncloud.org/ns}role' => 'admin',
			]);
		$groupNode2 = $this->createMock(GroupMembershipCollection::class);
		$groupNode2->method('getName')->willReturn('group2');
		$groupNode2->expects($this->once())
			->method('getProperties')
			->with($props)
			->willReturn([
				'{http://owncloud.org/ns}display-name' => 'Group Two',
				'{http://owncloud.org/ns}role' => 'member',
			]);

		$cases[] = [
			'customgroups/groups',
			GroupsCollection::class,
			GroupMembershipCollection::class,
			$props,
			[$groupNode1, $groupNode2],
			[
				[
					'href' => 'customgroups/groups/group1',
					200 => [
						'{http://owncloud.org/ns}display-name' => 'Group One',
						'{http://owncloud.org/ns}role' => 'admin',
					],
					404 => [],
				],
				[
					'href' => 'customgroups/groups/group2',
					200 => [
						'{http://owncloud.org/ns}display-name' => 'Group Two',
						'{http://owncloud.org/ns}role' => 'member',
					],
					404 => [],
				],
			]
		];

		$memberNode1 = $this->createMock(MembershipNode::class);
		$memberNode1->method('getName')->willReturn('testuser');
		$memberNode1->expects($this->once())
			->method('getProperties')
			->with($props)
			->willReturn([
				'{http://owncloud.org/ns}display-name' => 'Test User',
				'{http://owncloud.org/ns}role' => 'admin',
			]);
		$memberNode2 = $this->createMock(MembershipNode::class);
		$memberNode2->method('getName')->willReturn('user1');
		$memberNode2->expects($this->once())
			->method('getProperties')
			->with($props)
			->willReturn([
				'{http://owncloud.org/ns}display-name' => 'User One',
				'{http://owncloud.org/ns}role' => 'member',
			]);
		$cases[] = [
			'customgroups/groups/group1',
			GroupMembershipCollection::class,
			MembershipNode::class,
			$props,
			[$memberNode1, $memberNode2],
			[
				[
					'href' => 'customgroups/groups/group1/testuser',
					200 => [
						'{http://owncloud.org/ns}display-name' => 'Test User',
						'{http://owncloud.org/ns}role' => 'admin',
					],
					404 => [],
				],
				[
					'href' => 'customgroups/groups/group1/user1',
					200 => [
						'{http://owncloud.org/ns}display-name' => 'User One',
						'{http://owncloud.org/ns}role' => 'member',
					],
					404 => [],
				],
			]
		];

		return $cases;
	}

	/**
	 * @dataProvider reportNodesDataProvider
	 */
	public function testOnReportSearchGroupsCollection($reportTargetPath, $nodeClass, $resultClass, $props, $resultNodes, $expectedMultiStatus) {
		$reportRequest = new ReportRequest($props, new Search('searchpattern', 12, 256));

		$reportTargetNode = $this->createMock($nodeClass);
		$reportTargetNode->expects($this->once())
			->method('search')
			->willReturn($resultNodes);

		$response = $this->getMockBuilder('Sabre\HTTP\ResponseInterface')
			->disableOriginalConstructor()
			->getMock();

		$response->expects($this->once())
			->method('setHeader')
			->with('Content-Type', 'application/xml; charset=utf-8');

		$response->expects($this->once())
			->method('setStatus')
			->with(207);

		$response->expects($this->once())
			->method('setBody');

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/' . $reportTargetPath)
			->will($this->returnValue($reportTargetNode));

		$this->server->expects($this->any())
			->method('getRequestUri')
			->will($this->returnValue($reportTargetPath));
		$this->server->httpResponse = $response;
		$this->plugin->initialize($this->server);

		$this->server->expects($this->once())
			->method('generateMultiStatus')
			->with($expectedMultiStatus);

		$this->assertFalse($this->plugin->onReport(CustomGroupsPlugin::REPORT_NAME, $reportRequest, '/' . $reportTargetPath));
	}
}

