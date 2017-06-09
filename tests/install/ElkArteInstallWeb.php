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
 * ElkArteInstallWeb uses Selenium 2 for testing installation.
 *
 * It extends PHPUnit_Extensions_Selenium2TestCase and provides additional functions
 * as well as sets up the common environments for the tests
 *
 */
class ElkArteInstallWeb extends ElkArteWebTest
{
	protected $coverageScriptUrl = 'http://127.0.0.1/phpunit_coverage.php';
	protected $captureScreenshotOnFailure = true;
	protected $screenshotPath = '/var/www/screenshots';
	protected $forumPath = '/var/www/test';
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
		{
			DEFINE('PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_HOST', 'http://127.0.0.1/test');
		}
		$this->setBrowserUrl('http://127.0.0.1/test');

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
	 * - Calls parent setUpPage
	 * - sets a window size good for screenshots etc.
	 */
	public function setUpPage()
	{
		parent::setUpPage();

		$this->timeouts()->implicitWait(10000);

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
	public function testInstall()
	{
		// Missing files warning
		$this->url('test/install/install.php');
		$this->assertEquals('ElkArte Installer', $this->title());
		$this->assertContains('It looks like Settings.php and/or Settings_bak.php are missing', $this->byCssSelector('#main_screen form .information')->text());

		$this->prepareSettings();

		// Warning gone
		$this->url('test/install/install.php');
		$this->assertEquals('ElkArte Installer', $this->title());
		$this->assertNotContains('It looks like Settings.php and/or Settings_bak.php are missing', $this->byCssSelector('#main_screen form')->text());

		// Let's start
		$this->clickit('#contbutt');
		$this->assertEquals('Database Server Settings', $this->byCssSelector('#main_screen > h2')->text());

		// Filling the database settings
		$this->byCssSelector('#db_user_input')->click();
		$this->keys('root');
		$this->byCssSelector('#db_name_input')->click();
		$this->keys('elkarte_install_test');
		$this->clickit('#contbutt');

		$this->assertEquals('Forum Settings', $this->byCssSelector('#main_screen > h2')->text());
		$this->clickit('#contbutt');

		$this->assertEquals('Populated Database', $this->byCssSelector('#main_screen > h2')->text());
		$this->clickit('#contbutt');

		$this->assertEquals('Create Your Account', $this->byCssSelector('#main_screen > h2')->text());
		$this->byCssSelector('#username')->click();
		$this->keys($this->adminuser);
		$this->byName('password1')->value($this->adminpass);
		$this->byName('password2')->value($this->adminpass);
		$this->byCssSelector('#email')->click();
		$this->keys('an_email_address@localhost.tld');
		$this->clickit('#contbutt');

// 		$this->assertEquals('Critical Error!', $this->byCssSelector('.errorbox')->text());
		$this->assertEquals('Congratulations, the installation process is complete!', $this->byCssSelector('#main_screen > h2')->text());
	}

	/**
	 * Renames Settings and db_last_error
	 */
	protected function prepareSettings()
	{
		rename($this->forumPath . '/Settings.sample.php', $this->forumPath . '/Settings.php');
		rename($this->forumPath . '/Settings_bak.sample.php', $this->forumPath . '/Settings_bak.php');
		rename($this->forumPath . '/db_last_error.sample.txt', $this->forumPath . '/db_last_error.txt');
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
