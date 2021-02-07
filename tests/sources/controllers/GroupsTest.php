<?php

use ElkArte\Controller\Groups;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;

/**
 * TestCase class for the Groups Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestGroups extends ElkArteCommonSetupTest
{
	protected $controller;
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global 	$modSettings;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		$modSettings['latestMember'] = 1;
		$modSettings['latestRealName'] = 'itsme';

		new ElkArte\Themes\ThemeLoader();
		\ElkArte\Themes\ThemeLoader::loadLanguageFile('Errors', 'english', true, true);
		\ElkArte\Themes\ThemeLoader::loadLanguageFile('ManageMembers', 'english', true, true);
		\ElkArte\Themes\ThemeLoader::loadLanguageFile('ModerationCenter', 'english', true, true);

		// Get the controller
		$this->controller = new Groups(new EventManager());
		$this->controller->setUser(User::$info);
		$this->controller->pre_dispatch();
	}

	protected function tearDown(): void
	{
		global $modSettings;

		parent::tearDown();
		unset($modSettings['latestMember'], $modSettings['latestRealName']);
	}

	/**
	 * Test getting the groups listing
	 */
	public function testActionList()
	{
		global $context;

		// Default action will be called, its list
		$this->controller->action_index();

		// Check the action ran
		$this->assertEquals('show_list', $context['sub_template']);
		$this->assertEquals(4, $context['group_lists']['num_columns']);
	}

	/**
	 * Test getting the members of a group
	 */
	public function teestActionMembers()
	{
		global $context;

		// Set the form
		$_req = HttpReq::instance();
		$_req->query['group'] = 1;
		$_req->query['start'] = 0;

		// List the members of group 1
		$this->controller->action_members();

		// Check the action ran
		$this->assertEquals(1, $context['members'][1]['id']);
		$this->assertEquals(1, $context['total_members']);
	}
}