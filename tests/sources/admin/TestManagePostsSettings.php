<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');
require_once(ADMINDIR . '/ManagePosts.controller.php');

/**
 * TestCase class for manage posts settings
 */
class TestManagePostsSettings extends UnitTestCase
{
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		//
	}

	/**
	 * Test the settings for admin search
	 */
	public function testSettings()
	{
		// trick
		loadLanguage('Admin');
		
		$controller = new ManagePosts_Controller();
		$settings = $controller->settings();
		
		// Lets see some hardcoded setting for posts management...
		$this->assertNotNull($settings);
		$this->assertTrue(in_array(array('check', 'removeNestedQuotes'), $settings));
		$this->assertTrue(in_array(array('check', 'disable_wysiwyg'), $settings));
		$this->assertTrue(in_array(array('int', 'spamWaitTime', 'postinput' => 'seconds'), $settings));
	}
}
