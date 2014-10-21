<?php

/**
 * TestCase class for ACP add group controller actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class TestACPGroup extends PHPUnit_Extensions_Selenium2TestCase
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

	/**
	 * Admin logiin via quick login
	 * Enter ACP area after second password challange
	 */
	public function testAcpLogin()
	{
		// Main page
		$this->url('index.php');
        $this->assertEquals('My Community - Index', $this->title());

		// Quick login
		$this->byName('user')->value("test_admin");
		$this->byName('passwrd')->value("test_admin_pwd");
		$this->byCssSelector("#password_login > input.button_submit")->click();

		// Select admin, enter password
		$this->byCssSelector("#button_admin > a")->click();
		$this->assertEquals('Administration Log in', $this->title());
		$this->byId('admin_pass')->value('test_admin_pwd');

		// Validate we are there
		$this->byCssSelector("p > input.button_submit")->click();
		$this->assertEquals("Administration Center", $this->title());
	}

	/**
	 * Add a group
	 *
	 * Depends on the fact that we are logged in to the ACP
	 */
	public function testAcpAddGroup()
	{
		$this->acpLogin();

		// Start at the add member group page
		$this->url('index.php?action=admin;area=membergroups;sa=add');
		$this->assertEquals("Add Member Group", $this->title());

		// Fill in the new group form with initial values
		$this->ById('group_name_input')->value('Test Group');
		$this->ById('checkall_check')->click();
		$this->byCssSelector('input.right_submit')->click();

		// Group Details, give it a description, icons, etc
		$this->assertEquals("Edit Membergroup", $this->title());
		$this->ById('group_desc_input')->value('The Test Group');
		$this->ById('icon_count_input')->value('2');
		$this->ByName('save')->click();

		// We should be back at the group listing, the new group should be there
		$this->assertEquals("Manage Membergroups", $this->title());
		$this->assertContains('Test Group', $this->byCssSelector('#regular_membergroups_list > #regular_membergroups_list')->text());
	}

	/**
	 * Try to logout with action=logout
	 *
	 * This does not seem to work yet. Rename to test when working
	 */
	public function Logout()
	{
		$this->acpLogin();

		// In order to interact with hidden elements, we need to expose them.
		// Here we hover (move) over the profile button
		// so the logout button is visable, such that we can click it.
		$this->timeouts()->implicitWait(10000);
		$this->moveto(array(
			'element' => $this->byId('button_profile'),
			'xoffset' => 10,
			'yoffset' => 10,
			)
		);
		$this->byCssSelector('#button_logout > a')->click();
		$this->assertContains('Log In', $this->byCssSelector('#button_login > a')->text());
	}

	/**
	 * Helper function, simply logs in to the ACP
	 */
	public function acpLogin()
	{
		$this->url('index.php');
		$this->byName('user')->value("test_admin");
		$this->byName('passwrd')->value("test_admin_pwd");
		$this->byCssSelector('#password_login > input.button_submit')->click();
		$this->byCssSelector('#button_admin > a')->click();
		$this->byId('admin_pass')->value('test_admin_pwd');
		$this->byCssSelector("p > input.button_submit")->click();
	}
}