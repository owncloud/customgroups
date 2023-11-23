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
namespace OCA\CustomGroups\Tests\unit\Service;

use OCA\CustomGroups\Service\GuestIntegrationHelper;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\IUser;
use OCA\CustomGroups\Service\MembershipHelper;
use OCA\CustomGroups\Search;
use OCP\Notification\IManager;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCA\CustomGroups\Dav\Roles;
use OCP\IConfig;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class MembershipHelperTest
 *
 * @package OCA\CustomGroups\Tests\unit\Service
 */
class MembershipHelperTest extends \Test\TestCase {
	public const CURRENT_USER = 'currentuser';

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
	 * @var IManager
	 */
	private $notificationManager;

	/**
	 * @var IUser
	 */
	private $user;

	/**
	 * @var IConfig
	 */
	private $config;

	public function setUp(): void {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		// swap for 10.1, method getSubAdmin is not on the interface in 10.0
		$this->groupManager = $this->createMock(\OC\Group\Manager::class);
		//$this->groupManager = $this->createMock(IGroupManager::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(IConfig::class);

		// currently logged in user
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn(self::CURRENT_USER);
		$this->user->method('getDisplayName')->willReturn('User One');
		$userSession
			->method('getUser')
			->willReturn($this->user);
		$this->guestIntegrationHelper = $this->createMock(GuestIntegrationHelper::class);

		$this->helper = new MembershipHelper(
			$this->handler,
			$userSession,
			$this->userManager,
			$this->groupManager,
			$this->notificationManager,
			$urlGenerator,
			$this->config,
			$this->guestIntegrationHelper
		);
	}

	public function testGetUserId(): void {
		self::assertEquals(self::CURRENT_USER, $this->helper->getUserId());
	}

	public function testGetUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('anotheruser');

		$this->userManager->expects($this->once())
			->method('get')
			->with('anotheruser')
			->willReturn($user);

		self::assertEquals($user, $this->helper->getUser('anotheruser'));
	}

	public function isUserAdminDataProvider(): array {
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
	public function testIsUserAdmin($isSuperAdmin, $memberInfo, $expectedResult): void {
		$this->config->method('getSystemValue')
			->with('customgroups.disallow-admin-access-all', false)
			->willReturn(false);
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn($isSuperAdmin);

		$this->handler
			->method('getGroupMemberInfo')
			->with('group1', self::CURRENT_USER)
			->willReturn($memberInfo);

		self::assertEquals($expectedResult, $this->helper->isUserAdmin('group1'));
	}

	public function testDenyAdminAccess(): void {
		$this->config->method('getSystemValue')
			->with('customgroups.disallow-admin-access-all', false)
			->willReturn(true);
		$this->groupManager->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn(true);
		$this->handler->method('getGroup')
			->with('group1')
			->willReturn(['role' => CustomGroupsDatabaseHandler::ROLE_MEMBER, 'display_name' => 'group1']);
		self::assertFalse($this->helper->isUserAdmin('group1'));
	}

	public function isUserMemberDataProvider(): array {
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
	public function testIsUserMember($memberInfo, $expectedResult): void {
		$this->handler->expects($this->once())
			->method('getGroupMemberInfo')
			->with('group1', self::CURRENT_USER)
			->willReturn($memberInfo);

		self::assertEquals($expectedResult, $this->helper->isUserMember('group1'));
	}

	public function isTheOnlyAdminDataProvider(): array {
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
	public function testIsTheOnlyAdmin($memberInfo, $expectedResult): void {
		$searchAdmins = new Search();
		$searchAdmins->setRoleFilter(CustomGroupsDatabaseHandler::ROLE_ADMIN);

		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with('group1', $searchAdmins)
			->willReturn($memberInfo);

		self::assertEquals($expectedResult, $this->helper->isTheOnlyAdmin('group1', 'admin1'));
	}

	private function createExpectedNotification($messageId, $messageParams) {
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('setApp')
			->with('customgroups')
			->willReturn($notification);
		$notification->expects($this->once())
			->method('setUser')
			->with('anotheruser')
			->willReturn($notification);
		$notification->expects($this->once())
			->method('setLink')
			->willReturn($notification);
		$notification->expects($this->once())
			->method('setDateTime')
			->willReturn($notification);
		$notification->expects($this->once())
			->method('setObject')
			->with('customgroup', 1)
			->willReturn($notification);
		$notification->expects($this->once())
			->method('setSubject')
			->with($messageId, $messageParams)
			->willReturn($notification);
		$notification->expects($this->once())
			->method('setMessage')
			->with($messageId, $messageParams)
			->willReturn($notification);

		return $notification;
	}

	public function testNotifyUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::CURRENT_USER);
		$user->method('getDisplayName')->willReturn('User One');
		$this->userManager->method('get')
			->with(self::CURRENT_USER)
			->willReturn($user);

		$notification = $this->createExpectedNotification(
			'added_member',
			['User One', 'Group One']
		);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->helper->notifyUser(
			'anotheruser',
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One']
		);
	}

	public function testNotifyUserRemoved(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::CURRENT_USER);
		$user->method('getDisplayName')->willReturn('User One');
		$this->userManager->method('get')
			->with(self::CURRENT_USER)
			->willReturn($user);

		$notification = $this->createExpectedNotification(
			'removed_member',
			['User One', 'Group One']
		);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->helper->notifyUserRemoved(
			'anotheruser',
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One']
		);
	}

	public function testNotifyUserRoleChange(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::CURRENT_USER);
		$user->method('getDisplayName')->willReturn('User One');
		$this->userManager->method('get')
			->with(self::CURRENT_USER)
			->willReturn($user);

		$notification = $this->createExpectedNotification(
			'changed_member_role',
			['User One', 'Group One', Roles::BACKEND_ROLE_MEMBER]
		);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$called = [];
		\OC::$server->getEventDispatcher()->addListener('\OCA\CustomGroups::changeRoleInGroup', function ($event) use (&$called) {
			$called[] = '\OCA\CustomGroups::changeRoleInGroup';
			\array_push($called, $event);
		});
		$this->helper->notifyUserRoleChange(
			'anotheruser',
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
			['group_id' => 1, 'role' => Roles::BACKEND_ROLE_MEMBER]
		);

		self::assertSame('\OCA\CustomGroups::changeRoleInGroup', $called[0]);
		self::assertInstanceOf(GenericEvent::class, $called[1]);
		self::assertArrayHasKey('user', $called[1]);
		self::assertEquals('anotheruser', $called[1]->getArgument('user'));
		self::assertArrayHasKey('groupName', $called[1]);
		self::assertEquals('Group One', $called[1]->getArgument('groupName'));
		self::assertArrayHasKey('roleNumber', $called[1]);
		self::assertEquals(0, $called[1]->getArgument('roleNumber'));
		self::assertArrayHasKey('roleDisaplayName', $called[1]);
		self::assertEquals('Member', $called[1]->getArgument('roleDisaplayName'));
	}

	public function canCreateRolesProvider(): array {
		return [
			['ocadmin', false, true],
			['subadmin', false, true],
			['user', false, true],
			['ocadmin', true, true],
			['subadmin', true, true],
			['user', true, false],
		];
	}

	/**
	 * @dataProvider canCreateRolesProvider
	 */
	public function testCanCreateGroups($role, $restrictToSubAdmins, $expectedResult): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('customgroups', 'only_subadmin_can_create', 'false')
			->willReturn($restrictToSubAdmins ? 'true' : 'false');

		$this->groupManager
			->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn($role === 'ocadmin');

		// TODO: swap for 10.1 as 10.0 doesn't provide this interface
		//$subadminManager = $this->createMock(ISubAdminManager::class);
		$subadminManager = $this->createMock(\OC\SubAdmin::class);
		$subadminManager
			->method('isSubAdmin')
			->with($this->user)
			->willReturn($role === 'subadmin');

		$this->groupManager
			->method('getSubAdmin')
			->willReturn($subadminManager);

		self::assertEquals($expectedResult, $this->helper->canCreateGroups());
	}

	public function testIsGroupDisplayNameAvailableWhenDuplicatesAreAllowed(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('customgroups', 'allow_duplicate_names', 'false')
			->willReturn('true');

		$this->handler->expects($this->never())
			->method('getGroupsByDisplayName');

		self::assertTrue($this->helper->isGroupDisplayNameAvailable('test'));
	}

	public function testIsGroupDisplayNameAvailableNoDuplicateExists(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('customgroups', 'allow_duplicate_names', 'false')
			->willReturn('false');

		$this->handler->expects($this->once())
			->method('getGroupsByDisplayName')
			->with('test')
			->willReturn([]);

		self::assertTrue($this->helper->isGroupDisplayNameAvailable('test'));
	}

	public function testIsGroupDisplayNameAvailableDuplicateExists(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('customgroups', 'allow_duplicate_names', 'false')
			->willReturn('false');

		$this->handler->expects($this->once())
			->method('getGroupsByDisplayName')
			->with('test')
			->willReturn([['duplicate']]);

		self::assertFalse($this->helper->isGroupDisplayNameAvailable('test'));
	}
}
