<?php

/**
 * TestCase class for the Groups Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestGroups extends ElkArteCommonSetupTest
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
	 * Test getting the groups listing
	 */
	public function testActionList()
	{
		global $context;

		// Get the controller
		$controller = new \ElkArte\Controller\Groups(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();

		// Default action will be called, its list
		$controller->action_index();

		// Check the action ran
		$this->assertEquals('show_list', $context['sub_template']);
		$this->assertEquals(4, $context['group_lists']['num_columns']);
	}

	/**
	 * Test getting the members of a group
	 */
	public function testActionMembers()
	{
		global $context;

		// Get the controller
		$controller = new \ElkArte\Controller\Groups(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();

		// Set the form
		$_req = \ElkArte\HttpReq::instance();
		$_req->query['group'] = 1;
		$_req->query['start'] = 0;

		// List the members of group 1
		$controller->action_members();

		// Check the action ran
		$this->assertEquals(1, $context['members'][1]['id']);
		$this->assertEquals(1, $context['total_members']);
	}
}