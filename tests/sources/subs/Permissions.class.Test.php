<?php

/**
 * TestCase class for Permissions Class.
 */

class TestPermissionsClass extends PHPUnit_Framework_TestCase
{
	public $permissionsObject;
	private $illegal_permissions = array();
	private $illegal_guest_permissions = array();

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{

	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{

	}

	/**
	 * Load and verify default illegal permissions
	 */
	public function testDefaultIllegalPermissions()
	{
		$permissionsObject = new Permissions;
		$this->illegal_permissions = $permissionsObject->getIllegalPermissions();

		foreach (array('admin_forum', 'manage_membergroups', 'manage_permissions') as $check)
		{
			$valid = in_array($check, $this->illegal_permissions);
			$this->assertTrue($valid, $check);
		}
	}

	/**
	 * Load and verify some things guests just can't do
	 */
	public function testDefaultIllegalGuestPermissions()
	{
		$permissionsObject = new Permissions;
		$this->illegal_guest_permissions = $permissionsObject->getIllegalGuestPermissions();

		// Simple spot check
		foreach (array('admin_forum', 'edit_news', 'mark_notify') as $check)
		{
			$valid = in_array($check, $this->illegal_guest_permissions);
			$this->assertTrue($valid, $check);
		}
	}

	/**
	 * Load and verify some things guests just can't do
	 */
	public function testIntegrationIllegalGuestPermissions()
	{
		// Add simple integration to play with values
		add_integration_function('integrate_load_illegal_guest_permissions', 'testIntegrationIGP', 'SOURCEDIR/Testing.php', false);

		$permissionsObject = new Permissions;
		$this->illegal_guest_permissions = $permissionsObject->getIllegalGuestPermissions();

		// Check if the integration worked
		$valid = in_array('no_bla_4_you', $this->illegal_guest_permissions);
		$this->assertTrue($valid, 'no_bla_4_you');

		$valid = in_array('issue_warning', $this->illegal_guest_permissions);
		$this->assertFalse($valid, 'issue_warning');
	}
}

/**
 * A function to test if integrate_load_illegal_guest_permissions is called
 */
function testIntegrationIGP(&$illegal_guest_permissions)
{
	// add this
	$illegal_guest_permissions[] = 'no_bla_4_you';

	// remove that
	if (($key = array_search('issue_warning', $illegal_guest_permissions)) !== false)
	{
		unset($illegal_guest_permissions[$key]);
	}
}
