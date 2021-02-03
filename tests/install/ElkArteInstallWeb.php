<?php

/**
 * Elkarte Web Testing base
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 */

use PHPUnit\Extensions\Selenium2TestCase;

/**
 * ElkArteInstallWeb uses Selenium 2 for testing installation.
 *
 * It extends Selenium2TestCase and provides additional functions
 * as well as sets up the common environments for the tests
 *
 */
class ElkArteInstallWeb extends ElkArteWebSupport
{
	protected $forumPath = '.';
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * You must provide a setUp() method for Selenium2TestCase
	 * If you override this method, make sure the parent implementation is invoked.
	 *
	 * This method is used to configure the Selenium Server session, url/browser
	 */
	protected function setUp(): void
	{
		// Set the browser to be used by Selenium, it must be available on localhost
		$this->setBrowser($this->browser);

		parent::setUp();
	}

	/**
	 * Actually install ElkArte just like a user would
	 */
	public function testInstall()
	{
		// Missing files warning
		$this->url('install/install.php');
		$this->assertEquals('ElkArte Installer', $this->title());
		$this->assertStringContainsString('It looks like Settings.php and/or Settings_bak.php are missing', $this->byCssSelector('#main_screen form .information')->text());

		// Warning gone
		$this->prepareSettings();
		$this->url('install/install.php');
		$this->assertEquals('ElkArte Installer', $this->title());
		$this->assertStringNotContainsString('It looks like Settings.php and/or Settings_bak.php are missing', $this->byCssSelector('#main_screen form')->text());

		// Let's start
		$this->clickit('#contbutt');
		$this->assertEquals('Database Server Settings', $this->byCssSelector('#main_screen > h2')->text());

		// Filling the database settings
		$this->byId('db_server_input')->clear();
		$this->byId('db_server_input')->value('127.0.0.1');
		$this->byId('db_user_input')->value('root');
		$this->byId('db_name_input')->clear();
		$this->byId('db_name_input')->value('elkarte_test');
		$this->clickit('#contbutt');
		$this->assertEquals('Forum Settings', $this->byCssSelector('#main_screen > h2')->text(), $this->source());

		// Let the install create the DB
		$this->clickit('#contbutt');

		// Hang tight while the server does its thing
		sleep(15);
		$this->assertEquals('Populated Database', $this->byCssSelector('#main_screen > h2')->text(), $this->source());

		// All that is left is to create our admin account
		$this->clickit('#contbutt');
		$this->assertEquals('Create Your Account', $this->byCssSelector('#main_screen > h2')->text(), $this->source());
		$this->byCssSelector('#username')->value($this->adminuser);
		$this->byName('password1')->value($this->adminpass);
		$this->byName('password2')->value($this->adminpass);
		$this->byCssSelector('#email')->value('an_email_address@localhost.tld');
		$this->clickit('#contbutt');
		$this->assertStringContainsString('Congratulations', $this->byCssSelector('#main_screen > h2')->text(), $this->source());

		// Move the install dir so we can run more tests without redirecting back to install/update
		rename($this->forumPath  . '/install', $this->forumPath  . '/installdone');
	}

	/**
	 * Renames Settings and db_last_error.
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
		$this->timeouts()->implicitWait(10000);
		try
		{
			$selector = $this->byCssSelector($selector);
			$selector->click();
			sleep(1);
		}
		catch (Selenium2TestCase\WebDriverException $e)
		{
			// Pass through
		}
	}
}
