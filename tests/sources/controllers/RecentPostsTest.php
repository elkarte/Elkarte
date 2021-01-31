<?php

use ElkArte\Controller\Recent;
use ElkArte\EventManager;
use ElkArte\User;

/**
 * TestCase class for recent posts
 */
class TestRecentPosts extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	public function setUp()
	{
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Errors', 'english', true, true);
	}

	/**
	 * Test the settings for recent post listing
	 */
	public function testRecent()
	{
		global $context;

		$controller = new Recent(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_recent();

		// Lets see some items loaded into context, there should be the first post
		$this->assertNotNull($context);
		$this->assertEquals($context['posts'][1]['subject'], 'Welcome to ElkArte!');
		$this->assertEquals($context['sub_template'], 'recent');
	}
}
