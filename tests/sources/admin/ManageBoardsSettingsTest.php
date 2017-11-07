<?php

/**
 * TestCase class for manage boards settings
 */
class TestManageBoardsSettings extends PHPUnit_Framework_TestCase
{
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		theme()->getTemplates()->loadLanguageFile('ManagePermissions', 'english', true, true);
	}

	/**
	 * Test the settings for admin search
	 */
	public function testSettings()
	{
		global $txt;

		$controller = new ManageBoards_Controller(new Event_Manager());
		$controller->pre_dispatch();
		$settings = $controller->settings_search();

		// Lets see some hardcoded setting for boards management...
		$this->assertNotNull($settings);
		$this->assertTrue(in_array(array('title', 'settings'), $settings));
		$this->assertTrue(in_array(array('permissions', 'manage_boards', 'helptext' => $txt['permissionhelp_manage_boards']), $settings));
		$this->assertTrue(in_array(array('check', 'countChildPosts'), $settings));
	}
}
