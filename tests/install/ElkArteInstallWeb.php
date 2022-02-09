<?php

/**
 * Elkarte Web Testing base
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 */

/**
 * ElkArteInstallWeb
 *
 * Installs the forum using the install script
 */
class ElkArteInstallWeb extends ElkArteWebSupport
{
	protected $forumPath = '.';
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Called just before a test run, but after setUp() use to
	 * auto login or set a default page for initial browser view
	 */
	public function setUpPage()
	{
		$this->url = 'install/install.php';
		parent::setUpPage();
	}

	/**
	 * Actually install ElkArte just like a user would
	 */
	public function testInstall()
	{
		$this->waitUntil(function ($testCase)
		{
			try
			{
				return $testCase->title() === 'ElkArte Installer';
			}
			catch (PHPUnit\Extensions\Selenium2TestCase\WebDriverException $e)
			{
				return false;
			}
		}, 10000);

		// Missing files warning
		$this->assertEquals('ElkArte Installer', $this->title(), 'step1' . $this->source());
		$this->assertStringContainsString('It looks like Settings.php and/or Settings_bak.php are missing', $this->byCssSelector('#main_screen form .information')->text(), $this->source());

		// Warning gone
		$this->prepareSettings();
		$this->url('install/install.php');
		$this->assertEquals('ElkArte Installer', $this->title(), 'step 2' . $this->source());
		$this->assertStringNotContainsString('It looks like Settings.php and/or Settings_bak.php are missing', $this->byCssSelector('#main_screen form')->text(), $this->source());

		// Let's start
		$this->clickit('#contbutt');
		$this->assertEquals('Database Server Settings', $this->byCssSelector('#main_screen > h2')->text(), $this->source());

		// Filling the database settings
		$select = $this->select($this->byId('db_type_input'));
		$select->selectOptionByValue('mysqli');
		$this->assertEquals('MySQL', $select->selectedLabel());
		$this->byId('db_server_input')->clear();
		$this->byId('db_server_input')->value('127.0.0.1');
		$this->byId('db_user_input')->value('root');
		$this->byId('db_passwd_input')->clear();
		$this->byId('db_name_input')->clear();
		$this->byId('db_name_input')->value('elkarte_test');
		$this->byId('db_prefix_input')->clear();
		$this->byId('db_prefix_input')->value('elkarte_');
		$this->byId('db_settings')->submit();

		// Let the install create the DB
		$this->assertEquals('Forum Settings', $this->byCssSelector('#main_screen > h2')->text(), $this->source());
		$this->byId('forum_settings')->submit();
		sleep(15);
		$this->assertEquals('Populated Database', $this->byCssSelector('#main_screen > h2')->text(), $this->source());

		// All that is left is to create our admin account
		$this->byId('populate_db')->submit();
		$this->assertEquals('Create Your Account', $this->byCssSelector('#main_screen > h2')->text(), $this->source());
		$this->byid('username')->value($this->adminuser);
		$this->byid('password1')->value($this->adminpass);
		$this->byid('password2')->value($this->adminpass);
		$this->byid('email')->value('an_email_address@localhost.tld');
		$this->clickit('#contbutt');
		//$this->byId('admin_account')->submit();
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
}