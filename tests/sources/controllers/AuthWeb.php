<?php

/**
 * TestCase class for auth controller actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class TestAuth extends PHPUnit_Extensions_Selenium2TestCase
{
	/*
	 * Needed to provide test coverage results to phpunit
	 */
	protected $coverageScriptUrl = 'http://127.0.0.1/phpunit_coverage.php';

	/**
	 * You must provide a setUp() method for Selenium2TestCase
     *
	 * This method is used to configure the Selenium Server session, url/browser
	 */
	public function setUp()
	{
		// Set the browser to be used by Selenium, it must be available on localhost
		$this->setBrowser(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM2_BROWSER);

		// Set the base URL for the tests.
        $this->setBrowserUrl(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_HOST);
	}

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
		$this->byCssSelector('#button_login > a')->click();
		$this->assertEquals('Log in', $this->title());

		// Fill in the form, long hand style
		$usernameInput = $this->byId('user');
		$usernameInput->clear();
		$this->keys($username);
		$this->assertEquals($username, $usernameInput->value());

		$passwordInput = $this->byId('passwrd');
		$passwordInput->clear();
		$this->keys($password);
		$this->assertEquals($password, $passwordInput->value());

		// Submit it
        $this->byCssSelector("p > input.button_submit")->click();

		// Nope, huh? I hope :P
		$this->assertEquals('That username does not exist.', $this->byClassName('errorbox')->text());
	}

	/**
	 * Try to logout with action=logout
	 *
	 * Should fail sincw we have not logged in.
	 */
	public function testLogout()
	{
		$this->url('index.php?action=logout');

		// Yeah, you're not logged in and without no session token
		$this->assertEquals('An Error Has Occurred', $this->title());
	}
}