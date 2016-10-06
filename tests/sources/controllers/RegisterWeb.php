<?php

/**
 * TestCase class for register controller actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class TestRegister_Controller extends ElkArteWebTest
{
	/*
	 * Used by teardown();
	 */
	public $registration_method;
	public $visual_verification_type;

	/**
	 * Initialize or add whatever is necessary for these tests
	 */
	public function setUp()
	{
		global $modSettings;

		// Let's remember about these
		$this->registration_method = $modSettings['registration_method'];
		$this->visual_verification_type = $modSettings['visual_verification_type'];

		// Set it to email activation
		updateSettings(array('registration_method' => 1));
		updateSettings(array('visual_verification_type' => 0));

		parent::setUp();
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		// Restore it to default
		updateSettings(array('registration_method' => $this->registration_method));
		updateSettings(array('visual_verification_type' => $this->visual_verification_type));
	}

	public function registerMember()
	{
		$username = 'testuser';
		$email = 'a.valid@emailaddress.tld';
		$password = 'ainttellin';

		// Register from the main menu
		$this->url('index.php');
		$this->byCssSelector('#button_register > a')->click();
		$this->assertEquals('Registration Agreement', $this->title());

		// Accept the agreement, we should see the Registration form
		$this->byCssSelector('#confirm_buttons input[type="submit"]')->click();
		$this->assertEquals('Registration Form', $this->title());

		// Fill out the registration form
		$this->byId('elk_autov_username')->value($username);
		$this->byId('elk_autov_reserve1')->value($email);
		$this->byId('elk_autov_pwmain')->value($password);
		$this->byId('elk_autov_pwverify')->value($password);
	}

	/**
	 * Register like a bot, go too fast and fail
	 */
	public function testRegisterInValid()
	{
		$this->registerMember();

		// Lets select register!
		$this->byName('regSubmit')->click();

		// Should fail for speed reasons
		$this->assertContains('You went through the registration process too quickly', $this->byCssSelector('div.errorbox')->text());
	}

	/**
	 * Click on register button and try to complete the process.
	 */
	public function testRegisterValid()
	{
		$this->registerMember();

		// We need this to avoid our anti-spam feature
		sleep(8.5);

		// Lets select register!
		$this->byName('regSubmit')->click();

		// Did it work? Did it work?
		$this->assertEquals('Registration Successful', $this->byCssSelector('h2.category_header')->text());
	}

	/**
	 * Delete the account
	 */
	public function testDeleteAccount()
	{
		require_once(SUBSDIR . '/Members.subs.php');
		$_SESSION['just_registered'] = 0;
		$regOptions = array(
			'interface' => 'admin',
			'username' => 'user49',
			'email' => 'user49@mydomain.com',
			'password' => 'user49',
			'password_check' => 'user49',
			'require' => 'nothing',
		);
		$memberID = registerMember($regOptions);
		$_SESSION['just_registered'] = 0;

		// Select login from the main page
		$this->url('index.php');
		$this->byCssSelector('#button_login > a')->click();
		$this->assertEquals('Log in', $this->title());

		// Fill in the form, long hand style
		$usernameInput = $this->byId('user');
		$usernameInput->clear();
		$this->keys('user49');

		$passwordInput = $this->byId('passwrd');
		$passwordInput->clear();
		$this->keys('user49');

		// Submit it
		$this->byCssSelector('.login > div > dl > input[type="submit"]')->click();
		$this->url('index.php?action=profile;area=deleteaccount');

		// Delete the account by using the mainprofile area.
		$this->assertEquals('Delete this account: user49', $this->title());
		$passwordInput = $this->byId('oldpasswrd');
		$passwordInput->clear();
		$this->keys('user49');
		$this->assertEquals('user49', $passwordInput->value());

		// Submit it
		$this->byCssSelector('input[type="submit"]')->click();
	}
}
