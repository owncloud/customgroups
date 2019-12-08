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

use OCA\CustomGroups\Service\MembershipHelper;
use OCA\CustomGroups\SettingsPanel;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

/**
 * @package OCA\CustomGrouops\Tests\unit
 */
class SettingsPanelTest extends \Test\TestCase {

	/**
	 * @var SettingsPanel
	 */
	private $panel;

	/** @var MembershipHelper | \PHPUnit\Framework\MockObject\MockObject */
	private $helper;
	/** @var IGroupManager | \PHPUnit\Framework\MockObject\MockObject */
	private $groupManager;
	/** @var IUserSession | \PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var IConfig | \PHPUnit\Framework\MockObject\MockObject */
	private $config;

	public function setUp(): void {
		parent::setUp();
		$this->helper = $this->createMock(MembershipHelper::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->config = $this->createMock(IConfig::class);
		$this->panel = new SettingsPanel($this->helper, $this->groupManager,
			$this->userSession, $this->config);
	}

	public function testGetSection() {
		$this->assertEquals('customgroups', $this->panel->getSectionID());
	}

	public function testGetPriority() {
		$this->assertTrue(\is_integer($this->panel->getPriority()));
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

	public function testGuestUserNotDisplayCustomGroup() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('user1');

		$this->userSession->method('getUser')
			->willReturn($user);
		$this->config->method('getSystemValue')
			->with('customgroups.disallowed-groups', null)
			->willReturn(['testing','guest_app']);

		$group = $this->createMock(IGroup::class);
		$group->method('getGID')
			->willReturn('guest_app');
		$this->groupManager->method('get')
			->willReturnOnConsecutiveCalls(null, $group);
		$this->groupManager->method('isInGroup')
			->willReturn(true);

		$this->assertEquals('', $this->panel->getSectionID());
	}
}
