<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . 'simpletest/web_tester.php');

/**
 * TestCase class for auth controller actions
 * Testing of web pages requests
 */
class TestAuth extends WebTestCase
{
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		global $scripturl;

		// it'd be kinda difficult without this :P
		$this->scripturl = $scripturl;
	}

	/**
	 * Click on login button and try to login.
	 * It should fail with an error message.
	 */
	function testLogin()
	{
		$this->get($this->scripturl);
		$this->click('Login');
		$this->assertTitle('Login');
		$this->assertText('Login');

		// Lets login!
		$this->setField('user', 'test');
		$this->setField('passwrd', 'ainttellin');

		// Set some hidden fields or not

		$this->click("Login");

		// Nope, huh? I hope :P
		$this->assertTitle('An Error Has Occurred');
	}

	/**
	 * action=logout
	 * Should fail with no session.
	 */
	function testLogout()
	{
		$this->get($this->scripturl . '?action=logout');
		$this->click('Logout');

		// yeah, you're not logged in and without no session token
		$this->assertTitle('An Error Has Occurred');
	}
}
