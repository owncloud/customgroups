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

use OCP\Settings\ISettings;
use OCP\Template;

class SettingsPanel implements ISettings {

	public function __construct() {
	}

	public function getPanel() {
		// TODO: cache or add to info.xml ?
		$modules = json_decode(file_get_contents(__DIR__ . '/../js/modules.json'), true);
		$tmpl = new Template('customgroups', 'index');
		$tmpl->assign('modules', $modules);
		return $tmpl;
	}

	public function getPriority() {
		return 0;
	}

	public function getSectionID() {
		return 'customgroups';
	}

}
