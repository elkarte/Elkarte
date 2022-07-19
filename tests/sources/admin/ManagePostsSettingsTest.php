<?php

/**
 * TestCase class for manage posts settings
 */
class TestManagePostsSettings extends PHPUnit\Framework\TestCase
{
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp() : void
	{
		loadLanguage('Admin', 'english', true, true);
	}

	/**
	 * Test the settings for admin search
	 */
	public function testSettings()
	{
		$controller = new ManagePosts_Controller(new Event_Manager());
		$controller->pre_dispatch();
		$settings = $controller->settings_search();

		// Lets see some hardcoded setting for posts management...
		$this->assertNotNull($settings);
		$this->assertTrue(in_array(array('int', 'removeNestedQuotes', 'postinput' => '(0 to disable)'), $settings));
		$this->assertTrue(in_array(array('int', 'spamWaitTime', 'postinput' => 'seconds'), $settings));
	}
}
