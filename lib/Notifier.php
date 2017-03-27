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

namespace OCA\CustomGroups;

use OCP\L10N\IFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCA\CustomGroups\CustomGroupsDatabaseHandler;

class Notifier implements INotifier {

	/**
	 * @var IFactory
	 */
	protected $l10NFactory;

	/**
	 * @var CustomGroupsDatabaseHandler
	 */
	protected $handler;

	/**
	 * Notifier constructor.
	 *
	 * @param IFactory $l10NFactory
	 * @param CustomGroupsDatabaseHandler $handler
	 */
	public function __construct(IFactory $l10NFactory, CustomGroupsDatabaseHandler $handler) {
		$this->l10NFactory = $l10NFactory;
		$this->handler = $handler;
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 */
	public function prepare(INotification $notification, $languageCode) {
		if ($notification->getApp() !== 'customgroups') {
			throw new \InvalidArgumentException();
		}

		$l = $this->l10NFactory->get('customgroups', $languageCode);
		if ($notification->getObjectType() === 'customgroup') {
			if ($notification->getSubject() === 'added_member') {
				$notification->setParsedSubject(
					$l->t('Added to group "%1$s" by "%2$s".', $notification->getSubjectParameters())
				);
			}
			if ($notification->getMessage() === 'added_member') {
				$notification->setParsedMessage(
					$l->t('You have been added to the group "%1$s" by "%2$s".', $notification->getMessageParameters())
				);
			}
		}

		return $notification;
	}
}
