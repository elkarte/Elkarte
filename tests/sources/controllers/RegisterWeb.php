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

	/**
	 * Register like a bot, go to fast and fail
	 */
	public function testRegisterInValid()
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

		// Lets select register!
		$this->byName("regSubmit")->click();

		// Should fail for speed reasons
		$this->assertContains("You went through the registration process too quickly", $this->byCssSelector("div.errorbox")->text());
	}

	/**
	 * Click on register button and try to complete the process.
	 */
	public function testRegisterValid()
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
		$usernameInput = $this->byId('elk_autov_username');
		$usernameInput->clear();
		$this->keys($username);
		$this->assertEquals($username, $usernameInput->value());

		$emailInput = $this->byId('elk_autov_reserve1');
		$emailInput->clear();
		$this->keys($email);
		$this->assertEquals($email, $emailInput->value());

		$passInput = $this->byId('elk_autov_pwmain');
		$passInput->clear();
		$this->keys($password);
		$this->assertEquals($password, $passInput->value());

		$passInput = $this->byId('elk_autov_pwverify');
		$passInput->clear();
		$this->keys($password);

		// We need this to avoid our anti-spam feature
		sleep(8.5);

		// Lets select register!
		$this->byName("regSubmit")->click();

		// I hope :P
		$this->assertEquals("Registration Successful", $this->byCssSelector("h2.category_header")->text());
	}
}