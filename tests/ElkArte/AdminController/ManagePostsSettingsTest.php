<?php

/**
 * TestCase class for manage posts settings
 */

namespace ElkArte\AdminController;

use ElkArte\EventManager;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class ManagePostsSettingsTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;
		parent::setUp();
		$lang = new Loader('english', $txt, database());
		$lang->load('Admin');
	}

	/**
	 * Test the settings for admin search
	 */
	public function testSettings()
	{
		$controller = new ManagePosts(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$settings = $controller->settings_search();

		// Lets see some hardcoded setting for posts management...
		$this->assertNotNull($settings);
		$this->assertContains(array('int', 'removeNestedQuotes', 'postinput' => '(0 to allow none)'), $settings);
		$this->assertContains(array('int', 'spamWaitTime', 'postinput' => 'seconds'), $settings);
		$this->assertContains(array('check', 'enableCodePrettify'), $settings);
	}
}
