<?php

/**
 * TestCase class for the BoardIndex Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestAuth extends ElkArteCommonSetupTest
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
	 * Test getting the group list for an announcement
	 */
	public function testActionLogin()
	{
		global $context;

		// Make us not logged in
		\ElkArte\User::$info->id = null;

		// Get the controller
		$controller = new \ElkArte\Controller\Auth(new \ElkArte\EventManager());
		$controller->action_login();

		// Check that a token was set.
		$this->assertEquals(strlen($context[ 'login_token']), 32);
		$this->assertNotNull($context[ 'login_token_var']);
	}
}