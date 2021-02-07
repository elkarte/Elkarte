<?php

use ElkArte\Controller\Help;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;

/**
 * TestCase class for the Help Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestHelpController extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		new ElkArte\Themes\ThemeLoader();
		\ElkArte\Themes\ThemeLoader::loadLanguageFile('Manual', 'english', false, true);
		\ElkArte\Themes\ThemeLoader::loadLanguageFile('Help', 'english', false, true);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	/**
	 * Cuz we all need a little help
	 */
	public function testActionHelp()
	{
		global $context;

		// Get the controller, call index
		$controller = new Help(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_index();

		// Check that the send topic template was set
		$this->assertEquals('manual', $context['sub_template']);
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