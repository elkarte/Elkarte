<?php

use ElkArte\AdminController\ManageBoards;
use ElkArte\EventManager;
use ElkArte\User;
use ElkArte\Languages\Loader;

/**
 * TestCase class for manage boards settings
 */
class TestManageBoardsSettings extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;

		parent::setUp();
		$lang = new Loader('english', $txt, database());
		$lang->load('ManagePermissions');
	}

	/**
	 * Test the settings for admin search
	 */
	public function testSettings()
	{
		global $txt;

		$controller = new ManageBoards(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$settings = $controller->settings_search();

		// Lets see some hardcoded setting for boards management...
		$this->assertNotNull($settings);
		$this->assertContains(array('title', 'settings'), $settings);
		$this->assertContains(array('permissions', 'manage_boards', 'helptext' => $txt['permissionhelp_manage_boards'], 'collapsed' => true), $settings);
		$this->assertContains(array('check', 'countChildPosts'), $settings);
	}
}
