<?php

/**
 * TestCase class for the BoardIndex Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

namespace ElkArte\Controller;

use ElkArte;
use ElkArte\EventManager;
use tests\ElkArteCommonSetupTest;

class BoardIndexTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
	}

	/**
	 * Test getting the group list for an announcement
	 */
	public function testActionBoardindex()
	{
		global $context;

		// Get the controller
		$controller = new BoardIndex(new EventManager());
		$controller->action_index();

		// Check
		$this->assertIsArray($context['latest_post']['member']);
		$this->assertEquals('boards_list', $context['sub_template']);
	}
}