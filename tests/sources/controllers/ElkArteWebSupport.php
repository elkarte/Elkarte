<?php

/**
 * Elkarte Web Testing base
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 */

use ElkArte\User;
use ElkArte\UserInfo;
use PHPUnit\Extensions\Selenium2TestCase;

/**
 * ElkArteWebSupport is the base class for Selenium 2 functional test case classes.
 *
 * It extends Selenium2TestCase and provides additional functions
 * as well as sets up the common environments for all tests
 */
abstract class ElkArteWebSupport extends Selenium2TestCase
{
	protected $coverageScriptUrl = 'http://127.0.0.1/phpunit_coverage.php';
	protected $backupGlobalsBlacklist = ['user_info'];
	protected $width = 2560;
	protected $height = 1440;
	protected $adminuser = 'test_admin';
	protected $adminname = 'admin';
	protected $adminpass = 'test_admin_pwd';
	protected $browser = 'chrome';
	protected $port = 4444;
	protected $keysHolder;
	public $url = '';
	public $login = false;

	/**
	 * You must provide a setUp() method for Selenium2TestCase
	 * If you override this method, make sure the parent implementation is invoked.
	 *
	 * This method is used to configure the Selenium Server session, url/browser
	 */
	protected function setUp(): void
	{
		// Set the browser to be used by Selenium, it must be available on localhost
		$this->keysHolder = new Selenium2TestCase\KeysHolder();
		$this->setBrowser($this->browser);
		$this->setDesiredCapabilities([
			"chromeOptions" => [
				'w3c' => false,
			],
			"firefoxOptions" => [
				'headless' => true,
				'w3c' => false,
			]
		]);
		$this->setPort($this->port);
		$this->setHost('localhost');
		$this->setBrowserUrl('http://127.0.0.1/');
		$this::shareSession(true);
		$this::keepSessionOnFailure(true);

		parent::setUp();
	}

	/**
	 * Any common teardown functions
	 */
	protected function tearDown(): void
	{
		$this->adminLogout();
		$this->timeouts()->implicitWait(0);

		parent::tearDown();
	}

	/**
	 * Common setUpPage functions
	 *
	 * - Calls parent setUpPage
	 * - sets a window size good for screenshots etc.
	 * - Logins in the admin (optional)
	 * - Sets initial browser page (optional)
	 */
	public function setUpPage()
	{
		parent::setUpPage();

		$this->timeouts()->implicitWait(10000);

		if ($this->width && $this->height)
		{
			$this->setWindowSize();
			$this->currentWindow()->maximize();
		}

		if ($this->login)
		{
			$this->adminLogin();
		}

		if (!empty($this->url))
		{
			$this->url($this->url);
		}
	}

	/**
	 * Helper function should a specific window size be wanted for a test
	 */
	public function setWindowSize()
	{
		// Set a window size
		$this->currentWindow()->size(array('width' => $this->width, 'height' => $this->height));
	}

	/**
	 * Log in the admin via the quick login bar
	 */
	public function adminQuickLogin()
	{
		// Main page
		$this->url('index.php');
		$this->assertEquals('My Community - Index', $this->title());

		// Can we log in?
		$check = $this->byId('menu_nav')->text();
		$check = strpos($check, 'Log in');
		if ($check !== false)
		{
			// Use Quick login
			$this->byName('user')->click();
			$this->keys($this->adminuser);

			$this->byName('passwrd')->click();
			$this->keys($this->adminpass);

			$submit = $this->byId('password_login')->byCssSelector('input[type="submit"]');
			$submit->click();
		}

		// Should see the admin button now
		$this->assertStringContainsString('Admin', $this->byId('menu_nav')->text());
	}

	/**
	 * Log in the admin via the login form
	 */
	public function adminLogin()
	{
		// Main page
		$this->url('index.php');

		// Can we log in?
		$check = $this->byId('menu_nav')->text();
		if (strpos($check, 'Log in') !== false)
		{
			// Select login from the main page
			$this->url('index.php?action=login');
			$this->assertEquals('Log in', $this->title(), 'Unable to find the login forum');

			// Fill in the form, long hand style
			$this->byId('user')->click();
			$this->keys($this->adminuser);

			$this->byId('passwrd')->click();
			$this->keys($this->adminpass);

			// Submit it
			//$this->clickit('#password_login > input[type="submit"]');
			$this->byId('frmLogin')->submit();
		}
		else
		{
			return;
		}

		// Hang about until the page refreshes
		$this->url('index.php');
		$this->waitUntil(function ($testCase)
		{
			try
			{
				return $testCase->byId('menu_nav');
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				return false;
			}
		}, 5000);

		// Should see the admin main menu button
		$this->assertStringContainsString('Admin', $this->byId('menu_nav')->text(), $this->source());
	}

	/**
	 * Logout when needed
	 */
	public function adminLogout()
	{
		// Logout, if logged in
		$this->url('index.php');
		$check = $this->byId('menu_nav')->text();
		$check = strpos($check, 'Admin');

		// Logged in as Admin
		if ($check)
		{
			$link = $this->byId('button_logout')->byCssSelector('a')->attribute('href');
			$this->url($link);
		}
	}

	/**
	 * Enter the ACP
	 */
	public function enterACP()
	{
		// Select admin
		$this->url('index.php?action=admin');

		// Do we need to start the admin session?
		if ($this->title() === 'Administration Log in')
		{
			// enter password to access
			$this->assertEquals('Administration Log in', $this->title(), $this->source());
			$this->byId('admin_pass')->click();
			$this->keys($this->adminpass);
			$this->byId('frmLogin')->submit();
		}

		// Validate we are there
		$this->assertEquals('Administration Center', $this->title(), $this->source());
	}

	/**
	 * Wait for the selector and then ->click()
	 *
	 * @param string $selector
	 */
	public function clickit($selector)
	{
		$found = $this->waitUntil(function ($testCase) use ($selector) {
			try
			{
				$testCase->byCssSelector($selector);
				return true;
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				echo 'Selector ' . $selector . " was not found\n";
				return false;
			}
		}, 15000);

		if ($found)
		{
			// Make sure the element is in the viewport, then move cursor to it and click
			$element = $this->byCssSelector($selector);
			$this->moveto($element);
			$element->click();
		}
	}

	/**
	 * In some places we are not logged on, so we need to fake it to keep things moving along
	 */
	public function loadUserData()
	{
		global $context;

		$context['linktree'] = [];
		$context['session_id'] ='';
		$context['session_var'] = '';

		$userData = [
			'id' => 1,
			'ip' => long2ip(rand(0, 2147483647)),
			'language' => 'english',
			'is_admin' => true,
			'is_guest' => false,
			'username' => $this->adminuser,
			'query_wanna_see_board' => '1=1',
			'query_see_board' => '1=1',
			'is_moderator' => false,
			'email' => 'a@a.com',
			'ignoreusers' => [],
			'name' => $this->adminname,
			'smiley_set' => 'none',
			'time_offset' => 0,
			'time_format' => '',
			'possibly_robot' => false,
			'posts' => '15',
			'buddies' => [],
			'groups' => [0 => 1],
			'ignoreboards' => [],
			'avatar' => ['url' => '', 'name' => ''],
			'permissions' => ['admin_forum'],
		];

		User::$info = new UserInfo($userData);
		User::load();
	}
}
