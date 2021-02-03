<?php

use ElkArte\Controller\Auth;
use ElkArte\EventManager;
use ElkArte\User;

/**
 * TestCase class for the Auth Controller
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
	protected function setUp(): void
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
	}

	/**
	 * Test trying to login ... limited since we can't set a cookie without a
	 * headers violation.  Now AuthWeb test does this but the code coverage is
	 * not captured for some reason.
	 */
	public function testActionLogin()
	{
		global $context;

		// Make us not logged in
		User::$info->id = null;

		// Get the controller, call login via index
		$controller = new Auth(new EventManager());
		$controller->action_index();

		// Check that a token was set.
		$this->assertEquals(32, strlen($context['login_token']));
		$this->assertNotNull($context['login_token_var']);

		// Lets try to login with some bogus stuff
		User::$info->is_guest = true;
		$this->setSession();
		$_POST['user'] = 'somebotie';
		$_POST['passwrd'] = 'password';
		$controller->action_login2();

		// We should fail
		$this->assertEquals('That username does not exist.', $context['login_errors'][0]);
	}

	/**
	 * Give them the boot
	 */
	public function testactionKickguest()
	{
		global $context;

		// Get the controller, call login via index
		$controller = new Auth(new EventManager());
		$controller->action_kickguest();

		$this->assertEquals('Log in', $context['page_title']);
	}
}