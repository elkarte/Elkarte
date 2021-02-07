<?php

use ElkArte\AdminController\ManagePosts;
use ElkArte\EventManager;
use ElkArte\User;

/**
 * TestCase class for manage posts settings
 */
class TestManagePostsSettings extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		parent::setUp();
		\ElkArte\Themes\ThemeLoader::loadLanguageFile('Admin', 'english', true, true);
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
		$this->assertTrue(in_array(array('check', 'removeNestedQuotes'), $settings));
		$this->assertTrue(in_array(array('int', 'spamWaitTime', 'postinput' => 'seconds'), $settings));
	}
}
