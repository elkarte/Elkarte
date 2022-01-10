<?php

use ElkArte\Controller\Display;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;
use ElkArte\Languages\Loader;

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
	protected function setUp(): void
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english');
		$lang->load('Errors');
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
		$_req = HttpReq::instance();
		$_req->query['prev_next'] = 'next';
		$modSettings['enablePreviousNext'] = 1;
		$settings['display_who_viewing'] = 1;

		// Get the controller
		$controller = new Display(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_index();

		// Check the action ran
		$this->assertIsNumeric($context['real_num_replies']);
		$this->assertEquals(0, $context['start_from']);
		$this->assertEquals('Welcome to ElkArte!', $context['subject']);
		$this->assertEquals(5, count($context['mod_buttons']));

		// Give the renderer a check as well
		$controller = $context['get_message'][0];
		$message = $controller->{$context['get_message'][1]}();
		$this->assertEquals('Welcome to ElkArte!<br /><br />We hope you enjoy using this software and building your community.&nbsp; If you have any problems, please feel free to <a href="https://www.elkarte.net/index.php" class="bbc_link" target="_blank" rel="noopener noreferrer">ask us for assistance</a>.<br /><br />Thanks!<br />The ElkArte Community.', $message['body']);
	}
}