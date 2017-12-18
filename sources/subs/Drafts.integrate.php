<?php

/**
 * A class to handle the basic drafts integrations.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0-dev
 *
 */

/**
 * Integration trick.
 */
class Drafts_Integrate
{
	/**
	 * Registers hooks as needed for the drafts function to work
	 * @return array
	 */
	public static function register()
	{
		// $hook, $function, $file
		return array(
		);
	}

	/**
	 * Returns the config settings form the drafts module
	 *
	 * @return array
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return array(
			array('integrate_load_permissions', 'ManageDraftsModule_Controller::integrate_load_permissions'),
			array('integrate_topics_maintenance', 'ManageDraftsModule_Controller::integrate_topics_maintenance'),
			array('integrate_sa_manage_maintenance', 'ManageDraftsModule_Controller::integrate_sa_manage_maintenance'),
			array('integrate_delete_members', 'ManageDraftsModule_Controller::integrate_delete_members'),
			array('integrate_load_illegal_guest_permissions', 'ManageDraftsModule_Controller::integrate_load_illegal_guest_permissions'),
		);
	}
}