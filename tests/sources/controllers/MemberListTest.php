<?php

/**
 * TestCase class for the MemberList Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestMemberListController extends ElkArteCommonSetupTest
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
	}

	/**
	 * Show the memberlist
	 */
	public function testActionIndexMembers()
	{
		global $context;

		// Get the controller, call index
		$controller = new \ElkArte\Controller\Memberlist(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();

		// With no options this will call the mlall action as well
		$controller->action_index();

		$this->assertEquals(count($context['columns']), 8, count($context['columns']));
		$this->assertEquals($context['num_members'], 1, $context['num_members']);
		$this->assertEquals($context['members'][1]['sort_letter'], 't', $context['members'][1]['sort_letter']);
	}

	public function testActionMlSearch()
	{
		global $context;

		// Get the controller, call index
		$controller = new \ElkArte\Controller\Memberlist(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();

		$req = \ElkArte\HttpReq::instance();
		$req->query->search = 'admin';
		$req->query->fields = 'name, email';

		// Lets do some searching
		$req->query->sa = 'search';
		//$controller->action_mlsearch();
		$this->assertStringContains('test_admin', $context['members'][1]['real_name'], $context['members'][1]['real_name']);
	}
}