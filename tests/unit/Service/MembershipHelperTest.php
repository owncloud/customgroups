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

	/**
	 * @var IManager
	 */
	private $notificationManager;

	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * @var IUser
	 */
	private $user;

	/**
	 * @var IConfig
	 */
	private $config;

	public function setUp() {
		parent::setUp();
		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		// swap for 10.1, method getSubAdmin is not on the interface in 10.0
		$this->groupManager = $this->createMock(\OC\Group\Manager::class);
		//$this->groupManager = $this->createMock(IGroupManager::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(IConfig::class);

		// currently logged in user
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn(self::CURRENT_USER);
		$this->user->method('getDisplayName')->willReturn('User One');
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($this->user);

		$this->helper = new MembershipHelper(
			$this->handler,
			$this->userSession,
			$this->userManager,
			$this->groupManager,
			$this->notificationManager,
			$this->urlGenerator,
			$this->config
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

	public function testSearchForNewMembers() {
		$search = new Search('us');
		
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(1)
			->willReturn([
				['user_id' => 'user1'],
				['user_id' => 'user2'],
			]);

		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')->willReturn('user1');
		$user1->method('getDisplayName')->willReturn('User One');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')->willReturn('user2');
		$user2->method('getDisplayName')->willReturn('User Two');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')->willReturn('user3');
		$user3->method('getDisplayName')->willReturn('User Three');
		$user4 = $this->createMock(IUser::class);
		$user4->method('getUID')->willReturn('user4');
		$user4->method('getDisplayName')->willReturn('User Four');

		$this->userManager->expects($this->once())
			->method('find')
			->with('us', 150, 0)
			->willReturn([$user1, $user2, $user3, $user4]);
		$results = $this->helper->searchForNewMembers(1, 'us', 150);

		$this->assertCount(2, $results);

		$this->assertEquals('user3', $results[0]->getUID());
		$this->assertEquals('user4', $results[1]->getUID());
	}

	public function testSearchForNewMembersBigPage() {
		$search = new Search('us');

		$users = [];
		for ($i = 0; $i < 25; $i++) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('user' . $i);
			$user->method('getDisplayName')->willReturn('User ' . $i);
			$users[] = $user;
		}
		
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(1)
			->willReturn([
				['user_id' => 'user15'],
				['user_id' => 'user16'],
				['user_id' => 'user19'],
			]);

		$usersChunk = array_chunk($users, 20);

		$this->userManager->expects($this->at(0))
			->method('find')
			->with('us', 20, 0)
			->willReturn($usersChunk[0]);
		$this->userManager->expects($this->at(1))
			->method('find')
			->with('us', 20, 20)
			->willReturn($usersChunk[1]);

		$results = $this->helper->searchForNewMembers(1, 'us', 20);

		$this->assertCount(20, $results);

		$this->assertEquals('user0', $results[0]->getUID());
		$this->assertEquals('user1', $results[1]->getUID());
		$this->assertEquals('user14', $results[14]->getUID());
		$this->assertEquals('user17', $results[15]->getUID());
		$this->assertEquals('user18', $results[16]->getUID());
		$this->assertEquals('user20', $results[17]->getUID());
		$this->assertEquals('user21', $results[18]->getUID());
		$this->assertEquals('user22', $results[19]->getUID());
	}

	public function testSearchForNewMembersSmallLastPage() {
		$search = new Search('us');

		$users = [];
		for ($i = 0; $i < 21; $i++) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('user' . $i);
			$user->method('getDisplayName')->willReturn('User ' . $i);
			$users[] = $user;
		}
		
		$this->handler->expects($this->once())
			->method('getGroupMembers')
			->with(1)
			->willReturn([
				['user_id' => 'user15'],
				['user_id' => 'user16'],
				['user_id' => 'user19'],
			]);

		$usersChunk = array_chunk($users, 20);

		$this->userManager->expects($this->at(0))
			->method('find')
			->with('us', 20, 0)
			->willReturn($usersChunk[0]);
		$this->userManager->expects($this->at(1))
			->method('find')
			->with('us', 20, 20)
			->willReturn($usersChunk[1]);

		$results = $this->helper->searchForNewMembers(1, 'us', 20);

		$this->assertCount(18, $results);

		$this->assertEquals('user0', $results[0]->getUID());
		$this->assertEquals('user1', $results[1]->getUID());
		$this->assertEquals('user14', $results[14]->getUID());
		$this->assertEquals('user17', $results[15]->getUID());
		$this->assertEquals('user18', $results[16]->getUID());
		$this->assertEquals('user20', $results[17]->getUID());
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

	public function testNotifyUser() {
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

	public function testNotifyUserRemoved() {
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

	public function testNotifyUserRoleChange() {
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

		$called = array();
		\OC::$server->getEventDispatcher()->addListener('\OCA\CustomGroups::changeRoleInGroup', function ($event) use (&$called) {
			$called[] = '\OCA\CustomGroups::changeRoleInGroup';
			array_push($called, $event);
		});
		$this->helper->notifyUserRoleChange(
			'anotheruser',
			['group_id' => 1, 'uri' => 'group1', 'display_name' => 'Group One'],
			['group_id' => 1, 'role' => Roles::BACKEND_ROLE_MEMBER]
		);

		$this->assertSame('\OCA\CustomGroups::changeRoleInGroup', $called[0]);
		$this->assertTrue($called[1] instanceof GenericEvent);
	}

	public function canCreateRolesProvider() {
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
	public function testCanCreateGroups($role, $restrictToSubAdmins, $expectedResult) {

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('customgroups', 'only_subadmin_can_create', 'false')
			->willReturn($restrictToSubAdmins ? 'true' : 'false');

		$this->groupManager->expects($this->any())
			->method('isAdmin')
			->with(self::CURRENT_USER)
			->willReturn($role === 'ocadmin');

		// TODO: swap for 10.1 as 10.0 doesn't provide this interface
		//$subadminManager = $this->createMock(ISubAdminManager::class);
		$subadminManager = $this->createMock(\OC\SubAdmin::class);
		$subadminManager->expects($this->any())
			->method('isSubAdmin')
			->with($this->user)
			->willReturn($role === 'subadmin');

		$this->groupManager->expects($this->any())
			->method('getSubAdmin')
			->willReturn($subadminManager);

		$this->assertEquals($expectedResult, $this->helper->canCreateGroups());
	}

	public function testIsGroupDisplayNameAvailableWhenDuplicatesAreAllowed() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('customgroups', 'allow_duplicate_names', 'false')
			->willReturn('true');

		$this->handler->expects($this->never())
			->method('getGroupsByDisplayName');

		$this->assertTrue($this->helper->isGroupDisplayNameAvailable('test'));
	}

	public function testIsGroupDisplayNameAvailableNoDuplicateExists() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('customgroups', 'allow_duplicate_names', 'false')
			->willReturn('false');

		$this->handler->expects($this->once())
			->method('getGroupsByDisplayName')
			->with('test')
			->willReturn([]);

		$this->assertTrue($this->helper->isGroupDisplayNameAvailable('test'));
	}

	public function testIsGroupDisplayNameAvailableDuplicateExists() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('customgroups', 'allow_duplicate_names', 'false')
			->willReturn('false');

		$this->handler->expects($this->once())
			->method('getGroupsByDisplayName')
			->with('test')
			->willReturn([['duplicate']]);

		$this->assertFalse($this->helper->isGroupDisplayNameAvailable('test'));
	}
}
