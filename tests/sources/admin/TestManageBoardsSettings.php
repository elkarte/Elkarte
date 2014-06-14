<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');

/**
 * TestCase class for manage boards settings
 */
class TestManageBoardsSettings extends UnitTestCase
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
		global $txt;

		$controller = new ManageBoards_Controller();
		$settings = $controller->settings_search();

		// Lets see some hardcoded setting for boards management...
		$this->assertNotNull($settings);
		$this->assertTrue(in_array(array('title', 'settings'), $settings));
		$this->assertTrue(in_array(array('permissions', 'manage_boards', 'helptext' => $txt['permissionhelp_manage_boards']), $settings));
		$this->assertTrue(in_array(array('check', 'countChildPosts'), $settings));
	}
}
