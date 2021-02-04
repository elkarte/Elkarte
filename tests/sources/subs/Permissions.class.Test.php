<?php

use ElkArte\Permissions;
use ElkArte\User;
use ElkArte\ValuesContainer;

/**
 * TestCase class for Permissions Class.
 */

class TestPermissionsClass extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Load and verify default illegal permissions
	 */
	public function testDefaultIllegalPermissions()
	{
		User::$info = new ValuesContainer(['is_admin' => false]);
		$permissionsObject = new Permissions();
		$illegal_permissions = $permissionsObject->getIllegalPermissions();

		foreach (array('admin_forum', 'manage_membergroups', 'manage_permissions') as $check)
		{
			$valid = in_array($check, $illegal_permissions);
			$this->assertTrue($valid, $check);
		}
	}

	/**
	 * Load and verify some things guests just can't do
	 */
	public function testDefaultIllegalGuestPermissions()
	{
		$permissionsObject = new Permissions();
		$illegal_guest_permissions = $permissionsObject->getIllegalGuestPermissions();

		// Simple spot check
		foreach (array('admin_forum', 'edit_news', 'mark_notify') as $check)
		{
			$valid = in_array($check, $illegal_guest_permissions);
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

		$permissionsObject = new Permissions();
		$illegal_guest_permissions = $permissionsObject->getIllegalGuestPermissions();

		// Check if the integration worked
		$valid = in_array('no_bla_4_you', $illegal_guest_permissions);
		$this->assertTrue($valid, 'no_bla_4_you');

		$valid = in_array('issue_warning', $illegal_guest_permissions);
		$this->assertFalse($valid, 'issue_warning');

		$valid = in_array('pm_read', $illegal_guest_permissions);
		$this->assertFalse($valid, 'pm_read');

		// Compatibility check
		global $context;
		$valid = in_array('no_bla_4_you', $context['non_guest_permissions']);
		$this->assertTrue($valid, 'no_bla_4_you');

		$valid = in_array('issue_warning', $context['non_guest_permissions']);
		$this->assertFalse($valid, 'issue_warning');

		$valid = in_array('pm_read', $context['non_guest_permissions']);
		$this->assertFalse($valid, 'pm_read');
	}
}

/**
 * A function to test if integrate_load_illegal_guest_permissions is called
 */
function testIntegrationIGP(&$illegal_guest_permissions)
{
	global $context;

	// add this
	$illegal_guest_permissions[] = 'no_bla_4_you';

	// remove that
	if (($key = array_search('issue_warning', $illegal_guest_permissions)) !== false)
	{
		unset($illegal_guest_permissions[$key]);
	}

	// remove this
	if (($key = array_search('pm_read', $context['non_guest_permissions'])) !== false)
	{
		unset($context['non_guest_permissions'][$key]);
	}
}
