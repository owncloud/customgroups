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

use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCA\CustomGroups\Service\MembershipHelper;
use OCA\CustomGroups\Controller\PageController;
use OCP\IRequest;
use OCP\IUser;

/**
 * Class PageControllerTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class PageControllerTest extends \Test\TestCase {

	const CURRENT_USER = 'currentuser';

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	/**
	 * @var MembershipHelper
	 */
	private $helper;

	/**
	 * @var PageController
	 */
	private $pageController;

	public function setUp() {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->helper = $this->createMock(MembershipHelper::class);

		$this->pageController = new PageController(
			'customgroups',
			$this->createMock(IRequest::class),
			$this->helper,
			$this->handler
		);
	}

	public function testSearchUsers() {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user1->method('getDisplayName')->willReturn('User One');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('getDisplayName')->willReturn('User Two');

		$this->handler->expects($this->once())
			->method('getGroupByUri')
			->with('group1')
			->willReturn(['group_id' => 128]);
		$this->helper->expects($this->once())
			->method('searchForNewMembers')
			->with(128, 'us', 150)
			->willReturn([$user1, $user2]);

		$response = $this->pageController->searchUsers('group1', 'us', 150);
		$data = $response->getData();

		$this->assertTrue(isset($data['results']));
		$this->assertEquals([
			['userId' => 'user1', 'displayName' => 'User One'],
			['userId' => 'user2', 'displayName' => 'User Two'],
		], $data['results']);
	}

}
