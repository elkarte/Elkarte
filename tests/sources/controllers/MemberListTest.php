<?php

use ElkArte\Controller\Memberlist;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;

/**
 * TestCase class for the MemberList Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestMemberListController extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
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
		$controller = new Memberlist(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		// With no options this will call the mlall action as well
		$controller->action_index();

		$this->assertCount(8, $context['columns'], count($context['columns']));
		$this->assertEquals(1, $context['num_members'], $context['num_members']);
		$this->assertEquals('t', $context['members'][1]['sort_letter'], $context['members'][1]['sort_letter']);
	}

	public function testActionMlSearch()
	{
		global $context;

		// Get the controller, call index
		$controller = new Memberlist(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		$req = HttpReq::instance();
		$req->query->search = 'admin';
		$req->query->fields = 'name, email';

		// Lets do some searching
		$req->query->sa = 'search';
		$controller->action_index();
		$this->assertStringContainsString('test_admin', $context['members'][1]['real_name'], $context['members'][1]['real_name']);
		$req->query->sa = null;
	}
}