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
	protected $backupGlobalsBlacklist = ['user_info'];
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

		// Select login from the main page
		$this->url('index.php');
		$this->clickit('#button_login > a');
		$this->assertEquals('Log in', $this->title());

		// Fill in the form, long hand style
		$usernameInput = $this->byId('user');
		$usernameInput->clear();
		$usernameInput->value($username);
		//$this->keys($username);
		$this->assertEquals($username, $usernameInput->value());

		$passwordInput = $this->byId('passwrd');
		$passwordInput->clear();
		$passwordInput->value($password);
		//$this->keys($password);
		$this->assertEquals($password, $passwordInput->value());

		// Submit it
		$this->clickit('.login > div > dl > input[type="submit"]');

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
