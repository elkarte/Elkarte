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
	protected $browser = 'htmlunit';
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
		$this->setDesiredCapabilities(array('javascript_enabled' => true, 'javascript' => 1));
		$this->setPort($this->port);
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
		$check = $this->byCssSelector('#menu_nav')->text();
		$check = strpos($check, 'Log in');
		if ($check !== false)
		{
			// Use Quick login
			$this->byCssSelector('input[name="user"]')->value($this->adminuser);
			$this->byCssSelector('input[name="passwrd"]')->value($this->adminpass);
			$this->clickit('#password_login > input[type="submit"]');
		}

		// Should see the admin button now
		$this->assertStringContainsString('Admin', $this->byCssSelector('#button_admin > a')->text());
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
		$check = $this->byCssSelector('#menu_nav')->text();
		$check = strpos($check, 'Admin');
		if ($check === false)
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
			$this->clickit('.login > div > dl > input[type="submit"]');
		}

		// Should see the admin main menu button
		$this->assertStringContainsString('Admin', $this->byCssSelector('#button_admin > a')->text());
	}

	/**
	 * Logout when needed
	 */
	public function adminLogout()
	{
		// Seems this is throwing a session error so can't logout?
		if (isset($_SESSION['session_var'], $_SESSION['session_value']))
		{
			$this->url('/index.php?action=logout;' . $_SESSION['session_var'] . '=' . $_SESSION['session_value']);
			sleep(3);
			return;
		}

		$this->url('index.php');
	}

	/**
	 * Enter the ACP
	 */
	public function enterACP()
	{
		// Select admin
		$this->clickit('#button_admin > a');

		// Do we need to start the admin session?
		if ($this->title() === 'Administration Log in')
		{
			// enter password to access
			$this->assertEquals('Administration Log in', $this->title());
			$this->byId('admin_pass')->value($this->adminpass);
			$this->clickit('input[type="submit"]');
		}

		// Validate we are there
		$this->assertEquals('Administration Center', $this->title());
	}

	/**
	 * Click the selector and briefly pause.
	 *
	 * @param string $selector
	 */
	public function clickit($selector)
	{
		$this->timeouts()->implicitWait(5000);
		try
		{
			$selector = $this->byCssSelector($selector);
			$selector->click();
		}
		catch (Selenium2TestCase\WebDriverException $exception)
		{
			// continue on
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
