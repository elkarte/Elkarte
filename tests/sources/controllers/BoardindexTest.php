<?php

/**
 * TestCase class for the Attachment Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestBoardIndex extends ElkArteCommonSetupTest
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
	public function testActionBoardindex()
	{
		global $context;

		// Get the controller
		$controller = new \ElkArte\Controller\BoardIndex(new \ElkArte\EventManager());
		$controller->action_index();

		// Check
		$this->assertIsArray($context['latest_post']['member']);
		$this->assertEquals('boards_list', $context['sub_template']);
	}
}