<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2022, ownCloud GmbH
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

namespace OCA\CustomGroups\Service;

use OCA\Guests\Controller\UsersController;
use OCA\Guests\Mail;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\QueryException;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\IConfig;

class GuestIntegrationHelper {

	/**
	 * @var IAppManager
	 */
	private $appManager;
	/**
	 * @var IMailer
	 */
	private $mailer;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IUserSession
	 */
	private $userSession;

	public function __construct(
		IAppManager $appManager,
		IMailer $mailer,
		IUserManager $userManager,
		IUserSession $userSession,
		IConfig $config
	) {
		$this->appManager = $appManager;
		$this->mailer = $mailer;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->userSession = $userSession;
	}

	public function canBeGuest(string $email): bool {
		if (!$this->appManager->isEnabledForUser('guests')) {
			return false;
		}
		if (!$this->mailer->validateMailAddress($email)) {
			return false;
		}

		# in addition, make sure the domain holds at least one . so it matches the guests app logic
		[$localPart, $domain] = explode('@', $email, 2);
		if (\strpos($domain, '.') === false) {
			return false;
		}

		# test if the correct guest app version is used
		$mail = $this->getGuestMail();
		if ($mail && !method_exists($mail, 'sendGuestPlainInviteMail')) {
			# TODO: log error
			return false;
		}
		# test if domain is black listed
		$controller = $this->getGuestUsersController();
		if ($controller && method_exists($controller, 'isDomainBlocked')) {
			return !$controller->isDomainBlocked($email);
		}
		return true;
	}

	public function createGuest(string $userId): ?IUser {
		if (!$this->appManager->isEnabledForUser('guests')) {
			return null;
		}
		$controller = $this->getGuestUsersController();
		$mail = $this->getGuestMail();
		if ($controller && $mail) {
			$resp = $controller->create($userId, '');
			if ($resp->getStatus() === 201) {
				$user = $this->userManager->get($userId);
				if (!$user) {
					return null;
				}
				$registerToken = $this->config->getUserValue(
					$userId,
					'guests',
					'registerToken',
					null
				);

				try {
					if ($registerToken) {
						$uid = $this->userSession->getUser()->getUID();

						// send invitation
						$mail->sendGuestPlainInviteMail(
							$user->getUID(),
							$uid,
							$registerToken
						);
					}

					return $user;
				} catch (DoesNotExistException|\Exception $ex) {
				}
			}
		}
		return null;
	}

	private function getGuestUsersController(): ?UsersController {
		try {
			return \OC::$server->query(UsersController::class);
		} catch (QueryException $e) {
		}
		return null;
	}

	private function getGuestMail(): ?Mail {
		try {
			return \OC::$server->query(Mail::class);
		} catch (QueryException $e) {
		}
		return null;
	}
}
