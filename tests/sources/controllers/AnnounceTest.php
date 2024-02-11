<?php

/**
 * TestCase class for the Announce Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

use ElkArte\Controller\Announce;
use ElkArte\EventManager;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class AnnounceTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		$this->setSession();

		new ElkArte\Themes\ThemeLoader();
	}

	/**
	 * Test getting the group list for an announcement
	 */
	public function testSelectgroup()
	{
		global $board, $topic, $context;

		require_once(SUBSDIR . '/Topic.subs.php');

		// Set up
		$board = 1;
		$topic = 1;
		loadBoard();

		// Get the groups list
		$controller = new Announce(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Check
		$this->assertEquals('Welcome to ElkArte!', $context['topic_subject'], $context['topic_subject']);
		$this->assertNotEmpty($context['groups'], 'Groups is empty');
	}
}