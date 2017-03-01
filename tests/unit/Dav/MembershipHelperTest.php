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
namespace OCA\CustomGroups\Tests\unit\Dav;

use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\IUser;
use OCA\CustomGroups\Dav\MembershipHelper;
use OCA\CustomGroups\Search;

/**
 * Class MembershipHelperTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class MembershipHelperTest extends \Test\TestCase {

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
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	public function setUp() {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		// currently logged in user
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::CURRENT_USER);
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);

		$this->helper = new MembershipHelper(
			$this->handler,
			$this->userSession,
			$this->userManager,
			$this->groupManager
		);
	}

	public function testGetUserId() {
		$this->assertEquals(self::CURRENT_USER, $this->helper->getUserId());
	}

	public function testGetUser() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('anotheruser');

		$this->userManager->expects($this->once())
			->method('get')
			->with('anotheruser')
			->willReturn($user);

		$this->assertEquals($user, $this->helper->getUser('anotheruser'));
	}

	public function isUserAdminDataProvider() {
		return [
			// regular member
			[
				false,
				['role' => CustomGroupsDatabaseHandler::ROLE_MEMBER],
				false,
			],
			// admin member
			[
				false,
				['role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
				true,
			],
			// super-admin but non-admin member
			[
				true,
				['role' => CustomGroupsDatabaseHandler::ROLE_MEMBER],
				true,
			],
			// non-member
			[
				false,
				null,
				false,
			],
			// super-admin non-member
			[
				true,
				null,
				true,
			],
		];
	}

	/**
	 * @dataProvider isUserAdminDataProvider
	 */
	public function testIsUserAdmin($isSuperAdmin, $memberInfo, $expectedResult) {
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn($isSuperAdmin);

		$this->handler->expects($this->any())
			->method('getGroupMemberInfo')
			->with('group1', self::CURRENT_USER)
			->willReturn($memberInfo);

		$this->assertEquals($expectedResult, $this->helper->isUserAdmin('group1'));
	}

	public function isUserMemberDataProvider() {
		return [
			// regular member
			[
				['role' => CustomGroupsDatabaseHandler::ROLE_MEMBER],
				true,
			],
			// admin member
			[
				['role' => CustomGroupsDatabaseHandler::ROLE_ADMIN],
				true,
			],
			// non-member
			[
				null,
				false,
			],
		];
	}

	/**
	 * @dataProvider isUserMemberDataProvider
	 */
	public function testIsUserMember($memberInfo, $expectedResult) {
		$this->handler->expects($this->once())
			->method('getGroupMemberInfo')
			->with('group1', self::CURRENT_USER)
			->willReturn($memberInfo);

		$this->assertEquals($expectedResult, $this->helper->isUserMember('group1'));
	}

	public function isTheOnlyAdminDataProvider() {
		return [
			// user is not the last admin
			[
				[
					['user_id' => 'admin1'],
					['user_id' => 'admin2'],
				],
				false,
			],
			// user is the last admin
			[
				[
					['user_id' => 'admin1'],
				],
				true,
			],
			// someone else is the last admin
			[
				[
					['user_id' => 'admin2'],
				],
				false,
			],
		];
	}

	/**
	 * @dataProvider isTheOnlyAdminDataProvider
	 */
	public function testIsTheOnlyAdmin($memberInfo, $expectedResult) {
		$searchAdmins = new Search();
		$searchAdmins->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with('group1', $searchAdmins)
			->willReturn($memberInfo);

		$this->assertEquals($expectedResult, $this->helper->isTheOnlyAdmin('group1', 'admin1'));
	}
}
