<?php

/**
 * TestCase class for register controller actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class SupportRegisterController extends ElkArteWebSupport
{
	/*
	 * Used by teardown();
	 */
	public $registration_method;
	public $visual_verification_type;

	/**
	 * Initialize or add whatever is necessary for these tests
	 */
	protected function setUp(): void
	{
		global $modSettings;

		require_once('./bootstrap.php');
		new Bootstrap(false);

		// Let's remember about these
		$this->registration_method = $modSettings['registration_method'];
		$this->visual_verification_type = $modSettings['visual_verification_type'];

		// Set it to email activation
		updateSettings(array('registration_method' => 1));
		updateSettings(array('visual_verification_type' => 0));
		updateSettings(array('reg_verification' => 0));

		parent::setUp();
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
		// Restore it to default
		updateSettings(array('registration_method' => $this->registration_method));
		updateSettings(array('visual_verification_type' => $this->visual_verification_type));

		parent::tearDown();
	}

	/**
	 * Utility function to support function tests below
	 */
	public function registerMember()
	{
		$username = 'testuser';
		$email = 'valid@emailaddress.net';
		$password = 'ainttellin';

		// Register from the main menu
		$this->adminLogout();
		$this->url('index.php');
		$this->clickit('#button_register > a');
		$this->assertEquals('Registration Agreement', $this->title());

		// Accept the agreement, we should see the Registration form
		$this->clickit('#confirm_buttons input[name="accept_agreement"]');
		$this->assertEquals('Registration Form', $this->title());

		// Fill out the registration form
		$this->byId('elk_autov_username')->click();
		$this->keys($username);
		$this->byId('elk_autov_reserve1')->click();
		$this->keys($email);
		$this->byId('elk_autov_pwmain')->click();
		$this->keys($password);
		$this->byId('elk_autov_pwverify')->click();
		$this->keys($password);
	}

	/**
	 * Register like a bot, go too fast and fail
	 */
	public function testRegisterInValid()
	{
		$this->registerMember();

		// Let's select register!
		$this->byId('registration')->submit();

		// Should fail for speed reasons
		$this->assertStringContainsString('You went through the registration process too quickly', $this->byCssSelector('div.errorbox')->text(), $this->source());
	}

	/**
	 * Click on register button and try to complete the process.
	 */
	public function testRegisterValid()
	{
		$this->registerMember();

		// We need this to avoid our anti-spam feature
		sleep(9);

		// Let's select register!
		$this->byId('registration')->submit();

		// Did it work? Did it work?
		$this->assertEquals('Registration Successful', $this->byCssSelector('h2.category_header')->text());
	}

	/**
	 * User delete their account
	 */
	public function testDeleteAccount()
	{
		// Use standard functions and not GUI
		$this->loadUserData();

		// Register a member that we can delete
		require_once(SUBSDIR . '/Members.subs.php');
		$_SESSION['just_registered'] = 0;
		$regOptions = array(
			'interface' => 'admin',
			'username' => 'user49',
			'email' => 'user49@mydomain.com',
			'password' => 'user49',
			'password_check' => 'user49',
			'require' => 'nothing',
			'send_welcome_email' => false,
		);
		$memberID = registerMember($regOptions);
		$_SESSION['just_registered'] = 0;

		// Select login from the main page
		$this->url('index.php');
		$this->clickit('#button_login > a');
		$this->assertEquals('Log in', $this->title());

		// Fill in the form, long hand style
		$this->byId('user')->click();
		$this->keys('user49');

		$this->byId('passwrd')->click();
		$this->keys('user49');

		// Submit it
		$this->byId('frmLogin')->submit();
		$this->url('index.php?action=profile;area=deleteaccount');

		// Delete the account by using the main profile area.
		$this->assertEquals('Delete this account: user49', $this->title());
		$this->byId('oldpasswrd')->click();
		$this->keys('user49');

		// Submit it
		$this->clickit('input[type="submit"]');
		sleep(2);

		// Should be logged out
		$this->assertStringContainsString( 'Log in', $this->byId('menu_nav')->text());
	}
}
