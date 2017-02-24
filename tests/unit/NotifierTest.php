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

use OCP\Notification\IManager;
use OCP\L10N\IFactory;
use OCA\CustomGroups\Notifier;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;
use OCP\Notification\INotification;

/**
 * Class NotifierTest
 *
 * @package OCA\CustomGroups\Tests\Unit
 */
class NotifierTest extends \Test\TestCase {

	/**
	 * @var Notifier
	 */
	private $notifier;

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	private $handler;

	public function setUp() {
		parent::setUp();

		$this->handler = $this->createMock(CustomGroupsDatabaseHandler::class);

		$this->notifier = new Notifier(
			\OC::$server->getL10NFactory(),
			$this->handler
		);
	}

	public function testPrepare() {
		$notification = $this->createMock(INotification::class);

		$notification->method('getApp')->willReturn('customgroups');
		$notification->method('getObjectType')->willReturn('customgroup');
		$notification->method('getSubject')->willReturn('added_member');
		$notification->method('getMessage')->willReturn('added_member');
		$notification->method('getSubjectParameters')->willReturn(['group1', 'user1']);
		$notification->method('getMessageParameters')->willReturn(['group1', 'user1']);

		$notification->expects($this->once())
			->method('setParsedSubject')
			->with('Added to group "group1" by "user1".');
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('You have been added to the group "group1" by "user1".');

		$notification = $this->notifier->prepare($notification, 'en_US');
	}

}
