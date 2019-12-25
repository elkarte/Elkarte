<?php

/**
 * TestCase class for manage posts settings
 */
class TestManagePostsSettings extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	public function setUp()
	{
		parent::setUp();
		theme()->getTemplates()->loadLanguageFile('Admin', 'english', true, true);
	}

	/**
	 * Test the settings for admin search
	 */
	public function testSettings()
	{
		$controller = new \ElkArte\AdminController\ManagePosts(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$settings = $controller->settings_search();

		// Lets see some hardcoded setting for posts management...
		$this->assertNotNull($settings);
		$this->assertTrue(in_array(array('check', 'removeNestedQuotes'), $settings));
		$this->assertTrue(in_array(array('int', 'spamWaitTime', 'postinput' => 'seconds'), $settings));
	}
}
