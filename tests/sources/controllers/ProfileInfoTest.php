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
	function setUp()
	{
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Profile', 'english', true, true);
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		parent::tearDown();
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
