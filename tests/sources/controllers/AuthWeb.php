<?php

/**
 * TestCase class for auth controller actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class SupportAuthController extends ElkArteWebSupport
{
	protected function setUp(): void
	{
		parent::setUp();
	}

	/**
	 * Hello, good.
	 */
	public function testAlive()
	{
		$this->url('index.php');
		$this->assertEquals('My Community - Index', $this->title(), $this->source());
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

		$this->timeouts()->implicitWait(10000);
		$this->url('index.php');

		// We should be logged in after install ... should
		$check = $this->byId('menu_nav')->text();
		$check = strpos($check, 'Log in');
		if (!$check)
		{
			// Logged in, so log out
			$link = $this->byId('button_logout')->byCssSelector('a')->attribute('href');
			$this->url($link);
		}

		// Not logged in so go to the login page
		$this->url('index.php?action=login');

		// Now lets try to login with some bogus credentials
		$this->assertEquals('Log in', $this->title(), $this->source());

		// Fill in the form
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
