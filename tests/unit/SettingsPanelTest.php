<?php
/**
 * @author Vincent Petry
 * @copyright 2017 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\CustomGroups\Tests\unit;

use OCA\CustomGroups\Service\Helper;
use OCA\CustomGroups\SettingsPanel;

/**
 * @package OCA\CustomGrouops\Tests\unit
 */
class SettingsPanelTest extends \Test\TestCase {

	/**
	 * @var SettingsPanel
	 */
	private $panel;

	/**
	 * @var Helper
	 */
	private $config;

	public function setUp() {
		parent::setUp();
		$this->helper = $this->createMock(Helper::class);
		$this->panel = new SettingsPanel($this->helper);
	}

	public function testGetSection() {
		$this->assertEquals('customgroups', $this->panel->getSectionID());
	}

	public function testGetPriority() {
		$this->assertTrue(is_integer($this->panel->getPriority()));
	}

	public function testGetPanel() {
		$this->helper->expects($this->once())
			->method('canCreateGroups')
			->willReturn(true);
		$templateHtml = $this->panel->getPanel()->fetchPage();
		$this->assertContains('<div id="customgroups" class="section" data-cancreategroups="true"></div>', $templateHtml);
	}

	public function testGetPanelNoCreatePerm() {
		$this->helper->expects($this->once())
			->method('canCreateGroups')
			->willReturn(false);
		$templateHtml = $this->panel->getPanel()->fetchPage();
		$this->assertContains('<div id="customgroups" class="section" data-cancreategroups="false"></div>', $templateHtml);
	}
}
