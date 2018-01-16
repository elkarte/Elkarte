<?php

/**
 * Elkarte Web Testing base
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 */

/**
 * ElkArteWebTest is the base class for Selenium 2 functional test case classes.
 *
 * It extends PHPUnit_Extensions_Selenium2TestCase and provides additional functions
 * as well as sets up the common environments for all tests
 */
abstract class ElkArteWebTest extends \PHPUnit_Extensions_Selenium2TestCase
{
	protected $coverageScriptUrl = 'http://127.0.0.1/phpunit_coverage.php';

	// Screenshots will not be available with htmlunit since it does not render
	protected $captureScreenshotOnFailure = true;
	protected $screenshotPath = '/var/www/screenshots';

	protected $width = 1280;
	protected $height = 1024;
	protected $adminuser = 'test_admin';
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
	protected function setUp()
	{
		// Set the browser to be used by Selenium, it must be available on localhost
		$this->keysHolder = new PHPUnit_Extensions_Selenium2TestCase_KeysHolder();
		$this->setBrowser($this->browser);
		$this->setDesiredCapabilities(array('javascript_enabled' => true, 'javascript' => 1));
		$this->setPort($this->port);
		$this->setBrowserUrl('http://127.0.0.1/');

		parent::setUp();
	}

	/**
	 * Any common teardown functions
	 */
	protected function tearDown()
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
	public function adminLogin()
	{
		// Main page
		$this->url('index.php');
		$this->assertEquals('My Community - Index', $this->title());

		// Quick login
		$this->byName('user')->value($this->adminuser);
		$this->byName('passwrd')->value($this->adminpass);
		$this->clickit('#password_login > input[type="submit"]');

		// Should see the admin button now
		$this->assertContains('Admin', $this->byCssSelector('#button_admin > a')->text());
	}

	/**
	 * Enter the ACP
	 */
	public function enterACP()
	{
		// Select admin, enter password
		$this->clickit('#button_admin > a');
		$this->assertEquals('Administration Log in', $this->title());
		$this->byId('admin_pass')->value($this->adminpass);
		$this->clickit('input[type="submit"]');

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
		$this->byCssSelector($selector)->click();
		sleep(1);
	}
}