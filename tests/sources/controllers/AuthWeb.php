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
	/**
	 * Called just before a test run, but after setUp() use to
	 * auto login or set a default page for initial browser view
	 */
	public function setUpPage()
	{
		$this->url = 'index.php';
		$this->login = false;
		parent::setUpPage();
	}

	protected function setUp(): void
	{
		parent::setUp();
	}

	/**
	 * Hello, good.
	 */
	public function testAlive()
	{
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
		$this->byId('user')->click();
		$this->keys($username);

		$this->byId('passwrd')->click();
		$this->keys($password);

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
