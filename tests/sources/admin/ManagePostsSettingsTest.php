<?php

/**
 * TestCase class for manage posts settings
 */
class TestManagePostsSettings extends PHPUnit_Framework_TestCase
{
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
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
		$this->assertTrue(in_array(array('check', 'removeNestedQuotes'), $settings));
		$this->assertTrue(in_array(array('int', 'spamWaitTime', 'postinput' => 'seconds'), $settings));
	}
}
