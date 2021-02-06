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
	protected $width = 1280;
	protected $height = 1024;
	protected $adminuser = 'test_admin';
	protected $adminname = 'admin';
	protected $adminpass = 'test_admin_pwd';
	protected $browser = 'chrome';
	protected $port = 4444;
	protected $keysHolder;

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
			]
		]);
		$this->setPort($this->port);
		$this->setHost('localhost');
		$this->setBrowserUrl('http://127.0.0.1/');

		parent::setUp();
	}

	/**
	 * Any common teardown functions
	 */
	protected function tearDown(): void
	{
		$this->timeouts()->implicitWait(0);

		parent::tearDown();
	}

	/**
	 * Common setUpPage functions
	 *
	 * - Calls parent setUpPage
	 * - sets a window size good for screenshots etc.
	 */
	public function setUpPage()
	{
		parent::setUpPage();

		$this->timeouts()->implicitWait(10000);

		if ($this->width && $this->height)
		{
			$this->setWindowSize();
		}
	}

	/**
	 * Helper function should a specific window size be wanted for a test
	 */
	public function setWindowSize()
	{
		// Set a window size large than the default
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
			$usernameInput = $this->byName('user');
			$usernameInput->value($this->adminuser);

			$passwordInput = $this->byName('passwrd');
			$passwordInput->value($this->adminpass);

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
		$this->assertEquals('My Community - Index', $this->title());

		// Are we already logged in as the admin? check by seeing Admin in the main menu
		$check = $this->byId('menu_nav')->text();
		$check = strpos($check, 'Admin');
		if (!$check)
		{
			// Select login from the main page
			$this->clickit('#button_login > a');
			$this->assertEquals('Log in', $this->title());

			// Fill in the form, long hand style
			$usernameInput = $this->byId('user');
			$usernameInput->clear();
			$usernameInput->value($this->adminuser);

			$passwordInput = $this->byId('passwrd');
			$passwordInput->clear();
			$passwordInput->value($this->adminpass);

			// Submit it
			$this->byId('frmLogin')->submit();
		}

		// Should see the admin main menu button
		$this->assertStringContainsString('Admin', $this->byId('menu_nav')->text());
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
			$this->byId('admin_pass')->value($this->adminpass);
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
				$selector = $testCase->byCssSelector($selector);

				return $selector;
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				echo 'Selector ' . $selector . " was not found\n";
				return false;
			}
		}, 15000);

		if ($found)
		{
			$this->moveto($found);
			$found->click();
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

		new ElkArte\Themes\ThemeLoader();
	}
}
