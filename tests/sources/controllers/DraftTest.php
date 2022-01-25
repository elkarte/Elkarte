<?php

use ElkArte\Controller\Draft;
use ElkArte\EventManager;
use ElkArte\User;
use ElkArte\Languages\Loader;

/**
 * TestCase class for the Draft Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestDraft extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;
		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Drafts');
	}

	/**
	 * Test trying to show a list of drafts, there are none but who cares
	 */
	public function testActionShowProfileDrafts()
	{
		global $context;

		$context['profile_menu_name'] = 'profile_menu';
		$context['profile_menu']['tab_data'] = [];

		// Get the controller, call draft listing the long way
		$controller = new Draft(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_post();

		// Check that we set the sub template
		$this->assertEquals('showDrafts', $context['sub_template']);
	}

	/**
	 * Test trying to show a list of PM drafts, there are none but who cares
	 */
	public function testActionShowPmDrafts()
	{
		global $context;

		// Get the controller, call draft listing the long way
		$controller = new Draft(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_showPMDrafts();

		// Check that we set the sub template
		$this->assertEquals('showPMDrafts', $context['sub_template']);
	}
}