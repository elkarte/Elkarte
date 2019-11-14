<?php

/**
 * TestCase class for the Profile Info Controller
 */
class TestProfileInfo extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	public function setUp()
	{
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Profile', 'english', true, true);
	}

	/**
	 * Test the settings for profile summary
	 */
	public function testProfileSummary()
	{
		global $context, $modSettings;

		$controller = new \ElkArte\Controller\ProfileInfo(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Lets see some items loaded into context, there should some data
		$this->assertNotNull($context);
		$this->assertEquals($context['can_see_ip'], true);
		$this->assertEquals($modSettings['jquery_include_ui'], true);
	}
}
