<?php

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
	public function setUp()
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Manual', 'english', false, true);
		theme()->getTemplates()->loadLanguageFile('Help', 'english', false, true);
	}

	public function tearDown()
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
		$controller = new \ElkArte\Controller\Help(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->action_index();

		// Check that the send topic template was set
		$this->assertEquals($context['sub_template'], 'manual');
	}

	/**
	 * Test some quickhelp
	 */
	public function testActionQuickhelp()
	{
		global $context;

		$req = \ElkArte\HttpReq::instance();
		$req->query->help = 'permissionname_like_posts_stats';

		$controller = new \ElkArte\Controller\Help(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->action_quickhelp();

		$this->assertEquals($context['help_text'], 'See like posts stats', $context['help_text']);
	}
}