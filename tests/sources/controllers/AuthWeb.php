<?php

/**
 * TestCase class for auth controller actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class TestAuthController extends ElkArteWebTest
{
	public function setUp()
	{
		parent::setUp();

		//require_once('./bootstrap.php');
		//new Bootstrap(false);
	}

	/**
	 * Hello, good.
	 */
	public function testAlive()
	{
		$this->url('index.php');
		$this->assertEquals('My Community - Index', $this->title());
	}

	/**
	 * Click on login button and try to login.
	 *
	 * It should fail with an error message.
	 * You can echo a page result with $source = $this->source(); to help debug
	 */
	public function testLogin()
	{
		$username = 'test';
		$password = 'ainttellin';

		// Goto the main page
		$this->url('index.php');

		// First lets log out as we have just come from install
		$this->clickit('#button_logout > a');

		// Now lets login
		$this->clickit('#button_login > a');
		$this->assertEquals('Log in', $this->title());

		// Fill in the form, long hand style
		$usernameInput = $this->byId('user');
		$usernameInput->clear();
		$usernameInput->value($username);
		$this->assertEquals($username, $usernameInput->value());

		$passwordInput = $this->byId('passwrd');
		$passwordInput->clear();
		$passwordInput->value($password);
		$this->assertEquals($password, $passwordInput->value());

		// Submit it
		$this->byId('frmLogin')->submit();

		// Nope, huh? I hope :P
		$this->assertEquals('That username does not exist.', $this->byClassName('errorbox')->text());
	}

	/**
	 * Try to logout with action=logout
	 *
	 * Should fail since we have not logged in.
	 */
	public function testLogout()
	{
		$this->url('index.php?action=logout');

		// Yeah, you're not logged in and without no session token
		$this->assertEquals('An Error Has Occurred', $this->title());
	}
}
