<?php

/**
 * TestCase class for the Help Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

use ElkArte\Controller\Help;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class HelpTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Help');
	}

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	/**
	 * Test some quickhelp
	 */
	public function testActionQuickhelp()
	{
		global $context;

		$req = HttpReq::instance();
		$req->query->help = 'permissionname_like_posts_stats';

		$controller = new Help(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_quickhelp();

		$this->assertEquals('See like posts stats', $context['help_text'], $context['help_text']);
	}
}