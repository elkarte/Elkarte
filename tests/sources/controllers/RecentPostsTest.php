<?php

/**
 * TestCase class for recent posts
 */
class TestRecentPosts extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Errors', 'english', true, true);
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
	 * Test the settings for recent post listing
	 */
	public function testRecent()
	{
		global $context;

		$controller = new \ElkArte\Controller\Recent(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_recent();

		// Lets see some items loaded into context, there should be the first post
		$this->assertNotNull($context);
		$this->assertEquals($context['posts'][1]['subject'], 'Welcome to ElkArte!');
		$this->assertEquals($context['sub_template'], 'recent');
	}
}
