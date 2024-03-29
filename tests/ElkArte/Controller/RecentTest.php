<?php

/**
 * TestCase class for recent posts
 */

namespace ElkArte\Controller;

use ElkArte;
use ElkArte\EventManager;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class RecentTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
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
