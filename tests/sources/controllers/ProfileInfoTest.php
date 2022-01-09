<?php

use ElkArte\Controller\ProfileInfo;
use ElkArte\EventManager;
use ElkArte\User;

/**
 * TestCase class for the Profile Info Controller
 */
class TestProfileInfo extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english');
		$lang->load('Profile');
	}

	/**
	 * Test the settings for profile summary
	 */
	public function testProfileSummary()
	{
		global $context, $modSettings;

		$context['user']['is_owner'] = true;
		$context['profile_menu_name'] = 'menu_data_1';

		$controller = new ProfileInfo(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_summary();

		// Lets see some items loaded into context, there should some data
		$this->assertNotNull($context);
		$this->assertEquals(true, $context['can_see_ip']);
		$this->assertEquals(true, $modSettings['jquery_include_ui']);
	}
}
