<?php

/**
 * TestCase class for the Display Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestDisplayIndex extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		// Load in the common items so the system thinks we have an active login
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
	 * Test getting the group list for an announcement
	 */
	public function testActionDisplay()
	{
		global $context, $board, $topic, $settings, $modSettings;

		$board = 1;
		$topic = 1;
		loadBoard();

		// Set up to exercise various areas
		$_req = \ElkArte\HttpReq::instance();
		$_req->query['prev_next'] = 'next';
		$modSettings['enablePreviousNext'] = 1;
		$settings['display_who_viewing'] = 1;

		// Get the controller
		$controller = new \ElkArte\Controller\Display(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->action_index();

		// Check the action ran
		$this->assertIsNumeric($context['real_num_replies']);
		$this->assertEquals(0, $context['start_from']);
		$this->assertEquals('Welcome to ElkArte!', $context['subject']);
		$this->assertEquals(5, count($context['mod_buttons']));

		// Give the renderer a check as well
		$controller = $context['get_message'][0];
		$message = $controller->{$context['get_message'][1]}();
		$this->assertEquals('Welcome to ElkArte!<br /><br />We hope you enjoy using this software and building your community.&nbsp; If you have any problems, please feel free to <a href="http://www.elkarte.net/index.php" class="bbc_link" target="_blank" rel="noopener noreferrer">ask us for assistance</a>.<br /><br />Thanks!<br />The ElkArte Community.', $message['body']);
	}
}