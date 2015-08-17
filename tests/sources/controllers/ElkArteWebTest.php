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
 *
 */
abstract class ElkArteWebTest extends PHPUnit_Extensions_Selenium2TestCase
{
	protected $coverageScriptUrl = 'http://127.0.0.1/phpunit_coverage.php';
	protected $captureScreenshotOnFailure = true;
	protected $screenshotPath = '/var/www/screenshots';
	protected $width = 1280;
	protected $height = 800;
	protected $adminuser = 'test_admin';
	protected $adminpass = 'test_admin_pwd';

	/**
	 * You must provide a setUp() method for Selenium2TestCase
	 * If you override this method, make sure the parent implementation is invoked.
	 *
	 * This method is used to configure the Selenium Server session, url/browser
	 */
	protected function setUp()
	{
		// Set the browser to be used by Selenium, it must be available on localhost
		$this->setBrowser(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM2_BROWSER);

		// Set the base URL for the tests.
		if (!defined('PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_HOST'))
			DEFINE('PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_HOST', 'http://127.0.0.1/');
		$this->setBrowserUrl(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_HOST);

		parent::setUp();
	}

	/**
	 * Any common teardown functions
	 */
	protected function tearDown()
	{
		parent::tearDown();
	}

	/**
	 * Common setUpPage functions
	 * - Calls parent setUpPage
	 * - sets a window size good for screenshots etc.
	 */
	public function setUpPage()
	{
		parent::setUpPage();

		if ($this->width && $this->height)
			$this->currentWindow()->size(array('width' => $this->width, 'height' => $this->height));
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
		$this->byCssSelector("#password_login > input.button_submit")->click();

		// Should see the admin button now
		$this->assertContains('Admin', $this->byCssSelector('#button_admin > a')->text());
	}

	/**
	 * Enter the ACP
	 */
	public function enterACP()
	{
		// Select admin, enter password
		$this->byCssSelector("#button_admin > a")->click();
		$this->assertEquals('Administration Log in', $this->title());
		$this->byId('admin_pass')->value($this->adminpass);
		$this->byCssSelector("p > input.button_submit")->click();

		// Validate we are there
		$this->assertEquals("Administration Center", $this->title());
	}
}