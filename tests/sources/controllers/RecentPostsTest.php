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
	protected function setUp(): void
	{
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english');
		$lang->load('Errors');
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
		$this->assertEquals('Welcome to ElkArte!', $context['posts'][1]['subject']);
		$this->assertEquals('recent', $context['sub_template']);
	}
}
