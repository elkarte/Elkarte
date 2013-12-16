<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');
require_once(TESTDIR . 'simpletest/web_tester.php');

/**
 * TestCase class for register controller actions
 * Testing of web pages requests
 */
class TestRegistration extends WebTestCase
{
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		global $scripturl, $modSettings;

		// it'd be kinda difficult without this :P
		$this->scripturl = $scripturl;

		// Let's remember about these
		$this->registration_method = $modSettings['registration_method'];
		$this->visual_verification_type = $modSettings['visual_verification_type'];

		// Set it to email activation
		updateSettings(array('registration_method' => 1));
		updateSettings(array('visual_verification_type' => 0));

		$this->restart();
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	function tearDown()
	{
		// Restore it to default
		updateSettings(array('registration_method' => $this->registration_method));
		updateSettings(array('visual_verification_type' => $this->visual_verification_type));
	}

	/**
	 * Click on register button and try to complete the process.
	 */
	function testRegisterValid()
	{
		global $modSettings;

		$this->get($this->scripturl);
		$this->click('Register');
		$this->assertTitle('Registration Agreement');
		$this->assertText('Registration Agreement');

		$this->clickSubmit("I accept the terms of the agreement.");

		$this->assertText('Registration Form');

		// Set some fields
		$this->setField('user', 'testuser');
		$this->setField('email', 'a.valid@emailaddress.tld');
		$this->setField('passwrd1', 'ainttellin');
		$this->setField('passwrd2', 'ainttellin');

		// We need this to avoid out anti-spam feature
		sleep(8.5);

		// Lets register!
		$this->clickSubmitByName("regSubmit");

		// Nope, huh? I hope :P
		$this->assertText('Registration Successful');
	}
}
