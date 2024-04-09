<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH.
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

use OCA\CustomGroups\Hooks;

$app = new \OCA\CustomGroups\Application();
$app->registerGroupBackend();
$app->registerNotifier();
$app->getContainer()->query(Hooks::class)->register();

if (!\defined('PHPUNIT') && !\OC::$CLI) {
	$pathInfo = \OC::$server->getRequest()->getPathInfo();
	if (\strstr($pathInfo, 'settings/') !== false) {
		// Temporarily fix icon until custom icons are supported
		\OCP\Util::addStyle('customgroups', 'icon');
	}
}

\OC::$server->getNavigationManager()->add(function () {
	$urlGenerator = \OC::$server->getURLGenerator();
	return [
		// the string under which your app will be referenced in owncloud
		'id' => 'customgroups',

		// sorting weight for the navigation. The higher the number, the higher
		// will it be listed in the navigation
		'order' => 90,

		// the route that will be shown on startup
		'href' => $urlGenerator->linkToRoute('settings.SettingsPage.getPersonal', ['sectionid' => 'customgroups']),

		// the icon that will be shown in the navigation
		// this file needs to exist in img/
		'icon' => $urlGenerator->imagePath('customgroups', 'app.svg'),

		// the title of your application. This will be used in the
		// navigation or on the settings page of your app
		'name' => \OC::$server->getL10N('customgroups')->t('Groups'),
	];
});
